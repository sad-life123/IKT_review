<?php
// This file is part of Moodle - http://moodle.org/

require_once __DIR__ . '/../../config.php';

require_login();

$context = context_system::instance();

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/ikt_review/index.php'));

echo $OUTPUT->header();
echo $OUTPUT->notification(get_string('nothingtodisplay'), 'info');
echo $OUTPUT->footer();
