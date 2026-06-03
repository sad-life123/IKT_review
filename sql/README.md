# IKT Review SQL

`collect/` contains PostgreSQL collect steps used by
`local_ikt_review\manager`.

The production pipeline creates temporary course and full-time student tables,
collects Moodle facts into `local_ikt_review_snap`, then calculates metrics in
PHP and stores them in `local_ikt_review_metric`.

Important current limitations:

- production courses are selected by `visible = 1 AND idnumber LIKE '0%'`;
- full-time students use the `full-time-student` role and current enrolments;
- old log periods use historical events, but current courses, enrolments and
  visible course modules;
- VPL is included in `Gr`, but VPL answers are not collected yet;
- `grades.sql` reads current course final grades without a period filter.

See [`../ARCHITECTURE.md`](../ARCHITECTURE.md) for formulas, source tables,
index deployment notes, and the technical debt list.
