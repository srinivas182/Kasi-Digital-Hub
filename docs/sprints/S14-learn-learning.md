# Sprint 14 - KasiLearn learning and assessments

- **Status:** Complete
- **Version:** v0.14.0

## Delivered

- **Housekeeping:** CI on a fixed Ubuntu 24.04 image (before `ubuntu-latest` moves to Ubuntu 26); cache action v5.
- **Enrolment** (version-pinned, switching, leaving), "enrolment is open" for saved courses, "My learning",
  "Continue" on the hub home.
- **Player:** finish button / 90% video, resume, data saver with sizes, previous/next.
- **Offline:** download with size and cap, offline reader, queued progress synced automatically, cleared at sign-out.
- **Quizzes** (practice and graded, server marking, attempts and wait) with a quiz builder and AI-suggested
  questions; **assignments** with criteria and evidence; **assessor** queue and marking.
- **Completion** rules and events (ADR-022). Demo: customer service course has a practice quiz, graded quiz and
  assignment.

## Issues found and fixed during the sprint

- The assessment form set the decision and submitted in the same click (stale decision) - it now takes the
  decision from the button pressed.
- Service worker updates would have deleted downloaded courses (they removed all unknown caches).
- The offline-reader lint rule caught a state update during render setup.
- An existing admin test could fail when random test data happened to match its search; it now uses unique values.
  Found by the new local MySQL run (`scripts/test-mysql.sh`), which now runs before every push.

## To watch

The public first load is at 130.0 of 130 KB. S15 starts with trimming shared code.
