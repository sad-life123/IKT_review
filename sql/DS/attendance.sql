SELECT
    c.id AS courseid,
    COALESCE(v.view_count, 0) AS view_count,
    COALESCE(e.student_count, 0) AS student_count,
    (
        SELECT COUNT(cm.id)
        FROM {course_modules} cm
        JOIN {modules} m ON m.id = cm.module
        WHERE cm.course = c.id AND cm.visible = 1 AND m.name IN ('assign', 'quiz', 'resource', 'page', 'lesson', 'book')
    ) AS elements_count
FROM {course} c
LEFT JOIN (
    SELECT courseid, COUNT(id) AS view_count
    FROM {logstore_standard_log}
    WHERE crud = 'r' AND contextlevel = 70
    GROUP BY courseid
) v ON v.courseid = c.id
LEFT JOIN (
    SELECT e.courseid, COUNT(DISTINCT ue.userid) AS student_count
    FROM {enrol} e
    JOIN {user_enrolments} ue ON ue.enrolid = e.id
    GROUP BY e.courseid
) e ON e.courseid = c.id
