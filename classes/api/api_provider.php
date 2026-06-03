<?php
// This file is part of Moodle - http://moodle.org/
// do that in future. For now, just a placeholder for API calls. See manager.php for usage.

namespace local_ikt_review\api;

defined('MOODLE_INTERNAL') || die();

class api_provider {
    private const FALLBACK_LECTURES_HOURS = 36; // yeah
    private const FALLBACK_PZ_HOURS = 18; // yeah, again
    private const FALLBACK_LR_HOURS = 18; // and again

    public function get_workload(\stdClass $course): array {
        $apiworkload = $this->get_api_workload($course);
        if ($apiworkload !== null) {
            return $apiworkload + ['source' => 'api'];
        }

        return [
            'lectures' => self::FALLBACK_LECTURES_HOURS, // fallback values, should be better than nothing
            'pz' => self::FALLBACK_PZ_HOURS, // yeah, again
            'lr' => self::FALLBACK_LR_HOURS, // and again
            'source' => 'fallback', // change to api
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
