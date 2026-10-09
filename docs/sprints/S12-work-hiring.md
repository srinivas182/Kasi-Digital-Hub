# Sprint 12 - KasiWork hiring: applications and pipeline

- **Status:** Complete
- **Version:** v0.12.0

## Delivered

- **Apply** from job pages (CV choice or made on the spot, screening answers, message, sharing confirmation);
  "My applications" with status, timeline, interviews, messages, withdraw; assisted applying.
- **Employer pipeline:** board on wide screens, one stage at a time on phones (opening on the first stage with
  applicants), bulk moves, kind "not successful" messages with private reasons, team notes, CV (audited),
  blind shortlisting, interviews (propose; applicant confirms, asks for another time or declines).
- **Messages** per application, checked for scams, reportable.
- **Outcomes:** no ghosting, two-sided hire confirmation, 30/90-day retention check-ins, platform events.
- **Daily housekeeping** and 12-month anonymisation (ADR-020). Demo: Thandi's shortlisted application.

## Issues found and fixed during the sprint

- Interview reminders used an 18-30 hour window, but the job runs once a day - interviews later in the day
  would never have been reminded. Now: every interview in the next 36 hours, once.
- On phones the pipeline opened on "New" even when all applicants were further along, so it looked empty.
  It now opens on the first stage that has applicants.
- The reminder test depended on the time of day it ran; it now uses a fixed time.
