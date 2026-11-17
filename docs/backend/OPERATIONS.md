# Connected improvement implementation

Implementation baseline: 9 November 2026. The user authorized Red Cross Administrator-managed assignments and separate clinical grants, with an initially empty configurable laboratory catalog. This document specifies development behavior, not medical policy or production readiness.

## Access and migration

All operational donor reads, corrections, donation history and counts require an active donor assignment and the existing action permission. There is no administrator bypass for donor/clinical details. Red Cross Administrators can manage assignments using a reference-only queue and active operational staff directory. They may assign themselves explicitly. Assignment changes require a reason and current version. Revocation retains the row/version, clears grants and applies on the next protected request; re-grant does not reset the version. Inactive accounts remain blocked independently.

Migration assigns existing donor records to their original creator without clinical grants. New registrations inherit that creator assignment under their Red Cross-provisioned create permission. Other former chapter-wide visibility is removed; administrators assign other staff explicitly. No existing donor, account or audit row is deleted.

| Role | Assigned donors/donations | Assignment/grant administration | Inventory | Stock rules | Clinical catalog | Backups | Inventory/expiry reports | Publish availability | Staff accounts | Audit |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Red Cross Administrator | Read/create/correct | Yes | Read/write | Yes | Yes | No | Yes | Yes | No | Yes |
| Red Cross Personnel | Read/create/correct | No | Read/write | No | No | No | No | No | No | No |
| Barangay Health Coordinator | Read/create/correct | No | No | No | No | No | No | No | No | No |
| System Administrator | No | No | No | No | No | Yes | No | No | Yes | Yes |
| Report Viewer | No | No | No | No | No | No | Yes | No | No | No |
| Public visitor | No | No | No | No | No | No | No | No | No | No |

All visitors may read only the current public aggregate snapshot at `/availability`. Assigned donor readers may obtain donation reports over their accessible records. All active staff may change their own password; this does not grant staff-account administration.

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

Expiry ordering applies only to verified, available, unexpired matching stock with a completed linked donation; expiry is an entered instant, with equality considered expired. No shelf-life, compatibility, suitability or medical-release calculation is supplied. Component/location values and thresholds are explicit staff-entered configuration, not inferred medical constants. Authorized staff manually verify/release. Pending, quarantined, spoiled, expired, released and discarded units are excluded from available counts. Release/discard are terminal; spoilage is a distinct labeled unusable status and cannot directly become available. Every transition has a reason and optimistic version check.

Rules are scoped to blood type + component + location; no threshold is seeded. Zero available units is out-of-stock. Low stock is a positive usable count at or below the configured threshold. Durable alerts represent one unresolved condition per rule, with acknowledgement separate from resolution. Replenishment resolves the condition; later shortage opens another alert. Evaluation runs after changes and via a scheduled reconciliation to account for expiry without user writes. External SMS/email are not enabled by this implementation.

## Clinical boundaries

The catalog starts empty. Administrators define named laboratory fields with text/number/choice formats and optional units/reference text, supplying their own approved definitions. No tests, reference ranges or interpretations are seeded. Screening and eligibility records store staff-entered status/notes, not algorithmic clearance. Entry, review and correction are separate actions. Results are omitted from routine donor resources, stock views, aggregate alerts and audit values; access is rechecked on every request. Attachments remain out of scope.

## Recovery boundary

The first actual backup adapter targets the existing local SQLite configuration. Backups are private encrypted snapshots with checksums and application-key fingerprint metadata; the application key must be kept separately. Restore verification uses a new isolated file target and checks integrity/counts; it cannot overwrite the running database. MySQL backup/restore is explicitly unsupported until an approved MySQL service/tooling is tested. No production cutover, key escrow, offsite destination, retention deletion or guaranteed recovery objective is implied.

## API conventions

The operational routes listed immediately below are protected session/CSRF `/api/v1` routes; GETs require read access, writes use action authorization and atomic audit. List responses use `data` and pagination metadata. Validation 422, no access 403 or scoped missing 404, stale/duplicate 409; safe errors contain request references. Creation accepts a UUID `Idempotency-Key` where specified; corrections require current `version` and `reason`.

