# Demo environment

Demo mode (`KASI_DEMO_MODE=true`) is for local development, client demos and UAT only - **never production**.
All people, numbers and figures are fictitious.

## What demo mode changes

- A banner on every screen says the data is sample data.
- One-time SMS codes are shown on screen ("Demo code: 482913") - no phone needed.
- Staff authenticator codes are shown on screen during the second step.
- `php artisan kasi:demo:reset --force` rebuilds the demo database from scratch.

## Demo accounts

All demo accounts use PIN **24680**. Roles are attached in Sprint 3.

| Phone | Person | Story |
|---|---|---|
| 072 000 0001 | Thandi Mabasa | Job seeker, learner, later trader (Tsutsumani) |
| 072 000 0002 | Lwazi Chauke | Learner on a sponsored retail learnership |
| 072 000 0003 | Nomsa Mthembu | Entrepreneur - catering and baking |
| 072 000 0004 | Kurhula Mabunda | 17-year-old learner (guardian consent given) |
| 072 000 0010 | Sipho Nkuna | Employer - Mopani Fresh Market (staff sign-in) |
| 072 000 0011 | Palesa Molefe | Business mentor (staff sign-in) |
| 072 000 0012 | Herman Moolman | Training provider - HBM EduTech (staff sign-in) |
| 072 000 0020 | Rhulani Baloyi | Hub facilitator - Tsutsumani (staff sign-in) |
| 072 000 0021 | Tsakani Mathebula | Hub manager - Tsutsumani (staff sign-in) |
| 072 000 0030 | Naledi Khumalo | Funder programme manager (staff sign-in) |
| 072 000 0040 | Lucky Siwela | National super admin (staff sign-in) |

"Staff sign-in" accounts set up an authenticator on first sign-in; in demo mode the code is shown on screen.

## Demo script - sign-up and sign-in (5 minutes)

1. Open `/login`, enter any new mobile number (e.g. 083 555 0101), use the demo code.
2. Choose a PIN, date of birth (try 17 years ago to see guardian consent), name and consent choices.
3. Sign out, sign in again as 072 000 0001 - code, then PIN.
4. Show **My account**: devices, privacy choices, PIN and phone change.
5. Sign in as 072 000 0040 to show the staff authenticator step.
