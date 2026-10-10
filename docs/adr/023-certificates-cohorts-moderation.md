# ADR-023: Certificates, cohorts and moderation

- **Status:** Accepted
- **Date:** 2026-10-10
- **Sprint:** S15

## Decisions

1. **Certificates** are issued automatically on completion as verifiable PDFs (S8 documents: code, QR, share, revoke).
   Short courses: "Certificate of completion". Accredited courses: "Statement of results", which states that the
   official certificate is issued by QCTO/SETA; these need a verified ID first (the daily job issues them once it is).
   Revocation (provider admin or KasiHub) shows "Revoked" on the verification page and tells the learner why.
2. **Achievements extension point** (`Modules\Core\Achievements\AchievementSource`): KasiLearn certificates appear on
   the KasiWork CV ("Certificates", verified) unless the learner hides them.
3. **Cohorts:** created by provider admins; cohorts at a hub need the hub manager's approval. Join by code/QR or added by
   a facilitator; waiting list with automatic promotion. Sessions are hub sessions through the core `HubSessions`
   contract (implemented by hub events), so sign-ups, reminders and QR/staff attendance are reused; the hub portal
   raises `HubSessionAttended`. Blended/hub courses require attendance (default 80% of sessions held, per course);
   while sessions are still to come, the course is not complete.
4. **Moderation sample:** an assessor's first 5 assessments for the provider; every competent decision on accredited
   courses; every 10th assessment per assessor, course and month (at least 10%, at least 1). Moderators never moderate
   their own work. Disagreement returns the work to the assessor; an overturned competent decision withdraws the
   certificate until the work is competent again. CSV report for external moderators.
5. **First load:** tailwind-merge replaced by a small, tested `cn()`; CI fails above 125 KB (budget 130 KB).
