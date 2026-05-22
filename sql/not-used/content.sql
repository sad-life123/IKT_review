SELECT
    c.id AS courseid,
    c.fullname,
    SUM(CASE WHEN m.name IN ('assign', 'quiz') THEN 1 ELSE 0 END) AS gr_count,
    SUM(CASE WHEN m.name IN ('resource', 'page', 'lesson', 'book') THEN 1 ELSE 0 END) AS t_count
FROM {course} c
LEFT JOIN {course_modules} cm ON cm.course = c.id AND cm.visible = 1
LEFT JOIN {modules} m ON m.id = cm.module
GROUP BY c.id, c.fullname
