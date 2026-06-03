<?php
// This file is part of Moodle - http://moodle.org/

namespace local_ikt_review\report;

defined('MOODLE_INTERNAL') || die();

class run_analytics_service {
    private histogram_builder $histogrambuilder;
    private org_structure_service $orgstructure;

    public function __construct(?histogram_builder $histogrambuilder = null, ?org_structure_service $orgstructure = null) {
        $this->histogrambuilder = $histogrambuilder ?? new histogram_builder();
        $this->orgstructure = $orgstructure ?? new org_structure_service();
    }

    public function get_stats(int $runid, bool $includeorg = false): array {
        global $DB;

        $records = $DB->get_records('local_ikt_review_snap', ['runid' => $runid], 'courseid ASC',
            'courseid, gr_count, t_count, assign_count, quiz_count, vpl_count, resource_count, page_count, lesson_count, book_count, url_count, student_count, view_count, unique_view_count, submit_count, assign_graded_count, attempt_count, answer_count, live_bbb_count');

        $stats = [
            'total_courses' => count($records),
            'filled_courses' => 0,
            'courses_with_students' => 0,
            'courses_without_students' => 0,
            'filled_without_students' => 0,
            'total_student_enrolments' => 0,
            'total_elements' => 0,
            'total_gr' => 0,
            'total_t' => 0,
            'total_views' => 0,
            'total_unique_views' => 0,
            'total_answers' => 0,
            'total_submits' => 0,
            'total_graded' => 0,
            'courses_with_vpl' => 0,
            'total_vpl_items' => 0,
            'live_bbb_courses' => 0,
            'module_counts' => [
                'assign' => 0,
                'quiz' => 0,
                'vpl' => 0,
                'resource' => 0,
                'page' => 0,
                'lesson' => 0,
                'book' => 0,
                'url' => 0,
            ],
            'histograms' => [
                'elements' => $this->histogrambuilder->empty_buckets(),
                'gr' => $this->histogrambuilder->empty_buckets(),
                't' => $this->histogrambuilder->empty_buckets(),
            ],
            'org' => [],
        ];

        foreach ($records as $record) {
            $gr = (int)$record->gr_count;
            $t = (int)$record->t_count;
            $elements = $gr + $t;
            $studentcount = (int)$record->student_count;
            $vplcount = (int)$record->vpl_count;

            if ($elements > 5) {
                $stats['filled_courses']++;
                if ($studentcount === 0) {
                    $stats['filled_without_students']++;
                }
            }

            if ($studentcount > 0) {
                $stats['courses_with_students']++;
            } else {
                $stats['courses_without_students']++;
            }

            if ($vplcount > 0) {
                $stats['courses_with_vpl']++;
            }

            if ((int)$record->live_bbb_count > 0) {
                $stats['live_bbb_courses']++;
            }

            $stats['total_student_enrolments'] += $studentcount;
            $stats['total_elements'] += $elements;
            $stats['total_gr'] += $gr;
            $stats['total_t'] += $t;
            $stats['total_views'] += (int)$record->view_count;
            $stats['total_unique_views'] += (int)$record->unique_view_count;
            $stats['total_answers'] += (int)$record->answer_count;
            $stats['total_submits'] += (int)$record->submit_count;
            $stats['total_graded'] += (int)$record->assign_graded_count;
            $stats['total_vpl_items'] += $vplcount;

            $stats['module_counts']['assign'] += (int)$record->assign_count;
            $stats['module_counts']['quiz'] += (int)$record->quiz_count;
            $stats['module_counts']['vpl'] += $vplcount;
            $stats['module_counts']['resource'] += (int)$record->resource_count;
            $stats['module_counts']['page'] += (int)$record->page_count;
            $stats['module_counts']['lesson'] += (int)$record->lesson_count;
            $stats['module_counts']['book'] += (int)$record->book_count;
            $stats['module_counts']['url'] += (int)$record->url_count;

            $this->histogrambuilder->increment($stats['histograms']['elements'], $elements);
            $this->histogrambuilder->increment($stats['histograms']['gr'], $gr);
            $this->histogrambuilder->increment($stats['histograms']['t'], $t);
        }

        if ($includeorg) {
            $stats['org'] = $this->orgstructure->get_stats($runid);
        }

        return $stats;
    }
}
