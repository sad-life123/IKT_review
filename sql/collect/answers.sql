WITH params AS (
    SELECT
        CAST(:periodfrom AS bigint) AS periodfrom,
        CAST(:periodto AS bigint) AS periodto
),
assign_answers AS (
    SELECT
        a.course AS courseid,
        COUNT(s.id) AS submit_count
      FROM tmp_ikt_review_courses tc
      JOIN params p ON true
      JOIN {assign} a ON a.course = tc.courseid
      JOIN {assign_submission} s ON s.assignment = a.id
     WHERE s.status = 'submitted'
       AND s.timemodified BETWEEN p.periodfrom AND p.periodto
     GROUP BY a.course
),
quiz_answers AS (
    SELECT
        q.course AS courseid,
        COUNT(qa.id) AS attempt_count
      FROM tmp_ikt_review_courses tc
      JOIN params p ON true
      JOIN {quiz} q ON q.course = tc.courseid
      JOIN {quiz_attempts} qa ON qa.quiz = q.id
     WHERE qa.state = 'finished'
       AND qa.timefinish BETWEEN p.periodfrom AND p.periodto
     GROUP BY q.course
)
INSERT INTO {local_ikt_review_snap} (
    runid,
    courseid,
    submit_count,
    attempt_count,
    answer_count,
    timecreated
)
SELECT
    :runid,
    tc.courseid,
    COALESCE(aa.submit_count, 0),
    COALESCE(qa.attempt_count, 0),
    COALESCE(aa.submit_count, 0) + COALESCE(qa.attempt_count, 0),
    :now
  FROM tmp_ikt_review_courses tc
  LEFT JOIN assign_answers aa ON aa.courseid = tc.courseid
  LEFT JOIN quiz_answers qa ON qa.courseid = tc.courseid
ON CONFLICT (runid, courseid)
DO UPDATE SET
    submit_count = EXCLUDED.submit_count,
    attempt_count = EXCLUDED.attempt_count,
    answer_count = EXCLUDED.answer_count
