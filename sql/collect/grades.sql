WITH grade_values AS (
    SELECT
        gi.courseid,
        AVG(gg.finalgrade) AS avg_grade
      FROM tmp_ikt_review_courses tc
      JOIN {grade_items} gi ON gi.courseid = tc.courseid AND gi.itemtype = 'course'
      JOIN {grade_grades} gg ON gg.itemid = gi.id AND gg.finalgrade IS NOT NULL
     GROUP BY gi.courseid
)
INSERT INTO {local_ikt_review_snap} (
    runid,
    courseid,
    avg_grade,
    timecreated
)
SELECT
    :runid,
    tc.courseid,
    gv.avg_grade,
    :now
  FROM tmp_ikt_review_courses tc
  LEFT JOIN grade_values gv ON gv.courseid = tc.courseid
ON CONFLICT (runid, courseid)
DO UPDATE SET
    avg_grade = EXCLUDED.avg_grade
