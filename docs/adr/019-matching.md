# ADR-019: Explainable job matching

- **Status:** Accepted
- **Date:** 2026-10-09
- **Sprint:** S11

## Decisions

1. **Rule-based, explainable score (0-100):** must-have skills 35, experience 15, education 15, distance 15,
   kind of work 6 and sector 4, nice-to-have skills 5, languages 5. Every score stores plain reasons and "gaps"
   (translation keys), shown to young people and employers.
2. **Fair by construction:** the matcher receives `SeekerFacts` only - skills, experience, education level and
   verification, licence, languages, preferences and a location (home hub or area). Name, age, date of birth,
   gender, race, photo, phone and ID never reach it. Tests check the facts class and that identical profiles with
   different names and ages score the same.
3. **Informal work counts:** any experience satisfies "some experience"; durations like "about 2 years" count.
4. **Similar skills:** a curated synonym list (canonical skills) gives full credit; AI embeddings of skill phrases
   give at most half credit per skill. Embeddings are cached per phrase (`ai_embeddings`, no personal data) behind
   a driver (fake; OpenAI's small embedding model by default). Provider outages mean no embedding credit, never errors.
5. **Hard limits:** beyond the person's travel distance = not a match. Missing required licence or minimum
   education = a gap (shown to the person; such people are not suggested to employers).
6. **Privacy:** employers see only people with the "job matching" consent on, not hidden from them, anonymised
   (first name, surname initial, town) until an invitation is accepted. Every view is recorded and shown to the
   person. Invitations: 20 per advert per day, decline without reason, expire when the advert closes.
7. **Freshness:** `work_matches` is recalculated on the `matching` queue when a profile or advert changes, and
   nightly; matches below 30% are not kept. One job alert a day at most (70%+, consent on, quiet hours apply).
8. **Fairness monitoring:** an insights page compares strong-match and invitation rates for formal / informal-only /
   no experience and matric-or-more / below matric, plus strong matches by hub. No protected attributes are used.
