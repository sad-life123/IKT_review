<?php
// This file is part of Moodle - http://moodle.org/

require_once __DIR__ . '/../../config.php';

require_login();

$context = context_system::instance();

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/ikt_review/index.php'));
$PAGE->set_title('Метрики ИКТ');
$PAGE->set_heading('Показатели по курсам (Метрики ИКТ)');

echo $OUTPUT->header();

$starttime = microtime(true);

$manager = new \local_ikt_review\manager();
$all_courses_data = $manager->get_all_metrics();
$summary = $manager->get_summary($all_courses_data);

$endtime = microtime(true);
$processing_time = round($endtime - $starttime, 4);

echo '<h3>Общая сводка (Показатели по курсам)</h3>';

if (empty($summary)) {
    echo '<p>Нет данных по курсам.</p>';
} else {
    echo '<ul>';
    echo '<li>Количество курсов всего: ' . $summary['total_courses'] . '</li>';
    echo '<li>Количество заполненных электронных кабинетов: ' . $summary['filled_count'] . '</li>';
    echo '<li>Доля заполненных электронных кабинетов: ' . round($summary['filled_ratio'] * 100, 2) . '%</li>';
    echo '<li>Количество курсов с “живыми” ВКС (bbb): ' . $summary['live_bbb_count'] . '</li>';
    echo '</ul>';
    
    echo '<h4>Далее рассчитывается только по заполненным курсам:</h4>';
    echo '<ul>';
    echo '<li>Среднее количество котируемых открытых для студентов элементов: ' . round($summary['avg_elements'], 2) . '</li>';
    echo '<li>Средний коэффициент Content: ' . round($summary['avg_content'], 4) . '</li>';
    echo '<li>Средний коэффициент At: ' . round($summary['avg_at'], 4) . '</li>';
    echo '<li>Средний коэффициент Done: ' . round($summary['avg_done'], 4) . '</li>';
    echo '</ul>';
}

echo '<h4>Детализация по курсам (Пример):</h4>';
echo '<pre>';
echo print_r(array_slice($all_courses_data, 0, 5, true), true);
echo '</pre>';

echo '<hr>';
echo '<p><strong>Время обработки БД:</strong> ' . $processing_time . ' сек.</p>';

echo $OUTPUT->footer();
