<?php
// This file is part of Moodle - http://moodle.org/
// fix me later

namespace local_ikt_review;

defined('MOODLE_INTERNAL') || die();

class manager {
    public const CALCULATION_VERSION = 'collect-v4';
    public const PIPELINE_STEPS = [
        'courses',
        'course_students',
        'log_filter',
        'log_aggregate',
        'course_info',
        'course_items',
        'bbb_live',
        'students',
        'views',
        'answers',
        'grades',
        'metric_content',
        'metric_attendance',
        'metric_bbb',
        'metric_done',
        'metric_check',
        'metric_performance',
    ];

    private const STATEMENT_TIMEOUT = '30min';
    private const LOG_AGGREGATE_WORK_MEM = '1GB'; // remove me if we die
    private const STALE_RUNNING_RUN_TTL = 86400;
    private const FULL_TIME_STUDENT_ROLE_SHORTNAME = 'full-time-student'; // db const

    /** @var metric\base_metric[] */
    private array $metrics;

    public function __construct() {
        $this->metrics = [
            new metric\content(),
            new metric\attendance(),
            new metric\bbb(),
            new metric\done(),
            new metric\check(),
            new metric\performance(),
        ];
    }

    public function queue_run(int $periodfrom, int $periodto, ?array $courseids = null): int {
        if ($periodfrom > $periodto) {
            throw new \coding_exception('periodfrom must be less than or equal to periodto');
        }

        $courseids = $this->normalize_course_ids($courseids);
        $runid = $this->create_run_record($periodfrom, $periodto, $courseids, 'queued');
        $task = new \local_ikt_review\task\calculation_task();
        $task->set_custom_data([
            'runid' => $runid,
            'courseids' => $courseids,
        ]);

        try {
            \core\task\manager::queue_adhoc_task($task);
        } catch (\Throwable $e) {
            $this->finish_run($runid, 'failed', $e->getMessage());
            throw $e;
        }

        return $runid;
    }

    public function execute_queued_run(int $runid, ?array $courseids = null): void {
        global $DB;

        $run = $DB->get_record('local_ikt_review_run', ['id' => $runid], '*', MUST_EXIST);
        if ($run->status !== 'queued') {
            return;
        }

        $factory = \core\lock\lock_config::get_lock_factory('local_ikt_review');
        $lock = $factory->get_lock('calculation', 0);
        if (!$lock) {
            throw new \moodle_exception('error_calculationlocked', 'local_ikt_review');
        }

        try {
            $DB->update_record('local_ikt_review_run', (object)[
                'id' => $runid,
                'status' => 'running',
                'timestarted' => time(),
                'timefinished' => 0,
                'error' => null,
            ]);
            $this->execute_run($runid, $this->normalize_course_ids($courseids));
        } finally {
            $lock->release();
        }
    }

