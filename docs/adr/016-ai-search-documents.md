# ADR-016: Shared AI layer, search and document generation

- **Status:** Accepted
- **Date:** 2026-10-09
- **Sprint:** S8

## Context

CV writing, listing enhancement, matching, tutoring and marketing all need AI; jobs, courses and funding need
search; CVs, certificates and reports need PDFs that others can trust. Each must be built once, with privacy,
cost control and audit, and must not lock the platform to one provider.

## Decisions

**AI layer (`Modules\Core\Ai`)**
- One gateway, `AiService::run($promptKey, $vars, $for, $by, $hubId)`, behind drivers: `anthropic` (main),
  `openai` (switchable fallback), `fake` (demos/CI). Features ask for a tier (fast/strong), not a model.
  Anthropic was recommended by Claude (made by Anthropic) - a disclosed interest; switching is configuration.
- Prompts are versioned Markdown files in each module (`resources/prompts`). `kasi:ai:lock` records a fingerprint
  per version in `docs/ai-prompts.lock.json`; tests fail if wording changes without a new version.
- Personal identifiers (ID numbers, phone numbers, emails, street addresses) are removed before sending. A fixed
  system rule tells the model that user text is data, not instructions. Outputs must match a declared JSON shape
  (one retry); AI output is never executed.
- Every call is logged in `ai_requests` (feature, prompt version, model, tokens, cost in cents, duration, outcome,
  person, staff member, hub). Inputs/outputs are kept 30 days for quality review (`kasi:ai:purge`).
- Admin switches per feature and for all AI; budgets per person per day, per hub per month, per feature per month
  and platform-wide per month. On any refusal or failure the feature says "write it yourself".
- People see a notice each time AI is used (no separate consent switch) - to be confirmed by the Information Officer.
- Moderation: rules first (common South African job scams, banking details, spam), AI for longer text the rules
  let through; doubtful content goes to a review queue - never deleted automatically.

**Search (`Modules\Core\Search`)**
- Portals implement `SearchSource` (extension point). Drivers: `database` (pilot/CI, typo-forgiving) and
  `meilisearch` (production). Visibility per item: public / members / learners. Updates run on the `search` queue;
  nightly full reindex. People are never indexed.

**Documents (`Modules\Core\Documents\Generation`)**
- `DocumentIssuer` renders Blade templates to PDF (`gotenberg` driver in production, `fake` in CI), stores them
  privately, adds a QR code to `/verify/{code}`; documents can be revoked; share links expire and can be switched off.

## Consequences

- Later portals add a prompt file, a search source or a template - not new infrastructure.
- Real Meilisearch and Gotenberg are checked by a CI job that runs only when that code changes.
