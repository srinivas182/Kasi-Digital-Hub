# ADR-020: Applications and the hiring pipeline

- **Status:** Accepted
- **Date:** 2026-10-09
- **Sprint:** S12

## Decisions

1. **Applying shares** the chosen CV (or one made on the spot from the profile), profile, screening answers,
   name and phone with that employer only, after an explicit sharing confirmation. One application per job;
   at most 20 a day; withdraw any time while open. An open invitation becomes "accepted" when the person applies.
2. **Pipeline stages:** new, shortlisted, interview, offer, hired, not successful (+ withdrawn by the person).
   Every move is a timeline event and audited. Private rejection reasons are never shown to the applicant.
3. **Blind shortlisting** per advert (off by default): names, phone and CV hidden until the Interview stage.
4. **Messages stay in KasiHub:** text only, checked by the scam rules (held for review when flagged, delivered
   when a reviewer approves via `core.moderation.decided`); either side can report.
5. **No ghosting:** 7 days after an advert closes, everyone still open gets a kind outcome automatically.
6. **Confirmed hires need both sides** (employer marks Hired, the young person confirms starting). Retention
   check-ins at 30 and 90 days. Both feed funder data via platform events.
7. **Retention:** 12 months after the advert closes, applications lose the person link, answers, messages and
   notes; anonymous counts remain.
8. **Daily housekeeping** (`kasi:work:hiring`, 09:00 SAST): outcomes, interview reminders (once, for interviews in
   the next 36 hours), retention check-ins, "applying is now open" for saved jobs, Monday reminders to employers
   with applicants waiting more than 3 days, anonymisation.
