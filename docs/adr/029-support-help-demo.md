# ADR-029: Support, help centre, review inbox, system status and demo tools

- **Status:** Accepted
- **Date:** 2026-10-11
- **Sprint:** S21

## Decisions

1. **No impersonation.** Support staff see a summary of counts and statuses (never messages, documents or answers);
   every look-up needs a reason and is audited.
2. **Sensitive account help** needs the person's agreement: unlocking needs a one-time code sent to their phone and
   read back; changing the phone number needs a recorded ID check at a hub plus a code sent to the new number (the old
   number is told). Codes expire after 15 minutes and allow 5 attempts.
3. **Help requests** in the app with a 2-working-day first-response target (overdue flagged); "I feel unsafe" requests
   are escalated and sent to the safeguarding officers (S18). Staff can add internal notes and saved replies.
4. **Help centre:** articles in a simple, safe format (paragraphs, "## " headings, "- " bullets; everything escaped -
   no author HTML), reviewed by a second person before publishing, with "last checked" dates, search, "was this
   helpful?" and context links from portal pages (`kasi.help_context`, tested against real routes and articles).
   24 starter articles are marked for Ku Tirhisana's review.
5. **AI costs:** month-end projection, spend by hub, and alerts to super admins at 80% and 100% of the platform and
   feature budgets (once per level per month). The S8 budget check already pauses a feature at its cap; raising a
   budget is an audited change with a reason.
6. **Review inbox:** one list of everything held for review, gathered through the core `ReviewSource` extension point
   (held content, courses in review), with overdue flags and bulk approval of harmless held content. The per-portal
   review screens stay.
7. **System status:** queues, failed jobs (retry), scheduled-job heartbeats with overdue flags, drivers, storage.
8. **Demo tools** (stories, role switcher, reset) work only when demo mode is on AND the app is not production, and the
   switcher only signs in as demo accounts; a DEMO banner shows; every switch is audited. A demo health test opens
   every story step for its persona in CI.
