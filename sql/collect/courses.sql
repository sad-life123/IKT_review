CREATE TEMP TABLE tmp_ikt_review_courses AS
SELECT id AS courseid
  FROM {course}
 WHERE visible = 1
       -- IKT course idnumbers start with 0.
   AND idnumber LIKE '0%'
