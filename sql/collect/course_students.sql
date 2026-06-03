CREATE TEMP TABLE tmp_ikt_review_course_students AS
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
   AND r.shortname = :studentrole
