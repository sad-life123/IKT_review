INSERT INTO {local_ikt_review_snap} (
    runid,
    courseid,
    student_count,
    timecreated
)
SELECT
    :runid,
    tc.courseid,
    COUNT(DISTINCT ue.userid) FILTER (WHERE e.status = 0 AND ue.status = 0),
    :now
  FROM tmp_ikt_review_courses tc
  LEFT JOIN {enrol} e ON e.courseid = tc.courseid
  LEFT JOIN {user_enrolments} ue ON ue.enrolid = e.id
 GROUP BY tc.courseid
ON CONFLICT (runid, courseid)
DO UPDATE SET
    student_count = EXCLUDED.student_count
