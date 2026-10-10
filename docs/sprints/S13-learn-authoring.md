# Sprint 13 - KasiLearn courses and authoring

- **Status:** Complete
- **Version:** v0.13.0

## Delivered

- **Training providers:** registration (shared with employers), verification checklist, team (admins, authors,
  assessors), accreditation claims with evidence and KasiHub verification.
- **Authoring:** courses (details, outcomes, topic, level, language, age, delivery, licence, NQF/credits when
  accredited), modules and lessons (text, video, audio, download; quiz/assignment placeholders for S14), ordering,
  rich-text editor with pictures (with descriptions), tables and tip boxes, AI writing help, data sizes.
- **Checks and review:** accessibility checks, submit, provider approval, KasiHub review where needed, frozen
  versions, send back with comments, unpublish, history (ADR-021).
- **Media:** WebP pictures; 240p/360p/audio-only video conversion with ffmpeg (fake in CI, real in the
  integration job); ffmpeg added to the Docker image.
- **Catalogue:** search and filters (topic, level, language, delivery, data size, accredited, saved), course pages
  with contents and data per lesson, free preview lessons, public pages, "Courses for you" on the hub home.
- **Demo:** three published HBM EduTech demo courses and one draft.

## Issues found and fixed during the sprint

- The editor version first installed had a published security advisory, and an indirect dependency
  (`shell-quote`) had a new critical advisory that would have failed CI on the next push. Both resolved.
- The demo provider uses the existing organisation type `training_provider`; the first code used `provider`.
- CI (MySQL) failed one test that checked the order of keys in the stored video versions; MySQL re-orders
  JSON object keys. The app reads versions by name; the test now ignores order.
- KasiHub reviewers' buttons first set the decision and submitted in one click, which would have sent the
  previous decision - they now send the decision directly.
