CREATE TEMP TABLE tmp_ikt_review_log_filtered AS
SELECT
    l.courseid,
    l.userid,
    l.timecreated,
    l.contextlevel,
    l.crud,
    l.action
  FROM {logstore_standard_log} l
  JOIN tmp_ikt_review_courses c ON c.courseid = l.courseid
 WHERE l.timecreated BETWEEN :periodfrom AND :periodto
   AND (
       l.contextlevel = 70
       OR l.action = 'submitted'
   )
