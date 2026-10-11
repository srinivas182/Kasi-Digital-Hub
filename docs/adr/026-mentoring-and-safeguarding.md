# ADR-026: KasiConnect mentoring and safeguarding

- **Status:** Accepted
- **Date:** 2026-10-10
- **Sprint:** S18

## Decisions

1. **Vetting:** every mentor needs a verified ID (S4 document queue), two reference checks, the code of conduct and the
   safeguarding course; a **mentor coordinator** (new national role) records each check and approves. Mentoring
   16-17-year-olds additionally needs a police clearance (`minors_ok`). Further legal checks for people working with
   children are to be confirmed by Ku Tirhisana's legal adviser.
2. **Under-18s (Release 1):** only mentors cleared for minors, at the young person's own hub; sessions at the hub only
   (hub sessions via the core `HubSessions` contract); **no messages, phone or video**; the verified guardian is told by
   SMS when the mentorship starts.
3. **Matching** is explainable (goal, shared interests, language, place, format) and the young person chooses; mentors
   accept or decline. At most 3 waiting requests per young person.
4. **Agreements:** 1-3 goals, default twice a month for 3 months, both agree before it starts; renewable once.
5. **Sessions:** phone, video (link added by the mentor) or at a hub; shared notes and actions (both see), private notes
   (mentor only, never sent to the young person); "Was this helpful?" after each session.
6. **Messages:** on-platform and moderated; phone numbers, email addresses and social handles are **refused** before
   2 sessions have been held, then sent with a code-of-conduct reminder.
7. **Safeguarding:** "Report a concern" on every mentoring screen; concerns go only to **safeguarding officers** (new
   national role) with a 24-hour target (overdue flagged). Urgent concerns pause the mentorship immediately and alert
   officers on every channel. Officers can pause a mentor platform-wide (their mentorships pause, mentees are told
   kindly). Everything is audited; events carry no details.
8. **Recognition:** a verifiable certificate of mentoring hours (one-to-one sessions plus group sessions at hubs).
9. **Lesson (S15 and again S18):** set database-default fields explicitly when creating models that are used straight
   away (`status`), because defaults are not on the in-memory model.
