<?php
// This file is part of Moodle - http://moodle.org/

namespace local_ikt_review\report;

defined('MOODLE_INTERNAL') || die();

class run_details_page {
    public function export_for_template(array $stats): array {
        return [
            'cards' => $this->cards($stats),
            'quality' => $this->quality($stats),
            'histograms' => [
                $this->histogram_context(get_string('details_hist_elements', 'local_ikt_review'), $stats['histograms']['elements']),
                $this->histogram_context(get_string('details_hist_gr', 'local_ikt_review'), $stats['histograms']['gr']),
                $this->histogram_context(get_string('details_hist_t', 'local_ikt_review'), $stats['histograms']['t']),
            ],
            'module_counts' => $this->module_counts($stats['module_counts']),
            'org_structure' => $this->org_structure($stats['org'] ?? []),
        ];
    }

    public function strings(): array {
        return [
            'str_details_title' => get_string('rundetails', 'local_ikt_review'),
            'str_details_histograms' => get_string('details_histograms', 'local_ikt_review'),
            'str_details_quality' => get_string('details_quality', 'local_ikt_review'),
            'str_details_module_counts' => get_string('details_module_counts', 'local_ikt_review'),
            'str_org_departments' => get_string('org_departments', 'local_ikt_review'),
            'str_org_faculty' => get_string('org_faculty', 'local_ikt_review'),
            'str_org_department' => get_string('org_department', 'local_ikt_review'),
            'str_org_degree' => get_string('org_degree', 'local_ikt_review'),
            'str_org_courses' => get_string('org_courses', 'local_ikt_review'),
            'str_org_filled' => get_string('org_filled', 'local_ikt_review'),
            'str_org_filled_ratio' => get_string('org_filled_ratio', 'local_ikt_review'),
            'str_org_students' => get_string('org_students', 'local_ikt_review'),
            'str_org_avg_content' => get_string('report_avg_content', 'local_ikt_review'),
            'str_org_avg_at' => get_string('report_avg_at', 'local_ikt_review'),
            'str_org_avg_unique_at' => get_string('report_avg_unique_at', 'local_ikt_review'),
            'str_org_avg_done' => get_string('report_avg_done', 'local_ikt_review'),
            'str_org_avg_check' => get_string('report_avg_check', 'local_ikt_review'),
        ];
    }

    private function cards(array $stats): array {
        return [
            [
                'label' => get_string('details_courses_with_students', 'local_ikt_review'),
                'value' => $this->format_int($stats['courses_with_students'] ?? 0),
                'hint' => get_string('details_courses_with_students_help', 'local_ikt_review'),
            ],
            [
                'label' => get_string('details_courses_without_students', 'local_ikt_review'),
                'value' => $this->format_int($stats['courses_without_students'] ?? 0),
                'hint' => get_string('details_courses_without_students_help', 'local_ikt_review'),
            ],
            [
                'label' => get_string('details_total_views', 'local_ikt_review'),
                'value' => $this->format_int($stats['total_views'] ?? 0),
                'hint' => get_string('details_total_views_help', 'local_ikt_review'),
            ],
            [
                'label' => get_string('details_total_answers', 'local_ikt_review'),
                'value' => $this->format_int($stats['total_answers'] ?? 0),
                'hint' => get_string('details_total_answers_help', 'local_ikt_review'),
            ],
        ];
    }

    private function quality(array $stats): array {
        return [
            [
                'label' => get_string('details_filled_without_students', 'local_ikt_review'),
                'value' => $this->format_int($stats['filled_without_students'] ?? 0),
            ],
            [
                'label' => get_string('details_courses_with_vpl', 'local_ikt_review'),
                'value' => $this->format_int($stats['courses_with_vpl'] ?? 0),
            ],
            [
                'label' => get_string('details_total_vpl_items', 'local_ikt_review'),
                'value' => $this->format_int($stats['total_vpl_items'] ?? 0),
            ],
            [
                'label' => get_string('details_live_bbb_courses', 'local_ikt_review'),
                'value' => $this->format_int($stats['live_bbb_courses'] ?? 0),
            ],
            [
                'label' => get_string('details_total_unique_views', 'local_ikt_review'),
                'value' => $this->format_int($stats['total_unique_views'] ?? 0),
            ],
            [
                'label' => get_string('details_total_graded', 'local_ikt_review'),
                'value' => $this->format_int($stats['total_graded'] ?? 0),
            ],
        ];
    }

    private function module_counts(array $counts): array {
        $modulecounts = [];
        $maxmodulecount = max($counts ?: [0]);

        foreach ($counts as $key => $count) {
            $modulecounts[] = [
                'label' => $key,
                'count' => $this->format_int($count),
                'percent' => $this->percent_width((int)$count, (int)$maxmodulecount),
            ];
        }

        return $modulecounts;
    }

    private function org_structure(array $org): array {
        if (!$org) {
            return [
                'has_departments' => false,
                'department_rows' => [],
            ];
        }

        $departmentrows = $this->org_group_rows(array_slice($org['departments'] ?? [], 0, 30));

        return [
            'has_departments' => !empty($departmentrows),
            'department_rows' => $departmentrows,
        ];
    }

    private function org_group_rows(array $groups): array {
        $rows = [];

        foreach ($groups as $group) {
            $filledcourses = (int)($group['filled_courses'] ?? 0);
            $totalcourses = (int)($group['total_courses'] ?? 0);
            $avgdenominator = max(1, $filledcourses);

            $rows[] = [
                'label' => s($group['label'] ?? ''),
                'faculty' => s($group['faculty'] ?? ''),
                'degree' => s($group['degree'] ?? ''),
                'total_courses' => $this->format_int($totalcourses),
                'filled_courses' => $this->format_int($filledcourses),
                'filled_ratio' => $this->format_percent($totalcourses > 0 ? $filledcourses / $totalcourses : 0),
                'students' => $this->format_int($group['students'] ?? 0),
                'avg_content' => $this->format_number(($group['sum_content'] ?? 0) / $avgdenominator, 4),
                'avg_at' => $this->format_number(($group['sum_at'] ?? 0) / $avgdenominator, 4),
                'avg_unique_at' => $this->format_number(($group['sum_unique_at'] ?? 0) / $avgdenominator, 4),
                'avg_done' => $this->format_number(($group['sum_done'] ?? 0) / $avgdenominator, 4),
                'avg_check' => $this->format_number(($group['sum_check'] ?? 0) / $avgdenominator, 4),
                'live_bbb_courses' => $this->format_int($group['live_bbb_courses'] ?? 0),
            ];
        }

        return $rows;
    }

    private function histogram_context(string $title, array $buckets): array {
        $maxcount = 0;
        foreach ($buckets as $bucket) {
            $maxcount = max($maxcount, (int)$bucket['count']);
        }

        $rows = [];
        foreach ($buckets as $bucket) {
            $count = (int)$bucket['count'];
            $rows[] = [
                'label' => s($bucket['label']),
                'count' => $this->format_int($count),
                'percent' => $this->percent_width($count, $maxcount),
            ];
        }

        return [
            'title' => $title,
            'rows' => $rows,
        ];
    }

    private function percent_width(int $value, int $max): int {
        if ($max <= 0 || $value <= 0) {
            return 0;
        }

        return max(2, (int)round($value / $max * 100));
    }

    private function format_int($value): string {
        return (string)(int)$value;
    }

    private function format_number($value, int $decimals): string {
        return format_float((float)$value, $decimals);
    }

    private function format_percent($ratio): string {
        return format_float((float)$ratio * 100, 2) . '%';
    }

}
