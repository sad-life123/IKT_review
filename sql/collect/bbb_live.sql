WITH params AS (
    SELECT
        CAST(:periodfrom AS bigint) AS periodfrom,
        CAST(:periodto AS bigint) AS periodto
),
bbb_filter AS (
    SELECT
        b.id AS bbb_id,
        b.course AS courseid,
        cm.id AS cmid
      FROM tmp_ikt_review_courses tc
      JOIN {bigbluebuttonbn} b ON b.course = tc.courseid
      JOIN {course_modules} cm ON cm.course = b.course
       AND cm.instance = b.id
       AND cm.module = :bbbmoduleid
       AND cm.visible = 1
      JOIN {course_sections} cs ON cs.id = cm.section AND cs.visible = 1
),
course_students AS (
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
joins AS (
    SELECT
        bf.bbb_id,
        bf.courseid,
        bf.cmid,
        l.userid,
        l.timecreated
      FROM bbb_filter bf
      JOIN params p ON true
      JOIN {bigbluebuttonbn_logs} l ON l.bigbluebuttonbnid = bf.bbb_id
     WHERE l.log = 'Join'
       AND l.timecreated BETWEEN p.periodfrom AND p.periodto
),
teacher_joins AS (
    SELECT
        j.bbb_id,
        j.courseid,
        j.userid,
        j.timecreated
      FROM joins j
     WHERE EXISTS (
        SELECT 1
          FROM {role_assignments} ra
          JOIN {role} r ON r.id = ra.roleid
          JOIN {context} ctx ON ctx.id = ra.contextid
         WHERE ra.userid = j.userid
           AND r.shortname IN ('editingteacher', 'teacher')
           AND (
               (ctx.contextlevel = 50 AND ctx.instanceid = j.courseid)
               OR (ctx.contextlevel = 70 AND ctx.instanceid = j.cmid)
           )
     )
),
student_joins AS (
    SELECT DISTINCT
        j.bbb_id,
        j.courseid,
        j.userid,
        j.timecreated
      FROM joins j
      JOIN course_students cs ON cs.courseid = j.courseid AND cs.userid = j.userid
),
event_windows AS (
    SELECT bbb_id, courseid, timecreated AS window_start FROM teacher_joins
    UNION
    SELECT bbb_id, courseid, timecreated AS window_start FROM student_joins
),
live_bbb AS (
    SELECT
        w.courseid,
        w.bbb_id
      FROM event_windows w
      LEFT JOIN teacher_joins t ON t.bbb_id = w.bbb_id
       AND t.timecreated BETWEEN w.window_start AND (w.window_start + 3600)
      LEFT JOIN student_joins s ON s.bbb_id = w.bbb_id
       AND s.timecreated BETWEEN w.window_start AND (w.window_start + 3600)
     GROUP BY w.courseid, w.bbb_id, w.window_start
    HAVING COUNT(DISTINCT t.userid) >= 1
       AND COUNT(DISTINCT s.userid) >= 4
),
course_live_bbb AS (
    SELECT
        courseid,
        COUNT(DISTINCT bbb_id) AS live_bbb_count
      FROM live_bbb
     GROUP BY courseid
)
INSERT INTO {local_ikt_review_snap} (
    runid,
    courseid,
    live_bbb_count,
    timecreated
)
SELECT
    :runid,
    tc.courseid,
    COALESCE(clb.live_bbb_count, 0),
    :now
  FROM tmp_ikt_review_courses tc
  LEFT JOIN course_live_bbb clb ON clb.courseid = tc.courseid
ON CONFLICT (runid, courseid)
DO UPDATE SET
    live_bbb_count = EXCLUDED.live_bbb_count
