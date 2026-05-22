SELECT
    c.id AS courseid,
    COALESCE(sub_assign.ans_count, 0) + COALESCE(sub_quiz.ans_count, 0) AS total_ans,
    (
        SELECT COUNT(cm.id)
        FROM {course_modules} cm
        JOIN {modules} m ON m.id = cm.module
        WHERE cm.course = c.id AND cm.visible = 1 AND m.name IN ('assign', 'quiz')
    ) AS gr_count,
    COALESCE(e.student_count, 0) AS student_count
FROM {course} c
LEFT JOIN (
    SELECT a.course, COUNT(DISTINCT s.userid) AS ans_count
    FROM {assign} a
    JOIN {assign_submission} s ON s.assignment = a.id
    WHERE s.status = 'submitted'
    GROUP BY a.course
) sub_assign ON sub_assign.course = c.id
LEFT JOIN (
    SELECT q.course, COUNT(DISTINCT qa.userid) AS ans_count
    FROM {quiz} q
    JOIN {quiz_attempts} qa ON qa.quiz = q.id
    WHERE qa.state = 'finished'
    GROUP BY q.course
) sub_quiz ON sub_quiz.course = c.id
LEFT JOIN (
    SELECT e.courseid, COUNT(DISTINCT ue.userid) AS student_count
    FROM {enrol} e
    JOIN {user_enrolments} ue ON ue.enrolid = e.id
    GROUP BY e.courseid
) e ON e.courseid = c.id
