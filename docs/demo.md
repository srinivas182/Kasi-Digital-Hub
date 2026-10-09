# Demo environment

Demo mode (`KASI_DEMO_MODE=true`) is for local development, client demos and UAT only - **never production**.
All people, numbers and figures are fictitious.

## What demo mode changes

- A banner on every screen says the data is sample data.
- One-time SMS codes are shown on screen ("Demo code: 482913") - no phone needed.
- Staff authenticator codes are shown on screen during the second step.
- `php artisan kasi:demo:reset --force` rebuilds the demo database from scratch.

## Demo accounts

All demo accounts use PIN **24680**.

| Phone | Person | Role(s) and where | Home hub |
|---|---|---|---|
| 072 000 0001 | Thandi Mabasa | Job seeker + learner (own account) | Tsutsumani |
| 072 000 0002 | Lwazi Chauke | Learner + job seeker | Giyani Central |
| 072 000 0003 | Nomsa Mthembu | Entrepreneur | Soweto |
| 072 000 0004 | Kurhula Mabunda | Learner, 17 (guardian consent given) | Malamulele |
| 072 000 0010 | Sipho Nkuna | Employer admin - Mopani Fresh Market (staff) | - |
| 072 000 0011 | Palesa Molefe | Mentor | Tsutsumani |
| 072 000 0012 | Herman Moolman | Training provider admin - HBM EduTech (staff) | - |
| 072 000 0020 | Rhulani Baloyi | Hub facilitator - Tsutsumani (staff) | Tsutsumani |
| 072 000 0021 | Tsakani Mathebula | Hub manager - Tsutsumani (staff) | Tsutsumani |
| 072 000 0022 | Vusi Ngobeni | City coordinator - Greater Giyani (staff) | - |
| 072 000 0023 | Lerato Mokoena | Provincial coordinator - Limpopo (staff) | - |
| 072 000 0024 | Bongani Nkosi | Hub owner - Tsutsumani (staff) | Tsutsumani |
| 072 000 0030 | Naledi Khumalo | Funder manager - Limpopo Youth Skills Programme (staff) | - |
| 072 000 0040 | Lucky Siwela | Super admin - national (staff) | - |
| 072 000 0041 | Zanele Dlamini | Finance admin - national (staff) | - |
| 072 000 0042 | Ayanda Zwane | Operations admin - national (staff) | - |
| 072 000 0043 | Bheki Mthethwa | Support agent - national (staff) | - |
| 072 000 0050 | Karabo Molefe | Partner admin - Ubuntu Community Bank (demo) (staff) | - |

Plus **500 demo citizens** (+27 84 000 0001 to 0500, PIN 24680) spread across all hubs.

## Demo structure

| Province | City | Hubs (package) |
|---|---|---|
| Limpopo | Greater Giyani | Tsutsumani (Full), Giyani Central (Enterprise) |
| Limpopo | Collins Chabane | Malamulele (Growth) |
| Limpopo | Greater Tzaneen | Nkowankowa (Base) |
| Gauteng | City of Johannesburg | Soweto (Full), Alexandra (Growth), Diepsloot (Base) |
| Gauteng | City of Ekurhuleni | Tembisa (Enterprise), Katlehong (Growth) |
| KwaZulu-Natal | eThekwini | Umlazi (Full), KwaMashu (Enterprise), Inanda (Base, planned) |

Organisations (all fictitious or marked sample): Ku Tirhisana (platform), Tsutsumani Youth Development
Co-operative (hub operator), Mopani Fresh Market and Baloyi Wholesalers (employers), HBM EduTech (provider),
Ubuntu Community Bank and Kasi Mutual Insurance (demo partners), three sample funders.

"Staff sign-in" accounts already have the authenticator step set up; in demo mode the current code is shown on screen.

## Demo documents and updates

- Thandi (072 000 0001): ID and matric verified (matric shared with Mopani Fresh Market), a qualification awaiting
  review, and an updates feed with a hub event.
- Lwazi (072 000 0002): a proof of address that was not accepted (blurry photo) - shows the "why" in his feed.
- Nomsa (072 000 0003): CIPC certificate shared with the demo bank, an expired tax clearance and a bank letter
  expiring soon.

