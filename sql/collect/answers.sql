WITH params AS (
    SELECT
        CAST(:periodfrom AS bigint) AS periodfrom,
        CAST(:periodto AS bigint) AS periodto
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
visible_gradable_items AS (
    SELECT
        cm.course AS courseid,
        cm.instance,
        m.name AS modulename
      FROM tmp_ikt_review_courses tc
      JOIN {course_modules} cm ON cm.course = tc.courseid AND cm.visible = 1
      JOIN {course_sections} cs ON cs.id = cm.section AND cs.visible = 1
      JOIN {modules} m ON m.id = cm.module
     WHERE m.name IN ('assign', 'quiz', 'vpl')
),
assign_answers AS (
    SELECT
        a.course AS courseid,
        COUNT(DISTINCT (s.assignment, s.userid)) AS submit_count,
        COUNT(DISTINCT (s.assignment, s.userid)) FILTER (WHERE g.id IS NOT NULL) AS assign_graded_count
      FROM tmp_ikt_review_courses tc
      JOIN params p ON true
      JOIN {assign} a ON a.course = tc.courseid
      JOIN visible_gradable_items vgi ON vgi.courseid = a.course
       AND vgi.instance = a.id
       AND vgi.modulename = 'assign'
      JOIN {assign_submission} s ON s.assignment = a.id
      JOIN course_students cs ON cs.courseid = a.course AND cs.userid = s.userid
      LEFT JOIN {assign_grades} g ON g.assignment = s.assignment
       AND g.userid = s.userid
       AND g.grade IS NOT NULL
       AND g.grade >= 0
     WHERE s.status = 'submitted'
       AND s.timemodified BETWEEN p.periodfrom AND p.periodto
     GROUP BY a.course
),
quiz_answers AS (
    SELECT
        q.course AS courseid,
        COUNT(DISTINCT (qa.quiz, qa.userid)) AS attempt_count
      FROM tmp_ikt_review_courses tc
      JOIN params p ON true
      JOIN {quiz} q ON q.course = tc.courseid
      JOIN visible_gradable_items vgi ON vgi.courseid = q.course
       AND vgi.instance = q.id
       AND vgi.modulename = 'quiz'
      JOIN {quiz_attempts} qa ON qa.quiz = q.id
      JOIN course_students cs ON cs.courseid = q.course AND cs.userid = qa.userid
     WHERE qa.state = 'finished'
       AND qa.preview = 0
       AND qa.timefinish BETWEEN p.periodfrom AND p.periodto
     GROUP BY q.course
)
INSERT INTO {local_ikt_review_snap} (
    runid,
    courseid,
    submit_count,
    assign_graded_count,
    attempt_count,
    answer_count,
    timecreated
)
SELECT
    :runid,
    tc.courseid,
    COALESCE(aa.submit_count, 0),
    COALESCE(aa.assign_graded_count, 0),
    COALESCE(qa.attempt_count, 0),
    COALESCE(aa.submit_count, 0) + COALESCE(qa.attempt_count, 0),
    :now
  FROM tmp_ikt_review_courses tc
  LEFT JOIN assign_answers aa ON aa.courseid = tc.courseid
  LEFT JOIN quiz_answers qa ON qa.courseid = tc.courseid
ON CONFLICT (runid, courseid)
DO UPDATE SET
    submit_count = EXCLUDED.submit_count,
    assign_graded_count = EXCLUDED.assign_graded_count,
    attempt_count = EXCLUDED.attempt_count,
    answer_count = EXCLUDED.answer_count
