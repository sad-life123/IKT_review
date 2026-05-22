<?php
namespace local_ikt_review;

defined('MOODLE_INTERNAL') || die();

class manager {
    public function get_all_metrics(): array {
        $metrics = [
            new \local_ikt_review\metric\content(),
            new \local_ikt_review\metric\attendance(),
            new \local_ikt_review\metric\bbb(),
            new \local_ikt_review\metric\done(),
            new \local_ikt_review\metric\performance()
        ];
        
        $combined = [];
        
        foreach ($metrics as $metric) {
            $results = $metric->calculate();
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
        $total_courses = count($all_courses_data);
        if ($total_courses === 0) {
            return [];
        }
        
        $filled_count = 0;
        $live_bbb_count = 0;
        
        $sum_elements = 0;
        $sum_content = 0;
        $sum_at = 0;
        $sum_done = 0;
        
        foreach ($all_courses_data as $data) {
            // "Заполненный" курс: хотя бы 1 элемент GR или T
            $gr = $data['gr'] ?? 0;
            $t = $data['t'] ?? 0;
            $elements = $gr + $t;
            
            $is_filled = $elements > 0;
            
            if ($is_filled) {
                $filled_count++;
                $sum_elements += $elements;
                $sum_content += $data['content'] ?? 0;
                $sum_at += $data['at'] ?? 0;
                $sum_done += $data['done'] ?? 0;
            }
            
            if (!empty($data['is_live'])) {
                $live_bbb_count++;
            }
        }
        
        $filled_ratio = $filled_count / $total_courses;
        
        $avg_elements = $filled_count > 0 ? $sum_elements / $filled_count : 0;
        $avg_content = $filled_count > 0 ? $sum_content / $filled_count : 0;
        $avg_at = $filled_count > 0 ? $sum_at / $filled_count : 0;
        $avg_done = $filled_count > 0 ? $sum_done / $filled_count : 0;
        
        return [
            'total_courses' => $total_courses,
            'filled_count' => $filled_count,
            'filled_ratio' => $filled_ratio,
            'live_bbb_count' => $live_bbb_count,
            'avg_elements' => $avg_elements,
            'avg_content' => $avg_content,
            'avg_at' => $avg_at,
            'avg_done' => $avg_done
        ];
    }
}
