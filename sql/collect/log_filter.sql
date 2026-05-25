SELECT
    l.courseid,
    l.userid,
    l.contextlevel,
    l.crud,
    l.action
  FROM {logstore_standard_log} l
  JOIN tmp_ikt_review_courses c ON c.courseid = l.courseid
 WHERE l.timecreated BETWEEN CAST(:periodfrom AS bigint) AND CAST(:periodto AS bigint)
   AND (
       l.contextlevel = 70
       OR l.action = 'submitted'
   )
