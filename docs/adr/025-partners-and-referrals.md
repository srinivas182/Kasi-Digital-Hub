# ADR-025: Partners, offers and consented referrals

- **Status:** Accepted
- **Date:** 2026-10-10
- **Sprint:** S17

## Decisions

1. **Ownership:** the partner portal module owns partners, offers and referrals (both its partner pages and the
   entrepreneur's "Support" pages). KasiStart exposes business facts through the core `BusinessFacts` extension point
   and shows the number of fitting offers through `SupportOffers`; neither module depends on the other directly.
2. **Partners** register with the shared organisation registration (type `partner`), are verified, and must accept the
   current **data-sharing agreement** version (`kasi.partner.agreement_version`; draft wording until Ku Tirhisana's
   legal adviser provides it) before referrals can be sent to them.
3. **Offers** have structured eligibility criteria. KasiHub reviews a partner's first offer and anything flagged;
   offers charging a fee to apply are refused.
4. **Eligibility** is met / not met per criterion, with a reason and a link to the KasiStart step that fixes it - no
   ranking. Only fully eligible businesses can send a referral.
5. **Consent per referral:** the entrepreneur ticks exactly what is shared (business details always; plan highlights,
   readiness and specific *verified* documents of the owners optionally). Withdrawal ends partner access immediately.
6. **Document access** is limited to the shared documents until the referral closes plus 90 days; every view is logged
   and shown to the entrepreneur.
7. **No ghosting:** reminders at 7 and 14 days, "no response" at 30 days (entrepreneur told kindly, KasiHub operations
   alerted). Partners decline with a kind message; the reason stays private.
8. **Outcomes** are recorded by the partner and confirmed by the entrepreneur; 3- and 6-month "still trading?"
   follow-ups. Events feed aggregated numbers for hubs and funders.
9. **Rate limits per route** (`ThrottlePerRoute`, S16) apply to all referral actions.
