<?php
// This file is part of Moodle - http://moodle.org/

namespace local_ikt_review\metric;

defined('MOODLE_INTERNAL') || die();

class content extends base_metric {
    private const WEIGHT_T = 0.3;
    private const WEIGHT_GR = 0.7;

    public function get_metric_key(): string {
        return 'content';
    }

    public function get_name(): string {
        return 'Content';
    }

    public function calculate(?int $runid = null): array {
        $records = $this->get_snap_records($runid, 'courseid, fullname, idnumber, t_count, gr_count');
        $results = [];
        $workloadprovider = new \local_ikt_review\api\api_provider();

        foreach ($records as $record) {
            $t = (int)$record->t_count;
            $gr = (int)$record->gr_count;
            $workload = $workloadprovider->get_workload($record);
            $lectures = max((float)$workload['lectures'] / 2, 1);
            $practice = max((float)$workload['pz'] / 2 + (float)$workload['lr'] / 4, 1);
            $value = self::WEIGHT_T * $t / $lectures + self::WEIGHT_GR * $gr / $practice;

            $results[$record->courseid] = [
                'courseid' => $record->courseid,
                'fullname' => $record->fullname,
                't' => $t,
                'gr' => $gr,
                'elements_count' => $t + $gr,
                'content' => $value,
                'workload_source' => $workload['source'],
            ];
        }

        return $results;
    }

    protected function get_value_payload(array $data): array {
        return [
            'value' => (float)$data['content'],
            'workload_source' => (string)$data['workload_source'],
        ];
    }
}
