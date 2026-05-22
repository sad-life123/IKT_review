INSERT INTO mon_course_info (courseid, modules, bbb, live) 
SELECT 
    mc.id AS courseid,
    COUNT(mcm.id) AS modules,
    SUM(CASE WHEN mcm.module = 28 THEN 1 ELSE 0 END) AS bbb,
    NULL AS live
FROM mdl_course mc
LEFT JOIN mdl_course_modules mcm ON mcm.course = mc.id
WHERE mc.idnumber LIKE '0%' 
 -- AND mc.id IN (2714, 2414)
GROUP BY mc.id
ON CONFLICT (courseid) 
DO UPDATE SET 
    modules = EXCLUDED.modules,
    bbb     = EXCLUDED.bbb,
    live    = EXCLUDED.live;