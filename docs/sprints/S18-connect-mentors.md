# Sprint 18 - KasiConnect mentors

- **Status:** Complete
- **Version:** v0.18.0

## Delivered

- **Mentors:** application, vetting checklist (coordinator), approval, pause/remove, police clearance for 16-17s,
  group sessions at hubs, verifiable hours certificate.
- **Young people:** goal-based, explainable mentor suggestions; requests; agreements; sessions with notes, actions and
  "helpful" check-ins; messages with early contact-detail blocking; ending with goals met; renewal once.
- **Safeguarding:** report a concern everywhere, officer queue with 24-hour flag, urgent pause, platform-wide mentor
  pause, under-18 rules (hub only, no messages, guardian SMS) (ADR-026).
- **Demo:** Palesa (business mentor, cleared for 16-17s) mentoring Nomsa; Lucky is safeguarding officer, Ayanda mentor
  coordinator.

## Issues found and fixed during the sprint

- A new mentorship's database default status wasn't on the in-memory model (same as the S15 submission bug).
- "Get a mentor" first showed on everyone's hub home (caught by the hub home tests); it now shows for people running
  a business, as the spec intended. The "after several unsuccessful job applications" trigger needs KasiWork data and
  is left for a later core extension point.
- The new national roles were added to the agreed access matrix (a guard test that stops roles being added silently).
- The safeguarding queue sorted by an ambiguous `status` column (the joined users table has one too) - qualified.

## Waiting on Ku Tirhisana

- Mentor **code of conduct** wording; the **legal checks** for mentors of 16-17-year-olds; who the **safeguarding
  officers** and **mentor coordinators** will be.
