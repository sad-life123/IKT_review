<?php
// This file is part of Moodle - http://moodle.org/

namespace local_ikt_review\api;

defined('MOODLE_INTERNAL') || die();

class api_provider {
    private const FALLBACK_LECTURES_HOURS = 36;
    private const FALLBACK_PZ_HOURS = 18;
    private const FALLBACK_LR_HOURS = 18;

    public function get_workload(\stdClass $course): array {
        $apiworkload = $this->get_api_workload($course);
        if ($apiworkload !== null) {
            return $apiworkload + ['source' => 'api'];
        }

        return [
            'lectures' => self::FALLBACK_LECTURES_HOURS,
            'pz' => self::FALLBACK_PZ_HOURS,
            'lr' => self::FALLBACK_LR_HOURS,
            'source' => 'fallback',
        ];
    }

    private function get_api_workload(\stdClass $course): ?array {
        $endpoint = trim((string)get_config('local_ikt_review', 'workloadapiendpoint'));
        if ($endpoint === '') {
            return null;
        }

        // 
        return null;
    }
}
