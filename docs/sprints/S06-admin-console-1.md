# Sprint 6 - National admin console, part 1

- **Status:** Complete
- **Version:** v0.6.0

## Objectives

A secure console for the Ku Tirhisana team to run the platform day to day - people, roles, hubs, organisations,
verification, enquiries and the audit trail - replacing the command-line tools.

## Delivered

- **Fine-grained permissions** (ADR-014) declared per role in module manifests; `permission:` middleware and
  gate; menus show only permitted items. Matrix: super admin (all), operations admin (all except national roles
  and audit export), support agent (view + verify documents + enquiries), content reviewer (view).
- **Overview:** people, new this week, live hubs, documents and organisations waiting, open enquiries, failed
  messages; 12-week registrations chart (dependency-free SVG); hubs by status. Cached 5 minutes.
- **People:** search by name or last phone digits, filters (hub, province, role, status), server-side paging,
  masked phone numbers in lists. Person page: profile, roles (give/remove with scope and reason), documents
  (inline preview, audited), consent history, recent activity, sign out everywhere, suspend/reactivate with
  reason (signs out every device, blocks sign-in, SMS to the person). Every person page view is audited.
- **Hubs:** list, create, edit (address, place, coordinates, hours, contact, status, operator), package and
  add-on switches (access follows immediately), hub staff list.
- **Organisations:** list with filters (waiting first), create, edit, verify / reject with reason (members told),
  members added by phone number.
- **Document verification queue:** oldest first with waiting time, filters by type and hub, side-by-side
  preview through signed inline links, verify or reject with a plain-language reason (the person is told why).
- **Enquiries inbox:** new / handled / spam, filters by type.
- **Audit log:** filters (event, person, date), CSV export (rate-limited, the export itself audited).
- **Events:** `admin.organisation.verified|rejected`, `admin.account.suspended|reactivated`.
- **Demo:** operations admin (072 000 0042) and support agent (072 000 0043) accounts; 20 documents waiting,
  3 organisations waiting, 10 enquiries.

## Issues found and fixed during the sprint

- A role could be given with an empty or non-existent hub/organisation - roles now require a scope that exists.
- Website enquiries moved into the core (shared by the website and the console) so portals never depend on each other.
- Linting picked up browser-test trace files - test output folders are now ignored.
- **Hub sign-in limits.** The browser tests showed that the "per device" code limit treated every computer with
  the same browser and connection as one device - in a hub with identical PCs on one connection, people would
  have been blocked after a few sign-ins. Each browser now gets an anonymous id cookie, the device limit is 20
  codes per hour, and hub internet connections can be listed as trusted (`KASI_OTP_TRUSTED_IPS`) for a much
  higher per-IP limit. Registering a hub's IP address becomes part of hub setup in Sprint 7.

## Not in this sprint

Platform settings, content editor, message cost dashboard and page analytics are part 2 (S21).
Replying to enquiries happens in normal email for now.
