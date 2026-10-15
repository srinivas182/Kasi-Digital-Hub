# Sprint 20 - Funder portal and regional console

- **Status:** Complete
- **Version:** v0.20.0

## Delivered

- **Indicator extension point** with 20 indicators across reach, hubs, KasiWork, KasiLearn, KasiStart, partners and
  KasiConnect, each with a written definition; health signals for coordinators (ADR-028).
- **Funder portal:** programmes with scope, period, age group and targets; progress, month-by-month trend, breakdowns;
  small numbers suppressed; verifiable impact report PDF and CSV; licence invoices; consented stories; target changes
  with approval; frozen monthly snapshots.
- **Regional console:** hubs in the coordinator's area with indicators, health counts, activity-drop alerts and messages
  to hub managers.
- **Demo:** "Limpopo Youth Pathways 2026 (demo)" for the sample funder; Limpopo provincial coordinator.

## Finding

- The platform does not collect sex (or race/disability), so the spec's "sex where provided" breakdown isn't possible
  in Release 1. The portals say so plainly; Ku Tirhisana to decide whether any of these are needed (POPIA consent).

## Issues found and fixed during the sprint

- **Permissions lost by overwriting module settings (real regression, two places).** Updating the regional module's
  roles replaced the coordinators' permissions instead of adding to them, removing their S7 access to hub dashboards
  and comparison (caught by the existing hub operations tests). Checking the earlier sprints found the same in S19,
  already merged: commercial and finance admins had lost `hubops.dashboard` and `hubops.compare` (no test covered
  them). Both restored. New guard: `RolePermissionsSnapshotTest` - every role's permissions must match
  `tests/fixtures/role-permissions.json`, so any change to a role has to be made on purpose.

- Wide tables that scroll sideways weren't reachable by keyboard (accessibility check) - fixed in the funder,
  regional and commercial consoles.
- The privacy note first said "from 1 to 10" - corrected to "below 10".
