<?php
namespace local_ikt_review\metric;

defined('MOODLE_INTERNAL') || die();

class performance extends base_metric {
    public function get_name(): string {
        return 'Performance';
    }

    public function calculate(): array {
        global $DB;
        $sql = $this->get_sql('performance.sql');
        $records = $DB->get_records_sql($sql);
        
        $results = [];
        foreach ($records as $rec) {
            $results[$rec->courseid] = [
                'courseid' => $rec->courseid,
                'avg_grade' => (float)$rec->avg_grade
            ];
        }
        return $results;
    }
}
