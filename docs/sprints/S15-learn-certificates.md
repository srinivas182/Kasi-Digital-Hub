# Sprint 15 - KasiLearn certificates, cohorts and moderation

- **Status:** Complete
- **Version:** v0.15.0

## Delivered

- **First load** 130.0 -> about 122.9 KB (tailwind-merge replaced by a tested `cn()`); early-warning line at 125 KB.
- **Certificates:** completion certificates and statements of results, signatory, verification, share, learning
  record PDF, revocation, shown on the KasiWork CV (can be hidden) (ADR-023).
- **Cohorts:** hub approval, join codes, waiting list, facilitator adds, sessions as hub events with attendance,
  dashboard with progress, attendance, "stuck" learners and nudges.
- **Moderation:** risk-based sample, agree / disagree, re-assessment, certificate withdrawal and re-issue, CSV report.
- **Demo:** HBM signatory, a blended cohort at Tsutsumani (code KASI24) with a session next week.

## Target not fully met

The spec aimed for a first load of at most 120 KB. It is about 122.9 KB: what remains is mostly the framework itself
(React and Inertia, about 110 KB). The 125 KB early-warning line still leaves room under the 130 KB budget.

## Issues found and fixed during the sprint

- A new submission's status was not set in memory, so assessing it in the same request failed ("already assessed").
- Blended courses could complete before any session had taken place - they now wait for scheduled sessions.
- A moderation query first used SQL string concatenation that differs between MySQL and SQLite; replaced.
