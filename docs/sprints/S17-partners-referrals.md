# Sprint 17 - KasiStart partners and referrals

- **Status:** Complete
- **Version:** v0.17.0

## Delivered

- **Partners:** registration, verification, data-sharing agreement acceptance, team.
- **Offers** with structured eligibility, KasiHub review of first/flagged offers, fees to apply refused.
- **Support for you:** explainable eligibility with links to the fixing step; "N support offers fit your business" on
  the hub home.
- **Referrals:** per-referral consent, verified documents only, withdrawal, assisted ("warm") referrals with a
  facilitator note, partner pipeline with messages, kind declines, two-sided outcomes, follow-ups, no-ghosting
  reminders and "no response" with operations alerts, audited time-limited document access (ADR-025).
- **Demo:** Ubuntu Community Bank (demo) with three offers; Nomsa qualifies for some of them.

## Issues found and fixed during the sprint

- The partner pages were first rendered under the wrong page names; PHP tests passed because they don't load page
  files - only the browser test caught it. New architecture test: every `Inertia::render` in the modules must have
  a page file.
- The demo partner first used a new organisation and a demo phone number already used by a funder demo account; it now
  uses the existing demo bank and its admin.

## Waiting on Ku Tirhisana

- The final **data-sharing agreement** wording for partners (bump `kasi.partner.agreement_version` when it changes -
  partners then accept again).
