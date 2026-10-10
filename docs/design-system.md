# Design system

The KasiHub design system gives all 15 portals one consistent, accessible, mobile-first look. Every screen is
built from these parts. Live reference: **`/ui-kit`** (local, demo and staging only - never production).

## Principles

1. **Phone first.** Design for a 360 px wide entry-level Android phone, then enhance for larger screens.
2. **Light on data.** Every kilobyte counts on prepaid data. Respect the performance budgets below.
3. **Plain and clear.** See [content-guide.md](content-guide.md).
4. **Accessible by default.** WCAG 2.1 AA, checked automatically in CI with axe.
5. **One source of truth.** Colours, spacing and type come from tokens in `resources/css/app.css` - never
   hard-code hex values in components.

## Tokens

| Token | Light | Dark | Use |
|---|---|---|---|
| `kasi-indigo` | #24206B | (fixed) | Brand surfaces: app bar, console sidebar, emphasis cards |
| `primary` | #3B34B5 | #9893F4 | Buttons, links, active states |
| `kasi-marigold` | #F5B700 | (fixed) | Accent backgrounds **with dark text only** (never text on white - fails contrast) |
| `kasi-green` | #0E9F8A | (fixed) | Success, excellent match scores |
| `danger-text` | #B42F2F | #F19494 | Error text |
| `surface` / `surface-muted` / `surface-raised` | white / #F5F6FA / white | dark indigo shades | Backgrounds |
| `fg` / `fg-muted` | #16152B / #5B5F77 | light greys | Text |
| `line` | #E3E5EE | #2C2B52 | Borders |

Soft backgrounds (`*-soft`) are paired with their `*-ink` text colour for 4.5:1 contrast.

**Match score bands** (KasiWork, shared with the matching engine): 9-10 excellent (green), 7-8 strong
(indigo), 5-6 partial (marigold), below 5 low (grey).

**Type:** Poppins 400/600/700, self-hosted Latin subset (no third-party font calls). Body text 16 px minimum on
phones; inputs are 16 px so iOS does not zoom.

**Dark mode:** follows the phone setting, with a manual toggle (light -> dark -> phone setting) saved per
device. The theme class is applied before first paint to avoid flashing.

## Components (`resources/js/components/ui`)

| File | Components |
|---|---|
| `Button.tsx` | `Button` (primary, accent, secondary, ghost, danger; sm/md/lg; loading), `IconButton` (label required) |
| `form.tsx` | `Field` (label, hint, error - wired for screen readers), `Input`, `Textarea`, `Select` (native), `SearchInput`, `Checkbox`, `RadioGroup`, `Switch` |
| `PhoneInput.tsx` | +27 phone number input; reports E.164 |
| `OtpInput.tsx` | 6-digit code entry with auto-advance, paste and SMS autofill |
| `Stepper.tsx` | Multi-step form progress |
| `display.tsx` | `Card`, `Badge`, `Avatar`, `ProgressBar`, `StatCard`, `Timeline`, `EmptyState`, `Skeleton` |
| `ScoreRing.tsx` | 0-10 score ring with spoken description |
| `Alert.tsx`, `Toast.tsx` | Inline messages; toasts announced to screen readers |
| `Dialog.tsx` | `Dialog`, `ConfirmDialog`, `BottomSheet` |
| `DropdownMenu.tsx`, `Tooltip.tsx`, `Popover.tsx` | Menus and hints |
| `navigation.tsx` | `Tabs`, `Breadcrumbs`, `Pagination` |
| `DataTable.tsx` | Table on desktop, stacked cards on phones |
| `LazyImage.tsx` | Images with fixed size and lazy loading |

Interactive primitives (dialogs, menus, tabs, checkboxes, radios, switches) use **Radix UI** for keyboard and
screen-reader behaviour (ADR-007). Overlay components live in separate files so pages only load what they use.

Platform pieces (`resources/js/components/platform`): skip link, demo banner, offline notice, language switcher,
theme toggle, brand mark, portal switcher, bottom navigation, sidebar.

## Layouts (`resources/js/layouts`)

| Layout | Used by |
|---|---|
| `PublicLayout` | Public website |
| `AuthLayout` | Sign-in and sign-up steps |
| `AppLayout` | Hub home (bottom tab bar on phones) |
| `PortalLayout` | Service portals (sidebar on desktop, bottom nav on phones) |
| `ConsoleLayout` | Admin, commercial, funder and regional consoles |
| `KioskLayout` | Shared hub computers (large targets, automatic sign-out when idle) |

Every layout includes the skip link, demo banner, offline notice and toast area.

## Navigation

Each module declares its menu in `module.json`:

```json
"nav": { "icon": "briefcase", "href": "/work",
         "items": [{ "label": "nav.work.matches", "href": "/work", "icon": "briefcase" }] }
```

Labels are translation keys. Icons are names from `resources/js/lib/icons.ts`. Menus are shared with every page
and, from Sprint 3, filtered by the user's roles and their hub's package.

## Languages

Strings live in `lang/<code>.json`. Pages use `const { t } = useTranslation()` and `t('key', { name: value })`.
Missing keys fall back to English. isiZulu and Xitsonga contain **draft** sample strings only (marked `_note`)
and are hidden in production until professionally translated (S23).

## South African formats

`resources/js/lib/format.ts` and `App\Support\Format\SaFormat` (identical results, tested against
`tests/fixtures/format-cases.json`): money `R1 234.56` (amounts in cents), phones stored as `+27...` and shown
as `072 418 3390`, dates `7 Oct 2026`, times 24-hour SAST.

## Performance budgets (checked in CI)

| Budget | Limit | Current |
|---|---|---|
| Entry JS (React + Inertia) | 150 KB gzip | ~106 KB |
| Each page incl. its components | 60 KB gzip | 15-16 KB |
| Public first load (entry + home) | 130 KB gzip | ~121 KB |

UI kit pages are internal and exempt.

## Accessibility checklist (every screen)

- One `h1`; headings in order. Content inside `#main` (skip link target).
- Every input has a visible label (`Field`); errors say what to do next.
- Touch targets at least 44 px; visible focus outline.
- Never rely on colour alone; icons have text or a label.
- Tooltips are extras only - never essential information.
- Run `npx playwright test` - axe must report no serious or critical issues.

### Author tools budget (S13)

Pages under `modules/Learn/resources/js/Pages/Author/` (course authoring) may be up to 200 KB gzip: they hold the
rich-text editor (TipTap/ProseMirror), are used by provider staff on desktops and are never loaded by learners.
Learner and public pages keep the 60 KB page budget and the 130 KB first-load budget.

### First-load warning line (S15)

CI fails when the public first load (entry + home page) passes **125 KB**, keeping 5 KB in hand under the 130 KB
budget. In S15 the first load went from 130.0 KB to about 122.6 KB by replacing tailwind-merge with a small,
tested `cn()` (resources/js/lib/cn.ts). What remains is mostly the framework itself (React, Inertia).
