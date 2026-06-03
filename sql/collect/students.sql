INSERT INTO {local_ikt_review_snap} (
    runid,
    courseid,
    student_count,
    timecreated
)
SELECT
    :runid,
    tc.courseid,
    COUNT(DISTINCT cs.userid),
    :now
  FROM tmp_ikt_review_courses tc
  LEFT JOIN tmp_ikt_review_course_students cs ON cs.courseid = tc.courseid
 GROUP BY tc.courseid
ON CONFLICT (runid, courseid)
DO UPDATE SET
    student_count = EXCLUDED.student_count
