# Sprint 9 - KasiWork for job seekers: profile, AI CV, voice notes

- **Status:** Complete
- **Version:** v0.9.0

## Delivered

- **Job profile** in six short steps (about me, work and experience, education, skills and languages, what I'm
  looking for, who can see it), each saving on its own, with a completeness bar; informal work counts; education
  can be linked to vault documents; saving creates the job seeker role. Adults only.
- **AI writing help** for CV points and the profile summary (strong tier), shown as suggestions next to the
  person's own words, with a check for facts they never wrote; falls back to their own words.
- **CVs:** two templates (Classic; Simple, low ink), PDF with verification QR code and "Verified" marks for
  verified education; versions, download, share links with view counts, switch-off, WhatsApp sharing, delete
  (withdraws verification). Never shows ID number, photo, date of birth or street address; age only by choice.
- **Voice notes:** record in the browser, transcribed through a driver; audio deleted immediately; languages
  switched on after testing (English first); 2-minute and daily limits; costs logged with other AI usage.
- **Assisted:** facilitators build the profile and CV for the person they are helping (link on the help screen).
- **Hub home:** "Complete your job profile" and "Make your CV" next steps; KasiWork tile now available.
- **Impact:** `work.profile.completed` and `work.cv.created` events. **Demo:** Thandi has a full profile and CV;
  Lwazi has started his.

## Issues found and fixed during the sprint

- A CV template line ending "transport@endif" was not recognised by Blade (a directive needs a non-letter before
  "@") - the template failed to compile. Fixed; CV creation is now one transaction so a failed PDF leaves no
  half-made CV version.
- The fact check matched digits inside phone numbers ("3" in 072...) - numbers are now matched as whole numbers.

## Waiting on Ku Tirhisana

Voice-note test recordings (see `docs/content-inputs.md`) before switching on languages other than English.
