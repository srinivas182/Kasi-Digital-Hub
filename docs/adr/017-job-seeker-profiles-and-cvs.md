# ADR-017: Job seeker profiles, AI-assisted CVs and voice notes

- **Status:** Accepted
- **Date:** 2026-10-09
- **Sprint:** S9

## Decisions

1. **Informal work is experience.** Experience kinds include piece work, own/family business, caregiving,
   volunteering and learnerships. Dates are optional when a rough duration is given.
2. **The person decides.** AI suggestions (CV points, profile summary) are shown next to the person's own words
   and used only when accepted. A fact check flags numbers, names and qualification-like words that are not in
   what the person wrote. Contact details never go to the AI; templates add them.
3. **CV contents.** Never: ID number, photo, date of birth, street address. Age only if the person chooses.
   Education linked to a verified document carries a "Verified" mark, checkable through the QR code (`/verify`).
4. **CVs are verifiable documents** (S8 `DocumentIssuer`): versions are kept; deleting a version withdraws its
   verification; share links last 30 days, count views and can be switched off; WhatsApp sharing uses wa.me links.
5. **Voice notes** go through a `SpeechToText` driver (fake, OpenAI); audio is never stored (the temporary upload
   is deleted after transcription); each language is switched on by configuration only after testing with real
   recordings from the hubs (`KASI_SPEECH_LANGUAGES`, English first). Length and daily limits apply.
6. **One profile, two ways in.** `WorkSubject` makes every KasiWork screen work for the signed-in adult or - during
   a facilitator help session (S7) - for the person being helped, recorded as assisted.
7. **Not visible to employers yet.** Visibility depends on the existing "job matching" consent and starts in S11.
