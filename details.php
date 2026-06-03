<?php
// This file is part of Moodle - http://moodle.org/

require_once __DIR__ . '/../../config.php';

require_login();
require_sesskey();

$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/ikt_review/details.php'));

$runid = required_param('runid', PARAM_INT);
$run = $DB->get_record('local_ikt_review_run', ['id' => $runid], '*', MUST_EXIST);
if ($run->status !== 'finished') {
    throw new moodle_exception('error_rundetailsunfinished', 'local_ikt_review');
}

$service = new \local_ikt_review\report\run_analytics_service();
$page = new \local_ikt_review\report\run_details_page();
$templatecontext = array_merge(
    ['details' => $page->export_for_template($service->get_stats($runid, true))],
    $page->strings()
);

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'title' => get_string('rundetails', 'local_ikt_review'),
    'html' => html_writer::div(
        $OUTPUT->render_from_template('local_ikt_review/run_details', $templatecontext),
        'local-ikt-review-wrap'
    ),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
