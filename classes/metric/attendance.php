<?php
namespace local_ikt_review\metric;

defined('MOODLE_INTERNAL') || die();

class attendance extends base_metric {
    public function get_name(): string {
        return 'Attendance';
    }

    public function calculate(): array {
        global $DB;
        $sql = $this->get_sql('attendance.sql');
        $records = $DB->get_records_sql($sql);
        
        $results = [];
        foreach ($records as $rec) {
            $view_count = (int)$rec->view_count;
            $elements_count = (int)$rec->elements_count; // Gr + T
            
            // At = view/(gr+t)
            $at = $elements_count > 0 ? ($view_count / $elements_count) : 0;
            
            $results[$rec->courseid] = [
                'courseid' => $rec->courseid,
                'view_count' => $view_count,
                'elements_count' => $elements_count,
                'at' => $at
            ];
        }
        return $results;
    }
}
