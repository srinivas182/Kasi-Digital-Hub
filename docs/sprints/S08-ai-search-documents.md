# Sprint 8 - AI, search and document generation (shared core)

- **Status:** Complete
- **Version:** v0.8.0

## Delivered

- **AI layer:** provider drivers (Anthropic, OpenAI, fake), fast/strong tiers, versioned prompts with a lock file,
  automatic removal of ID numbers / phone numbers / emails / street addresses, injection-resistant system rules,
  structured outputs with one retry, full request log with cost in rand, 30-day text retention, budgets (person/day,
  hub/month, feature/month, platform/month - placeholder R2 000 a month), switches per feature and for all AI,
  graceful "write it yourself" fallbacks, moderation (rules + AI) and cached translation.
- **Admin console:** AI controls (usage, cost, failures per feature, daily chart, recent requests; texts only for
  super admins) and a content review queue (approve, reject, escalate - with reasons).
- **Search:** one search page and header button; hubs, events and help indexed; typo-tolerant; results limited to
  what each person may see; database driver for the pilot, Meilisearch for production; nightly reindex.
- **Documents:** PDF generation with verification QR codes, public `/verify` page, revocation, share links that
  expire or can be switched off, owner-only downloads, all audited.
- **First features:** "Help me write it" for hub event descriptions (staff), public event text checked for scams,
  certificates of attendance for people who attended an event.
- **Operations:** `search`, `ai` and `pdf` queues; Gotenberg in Docker Compose; a CI job against real Meilisearch
  and Gotenberg that runs only when search or PDF code changes.

## Decisions taken (per spec)

Anthropic as main AI provider with OpenAI as fallback; notice instead of a consent switch (pending the Information
Officer); embeddings decided in S11; Gotenberg for PDFs; placeholder platform AI cap until Ku Tirhisana sets one.

## Issues found and fixed during the sprint

- The new AI request log has a hub column, so the hub-scoping guard test required the hub-scoping trait on it.
- Help answers had no anchors to link to from search - added.
- **Phones:** the new search button made the signed-in header 13 px too wide on 360 px phones, so pages scrolled
  sideways and the bottom menu covered buttons. The theme switch moved into the account menu on phones, and a new
  browser test checks signed-in pages for sideways scrolling.
- The website form limit (5 per 10 minutes) did not use the browser-test throttle setting; it is now a named limit.
