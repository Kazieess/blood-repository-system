# Project working agreements

Read `IT-SIA - Synthesized-Project/Master Development Prompt - Blood Repository System.md` and the applicable policies in `MD FIles - AI Skills` before non-trivial changes. Preserve the established branding and existing demo. Apply framework-specific examples only to frameworks actually in use.

The current backend milestone is authentication, action-level donor authorization, persistent donor profiles and durable audit entries. Operational medical rules, inventory, SMS and recovery jobs remain separate milestones. Do not silently promote frontend fixture rules into approved production policy.

Backend source is in `backend`; static frontend source is in `frontend`. A portable PHP runtime may exist in `.tools/php84` and must not be committed. MySQL is the intended production engine; SQLite is an explicit isolated local/test option, not evidence of MySQL verification.

Use server-enforced permissions, safe API responses, CSRF-protected cookie sessions, transactions for donor changes plus audit, and fictional test data. Never print environment secrets or record donor names, credentials, or request bodies in operational logs. Restrict audit access.

Keep `frontend/index.html` working as the fictional demo. The backend-connected entry must use authenticated API responses and must not import the demo donor fixtures. Tests should verify real behavior, authorization and persistence. Run the relevant Laravel feature tests, frontend tests and asset-path checks before claiming completion.
