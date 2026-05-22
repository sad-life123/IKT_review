<?php
// This file is part of Moodle - http://moodle.org/

namespace local_ikt_review\metric;

defined('MOODLE_INTERNAL') || die();

abstract class base_metric {
    abstract public function get_metric_key(): string;

    abstract public function get_name(): string;

    /**
     * Returns metric values keyed by course id.
     *
     * @return array [courseid => metric_value_or_array]
     */
    abstract public function calculate(?int $runid = null): array;

    abstract protected function get_value_payload(array $data): array;

    public function persist(int $runid): int {
        global $DB;

        $results = $this->calculate($runid);
        $count = 0;
        $DB->delete_records('local_ikt_review_metric', [
            'runid' => $runid,
            'metric' => $this->get_metric_key(),
        ]);

        foreach ($results as $courseid => $data) {
            $payload = $this->get_value_payload($data);
            $DB->insert_record('local_ikt_review_metric', (object)[
                'runid' => $runid,
                'courseid' => $courseid,
                'metric' => $this->get_metric_key(),
                'value' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'timecreated' => time(),
            ]);

            $count++;
        }

        return $count;
    }

    protected function resolve_runid(?int $runid): int {
        global $DB;

        if ($runid !== null) {
            return $runid;
        }

        $runs = $DB->get_records_select(
            'local_ikt_review_run',
            'status = :status',
            ['status' => 'finished'],
            'id DESC',
            'id',
            0,
            1
        );

        if (!$runs) {
            return 0;
        }

        $run = reset($runs);
        return (int)$run->id;
    }

    protected function get_snap_records(?int $runid, string $fields): array {
        global $DB;

        $resolvedrunid = $this->resolve_runid($runid);
        if ($resolvedrunid === 0) {
            return [];
        }

        return $DB->get_records('local_ikt_review_snap', ['runid' => $resolvedrunid], 'courseid ASC', $fields);
    }

    protected function single_value(float $value): array {
        return ['value' => $value];
    }
}
