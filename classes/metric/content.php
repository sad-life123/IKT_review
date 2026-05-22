<?php
namespace local_ikt_review\metric;

defined('MOODLE_INTERNAL') || die();

class content extends base_metric {
    // Константы для логики заглушек з.е.
    const LECTURES_HOURS = 36;
    const PZ_HOURS = 18;
    const LR_HOURS = 18;
    
    const WEIGHT_T = 0.3;
    const WEIGHT_GR = 0.7;

    public function get_name(): string {
        return 'Content';
    }

    public function calculate(): array {
        global $DB;
        $sql = $this->get_sql('content.sql');
        $records = $DB->get_records_sql($sql);
        
        $results = [];
        $lectures = self::LECTURES_HOURS / 2;
        $pz = self::PZ_HOURS / 2;
        $lr = self::LR_HOURS / 4;
        
        foreach ($records as $rec) {
            $t = (int)$rec->t_count;
            $gr = (int)$rec->gr_count;
            
            // Content = 0,3*t/lectures + 0,7*gr/(PZ+LR)
            $denom = $pz + $lr;
            $content_val = ($lectures > 0 ? (self::WEIGHT_T * $t / $lectures) : 0) + 
                           ($denom > 0 ? (self::WEIGHT_GR * $gr / $denom) : 0);
            
            $results[$rec->courseid] = [
                'courseid' => $rec->courseid,
                'fullname' => $rec->fullname ?? 'Unknown',
                't' => $t,
                'gr' => $gr,
                'content' => $content_val
            ];
        }
        return $results;
    }
}
