SELECT
    c.id AS courseid,
    CASE WHEN COUNT(cm.id) > 0 THEN 1 ELSE 0 END AS has_bbb
FROM {course} c
LEFT JOIN {course_modules} cm ON cm.course = c.id AND cm.visible = 1
LEFT JOIN {modules} m ON m.id = cm.module AND m.name = 'bigbluebuttonbn'
GROUP BY c.id
