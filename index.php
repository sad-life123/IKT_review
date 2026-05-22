<?php
// This file is part of Moodle - http://moodle.org/

require_once __DIR__ . '/../../config.php';
require_once $CFG->libdir . '/tablelib.php';

require_login();

$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/ikt_review/index.php'));
$PAGE->set_title(get_string('pluginname', 'local_ikt_review'));
$PAGE->set_heading(get_string('pluginname', 'local_ikt_review'));

$manager = new \local_ikt_review\manager();
$message = null;

if (optional_param('action', '', PARAM_ALPHA) === 'run') {
    require_sesskey();

    $periodfrom = local_ikt_review_parse_date(optional_param('periodfrom', '', PARAM_RAW), time() - 180 * DAYSECS);
    $periodto = local_ikt_review_parse_date(optional_param('periodto', '', PARAM_RAW), time());
    $periodto = usergetmidnight($periodto) + DAYSECS - 1;

    try {
        $runid = $manager->run($periodfrom, $periodto);
        $message = $OUTPUT->notification(get_string('runfinished', 'local_ikt_review', $runid), 'success');
    } catch (Throwable $e) {
        $message = $OUTPUT->notification($e->getMessage(), 'error');
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'local_ikt_review'));

if ($message !== null) {
    echo $message;
}

echo local_ikt_review_render_run_form();
echo local_ikt_review_render_runs($manager->get_recent_runs());

$latestrun = $manager->get_latest_run();
if ($latestrun) {
    echo local_ikt_review_render_summary($latestrun);
    echo local_ikt_review_render_logs($latestrun->id);
    echo local_ikt_review_render_snap($latestrun->id);
}

echo $OUTPUT->footer();

function local_ikt_review_parse_date(string $date, int $default): int {
    if ($date === '') {
        return $default;
    }

    $parts = explode('-', $date);
    if (count($parts) !== 3) {
        return $default;
    }

    [$year, $month, $day] = array_map('intval', $parts);
    if (!checkdate($month, $day, $year)) {
        return $default;
    }

    return make_timestamp($year, $month, $day);
}

function local_ikt_review_render_run_form(): string {
    global $OUTPUT;

    $from = date('Y-m-d', time() - 180 * DAYSECS);
    $to = date('Y-m-d');

    $html = $OUTPUT->box_start('generalbox');
    $html .= html_writer::start_tag('form', [
        'method' => 'post',
        'action' => new moodle_url('/local/ikt_review/index.php'),
    ]);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'run']);
    $html .= html_writer::label(get_string('periodfrom', 'local_ikt_review'), 'ikt-review-periodfrom');
    $html .= html_writer::empty_tag('input', [
        'type' => 'date',
        'id' => 'ikt-review-periodfrom',
        'name' => 'periodfrom',
        'value' => $from,
    ]);
    $html .= html_writer::label(get_string('periodto', 'local_ikt_review'), 'ikt-review-periodto', false, ['class' => 'ml-3']);
    $html .= html_writer::empty_tag('input', [
        'type' => 'date',
        'id' => 'ikt-review-periodto',
        'name' => 'periodto',
        'value' => $to,
    ]);
    $html .= html_writer::tag('button', get_string('runreview', 'local_ikt_review'), [
        'type' => 'submit',
        'class' => 'btn btn-primary ml-3',
    ]);
    $html .= html_writer::end_tag('form');
    $html .= $OUTPUT->box_end();

    return $html;
}

function local_ikt_review_render_runs(array $runs): string {
    if (!$runs) {
        return '';
    }

    $table = new html_table();
    $table->head = [
        'id',
        get_string('periodfrom', 'local_ikt_review'),
        get_string('periodto', 'local_ikt_review'),
        get_string('status'),
        get_string('duration', 'local_ikt_review'),
        get_string('calculationversion', 'local_ikt_review'),
    ];

    foreach ($runs as $run) {
        $duration = $run->timefinished > 0 ? $run->timefinished - $run->timestarted : time() - $run->timestarted;
        $table->data[] = [
            $run->id,
            userdate($run->periodfrom),
            userdate($run->periodto),
            s($run->status),
            format_time($duration),
            s($run->calculationversion),
        ];
    }

    return html_writer::tag('h3', get_string('runs', 'local_ikt_review')) . html_writer::table($table);
}

function local_ikt_review_render_summary(stdClass $run): string {
    global $DB;

    $snapcount = $DB->count_records('local_ikt_review_snap', ['runid' => $run->id]);
    $metriccount = $DB->count_records('local_ikt_review_metric', ['runid' => $run->id]);

    $table = new html_table();
    $table->data = [
        [get_string('latestrun', 'local_ikt_review'), $run->id],
        [get_string('courses', 'local_ikt_review'), $snapcount],
        [get_string('metrics', 'local_ikt_review'), $metriccount],
    ];

    if (!empty($run->error)) {
        $table->data[] = [get_string('error'), s($run->error)];
    }

    return html_writer::tag('h3', get_string('summary', 'local_ikt_review')) . html_writer::table($table);
}

function local_ikt_review_render_logs(int $runid): string {
    global $DB;

    $logs = $DB->get_records('local_ikt_review_log', ['runid' => $runid], 'id ASC', '*', 0, 50);
    if (!$logs) {
        return '';
    }

    $table = new html_table();
    $table->head = ['id', get_string('level', 'local_ikt_review'), get_string('step', 'local_ikt_review'),
        get_string('durationms', 'local_ikt_review'), get_string('rowsprocessed', 'local_ikt_review'),
        get_string('message', 'local_ikt_review')];

    foreach ($logs as $log) {
        $table->data[] = [
            $log->id,
            s($log->level),
            s($log->step),
            $log->durationms,
            $log->rowsprocessed,
            s($log->message),
        ];
    }

    return html_writer::tag('h3', get_string('runlog', 'local_ikt_review')) . html_writer::table($table);
}

function local_ikt_review_render_snap(int $runid): string {
    global $DB;

    $records = $DB->get_records('local_ikt_review_snap', ['runid' => $runid], 'courseid ASC', '*', 0, 100);
    if (!$records) {
        return '';
    }

    $metrics = $DB->get_records('local_ikt_review_metric', ['runid' => $runid], 'courseid ASC, metric ASC');
    $metricmap = [];
    foreach ($metrics as $metric) {
        $metricmap[$metric->courseid][$metric->metric] = $metric->value;
    }

    $table = new html_table();
    $table->head = [
        'courseid',
        'fullname',
        'modules',
        'gr',
        't',
        'students',
        'views',
        'answers',
        'avg_grade',
        'metrics',
    ];

    foreach ($records as $record) {
        $table->data[] = [
            $record->courseid,
            s($record->fullname),
            $record->modules,
            $record->gr_count,
            $record->t_count,
            $record->student_count,
            $record->view_count,
            $record->answer_count,
            $record->avg_grade,
            s(json_encode($metricmap[$record->courseid] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
        ];
    }

    return html_writer::tag('h3', get_string('debugsnapshot', 'local_ikt_review')) . html_writer::table($table);
}
