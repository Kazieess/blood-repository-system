# Connected improvement implementation

Implementation baseline: 9 November 2026. The user authorized Red Cross Administrator-managed assignments and separate clinical grants, with an initially empty configurable laboratory catalog. This document specifies development behavior, not medical policy or production readiness.

## Access and migration

All operational donor reads, corrections, donation history and counts require an active donor assignment and the existing action permission. There is no administrator bypass for donor/clinical details. Red Cross Administrators can manage assignments using a reference-only queue and active operational staff directory. They may assign themselves explicitly. Assignment changes require a reason and current version. Revocation retains the row/version, clears grants and applies on the next protected request; re-grant does not reset the version. Inactive accounts remain blocked independently.

Migration assigns existing donor records to their original creator without clinical grants. New registrations inherit that creator assignment under their Red Cross-provisioned create permission. Other former chapter-wide visibility is removed; administrators assign other staff explicitly. No existing donor, account or audit row is deleted.

| Role | Assigned donors/donations | Assignment/grant administration | Inventory | Stock rules | Clinical catalog | Backups |
| --- | --- | --- | --- | --- | --- | --- |
| Red Cross Administrator | Read/create/correct | Yes | Read/write | Yes | Yes | No |
| Red Cross Personnel | Read/create/correct | No | Read/write | No | No | No |
| Barangay Health Coordinator | Read/create/correct | No | No | No | No | No |
| System Administrator | No | No | No | No | No | Yes |
| Report Viewer/public | No | No | No | No | No | No |

Clinical read/write/review is separately granted per assigned staff, donor and category. A role or ordinary assignment alone grants no clinical access. Laboratory catalog administration does not grant access to results.

## Initial field dictionary

All identifiers are server-owned. Staff timestamps are UTC instants, displayed in Philippine time. No retention period is assumed; no delete endpoint is introduced for official events.

| Record | Fields and validation | Ownership / privacy |
| --- | --- | --- |
| Assignment | donor/user references; assigner; active boolean; distinct clinical grants from fixed action allowlist; positive version | Red Cross Administrator / restricted |
| Donation | UUID; unique documentary reference (1–80 letters/digits/underscore/hyphen); donor; actual donation date (YYYY-MM-DD, not future); location (2–100 chars); completed/voided state; version; creator/idempotency metadata | Assigned operational staff / private |
| Stock unit | Unique bag reference; entered blood type; component/location; explicit collection/expiry instants; verification/state/version; optional documentary source | Authorized inventory staff / restricted |
| Stock rule | Exact blood type/component/location; administrator-entered nonnegative threshold; explicit recipients; active/version | Red Cross Administrator / restricted |
| Stock alert | Rule; open/resolved condition; computed available count; acknowledgement per assigned recipient; timestamps | Rule recipients / restricted aggregate |
| Clinical entry | Donor/category; structured payload restricted to catalog; entered/reviewed status; recorder/reviewer; version; source/time; correction provenance | Assigned category permissions / sensitive |
| Backup | Opaque ID; run state; checksum/size; verification result; timestamps; internal encrypted artifact reference | System Administrator / restricted; artifact path not public |

Donation counts derive only from recorded completed events, not a lifetime claim or editable number. Voided events remain in history and cannot be reactivated. Unique references and retry keys prevent repeated recording of the same documentary event; identifying duplicate people requires separate approved reconciliation.

## Inventory and alerts

Expiry ordering applies only to verified, available, unexpired matching stock; expiry is an entered instant, with equality considered expired. No shelf-life, compatibility, suitability or medical-release calculation is supplied. Component/location values and thresholds are explicit staff-entered configuration, not inferred medical constants. Authorized staff manually verify/release. Pending, quarantined, spoiled, expired, released and discarded units are excluded from available counts. Release/discard are terminal; spoilage is a distinct labeled unusable status and cannot directly become available. Every transition has a reason and optimistic version check.

Rules are scoped to blood type + component + location; no threshold is seeded. Zero available units is out-of-stock. Low stock is a positive usable count at or below the configured threshold. Durable alerts represent one unresolved condition per rule, with acknowledgement separate from resolution. Replenishment resolves the condition; later shortage opens another alert. Evaluation runs after changes and via a scheduled reconciliation to account for expiry without user writes. External SMS/email are not enabled by this implementation.

