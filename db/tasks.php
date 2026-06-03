<?php
// This file is part of Moodle - http://moodle.org/
// EDIT THIS FOR SCHEDULER TASKS. SEE /task/do_everything.php FOR TIMING.

defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname' => 'local_ikt_review\task\do_everything',
        'blocking' => 0,
        'minute' => '0',
        'hour' => '3',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '0', // Sunday
        'disabled' => 1, // disabled default
    ],
];
