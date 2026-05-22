<?php
namespace local_ikt_review\metric;

defined('MOODLE_INTERNAL') || die();

class done extends base_metric {
    public function get_name(): string {
        return 'Done';
    }

    public function calculate(): array {
        global $DB;
        $sql = $this->get_sql('done.sql');
        $records = $DB->get_records_sql($sql);
        
        $results = [];
        foreach ($records as $rec) {
            $total_ans = (int)$rec->total_ans;
            $gr_count = (int)$rec->gr_count;
            $student_count = (int)$rec->student_count;
            
            // Done = total_ans / (gr * s)
            $denom = $gr_count * $student_count;
            $done = $denom > 0 ? ($total_ans / $denom) : 0;
            
            $results[$rec->courseid] = [
                'courseid' => $rec->courseid,
                'total_ans' => $total_ans,
                'gr_count' => $gr_count,
                'student_count' => $student_count,
                'done' => $done
            ];
        }
        return $results;
    }
}
