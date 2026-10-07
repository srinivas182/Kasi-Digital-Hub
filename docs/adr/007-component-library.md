# ADR-007: Own component library on Radix UI primitives

- **Status:** Accepted
- **Date:** 2026-10-07
- **Sprint:** S1

## Context

Fifteen portals need consistent, accessible components that stay light on low-end phones.

## Decision

Components live in our repository (`resources/js/components/ui`), styled with Tailwind tokens. Interactive
behaviour (dialogs, menus, tabs, checkbox, radio, switch, popover, tooltip) uses Radix UI primitives. Icons use
Lucide (tree-shaken). Overlay components are separate files so pages load only what they use. Native `<select>`
and `<input type=date>` are preferred on phones.

## Consequences

- Full control of look and code; reliable keyboard and screen-reader behaviour.
- Public pages stay within the 25 KB component budget (the theme toggle uses a plain button, not a menu library).
- We maintain the component code ourselves.

## Alternatives considered

Ready-made kits (MUI, Ant Design) - rejected: ~100 KB heavier and generic-looking. Fully custom primitives - rejected: high risk of accessibility bugs.
