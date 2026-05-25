INSERT INTO {local_ikt_review_snap} (
    runid,
    courseid,
    view_count,
    unique_view_count,
    active_users,
    timecreated
)
SELECT
    :runid,
    tc.courseid,
    COALESCE(logagg.view_count, 0),
    COALESCE(logagg.unique_view_count, 0),
    COALESCE(logagg.active_users, 0),
    :now
  FROM tmp_ikt_review_courses tc
  LEFT JOIN tmp_ikt_review_log_agg logagg ON logagg.courseid = tc.courseid
ON CONFLICT (runid, courseid)
DO UPDATE SET
    view_count = EXCLUDED.view_count,
    unique_view_count = EXCLUDED.unique_view_count,
    active_users = EXCLUDED.active_users
