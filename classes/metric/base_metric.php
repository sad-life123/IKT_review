<?php
namespace local_ikt_review\metric;

defined('MOODLE_INTERNAL') || die();

abstract class base_metric {
    protected function get_sql(string $filename): string {
        global $CFG;
        $path = $CFG->dirroot . '/local/ikt_review/sql/' . $filename;
        if (!file_exists($path)) {
            throw new \coding_exception("SQL file not found: " . $path);
        }
        return file_get_contents($path);
    }
    
    abstract public function get_name(): string;
    
    /**
     * Возвращает метрику для всех курсов.
     * @return array [courseid => metric_value_or_array]
     */
    abstract public function calculate(): array;
}