## Clinical boundaries

The catalog starts empty. Administrators define named laboratory fields with text/number/choice formats and optional units/reference text, supplying their own approved definitions. No tests, reference ranges or interpretations are seeded. Screening and eligibility records store staff-entered status/notes, not algorithmic clearance. Entry, review and correction are separate actions. Results are omitted from routine donor resources, stock views, aggregate alerts and audit values; access is rechecked on every request. Attachments remain out of scope.

## Recovery boundary

The first actual backup adapter targets the existing local SQLite configuration. Backups are private encrypted snapshots with checksums and application-key fingerprint metadata; the application key must be kept separately. Restore verification uses a new isolated file target and checks integrity/counts; it cannot overwrite the running database. MySQL backup/restore is explicitly unsupported until an approved MySQL service/tooling is tested. No production cutover, key escrow, offsite destination, retention deletion or guaranteed recovery objective is implied.

## API conventions

All routes below are protected session/CSRF `/api/v1` routes; GETs require read access, writes use action authorization and atomic audit. List responses use `data` and pagination metadata. Validation 422, no access 403 or scoped missing 404, stale/duplicate 409; safe errors contain request references. Creation accepts a UUID `Idempotency-Key` where specified; corrections require current `version` and `reason`.

- `GET /assignments`: reference-only queue/staff directory for administrators; `GET|PUT /assignments/{donor}`: inspect/grant/revoke versioned assignments.
- `GET|POST /donors/{donor}/donations`, `PATCH /donors/{donor}/donations/{donation}`: scoped completed history, date/status filters, correction/void, retry-safe creation.
- `GET|POST /inventory`, `GET /inventory/{unit}`, `POST /inventory/{unit}/transitions`: expiry-ordered filters, unit history and versioned verify/quarantine/spoil/release/discard. Creation is retry-safe.
- `GET|POST /stock-rules`: administrator-entered scopes, thresholds and recipients, with versioned updates. `GET /stock-alerts`, `POST /stock-alerts/reconcile`, `POST /stock-alerts/{alert}/acknowledge`: recipient-scoped conditions, administrator reconciliation and acknowledgement.
- `GET|POST /clinical-catalog`: administrator-managed laboratory definitions. Keys/formats are immutable; updates require a version and reason. No default panel is installed.
- `GET|POST /donors/{donor}/clinical/{category}`, `PATCH .../{entry}`, `POST .../{entry}/review`, `GET .../{entry}/history`: category-scoped encrypted entries/revisions and access auditing. Creation is retry-safe. Categories are screening/laboratory/eligibility. Read and review grants are both needed to review. Self-review is disabled by the explicit `repository.allow_clinical_self_review` development setting. Corrections require re-review; deferral dates never automatically establish eligibility.
- `GET|POST /backups`, `POST /backups/{backup}/restore-copy`: technical administrator metadata, retry-safe encrypted SQLite snapshot and explicit isolated restore confirmation. No public backup download or running-database replacement endpoint exists.

Scheduler: `stock:evaluate` runs every minute when Laravel's scheduler is running. Set `REPOSITORY_BACKUP_AT=HH:MM` in the ignored local `.env` to enable daily backups at that Philippine time; an empty value leaves the backup schedule unconfigured. `repository:backup` and `repository:restore-copy {backup-uuid}` are technical CLI commands. The initial local snapshot capacity is explicitly limited to 100 MiB in configuration; large databases need a reviewed streaming/offsite strategy rather than increasing limits blindly. Keep the application key separately to recover encrypted backups and clinical entries. Restore copies contain private plaintext database data and stay under `storage/app/private/repository-restores`; they are never placed in a public directory.

SQLite snapshot implementation uses [VACUUM INTO](https://www.sqlite.org/lang_vacuum.html), with encryption, integrity and foreign-key checks and reconciliation against counts taken from the snapshot itself. This validates a local recovery copy, not a production recovery-time or availability guarantee.

Rollback: revert the corresponding feature commits and preserve a verified database snapshot first. Do not roll back/drop additive event tables on populated data merely to undo a UI change. No destructive migration command is part of normal setup.
