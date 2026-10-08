# ADR-014: Fine-grained permissions inside portals, declared in manifests

- **Status:** Accepted
- **Date:** 2026-10-08
- **Sprint:** S6

## Context

Portal access levels (view / use / assist / manage - ADR-011) decide who can open a portal. Inside the admin
console, roles with the same access need different powers: a support agent may verify documents but not
suspend accounts; only a super admin may give national roles.

## Decision

- Roles in `module.json` may list `permissions` (e.g. `admin.documents.verify`); `admin.*` grants every admin
  permission. Each module also lists the permissions it defines.
- `AccessResolver::hasPermission()` (cached with the rest of a person's access), the `permission:` route
  middleware and the `permission` gate enforce them. Menu items can require a permission and are hidden otherwise.
- Sensitive actions require a written reason, stored in the audit log: suspend/reactivate, give/remove roles,
  reject organisations. Document rejections use a fixed reason list (plain language for the person).
- Only super admins give or remove national roles, and nobody changes their own national role.
- No "log in as user" (impersonation) in Release 1 - a POPIA risk; the person page shows support what it needs.

## Consequences

- Every new console screen declares its permission next to its menu item; tests check each role's access.
- Permission changes are config, not code.
