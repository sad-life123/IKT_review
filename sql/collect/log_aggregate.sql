CREATE TEMP TABLE tmp_ikt_review_log_agg AS
SELECT
    courseid,
    COUNT(*) FILTER (WHERE crud = 'r' AND contextlevel = 70) AS view_count,
    COUNT(DISTINCT (userid, contextinstanceid)) FILTER (WHERE crud = 'r' AND contextlevel = 70) AS unique_view_count,
    COUNT(*) FILTER (WHERE action = 'submitted') AS submit_events,
    COUNT(DISTINCT userid) FILTER (WHERE contextlevel = 70) AS active_users
  FROM tmp_ikt_review_log_filtered
 GROUP BY courseid
