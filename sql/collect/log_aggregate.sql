CREATE TEMP TABLE tmp_ikt_review_log_agg AS
WITH course_students AS (
    SELECT DISTINCT
        e.courseid,
        ue.userid
      FROM tmp_ikt_review_courses tc
      JOIN {enrol} e ON e.courseid = tc.courseid
      JOIN {user_enrolments} ue ON ue.enrolid = e.id
      JOIN {context} ctx ON ctx.contextlevel = 50 AND ctx.instanceid = tc.courseid
      JOIN {role_assignments} ra ON ra.contextid = ctx.id AND ra.userid = ue.userid
      JOIN {role} r ON r.id = ra.roleid
     WHERE e.status = 0
       AND ue.status = 0
       AND r.shortname = 'student'
),
quoted_items AS (
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
  LEFT JOIN course_students cs ON cs.courseid = l.courseid AND cs.userid = l.userid
  LEFT JOIN quoted_items qi ON qi.courseid = l.courseid AND qi.cmid = l.contextinstanceid
 GROUP BY l.courseid
