INSERT INTO {local_ikt_review_snap} (
    runid,
    courseid,
    fullname,
    shortname,
    idnumber,
    modules,
    timecreated
)
SELECT
    :runid,
    c.id,
    c.fullname,
    c.shortname,
    c.idnumber,
    COUNT(cm.id) FILTER (WHERE cs.visible = 1),
    :now
  FROM tmp_ikt_review_courses tc
  JOIN {course} c ON c.id = tc.courseid
  LEFT JOIN {course_modules} cm ON cm.course = c.id AND cm.visible = 1
  LEFT JOIN {course_sections} cs ON cs.id = cm.section
 GROUP BY c.id, c.fullname, c.shortname, c.idnumber
ON CONFLICT (runid, courseid)
DO UPDATE SET
    fullname = EXCLUDED.fullname,
    shortname = EXCLUDED.shortname,
    idnumber = EXCLUDED.idnumber,
    modules = EXCLUDED.modules
