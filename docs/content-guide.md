# Content guide - writing for KasiHub

Many KasiHub users are young people using a phone with little data, sometimes in their second or third
language, sometimes with limited digital confidence. Write for them.

## Rules

1. **Plain words.** "Find a job", not "Explore employment opportunities". Aim for a 12-year-old's reading level.
2. **Short.** One idea per sentence. Most sentences under 15 words.
3. **Talk to the person.** "Your CV is ready", not "The CV has been generated".
4. **One main action per screen.** One primary button; everything else is secondary.
5. **Buttons say what happens.** "Send my code", "Apply with my CV", "Delete job" - never "OK" or "Submit".
6. **Icons always have words.** An icon alone is a guess for many users.
7. **Errors explain the fix.** "Check the number and try again" - not "Invalid input".
8. **Be honest about scores.** A match score is a fit score with reasons - never a promise of being hired.
9. **Respect privacy.** Say who will see information before asking for it.
10. **Long forms become steps.** Use the `Stepper`; ask only what is needed now.
11. **Informal work counts.** Never imply that piece jobs, family business or caring work are not "real" experience.
12. **Numbers and dates the South African way.** R1 234.56, 072 418 3390, 7 Oct 2026, 14:30.

## Words we use

| Use | Avoid |
|---|---|
| Sign in | Log on, authenticate |
| Cellphone number | Mobile/MSISDN |
| Code | OTP, token |
| Hub facilitator | Agent, operator |
| Job seeker | Candidate (in seeker-facing screens), applicant pool |

## Translation

Write English strings as translation keys in `lang/en.json`. Keep sentences complete (no joined fragments) so
translators can reorder words. Use `:name` placeholders for values.
