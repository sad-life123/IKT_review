<?php
// This file is part of Moodle - http://moodle.org/

namespace local_ikt_review\metric;

defined('MOODLE_INTERNAL') || die();

class bbb extends base_metric {
    public function get_metric_key(): string {
        return 'bbb';
    }

    public function get_name(): string {
        return 'BBB';
    }

    public function calculate(?int $runid = null): array {
        $records = $this->get_snap_records($runid, 'courseid, bbb_count, live_bbb_count');
        $results = [];

        foreach ($records as $record) {
            $hasbbb = (int)$record->bbb_count > 0 ? 1 : 0;
            $islive = (int)$record->live_bbb_count > 0 ? 1 : 0;

            $results[$record->courseid] = [
                'courseid' => $record->courseid,
                'has_bbb' => $hasbbb,
                'live_bbb_count' => (int)$record->live_bbb_count,
                'is_live' => $islive,
            ];
        }

        return $results;
    }

    protected function get_value_payload(array $data): array {
        return [
            'has_bbb' => (int)$data['has_bbb'],
            'is_live' => (int)$data['is_live'],
            'live_bbb_count' => (int)$data['live_bbb_count'],
        ];
    }
}
