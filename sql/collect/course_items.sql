INSERT INTO {local_ikt_review_snap} (
    runid,
    courseid,
    modules,
    bbb_count,
    assign_count,
    quiz_count,
    vpl_count,
    resource_count,
    page_count,
    lesson_count,
    book_count,
    url_count,
    gr_count,
    t_count,
    timecreated
)
SELECT
    :runid,
    tc.courseid,
    COUNT(cm.id) FILTER (WHERE cs.visible = 1),
    COUNT(cm.id) FILTER (WHERE cs.visible = 1 AND cm.module = :bbbmoduleid),
    COUNT(cm.id) FILTER (WHERE cs.visible = 1 AND m.name = 'assign'),
    COUNT(cm.id) FILTER (WHERE cs.visible = 1 AND m.name = 'quiz'),
    COUNT(cm.id) FILTER (WHERE cs.visible = 1 AND m.name = 'vpl'),
    COUNT(cm.id) FILTER (WHERE cs.visible = 1 AND m.name = 'resource'),
    COUNT(cm.id) FILTER (WHERE cs.visible = 1 AND m.name = 'page'),
    COUNT(cm.id) FILTER (WHERE cs.visible = 1 AND m.name = 'lesson'),
    COUNT(cm.id) FILTER (WHERE cs.visible = 1 AND m.name = 'book'),
    COUNT(cm.id) FILTER (WHERE cs.visible = 1 AND m.name = 'url'),
    COUNT(cm.id) FILTER (WHERE cs.visible = 1 AND m.name IN ('assign', 'quiz')),
    COUNT(cm.id) FILTER (WHERE cs.visible = 1 AND m.name IN ('resource', 'page', 'lesson', 'book')),
    :now
  FROM tmp_ikt_review_courses tc
  LEFT JOIN {course_modules} cm ON cm.course = tc.courseid AND cm.visible = 1
  LEFT JOIN {course_sections} cs ON cs.id = cm.section
  LEFT JOIN {modules} m ON m.id = cm.module
 GROUP BY tc.courseid
ON CONFLICT (runid, courseid)
DO UPDATE SET
    modules = EXCLUDED.modules,
    bbb_count = EXCLUDED.bbb_count,
    assign_count = EXCLUDED.assign_count,
    quiz_count = EXCLUDED.quiz_count,
    vpl_count = EXCLUDED.vpl_count,
    resource_count = EXCLUDED.resource_count,
    page_count = EXCLUDED.page_count,
    lesson_count = EXCLUDED.lesson_count,
    book_count = EXCLUDED.book_count,
    url_count = EXCLUDED.url_count,
    gr_count = EXCLUDED.gr_count,
    t_count = EXCLUDED.t_count
