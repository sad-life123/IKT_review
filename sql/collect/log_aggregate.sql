CREATE TEMP TABLE tmp_ikt_review_log_agg AS
SELECT
    l.courseid,
    COUNT(*) FILTER (WHERE l.crud = 'r' AND l.contextlevel = 70) AS view_count,
    COUNT(*) FILTER (WHERE l.action = 'submitted') AS submit_events,
    COUNT(DISTINCT l.userid) FILTER (WHERE l.contextlevel = 70) AS active_users
  FROM {logstore_standard_log} l
  JOIN tmp_ikt_review_courses c ON c.courseid = l.courseid
 WHERE l.timecreated BETWEEN :periodfrom AND :periodto
 GROUP BY l.courseid
