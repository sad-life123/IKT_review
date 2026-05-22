insert into mon_course_items (courseid, assign, quiz, vpl, resource, page, lesson, book, url )
select
            c.id AS courseid,
            SUM(CASE WHEN m.name = 'assign' THEN 1 ELSE 0 END) AS assign_count,
            SUM(CASE WHEN m.name = 'quiz' THEN 1 ELSE 0 END) AS quiz_count,
            SUM(CASE WHEN m.name = 'vpl' THEN 1 ELSE 0 END) AS vpl_count,
            SUM(CASE WHEN m.name = 'resource' THEN 1 ELSE 0 END) AS resource_count,
            SUM(CASE WHEN m.name = 'page' THEN 1 ELSE 0 END) AS page_count,
            SUM(CASE WHEN m.name = 'lesson' THEN 1 ELSE 0 END) AS lesson_count,
            SUM(CASE WHEN m.name = 'book' THEN 1 ELSE 0 END) AS book_count,
            SUM(CASE WHEN m.name = 'url' THEN 1 ELSE 0 END) AS url_count
            --SUM(CASE WHEN m.name = 'bigbluebuttonbn' THEN 1 ELSE 0 END) AS bbb_count
                    FROM mdl_course c 
        LEFT JOIN mdl_course_modules cm ON cm.course = c.id AND cm.visible = 1
        LEFT JOIN mdl_modules m ON m.id = cm.module
        LEFT JOIN mdl_course_sections s ON s.id = cm.section AND s.visible = 1
        where  c.visible = 1 and c.idnumber like'0%' --AND c.id in (1269, 5856, 10226)-- (1269, 5856,8230,5843,758,8174,2425,4326)
        GROUP BY c.id
       -- having count(m.name)>5
        ON CONFLICT (courseid) 
DO UPDATE SET 
    assign   = EXCLUDED.assign,
    quiz     = EXCLUDED.quiz,
    vpl      = EXCLUDED.vpl,
    resource = EXCLUDED.resource,
    page     = EXCLUDED.page,
    lesson   = EXCLUDED.lesson,
    book     = EXCLUDED.book,
    url      = EXCLUDED.url;
    --bbb      = EXCLUDED.bbb;
        