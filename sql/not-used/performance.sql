SELECT
    c.id AS courseid,
    AVG(gg.finalgrade) AS avg_grade
FROM {course} c
LEFT JOIN {grade_items} gi ON gi.courseid = c.id AND gi.itemtype = 'course'
LEFT JOIN {grade_grades} gg ON gg.itemid = gi.id AND gg.finalgrade IS NOT NULL
GROUP BY c.id