- `GET /assignments`: reference-only queue/staff directory for administrators; `GET|PUT /assignments/{donor}`: inspect/grant/revoke versioned assignments.
- `GET|POST /donors/{donor}/donations`, `PATCH /donors/{donor}/donations/{donation}`: scoped completed history, date/status filters, correction/void, retry-safe creation.
- `GET|POST /inventory`, `GET /inventory/{unit}`, `POST /inventory/{unit}/transitions`: expiry-ordered filters, unit history and versioned verify/quarantine/spoil/release/discard. Creation is retry-safe.
- `GET|POST /stock-rules`: administrator-entered scopes, thresholds and recipients, with versioned updates. `GET /stock-alerts`, `POST /stock-alerts/reconcile`, `POST /stock-alerts/{alert}/acknowledge`: recipient-scoped conditions, administrator reconciliation and acknowledgement.
- `GET|POST /clinical-catalog`: administrator-managed laboratory definitions. Keys/formats are immutable; updates require a version and reason. Corrections cannot silently omit previously recorded laboratory fields, including retired definitions; restore an approved definition before correcting those entries. No default panel is installed.
- `GET|POST /donors/{donor}/clinical/{category}`, `PATCH .../{entry}`, `POST .../{entry}/review`, `GET .../{entry}/history`: category-scoped encrypted entries/revisions and access auditing. Creation is retry-safe. Categories are screening/laboratory/eligibility. Read and review grants are both needed to review. Self-review is disabled by the explicit `repository.allow_clinical_self_review` development setting. Corrections require re-review; deferral dates never automatically establish eligibility.
- `GET|POST /backups`, `POST /backups/{backup}/restore-copy`: technical administrator metadata, retry-safe encrypted SQLite snapshot and explicit isolated restore confirmation. No public backup download or running-database replacement endpoint exists.

Scheduler: `stock:evaluate` runs every minute when Laravel's scheduler is running. Set `REPOSITORY_BACKUP_AT=HH:MM` in the ignored local `.env` to enable daily backups at that Philippine time; an empty value leaves the backup schedule unconfigured. `repository:backup` and `repository:restore-copy {backup-uuid}` are technical CLI commands. The initial local snapshot capacity is explicitly limited to 16 MiB in configuration; large databases need a reviewed streaming/offsite strategy rather than increasing limits blindly. Keep the application key separately to recover encrypted backups and clinical entries. Restore copies contain private plaintext database data and stay under `storage/app/private/repository-restores`; they are never placed in a public directory.

