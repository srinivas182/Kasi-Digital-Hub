# ADR-024: KasiStart - formalisation journey and business plan

- **Status:** Accepted
- **Date:** 2026-10-10
- **Sprint:** S16

## Decisions

1. **Guidance and assisted help, not automated registration.** CIPC offers no public interface for registering on
   someone's behalf; people register on BizPortal / SARS eFiling / at their municipality (alone or with a hub
   facilitator), and KasiStart keeps their progress and proof.
2. **Editable, reviewed content.** Formalisation steps (`start_steps`) are content the KasiHub team edits without a
   code change (permission `start.content`), each with a "last checked" date. Fees are never hard-coded - every step
   says where to check the current fee. The first draft is to be checked by Ku Tirhisana's legal adviser before launch.
3. **Personalised steps** by legal form (sole / pty / coop / npc), sector (e.g. food) and whether the business employs
   people. "Done" can carry proof stored in the document vault (new types: tax registration, B-BBEE affidavit,
   trading permit, UIF/COIDA registration, food certificate) and verified in the S6 queue.
4. **"Guidance, not legal advice"** on the legal-form guide and the journey.
5. **B-BBEE affidavit:** a pre-filled template to print and sign before a commissioner of oaths; not stored or verified.
6. **Business plan** with AI help that flags numbers the person never wrote, a price and break-even calculator (same
   rules on the phone and the server), and a one-page summary PDF verifiable by QR code.
7. **Readiness score** (profile 20, plan 20, steps 40, verified proof 20) with reasons and the next step; used for
   partner referrals in S17.
8. **Adults only** (18+); co-owners invited by phone, managed by the owner; hub facilitators can help (assisted, audited).
9. **Privacy:** turnover is a band, visible only to the owners.
