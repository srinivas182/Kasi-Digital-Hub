# ADR-022: Learning, offline downloads and assessments

- **Status:** Accepted
- **Date:** 2026-10-10
- **Sprint:** S14

## Decisions

1. **Enrolment** is free and instant, tied to the published course version at that moment. A new version is
   offered ("switch"), keeping progress for lessons that still exist. 16-17-year-olds: 16+ courses only. The
   `learning_records` consent is required (asked at enrolment).
2. **Progress is idempotent ("most progress wins"):** completing twice, an older video position or a lower practice
   score changes nothing. Offline events can therefore be sent more than once safely; graded work cannot be
   completed by a progress event.
3. **Finishing a lesson:** "I've finished" button, or 90% of a video watched.
4. **Data saver:** small (240p) video by default; standard and audio-only on request with sizes shown; the choice
   is remembered on the device.
5. **Offline downloads** (extends ADR-009): course data, media and the offline reader page are stored in the
   per-person cache `kasi-user-learn`; progress made offline is queued in `localStorage` (`kasi.user.learn.queue`)
   and sent when online. Per-person storage (`kasi-user-*` caches, `kasi.user.*` keys) is removed at sign-out and
   is never deleted by service worker updates. Downloaded media is served from the phone even when online (saves
   data). Cap: 500 MB per device. The offline page links to downloaded courses.
   *Exception to ADR-009:* the offline reader page is cached, only in the per-person cache, cleared at sign-out.
6. **Quizzes:** single choice, multiple choice, true/false. Practice quizzes include answers (marked on the phone,
   offline, don't count). Graded quizzes are marked on the server; answers and explanations are removed before
   anything is sent to the phone. Pass mark (default 70%), attempts (default 3) then a 24-hour wait.
7. **Assignments:** instructions, criteria, evidence (text/photo/PDF up to 10 MB, stored in the document vault as
   `learning_evidence`); assessors of the provider mark Competent / Not yet competent with feedback; resubmissions
   limited (default 2); audited. Moderation follows in S15.
8. **Completion:** all required lessons finished, graded quizzes passed, assignments competent (practice quizzes are
   optional); events `learn.enrolled`, `learn.lesson.completed`, `learn.quiz.passed`, `learn.assignment.assessed`,
   `learn.course.completed`.
