# Sprint 10 - KasiWork for employers and job listings

- **Status:** Complete
- **Version:** v0.10.0

## Delivered

- **Employer registration** (CIPC number and certificate, or community employer without CIPC), employer admin
  role, team of recruiters added by phone, company profile, employer dashboard with views and saves.
- **Verification checklist** in the admin console (different for community employers); verifying needs every item.
- **Job listings:** title, occupation, type, positions, location, required pay, hours, education, experience,
  licence, languages, skills (must/nice), up to 5 screening questions, closing date (max 60 days); draft, live,
  being checked, closed, filled, expired, taken down; renew; daily expiry and "closes in 3 days" reminders.
- **Fairness and safety:** minimum wage check, forbidden screening questions, review rules for unfair preferences
  and scam signs (lawful employment-equity wording allowed), held for review; staff can take adverts down.
- **"Help me write it"** for employers: rough notes to title, occupation (from the list), description, skills and
  screening questions (unsafe questions dropped).
- **Job board** for young people: search, type, sector, distance from home hub, "no experience needed", saved
  jobs; job pages with directions; public shareable job pages with JobPosting data; jobs in platform search
  (adults only). Applying opens in S12 (people can save jobs now).
- **Occupations:** 105-title starter list (major groups, no codes) and an import command for the official OFO list.
- **Demo:** Mopani Fresh Market (verified) with 5 live adverts; Mama Rose Kitchen (community, waiting).

## Note on occupation codes

The spec said the starter list would carry OFO codes. I did not want to ship codes I could not check, so the
starter list has titles and OFO major groups only; codes arrive with the official list (see content inputs).

## Issues found and fixed during the sprint

- Demo pay for hourly jobs was entered in cents twice (R3 200 an hour) - fixed.
- The demo AI returned text where lists were expected, producing a 200-character "skill" that failed validation
  without a visible message. The demo AI now returns short list items, suggested skills are trimmed, and the
  advert form lists every problem at the top.
- Verifying an employer now needs the checklist; the earlier admin test was updated accordingly.
