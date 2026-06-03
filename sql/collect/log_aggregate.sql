CREATE TEMP TABLE tmp_ikt_review_log_agg AS
WITH quoted_items AS (
    SELECT
        cm.course AS courseid,
        cm.id AS cmid
      FROM tmp_ikt_review_courses tc
      JOIN {course_modules} cm ON cm.course = tc.courseid AND cm.visible = 1
      JOIN {course_sections} cs ON cs.id = cm.section AND cs.visible = 1
      JOIN {modules} m ON m.id = cm.module
     WHERE m.name IN ('assign', 'quiz', 'vpl', 'resource', 'page', 'lesson', 'book')
)
SELECT
    l.courseid,
    COUNT(*) FILTER (WHERE cs.userid IS NOT NULL AND qi.cmid IS NOT NULL AND l.crud = 'r' AND l.contextlevel = 70) AS view_count,
    COUNT(DISTINCT (l.userid, l.contextinstanceid)) FILTER (WHERE cs.userid IS NOT NULL AND qi.cmid IS NOT NULL AND l.crud = 'r' AND l.contextlevel = 70) AS unique_view_count,
    COUNT(*) FILTER (WHERE cs.userid IS NOT NULL AND l.action = 'submitted') AS submit_events,
    COUNT(DISTINCT l.userid) FILTER (WHERE cs.userid IS NOT NULL AND qi.cmid IS NOT NULL AND l.contextlevel = 70) AS active_users
  FROM tmp_ikt_review_log_filtered l
  LEFT JOIN tmp_ikt_review_course_students cs ON cs.courseid = l.courseid AND cs.userid = l.userid
  LEFT JOIN quoted_items qi ON qi.courseid = l.courseid AND qi.cmid = l.contextinstanceid
 GROUP BY l.courseid
