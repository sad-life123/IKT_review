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
        MAX(j.timecreated) AS last_teacher_join
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
     GROUP BY j.bbb_id, j.courseid
),
participant_window AS (
    SELECT DISTINCT
        j.bbb_id,
        j.userid,
        j.timecreated
      FROM joins j
     WHERE NOT EXISTS (
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
live_bbb AS (
    SELECT
        t.courseid,
        t.bbb_id,
        COUNT(DISTINCT p.userid) AS participants
      FROM teacher_joins t
      LEFT JOIN participant_window p ON p.bbb_id = t.bbb_id
       AND p.timecreated BETWEEN (t.last_teacher_join - 3600) AND (t.last_teacher_join + 3600)
     GROUP BY t.courseid, t.bbb_id, t.last_teacher_join
    HAVING COUNT(DISTINCT p.userid) >= 4
),
course_live_bbb AS (
    SELECT
        courseid,
        COUNT(*) AS live_bbb_count
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