## Demo script - employers and job adverts (4 minutes)

1. Sign in as Sipho (072 000 0010, Mopani Fresh Market): My business - verified, 5 live adverts with views.
2. New job advert -> "Help me write it" with rough notes; try pay of R20 an hour (refused: below minimum wage)
   and a question "How old are you?" (refused).
3. Type "Ladies only" in the description and publish: held for review. As Lucky, approve or reject it in Content review.
4. As Thandi (072 000 0001): Find jobs - nearest first, filter "No experience needed", save a job.
5. Open a job's public page while signed out (/jobs/...) - no employer contact details, sign-in to apply.
6. As Lucky: Organisations - "Mama Rose Kitchen (demo)" shows the community employer checklist.

## Demo script - job profile and CV (4 minutes)

1. Sign in as Lwazi (072 000 0002): hub home shows "Complete your job profile".
2. Work and experience: his piece-work description -> "Help me write CV points" -> "Use this".
3. Skills: tap a few ideas. My CV: preview (note what is never on a CV), choose "Simple", Create my CV.
4. Download the PDF, "Get a share link", "Send on WhatsApp". Check the CV code at /verify.
5. Sign in as Thandi (072 000 0001): a finished profile with a CV whose matric shows "Verified".

## Demo script - AI, search and documents (4 minutes)

1. As Thandi (072 000 0001): tap the search button, type "Gyani" (spelling mistake on purpose) - hubs and events.
2. As Rhulani (072 000 0020): Hub Ops -> Events -> New event, "Help me write it" with rough notes (demo AI text).
3. As a person who attended an event: open the event, "Download my certificate of attendance"; check its code at `/verify`.
4. As Lucky (072 000 0040): Admin -> AI controls (usage, cost, switches) and Content review (create a public event
   mentioning a "registration fee" to see it flagged).

## Demo script - hub operations (5 minutes)

1. Open the door screen on a large display: `/kiosk/tsutsumani/demo-door`.
2. On a phone, sign in as Lwazi (072 000 0002), scan the code, choose "Learning" - checked in.
3. Sign in as Rhulani (072 000 0020, facilitator): Hub dashboard (six weeks of visits), Check-in desk (find Lwazi,
   "Help this person"), Register someone new (code shown on screen in demo mode).
4. Events: open "Retail and hospitality job day" - sign-ups, waiting list, the attendance QR code.
5. Sign in as Thandi (072 000 0001): "Your upcoming events" on the hub home; book "Write a CV that gets interviews".
6. Sign in as Tsakani (072 000 0021, hub manager): Hub settings - add the hub's internet address, appoint a facilitator.

## Demo script - admin console (5 minutes)

1. Sign in as Lucky (072 000 0040) and open "All services" -> National admin console.
2. Overview: numbers and the 12-week registrations chart.
3. Verification: preview a waiting document, verify one and reject one ("blurry") - the person is told why.
4. People: search "Thandi", open her page, give a role with a reason; show the audit trail.
5. Hubs: open Nkowankowa, switch on KasiLearn as an add-on.
6. Organisations: verify "Giyani Spar Express (demo)". Enquiries: mark one handled. Audit log: filter by "role.".
7. Sign in as Bheki (072 000 0043, support agent) to show the smaller menu.

## Demo script - public website (3 minutes)

1. Open `/` - impact numbers, services, hubs. Share the link in WhatsApp to show the preview.
2. "Find a hub" -> "Show hubs near me" (allow location) -> open Tsutsumani -> "Get directions".
3. Show For employers / For funders and send an enquiry.

## Demo script - sign-up and sign-in (5 minutes)

1. Open `/login`, enter any new mobile number (e.g. 083 555 0101), use the demo code.
2. Choose a PIN, date of birth (try 17 years ago to see guardian consent), name and consent choices.
3. Sign out, sign in again as 072 000 0001 - code, then PIN.
4. Show **My account**: devices, privacy choices, PIN and phone change.
5. Sign in as 072 000 0040 to show the staff authenticator step and the full list of portals.
6. Compare "All services" for Thandi (job seeker), Rhulani (facilitator) and Lucky (super admin).
