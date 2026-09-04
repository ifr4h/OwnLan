# Beta readiness audit

Living work queue for beta hardening. Cross-reference founder gate: `docs/20-founder-acceptance-test.md`.

## Status

**Closed instructor beta:** allowed after founder acceptance pass (Sep 2026).

**Open self-serve production:** not yet.

## Classification

| Severity | Meaning |
|----------|---------|
| P0 / BLOCKER | Data loss, security, financial corruption, unusable core path |
| P1 / HIGH | Core workflow broken or seriously confusing |
| P2 / MEDIUM | Significant UX / reliability |
| P3 / LOW | Polish |

## Fixed in hardening + acceptance

- Guest payment / share auth middleware
- Today Call + learner mobile
- Mobile Settings entry
- Demo seed production guard
- Beta feedback + build version in Settings
- Error handler sanitisation (non-debug)
- Package hours without float money drift
- Demo data naming coherence (Amina / Sarah / Amal)
- Payment + marketing copy cleanups

## Open / deferred

See deferred table in `docs/20-founder-acceptance-test.md`.

Priority deferred ops: **add CI** running API unit tests + web typecheck + build.

## Do not build next

No marketplace, AI assistant, chat, native apps, or new major feature areas until closed beta evidence says otherwise.
