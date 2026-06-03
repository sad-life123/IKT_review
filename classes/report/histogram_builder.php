<?php
// This file is part of Moodle - http://moodle.org/
// build histogram COUNTS. maybe chart api is better.

namespace local_ikt_review\report;

defined('MOODLE_INTERNAL') || die();

class histogram_builder {
    public function empty_buckets(): array {
        return [
            ['key' => '0', 'label' => '0', 'min' => 0, 'max' => 0, 'count' => 0],
            ['key' => '1_5', 'label' => '1-5', 'min' => 1, 'max' => 5, 'count' => 0],
            ['key' => '6_10', 'label' => '6-10', 'min' => 6, 'max' => 10, 'count' => 0],
            ['key' => '11_20', 'label' => '11-20', 'min' => 11, 'max' => 20, 'count' => 0],
            ['key' => '21_40', 'label' => '21-40', 'min' => 21, 'max' => 40, 'count' => 0],
            ['key' => '41_plus', 'label' => '41+', 'min' => 41, 'max' => null, 'count' => 0],
        ];
    }

    public function increment(array &$buckets, int $value): void { // ++
        foreach ($buckets as &$bucket) {
            $max = $bucket['max'];
            if ($value >= $bucket['min'] && ($max === null || $value <= $max)) {
                $bucket['count']++;
                return;
            }
        }
    }
}
