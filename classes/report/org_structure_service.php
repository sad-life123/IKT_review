<?php
// This file is part of Moodle - http://moodle.org/

namespace local_ikt_review\report;

defined('MOODLE_INTERNAL') || die();

class org_structure_service {
    private const DAY_CATEGORY = 'Дневная форма обучения';

    public function get_stats(int $runid): array {
        global $DB;

        $categories = $DB->get_records('course_categories', null, '', 'id, name');
        $records = $DB->get_records_sql("
            SELECT
                s.courseid,
                s.gr_count,
                s.t_count,
                s.student_count,
                s.view_count,
                s.unique_view_count,
                s.answer_count,
                s.live_bbb_count,
                cc.path AS categorypath
              FROM {local_ikt_review_snap} s
              JOIN {course} c ON c.id = s.courseid
         LEFT JOIN {course_categories} cc ON cc.id = c.category
             WHERE s.runid = :runid
        ", ['runid' => $runid]);
        $metricvalues = $this->get_metric_values($runid);

        $stats = [
            'departments' => [],
        ];

        foreach ($records as $record) {
            $pathnames = $this->category_path_names((string)$record->categorypath, $categories);
            $org = $this->parse_org($pathnames);

            $faculty = $org['faculty'] !== '' ? $org['faculty'] : get_string('org_unknown', 'local_ikt_review');
            $department = $org['department'] !== '' ? $org['department'] : get_string('org_without_department', 'local_ikt_review');
            $degree = $org['degree'] !== '' ? $org['degree'] : get_string('org_without_degree', 'local_ikt_review');
            $departmentkey = $this->group_key([$faculty, $degree, $department]);
            if (!isset($stats['departments'][$departmentkey])) {
                $stats['departments'][$departmentkey] = $this->empty_group($departmentkey, $department, $faculty, $degree);
            }
            $this->add_course($stats['departments'][$departmentkey], $record, $metricvalues[(int)$record->courseid] ?? []);
        }

        $stats['departments'] = $this->sort_groups($stats['departments']);

        return $stats;
    }

    private function category_path_names(string $path, array $categories): array {
        $ids = preg_split('/\//', trim($path, '/'), -1, PREG_SPLIT_NO_EMPTY);
        $names = [];

        foreach ($ids as $id) {
            $id = (int)$id;
            if (isset($categories[$id])) {
                $names[] = (string)$categories[$id]->name;
            }
        }

        return $names;
    }

    private function parse_org(array $pathnames): array {
        $dayindex = array_search(self::DAY_CATEGORY, $pathnames, true);
        $isday = $dayindex !== false;
        $faculty = '';
        $degree = '';
        $department = '';

        if ($isday) {
            $afterday = array_slice($pathnames, $dayindex + 1);
            $faculty = $afterday[0] ?? '';

            foreach ($afterday as $name) {
                if ($this->is_degree($name) && $degree === '') {
                    $degree = $name;
                }
                if ($this->is_department($name) && $department === '') {
                    $department = $name;
                }
            }
        }

        return [
            'faculty' => $faculty,
            'degree' => $degree,
            'department' => $department,
        ];
    }

    private function add_course(array &$group, \stdClass $record, array $metrics): void {
        $gr = (int)$record->gr_count;
        $t = (int)$record->t_count;
        $studentcount = (int)$record->student_count;
        $elements = $gr + $t;
        $filled = $elements > 5;
        $atdenominator = $studentcount * $elements;
        $donedenominator = $studentcount * $gr;

        $group['total_courses']++;
        $group['filled_courses'] += $filled ? 1 : 0;
        $group['students'] += $studentcount;
        $group['elements'] += $elements;
        $group['views'] += (int)$record->view_count;
        $group['unique_views'] += (int)$record->unique_view_count;
        $group['answers'] += (int)$record->answer_count;
        $group['live_bbb_courses'] += (int)$record->live_bbb_count > 0 ? 1 : 0;

        if ($filled) {
            $group['sum_content'] += (float)($metrics['content'] ?? 0);
            $group['sum_at'] += $atdenominator > 0 ? (int)$record->view_count / $atdenominator : 0;
            $group['sum_unique_at'] += $atdenominator > 0 ? (int)$record->unique_view_count / $atdenominator : 0;
            $group['sum_done'] += $donedenominator > 0 ? (int)$record->answer_count / $donedenominator : 0;
            $group['sum_check'] += (float)($metrics['check'] ?? 0);
        }
    }

    private function empty_group(string $key, string $label, string $faculty = '', string $degree = ''): array {
        return [
            'key' => $key,
            'label' => $label,
            'faculty' => $faculty,
            'degree' => $degree,
            'total_courses' => 0,
            'filled_courses' => 0,
            'students' => 0,
            'elements' => 0,
            'views' => 0,
            'unique_views' => 0,
            'answers' => 0,
            'live_bbb_courses' => 0,
            'sum_content' => 0.0,
            'sum_at' => 0.0,
            'sum_unique_at' => 0.0,
            'sum_done' => 0.0,
            'sum_check' => 0.0,
        ];
    }

    private function get_metric_values(int $runid): array {
        global $DB;

        [$insql, $params] = $DB->get_in_or_equal(['content', 'check'], SQL_PARAMS_NAMED, 'metric');
        $params['runid'] = $runid;
        $records = $DB->get_records_select('local_ikt_review_metric', "runid = :runid AND metric $insql", $params);
        $values = [];

        foreach ($records as $record) {
            $payload = json_decode((string)$record->value, true);
            if (!is_array($payload) || !array_key_exists('value', $payload)) {
                continue;
            }
            $courseid = (int)$record->courseid;
            if (!isset($values[$courseid])) {
                $values[$courseid] = [];
            }
            $values[$courseid][(string)$record->metric] = (float)$payload['value'];
        }

        return $values;
    }

    private function sort_groups(array $groups): array {
        uasort($groups, static function(array $left, array $right): int {
            return $right['total_courses'] <=> $left['total_courses'];
        });

        return array_values($groups);
    }

    private function group_key(array $parts): string {
        return md5(implode('|', $parts));
    }

    private function is_degree(string $name): bool {
        return strpos($name, 'Бакалавриат') !== false || strpos($name, 'Магистратура') !== false;
    }

    private function is_department(string $name): bool {
        return strpos($name, 'Кафедра ') === 0;
    }
}
