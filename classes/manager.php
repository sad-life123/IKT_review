<?php
// This file is part of Moodle - http://moodle.org/

namespace local_ikt_review;

defined('MOODLE_INTERNAL') || die();

class manager {
    private const STATEMENT_TIMEOUT = '30min';

    /** @var metric\base_metric[] */
    private array $metrics;

    public function __construct() {
        $this->metrics = [
            new metric\content(),
            new metric\attendance(),
            new metric\bbb(),
            new metric\done(),
            new metric\performance(),
        ];
    }

    public function run(int $periodfrom, int $periodto): int {
        global $DB;

        if ($periodfrom > $periodto) {
            throw new \coding_exception('periodfrom must be less than or equal to periodto');
        }

        $runid = $DB->insert_record('local_ikt_review_run', (object)[
            'periodfrom' => $periodfrom,
            'periodto' => $periodto,
            'status' => 'running',
            'timestarted' => time(),
            'timefinished' => 0,
            'calculationversion' => $this->get_calculation_version(),
            'error' => null,
        ]);

        try {
            $this->execute_step($runid, 'courses', function() {
                global $DB;
                $DB->execute('DROP TABLE IF EXISTS tmp_ikt_review_courses');
                $DB->execute($this->get_sql('collect/courses.sql'));
                $DB->execute('ANALYZE tmp_ikt_review_courses');
                return $DB->count_records_sql('SELECT COUNT(*) FROM tmp_ikt_review_courses');
            });

            $moduleids = $this->get_module_ids(['bigbluebuttonbn']);

            $this->execute_step($runid, 'log_filter', function() use ($periodfrom, $periodto) {
                global $DB;
                $DB->execute('DROP TABLE IF EXISTS tmp_ikt_review_log_filtered');
                $DB->execute('DROP TABLE IF EXISTS tmp_ikt_review_log_agg');
                $sql = $this->get_sql('collect/log_filter.sql');
                $DB->execute($sql, $this->filter_params($sql, [
                    'periodfrom' => $periodfrom,
                    'periodto' => $periodto,
                ]));
                $DB->execute('CREATE INDEX tmp_ikt_review_log_filtered_course_idx ON tmp_ikt_review_log_filtered(courseid)');
                $DB->execute('ANALYZE tmp_ikt_review_log_filtered');
                return $DB->count_records_sql('SELECT COUNT(*) FROM tmp_ikt_review_log_filtered');
            });

            $this->execute_step($runid, 'log_aggregate', function() {
                global $DB;
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
                'bbbmoduleid' => $moduleids['bigbluebuttonbn'] ?? 0,
            ];

            foreach (['course_info', 'course_items', 'students', 'views', 'answers', 'grades'] as $step) {
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

        return $runid;
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
        $sumdone = 0;

        foreach ($all_courses_data as $data) {
            $gr = $data['gr'] ?? 0;
            $t = $data['t'] ?? 0;
            $elements = $gr + $t;

            if ($elements > 0) {
                $filledcount++;
                $sumelements += $elements;
                $sumcontent += $data['content'] ?? 0;
                $sumat += $data['at'] ?? 0;
                $sumdone += $data['done'] ?? 0;
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
            'avg_done' => $filledcount > 0 ? $sumdone / $filledcount : 0,
        ];
    }

    public function get_latest_run(): ?\stdClass {
        global $DB;
        $runs = $DB->get_records('local_ikt_review_run', null, 'id DESC', '*', 0, 1);
        return $runs ? reset($runs) : null;
    }

    public function get_recent_runs(int $limit = 10): array {
        global $DB;
        return $DB->get_records('local_ikt_review_run', null, 'id DESC', '*', 0, $limit);
    }

    private function execute_step(int $runid, string $step, callable $callback): int {
        global $DB;

        $start = microtime(true);
        $rows = 0;
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

    private function get_calculation_version(): string {
        return 'collect-v1';
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