    private function execute_run(int $runid, array $courseids): void {
        global $DB;

        $run = $DB->get_record('local_ikt_review_run', ['id' => $runid], 'id, periodfrom, periodto', MUST_EXIST);
        $periodfrom = (int)$run->periodfrom;
        $periodto = (int)$run->periodto;

        try {
            $this->execute_step($runid, 'courses', function() use ($courseids) {
                global $DB;
                $DB->execute('DROP TABLE IF EXISTS tmp_ikt_review_courses');

                if ($courseids) {
                    [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'courseid');
                    $DB->execute("CREATE TEMP TABLE tmp_ikt_review_courses AS
                        SELECT id AS courseid
                          FROM {course}
                         WHERE visible = 1
                           AND id $insql", $params);
                } else {
                    $DB->execute($this->get_sql('collect/courses.sql'));
                }

                $DB->execute('CREATE UNIQUE INDEX tmp_ikt_review_courses_course_idx ON tmp_ikt_review_courses(courseid)');
                $DB->execute('ANALYZE tmp_ikt_review_courses');
                return $DB->count_records_sql('SELECT COUNT(*) FROM tmp_ikt_review_courses');
            });

            $moduleids = $this->get_module_ids(['bigbluebuttonbn']);

            $this->execute_step($runid, 'course_students', function() {
                global $DB;
                $DB->execute('DROP TABLE IF EXISTS tmp_ikt_review_course_students');
                $sql = $this->get_sql('collect/course_students.sql');
                $DB->execute($sql, $this->filter_params($sql, [
                    'studentrole' => self::FULL_TIME_STUDENT_ROLE_SHORTNAME,
                ]));
                $DB->execute('CREATE INDEX tmp_ikt_review_course_students_course_user_idx ON tmp_ikt_review_course_students(courseid, userid)');
                $DB->execute('ANALYZE tmp_ikt_review_course_students');
                return $DB->count_records_sql('SELECT COUNT(*) FROM tmp_ikt_review_course_students');
            });

            $this->execute_step($runid, 'log_filter', function() use ($periodfrom, $periodto) {
                global $DB;
                $DB->execute('DROP TABLE IF EXISTS tmp_ikt_review_log_filtered');
                $DB->execute('DROP TABLE IF EXISTS tmp_ikt_review_log_agg');
                $sql = 'CREATE TEMP TABLE tmp_ikt_review_log_filtered AS ' . $this->get_sql('collect/log_filter.sql');
                $DB->execute($sql, $this->filter_params($sql, [
                    'periodfrom' => $periodfrom,
                    'periodto' => $periodto,
                ]));
                $DB->execute('ANALYZE tmp_ikt_review_log_filtered');
                return $DB->count_records_sql('SELECT COUNT(*) FROM tmp_ikt_review_log_filtered');
            });

            $this->execute_step($runid, 'log_aggregate', function() {
                global $DB;
                $DB->execute("SET LOCAL work_mem = '" . self::LOG_AGGREGATE_WORK_MEM . "'");
                $sql = $this->get_sql('collect/log_aggregate.sql');
                $DB->execute($sql);
                $DB->execute('CREATE INDEX tmp_ikt_review_log_agg_course_idx ON tmp_ikt_review_log_agg(courseid)');
                $DB->execute('ANALYZE tmp_ikt_review_log_agg');
                return $DB->count_records_sql('SELECT COUNT(*) FROM tmp_ikt_review_log_agg');
            });

            $baseparams = [
                'runid' => $runid,
                'now' => time(),
                'periodfrom' => $periodfrom,
                'periodto' => $periodto,
                'assignperiodfrom' => $periodfrom,
                'assignperiodto' => $periodto,
                'quizperiodfrom' => $periodfrom,
                'quizperiodto' => $periodto,
                'bbbmoduleid' => $moduleids['bigbluebuttonbn'] ?? 0,
            ];

            foreach (['course_info', 'course_items', 'bbb_live', 'students', 'views', 'answers', 'grades'] as $step) {
                $this->execute_step($runid, $step, function() use ($step, $baseparams) {
                    global $DB;
                    $before = $DB->count_records('local_ikt_review_snap', ['runid' => $baseparams['runid']]);
                    $sql = $this->get_sql('collect/' . $step . '.sql');
                    $DB->execute($sql, $this->filter_params($sql, $baseparams));
                    $after = $DB->count_records('local_ikt_review_snap', ['runid' => $baseparams['runid']]);
                    return max($before, $after);
                });
            }

            foreach ($this->metrics as $metric) {
                $this->execute_step($runid, 'metric_' . $metric->get_metric_key(), function() use ($metric, $runid) {
                    return $metric->persist($runid);
                });
            }

            $this->finish_run($runid, 'finished');
        } catch (\Throwable $e) {
            $this->log($runid, 'error', 'run', $e->getMessage());
            $this->finish_run($runid, 'failed', $e->getMessage());
            throw $e;
        }

    }

    public function get_all_metrics(?int $runid = null): array {
        $combined = [];

        foreach ($this->metrics as $metric) {
            $results = $metric->calculate($runid);
            foreach ($results as $courseid => $data) {
                if (!isset($combined[$courseid])) {
                    $combined[$courseid] = [];
                }
                $combined[$courseid] = array_merge($combined[$courseid], $data);
            }
        }

        return $combined;
    }

    public function get_summary(array $all_courses_data): array {
        $totalcourses = count($all_courses_data);
        if ($totalcourses === 0) {
            return [];
        }

        $filledcount = 0;
        $livebbbcount = 0;

        $sumelements = 0;
        $sumcontent = 0;
        $sumat = 0;
        $sumuniqueat = 0;
        $sumdone = 0;
        $sumcheck = 0;

        foreach ($all_courses_data as $data) {
            $elements = (int)($data['elements_count'] ?? (($data['gr'] ?? 0) + ($data['t'] ?? 0)));

            if ($elements > 5) {
                $filledcount++;
                $sumelements += $elements;
                $sumcontent += $data['content'] ?? 0;
                $sumat += $data['at'] ?? 0;
                $sumuniqueat += $data['unique_at'] ?? 0;
                $sumdone += $data['done'] ?? 0;
                $sumcheck += $data['check'] ?? 0;
            }

            if (!empty($data['is_live'])) {
                $livebbbcount++;
            }
        }

        return [
            'total_courses' => $totalcourses,
            'filled_count' => $filledcount,
            'filled_ratio' => $filledcount / $totalcourses,
            'live_bbb_count' => $livebbbcount,
            'avg_elements' => $filledcount > 0 ? $sumelements / $filledcount : 0,
            'avg_content' => $filledcount > 0 ? $sumcontent / $filledcount : 0,
            'avg_at' => $filledcount > 0 ? $sumat / $filledcount : 0,
            'avg_unique_at' => $filledcount > 0 ? $sumuniqueat / $filledcount : 0,
            'avg_done' => $filledcount > 0 ? $sumdone / $filledcount : 0,
            'avg_check' => $filledcount > 0 ? $sumcheck / $filledcount : 0,
        ];
    }

    public function get_latest_run(): ?\stdClass {
        global $DB;
        $runs = $DB->get_records_select(
            'local_ikt_review_run',
            'status = :status AND calculationversion NOT LIKE :selectedversion AND calculationversion NOT LIKE :syntheticversion',
            [
                'status' => 'finished',
                'selectedversion' => '%-selected%',
                'syntheticversion' => '%-synthetic%',
            ],
            'id DESC',
            '*',
            0,
            1
        );
        return $runs ? reset($runs) : null;
    }

    public function get_recent_runs(int $limit = 10): array {
        global $DB;
        return $DB->get_records('local_ikt_review_run', null, 'id DESC', '*', 0, $limit);
    }

    public function get_recent_production_runs(int $limit = 10): array {
        global $DB;
        return $DB->get_records_select(
            'local_ikt_review_run',
            'calculationversion NOT LIKE :selectedversion AND calculationversion NOT LIKE :syntheticversion',
            [
                'selectedversion' => '%-selected%',
                'syntheticversion' => '%-synthetic%',
            ],
            'id DESC',
            '*',
            0,
            $limit
        );
    }

    public function fail_stale_runs(): int {
        global $DB;

        $now = time();
        [$insql, $params] = $DB->get_in_or_equal(['queued', 'running'], SQL_PARAMS_NAMED, 'status');
        $params['cutoff'] = $now - self::STALE_RUNNING_RUN_TTL;
        $runs = $DB->get_records_select(
            'local_ikt_review_run',
            "status $insql AND timefinished = 0 AND timestarted < :cutoff",
            $params,
            'id ASC',
            'id'
        );

        foreach ($runs as $run) {
            $message = 'Run marked as failed because it stayed queued or running for more than 24 hours.';
            $this->log((int)$run->id, 'error', 'run', $message);
            $this->finish_run((int)$run->id, 'failed', $message);
        }

        return count($runs);
    }

    private function execute_step(int $runid, string $step, callable $callback): int {
        global $DB;

        $start = microtime(true);
        $rows = 0;
        $this->log($runid, 'info', $step, 'Step started');
        $transaction = $DB->start_delegated_transaction();

        try {
            $DB->execute("SET LOCAL statement_timeout = '" . self::STATEMENT_TIMEOUT . "'");
            $rows = (int)$callback();
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            try {
                $transaction->rollback($e);
            } catch (\Throwable $rollbackexception) {
                $e = $rollbackexception;
            }

            $this->log($runid, 'error', $step, $this->describe_exception($e), $this->elapsed_ms($start), $rows);
            throw $e;
        }

        $this->log($runid, 'info', $step, 'Step finished', $this->elapsed_ms($start), $rows);

        return $rows;
    }

    private function log(
        int $runid,
        string $level,
        string $step,
        string $message,
        ?int $durationms = null,
        ?int $rowsprocessed = null
    ): void {
        global $DB;

        $DB->insert_record('local_ikt_review_log', (object)[
            'runid' => $runid,
            'level' => $level,
            'step' => $step,
            'message' => $message,
            'durationms' => $durationms,
            'rowsprocessed' => $rowsprocessed,
            'timecreated' => time(),
        ]);
    }

    private function finish_run(int $runid, string $status, ?string $error = null): void {
        global $DB;

        $DB->update_record('local_ikt_review_run', (object)[
            'id' => $runid,
            'status' => $status,
            'timefinished' => time(),
            'error' => $error,
        ]);
    }

    private function create_run_record(int $periodfrom, int $periodto, array $courseids, string $status): int {
        global $DB;

        return (int)$DB->insert_record('local_ikt_review_run', (object)[
            'periodfrom' => $periodfrom,
            'periodto' => $periodto,
            'status' => $status,
            'timestarted' => time(),
            'timefinished' => 0,
            'calculationversion' => $courseids ? self::CALCULATION_VERSION . '-selected' : self::CALCULATION_VERSION,
            'error' => null,
        ]);
    }

    private function get_module_ids(array $names): array {
        global $DB;

        if (!$names) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($names, SQL_PARAMS_NAMED, 'module');
        $records = $DB->get_records_select('modules', "name $insql", $params, '', 'name, id');
        $moduleids = [];

        foreach ($records as $record) {
            $moduleids[$record->name] = (int)$record->id;
        }

        return $moduleids;
    }

    private function normalize_course_ids(?array $courseids): array {
        if (!$courseids) {
            return [];
        }

        $normalized = [];
        foreach ($courseids as $courseid) {
            $courseid = (int)$courseid;
            if ($courseid > 0) {
                $normalized[$courseid] = $courseid;
            }
        }

        return array_values($normalized);
    }

    private function get_sql(string $filename): string {
        global $CFG;

        $path = $CFG->dirroot . '/local/ikt_review/sql/' . $filename;
        if (!file_exists($path)) {
            throw new \coding_exception('SQL file not found: ' . $path);
        }

        return file_get_contents($path);
    }

    private function filter_params(string $sql, array $params): array {
        preg_match_all('/:([a-zA-Z][a-zA-Z0-9_]*)/', $sql, $matches);
        $usedparams = array_flip($matches[1]);

        return array_intersect_key($params, $usedparams);
    }

    private function elapsed_ms(float $start): int {
        return (int)round((microtime(true) - $start) * 1000);
    }

    private function describe_exception(\Throwable $exception): string {
        $message = $exception->getMessage();

        if (property_exists($exception, 'debuginfo') && !empty($exception->debuginfo)) {
            $message .= ' | Debug: ' . $exception->debuginfo;
        }

        return $message;
    }
}
