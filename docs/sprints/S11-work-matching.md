# Sprint 11 - KasiWork matching

- **Status:** Complete
- **Version:** v0.11.0

## Delivered

- **Match score with reasons** (ADR-019): skills, experience (informal counts), education (verified bonus),
  distance, kind of work, sector, nice-to-have skills, languages; gaps for licence and education; travel limit.
- **Similar skills:** 26 curated synonym groups and capped AI embeddings with a cache and a fake driver.
- **"Jobs for you"** with reasons and gaps, invitations (accept / decline), "who viewed me", hide from employers
  (also from any job page); hub home "N jobs match you".
- **Suggested candidates** for verified employers' live adverts: consenting, visible, no gaps, anonymised;
  candidate profile (view recorded); invite to apply (20 per advert per day); contact details after acceptance.
- **Daily job alert** (at most one, 70%+, consent on) and nightly refresh (`kasi:work:matches`, 07:15 SAST).
- **Insights** for the KasiHub team and coordinators: totals, fairness check, strong matches by hub.
- Matches recalculate on profile/advert changes (`matching` queue); closing an advert expires its invitations.

## Issues found and fixed during the sprint

- Two front-end edits silently did not apply because the code had been reformatted (the employer dashboard's
  "Suggested candidates" link was missing) - caught by the browser test. Edits are now always checked.