SQLite snapshot implementation uses [VACUUM INTO](https://www.sqlite.org/lang_vacuum.html), with encryption, integrity and foreign-key checks and reconciliation against counts taken from the snapshot itself. This validates a local recovery copy, not a production recovery-time or availability guarantee.

Rollback: revert the corresponding feature commits and preserve a verified database snapshot first. Do not roll back/drop additive event tables on populated data merely to undo a UI change. No destructive migration command is part of normal setup.

## Connected completion milestone (9 November 2026)

### Traceability

Blood units now have an optional, foreign-key protected donation link. Existing units are not guessed or deleted. An assigned worker with inventory write permission links an unlinked Pending/legacy Available unit by its completed documentary donation reference. Collection date and donation date must match in Philippine time. Linking is versioned, audited, permanent and resets the unit to Pending for re-verification. Source mistakes require an administrative reconciliation procedure; the API does not silently replace a source. Verification/release requires a completed linked donation. Unlinked units are excluded from usable stock and public publication. A donation with linked units cannot be voided or have its date changed. Clinical entries may optionally identify a donation belonging to the same donor; association is immutable and retained in encrypted revisions. No clinical clearance prerequisite is inferred.

GET /donors/{donor}/units returns a paginated source trace only to assigned donor readers with inventory.read. POST /inventory/{unit}/source accepts donation_reference, version and reason. Routine inventory responses expose only source_linked, never donor/donation identity or clinical results.

### Reports and public availability

Red Cross Administrators and Report Viewers receive reports.read for inventory/expiry aggregates. Assigned operational donor readers can obtain donation aggregates over their accessible donors only. Report Viewers do not receive donor profiles or donation reports. GET /reports/{kind} supports inventory/expiry/donations with from/to dates, exact location, inventory blood type and page. Inventory dates refer to collection, expiry dates to expiry, donation dates to the recorded event; date boundaries use Philippine time. Pages contain 25 groups. POST /reports/{kind}/export returns formula-escaped CSV in JSON (csv, filename, row_count), up to 5,000 groups, and requires a successful audit write. Exports contain no donor identity or clinical payload.

Red Cross Administrators receive availability.publish. GET/POST /publications lists/prepares publications; POST /publications/{snapshot}/withdraw withdraws without resurrecting an older publication. Publishing requires explicit disclosure confirmation, selected storage location, approved public location label, validity of 1 to 1,440 minutes and a reason. The snapshot contains only blood type, component label, public location label and aggregate count. First included expiry caps validity; subsequent inventory writes invalidate it. No snapshot is automatically published or sent externally. Only the newest publication is served. Retry keys require unchanged inputs.

The new /availability page reads GET /api/v1/public/availability without login. State is available/stale/unavailable; stale and unavailable return empty counts. Visitors see publication timestamps and a non-reservation notice. The page refreshes while visible and removes counts at expiry. Publication is an as-of snapshot, not live reservation/medical approval. The original / fictional demonstration remains independent.

### Account administration and recovery

System Administrators receive users.manage. GET /staff provides paginated name/email search, POST provisions an account, PATCH /staff/{id} updates name, role and active state. All writes require the administrator's current password and a reason; updates also require the current record version. Initial passwords meet the existing 12-character/mixed-case/number/symbol policy. Create retries use a UUID and keyed input digest; no plaintext password is persisted or audited. Email is immutable in this milestone. Administrators cannot change their own access or remove the last active System Administrator. Role/activation changes increment session version, revoke all donor assignments and category grants, and invalidate reset tokens. Red Cross Administrators must explicitly reassign access after a role change/reactivation. No clinical grant is inherited from a technical role.

/recovery supports disabled-email guidance, reset-link consumption and authenticated password changes (/recovery#change). POST /auth/password confirms the old password, rotates session version and preserves the current session. POST /auth/forgot-password has a generic response and login throttling; POST /auth/reset-password consumes a single-use broker token (60-minute default expiry) atomically with audit/password changes. Other sessions fail on their next protected request. Reset tokens/email are in the email URL fragment, removed from the address bar on loading; no token is sent as a URL query to the app server.

REPOSITORY_PASSWORD_RESET_EMAIL defaults false. Configure an approved mail service and APP_URL before enabling it; log/array/failover mail transports cannot enable recovery. No real delivery was tested or enabled. Framework password broker behavior follows [Laravel's password recovery documentation](https://laravel.com/framework/docs/passwords).

### Validation and deployment boundary

The MySQL CI job uses a disposable blood_repository_ci service database. Explicit REPOSITORY_MYSQL_TEST=isolated is required; normal tests continue using isolated SQLite. SQLite backup and temporary HTTP-server tests are excluded from the MySQL job and remain covered in the SQLite job. Neither passing MySQL feature tests nor the read-only repository:deployment-check command establishes production MySQL backup/restore, load capacity or deployment readiness.

The deployment check prints pass/fail labels only for environment, debug, HTTPS URL, session cookie settings, key presence, MySQL selection, writable directories, connectivity, migrations and recent scheduler heartbeat. Scheduler heartbeat runs every minute alongside stock evaluation. No hosting/mail provider has been selected. Hosting/TLS verification, production recovery/offsite retention, browser/device/accessibility acceptance and load testing remain pending. Follow [Laravel deployment guidance](https://laravel.com/framework/docs/13.x/deployment) when an approved host is selected. Do not configure a production database as a disposable test target.
