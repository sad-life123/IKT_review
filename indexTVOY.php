<?php
/**
 * Мониторинг показателей курсов дневной формы обучения
 */

require_once(__DIR__ . '/../../config.php');


require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/ikt_review/index.php'));

echo $OUTPUT->header();

global $DB;


$sql = "
    SELECT 
    c.id AS courseid,
    COUNT(DISTINCT CASE WHEN cm.visible = 1 AND m.name IN ('assign', 'quiz', 'resource', 'page', 'lesson', 'book', 'vpl', 'url') THEN cm.id END) AS elements_count,
    COUNT(DISTINCT CASE WHEN cm.visible = 1 AND m.name IN ('assign', 'quiz', 'vpl') THEN cm.id END) AS gr_count,
    COUNT(DISTINCT CASE WHEN cm.visible = 1 AND m.name IN ('resource', 'page', 'lesson', 'book', 'url') THEN cm.id END) AS t_count,
    MAX(CASE WHEN cm.visible = 1 AND m.name = 'bigbluebuttonbn' THEN 1 ELSE 0 END) AS has_bbb,
    COALESCE(students.student_count, 0) AS student_count,
    COALESCE(submissions.total_ans, 0) AS total_ans,
    COALESCE(logs.view_count, 0) AS view_count
FROM {course} c
LEFT JOIN {course_modules} cm ON cm.course = c.id
LEFT JOIN {modules} m ON m.id = cm.module
LEFT JOIN (
    SELECT e.courseid, COUNT(DISTINCT ue.userid) AS student_count
    FROM {enrol} e
    JOIN {user_enrolments} ue ON ue.enrolid = e.id
    GROUP BY e.courseid
) students ON students.courseid = c.id
LEFT JOIN (
    SELECT courseid, COUNT(id) AS view_count
    FROM {logstore_standard_log}
    WHERE crud = 'r' AND contextlevel = 70
    GROUP BY courseid
) logs ON logs.courseid = c.id
LEFT JOIN (
    SELECT course, SUM(ans_count) AS total_ans FROM (
        SELECT a.course, COUNT(DISTINCT s.userid) AS ans_count 
        FROM {assign} a JOIN {assign_submission} s ON s.assignment = a.id WHERE s.status = 'submitted' GROUP BY a.course
        UNION ALL
        SELECT q.course, COUNT(DISTINCT qa.userid) AS ans_count 
        FROM {quiz} q JOIN {quiz_attempts} qa ON qa.quiz = q.id WHERE qa.state = 'finished' GROUP BY q.course
    ) sub GROUP BY course
) submissions ON submissions.course = c.id
WHERE c.visible = 1 AND c.idnumber LIKE '0%'
GROUP BY c.id, students.student_count, submissions.total_ans, logs.view_count
";

$courses_data = $DB->get_records_sql($sql);

if (empty($courses_data)) {
    echo $OUTPUT->notification('Нет данных для отображения (курсы дневной формы не найдены).', 'info');
    echo $OUTPUT->footer();
    exit;
}


$total_courses = count($courses_data);
$filled_cab_count = 0;
$live_vks_count = 0;

$filled_courses_metrics = [
    'elements_sum' => 0,
    'content_coef_sum' => 0,
    'at_coef_sum' => 0,
    'done_coef_sum' => 0,
    'count' => 0
];

$min_elements = 5;

foreach ($courses_data as $course) {
    // проверка на "живую" ВКС
    if ($course->has_bbb > 0) {
        $live_vks_count++;
    }

    
    $is_filled = ($course->elements_count > $min_elements);
    if ($is_filled) {
        $filled_cab_count++;
        $filled_courses_metrics['count']++;
        
        // количество котируемых элементов
        $filled_courses_metrics['elements_sum'] += $course->elements_count;

        // коэффициент Content (Соотношение теории и практики)
        // Защита от деления на ноль: если практики 0, берем 1
        $pract = $course->gr_count > 0 ? $course->gr_count : 1;
        $content_coef = $course->t_count / $pract;
        $filled_courses_metrics['content_coef_sum'] += $content_coef;

        // коэффициент At 
        
        $students_weight = $course->student_count > 0 ? $course->student_count : 1;
        $at_coef = $course->view_count / $students_weight;
        $filled_courses_metrics['at_coef_sum'] += $at_coef;

        // коэфф Done 
        
        $done_coef = $course->total_ans / $students_weight;
        $filled_courses_metrics['done_coef_sum'] += $done_coef;
    }
}

$filled_share = $total_courses > 0 ? ($filled_cab_count / $total_courses) * 100 : 0;
$filled_count = $filled_courses_metrics['count'];

$avg_elements     = $filled_count > 0 ? $filled_courses_metrics['elements_sum'] / $filled_count : 0;
$avg_content_coef = $filled_count > 0 ? $filled_courses_metrics['content_coef_sum'] / $filled_count : 0;
$avg_at_coef      = $filled_count > 0 ? $filled_courses_metrics['at_coef_sum'] / $filled_count : 0;
$avg_done_coef    = $filled_count > 0 ? $filled_courses_metrics['done_coef_sum'] / $filled_count : 0;


$table = new html_table();
$table->head = ['Показатель', 'Значение'];
$table->attributes['class'] = 'generaltable boxaligncenter';

$table->data[] = ['**Всего курсов дневной формы (idnumber как "0%")**', $total_courses];
$table->data[] = ['Количество заполненных электронных кабинетов (>' . $min_elements . ' элм.)', $filled_cab_count];
$table->data[] = ['Доля заполненных электронных кабинетов', number_format($filled_share, 2) . '%'];
$table->data[] = ['Количество курсов с “живыми” ВКС (BigBlueButton)', $live_vks_count];

// Разделитель для второй группы метрик
$table->data[] = ['show_info' => 'colspan="2" style="background:#f4f4f4; font-weight:bold; text-align:center;"', 'text' => 'Далее рассчитывается только по заполненным курсам (' . $filled_count . ' шт.)'];

$table->data[] = ['Среднее кол-во котируемых открытых для студентов элементов', number_format($avg_elements, 2)];
$table->data[] = ['Средний коэффициент Content (Теория / Практика)', number_format($avg_content_coef, 2)];
$table->data[] = ['Средний коэффициент At (Просмотры / Студенты)', number_format($avg_at_coef, 2)];
$table->data[] = ['Средний коэффициент Done (Сданные работы / Студенты)', number_format($avg_done_coef, 2)];


echo html_writer::table($table);

echo $OUTPUT->footer();
