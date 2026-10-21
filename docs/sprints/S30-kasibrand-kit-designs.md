# Sprint 30 - KasiBrand part 1: brand kit, designs and the WhatsApp catalogue

- **Status:** Complete
- **Version:** v0.30.0

## Delivered

Brand kit (uploaded or generated text logo, tested palettes with a contrast check, lettering, tagline ideas, contact
choices); designs from 8 templates with a live preview, PNG on the phone and PDF for printing; my designs; the WhatsApp
catalogue maker; the business-products core contract; "has a brand kit" signal and indicators (ADR-038).

## Issues found and fixed during the sprint

- The PDF download first sent the encrypted XSRF cookie as a form field (Laravel would reject it); it now sends the
  header and saves the returned PDF.
- After a PNG download, the editor's notice hid the "Design saved." message (caught by the phone browser test); both
  now show.
- The new tagline prompt wasn't in the prompt lock (`docs/ai-prompts.lock.json`); recorded with `kasi:ai:lock` (only
  the new entry was added).
- CI (browser tests): the S29 KasiBiz browser test let phone and desktop mark the same deadline done at the same time
  (they share Nomsa's demo business) and wasn't retry-safe; each project now uses its own deadline and unique names.
  The KasiStart plan-builder check for "20" matched two elements; it now reads the value next to its label.
- Before creating the module, checked that no KasiBrand work existed anywhere (lesson from S28); the extra `'brand'`
  config match is the platform's branding settings, not a duplicate.

## Known limitation

An uploaded logo or product photo can be missing from the phone-made PNG until the single asset address (S34); the PDF
always includes them.

## Waiting on Ku Tirhisana

Feedback on the palettes and templates (ideally from hub entrepreneurs); whether designs carry "Made with KasiHub".
