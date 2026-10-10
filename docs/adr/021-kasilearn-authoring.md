# ADR-021: KasiLearn courses, safe content, low-data media and review

- **Status:** Accepted
- **Date:** 2026-10-10
- **Sprint:** S13

## Decisions

1. **Shared organisation registration** (`Modules\Core\Structure\OrganisationRegistration`) for employers and
   training providers (type `training_provider`): certificate in the vault, admin role, team members.
2. **Editor:** TipTap 3 (MIT, ProseMirror). Lessons are stored as the editor's structured document and rendered
   to HTML on the server by `ContentRenderer`, which walks an allow-list of nodes and marks. Scripts, iframes,
   event handlers, `javascript:`/protocol-relative links and images outside `/learn/media/{id}` are never output.
   TipTap is installed from releases older than two weeks (`npm install --before`), after the 3.30.x advisory fix.
3. **Accessibility checks** block submission (image descriptions, heading order, "click here" links, empty
   lessons, transcripts for video/audio, uploaded media); long sentences are advice only.
4. **Low-data media:** pictures are re-encoded to WebP (max 1280 px, metadata removed). Video and audio are
   converted on the `media` queue: 240p and 360p H.264/AAC mono, audio-only AAC 48 kbps, thumbnail; maximum
   15 minutes and 500 MB. Driver: fake (CI/demo) or ffmpeg (the PHP image now includes ffmpeg and WebP).
   The real converter is contract-tested in the CI integration job.
5. **Data size** per lesson and course: rendered HTML, pictures and the standard video/audio version.
6. **Review:** author submits -> provider admin approves or sends back -> KasiHub review only for a provider's
   first course, accreditation claims and courses for 16-17-year-olds -> published as a frozen, numbered version
   (`learn_course_versions`). Editing a published course marks it "changed"; learners keep their version.
   KasiHub (`learn.review`) can unpublish with a reason. Accreditation shows only once verified.
7. **AI writing help** (lesson drafts, outcomes, plain language) through the AI layer; drafted lessons are marked
   "AI-drafted" until edited, visible to reviewers. Translation stays off until native-speaker review.
8. **Catalogue:** published courses only; 16-17-year-olds see 16+ courses; public course pages with schema.org
   Course data; saved courses; "Courses for you" from the core `SkillGapSource` extension point (KasiWork
   supplies missing must-have skills of the person's best job matches).
9. **Bundle budget:** author-only pages (course editor) have a separate 200 KB budget; learner and public pages
   keep 60 KB per page and 130 KB first load.
