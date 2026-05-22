<?php
namespace local_ikt_review\metric;

defined('MOODLE_INTERNAL') || die();

class bbb extends base_metric {
    public function get_name(): string {
        return 'BBB';
    }

    public function calculate(): array {
        global $DB;
        $sql = $this->get_sql('bbb.sql');
        $records = $DB->get_records_sql($sql);
        
        $results = [];
        foreach ($records as $rec) {
            // Заглушка для "живого" элемента:
            // 0/1 живой элемент если за семестр к элементу обращалось минимум 5 человек и 1 преподаватель в течение 1 часа.
            // Для демо-версии мы используем наличие элемента как базовую заглушку, 
            // так как анализ логов по времени - слишком тяжеловесная операция для простого демо.
            $has_bbb = (int)$rec->has_bbb;
            $is_live = $has_bbb > 0 ? 1 : 0;
            
            $results[$rec->courseid] = [
                'courseid' => $rec->courseid,
                'has_bbb' => $has_bbb,
                'is_live' => $is_live
            ];
        }
        return $results;
    }
}
