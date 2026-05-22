INSERT INTO {local_ikt_review_snap} (
    runid,
    courseid,
    avg_grade,
    timecreated
)
SELECT
    :runid,
    tc.courseid,
    AVG(gg.finalgrade),
    :now
  FROM tmp_ikt_review_courses tc
  LEFT JOIN {grade_items} gi ON gi.courseid = tc.courseid AND gi.itemtype = 'course'
  LEFT JOIN {grade_grades} gg ON gg.itemid = gi.id AND gg.finalgrade IS NOT NULL
 GROUP BY tc.courseid
ON CONFLICT (runid, courseid)
DO UPDATE SET
    avg_grade = EXCLUDED.avg_grade
