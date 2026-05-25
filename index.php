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

$action = optional_param('action', '', PARAM_ALPHA);
if ($action === 'run') {
    require_sesskey();

    $periodfrom = local_ikt_review_parse_date(optional_param('periodfrom', '', PARAM_RAW), time() - 180 * DAYSECS);
    $periodto = local_ikt_review_parse_date(optional_param('periodto', '', PARAM_RAW), time());
    $periodto = usergetmidnight($periodto) + DAYSECS - 1;

    try {
        $runid = $manager->run($periodfrom, $periodto);
        $message = [
            'text' => get_string('runfinished', 'local_ikt_review', $runid),
            'type' => 'success'
        ];
    } catch (Throwable $e) {
        $message = [
            'text' => local_ikt_review_describe_exception($e),
            'type' => 'error'
        ];
    }
}

$form_data = [
    'action_url' => (new moodle_url('/local/ikt_review/index.php'))->out(false),
    'sesskey' => sesskey(),
    'from_date' => date('Y-m-d', time() - 180 * DAYSECS),
    'to_date' => date('Y-m-d'),
    'str_periodfrom' => get_string('periodfrom', 'local_ikt_review'),
    'str_periodto' => get_string('periodto', 'local_ikt_review'),
    'str_runreview' => get_string('runreview', 'local_ikt_review'),
];
$runs_list = [];
$recent_runs = $manager->get_recent_runs();
if (!empty($recent_runs)) {
    foreach ($recent_runs as $run) {
        $duration = $run->timefinished > 0 ? $run->timefinished - $run->timestarted : time() - $run->timestarted;
        $runs_list[] = [
            'id' => $run->id,
            'periodfrom' => userdate($run->periodfrom),
            'periodto' => userdate($run->periodto),
            'status' => s($run->status),
            'duration' => format_time($duration),
            'calculationversion' => s($run->calculationversion),
        ];
    }
}

$summary_data = null;
$latestrun = $manager->get_latest_run();
if ($latestrun) {
    global $DB;
    $snapcount = $DB->count_records('local_ikt_review_snap', ['runid' => $latestrun->id]);
    $metriccount = $DB->count_records('local_ikt_review_metric', ['runid' => $latestrun->id]);

    $summary_data = [
        'id' => $latestrun->id,
        'courses' => $snapcount,
        'metrics' => $metriccount,
        'error' => !empty($latestrun->error) ? s($latestrun->error) : null,
    ];
}


echo $OUTPUT->header();

if ($message !== null) {
    echo $OUTPUT->notification($message['text'], $message['type']);
}


$template_context = [
    'form' => $form_data,
    'has_runs' => !empty($runs_list),
    'runs' => $runs_list,
    'summary' => $summary_data,
    'str_runs' => get_string('runs', 'local_ikt_review'),
    'str_summary' => get_string('summary', 'local_ikt_review'),
    'str_latestrun' => get_string('latestrun', 'local_ikt_review'),
    'str_courses' => get_string('courses', 'local_ikt_review'),
    'str_metrics' => get_string('metrics', 'local_ikt_review'),
    'str_status' => get_string('status'),
    'str_duration' => get_string('duration', 'local_ikt_review'),
    'str_version' => get_string('calculationversion', 'local_ikt_review'),
    'str_error' => get_string('error'),
];

echo $OUTPUT->render_from_template('local_ikt_review/index_page', $template_context);

echo $OUTPUT->footer();

function local_ikt_review_parse_date(string $date, int $default): int {
    if ($date === '') { return $default; }
    $parts = explode('-', $date);
    if (count($parts) !== 3) { return $default; }
    [$year, $month, $day] = array_map('intval', $parts);
    if (!checkdate($month, $day, $year)) { return $default; }
    return make_timestamp($year, $month, $day);
}

function local_ikt_review_describe_exception(Throwable $exception): string {
    $message = $exception->getMessage();
    if (property_exists($exception, 'debuginfo') && !empty($exception->debuginfo)) {
        $message .= ' | Debug: ' . $exception->debuginfo;
    }
    return $message;
}