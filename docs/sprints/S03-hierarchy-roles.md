# Sprint 3 - Hierarchy, organisations & roles

- **Status:** Complete
- **Version:** v0.3.0

## Objectives

Build the national structure, organisations, all Release 1 roles with scoped access, and hub packages, so every
portal knows who someone is, where they belong and what they may see and do.

## Delivered

- **Geography** (reference data, all environments): 9 provinces, the 8 metros, and the pilot municipalities in
  Limpopo (Mopani, Vhembe, Capricorn) and KwaZulu-Natal (uMgungundlovu / Msunduzi), with district links and
  approximate centre points. Source recorded on every row. `kasi:geography:import` loads the full official
  Municipal Demarcation Board list from CSV and can be re-run safely.
- **Structure**: provinces -> cities (metros and local municipalities) -> hubs; places (villages, townships)
  added as hubs open; hubs with status, package, operator organisation, coordinates and opening hours.
- **Organisations**: employer, training provider, partner, funder, hub operator, platform - with registration
  number, verification status and members.
- **Roles** (ADR-011): 24 roles declared in module manifests with scope, staff flag and access level per portal;
  role assignments with scope and expiry; `AccessResolver` with caching; `portal` middleware and gate;
  `BelongsToHub::visibleTo()` for hub data; staff roles switch on the authenticator step automatically; minors
  limited to learning roles; every change audited.
- **Hub packages**: Base (Hub Ops, KasiWork), Growth (+KasiLearn), Enterprise (+KasiStart), Full (+KasiConnect),
  plus add-ons and switch-offs per hub, audited. National services stay open to everyone.
- **Menus** show only the portals a person can open; guests see none.
- **Profile**: optional nearest hub at sign-up and in the account page, province / city / village location,
  "My roles" list. Hub home shows the person's hub.
- **Commands**: `kasi:roles assign|revoke|list|available`.
- **Demo**: 12 hubs in 6 cities, 10 organisations, 16 demo staff and citizen accounts with roles (staff
  authenticators ready), 500 demo citizens across hubs. See docs/demo.md.

## Database changes

New: `provinces`, `municipalities`, `places`, `organisations`, `organisation_members`, `hubs`, `hub_modules`,
`role_assignments`. `users` gained `home_hub_id`, `province_id`, `municipality_id`, `place_name`,
`access_version`.

## Tests

PHP 218 - including the agreed access matrix for every role, scope isolation (hub, city, province, national),
package switching, route guards, cache invalidation, minors, expiry, commands, geography import, and a guard that
every hub-owned model uses the scoping trait. Browser 42 - including menus for a job seeker, a facilitator and
the super admin, and the account roles list.

## Notes and deviations

- **Geography source:** the official MDB/Stats SA files could not be downloaded into the build environment, so
  the pilot list is curated (marked "verify against MDB") and the full official list is loaded with the CSV
  importer before the national roll-out.
- **Roles:** 24 assignable roles in Release 1 (the Portals & Roles document's 25 includes "Visitor", which is a
  guest, not a role). Trader and Buyer arrive with KasiMarket.
- **Mentors** are individual volunteers, so they do not need the authenticator step.
- **Demo staff** have the authenticator already set up so demos go straight to the (on-screen) code.
- Role, hub and organisation management screens follow in the admin console (S6).
