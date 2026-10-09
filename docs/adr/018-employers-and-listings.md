# ADR-018: Employers, verification and fair job listings

- **Status:** Accepted
- **Date:** 2026-10-09
- **Sprint:** S10

## Decisions

1. **Employers are organisations** (core): profile fields (trading name, sector, size, description), a
   `community` flag and a verification checklist live on `organisations`, so other portals reuse them.
2. **Verification is manual in Release 1** with a checklist per kind (`kasi.verification.checklists`):
   CIPC found and active, person linked, phone answered; community employers: ID verified, proof of address,
   hub visit. Verifying is refused until every item is ticked. A driver for an automatic company-data check can
   be added later.
3. **Community employers** (no CIPC registration) may register, are labelled, and are limited to 3 active listings.
4. **Pay is required** and checked against the national minimum wage (`KASI_MINIMUM_WAGE_CENTS`, hours per
   period configurable); learnership and internship stipends are exempt. The rate must be updated yearly.
5. **Fairness and safety:** screening questions about age, religion, pregnancy, health, marital status, race, ID or
   bank details are refused. Gender/age/race/marital preferences (except lawful employment-equity wording), ID
   copies before interview and unrealistic pay send the listing to the content review queue, together with the S8
   scam rules and AI check. Held listings are not published until a reviewer approves; rejection takes them down.
   Reviewers' decisions reach portals through the `core.moderation.decided` event.
6. **Occupations (OFO):** a starter list of 105 entry-level occupations with OFO major groups but **no codes**
   (codes were not invented); `kasi:work:import-ofo` loads the official list and fills codes by title.
7. **Public job pages** at `/jobs/{id}` carry Google JobPosting structured data and never show employer contact
   details; applying needs an account (S12). Signed-in adults use the job board at `/work/jobs`.
