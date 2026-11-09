# Suggested improvements: implementation plan

Review date: 9 November 2026.

Status: connected local-development implementation delivered on 9 November 2026, following the user's scope/access clarifications. [Implementation decisions/contracts](backend/OPERATIONS.md) and [the connected walkthrough](../frontend/IMPROVEMENTS.md) describe delivered behavior and configuration. The original gap table below describes the baseline before implementation. Clinical interpretation, production cutover, external SMS and MySQL verification are not implied.

## Delivered status

- Versioned Red Cross Administrator-managed assignments, creator backfill and separate clinical grants; server-enforced assigned donor/history access.
- Persistent donation events, correction/voiding, scoped filters and derived completed counts.
- Inventory, explicit verification/stock transitions, spoilage labeling, expiry-ordered available candidates and event history.
- Configurable thresholds/recipients, deduplicated shortage episodes, acknowledgement/resolution, scheduled expiry evaluation and a visible periodic notice.
- Empty configurable laboratory catalog, screening/laboratory/eligibility entry, independent review, encrypted payloads/revisions and access auditing.
- Actual encrypted local SQLite snapshots and verified isolated restore copies. Daily backup schedule stays unconfigured until set; no running database is overwritten.
- Connected responsive screens, API contracts and behavior tests. Visual/device review is outstanding because automation hosts were unavailable. Deployment-specific MySQL/offsite recovery and external notifications remain separate work.

## Confirmed scope clarifications

1. Donor-list access is for authorized staff assigned by the Red Cross. This does not introduce a donor self-service portal or designated-contact access. The assignment model must be enforced on server queries and individual records, not only navigation.
2. The requested clinical feature includes all three categories: screening status, detailed laboratory results, and eligibility/deferral notes. These are distinct records and permissions, even if presented within one donor profile. Assignment to a donor alone must not silently grant every clinical permission; the field/action access matrix still needs definition.

These decisions resolve the intended audience and clinical feature scope. Remaining design decisions include the staff role allowed to assign/reassign access, donor/team/event assignment granularity, supervisory exceptions, exact clinical fields and result formats, review/correction authority and retention. Do not invent test panels, reference ranges, diagnostic interpretations or eligibility intervals. File attachments are a separate decision, not implicitly required by detailed structured results.

The delivered migration maps existing records to their creator without clinical grants. Broader chapter-wide donor visibility is removed. Revocation/re-grant retain a monotonic version and apply on subsequent protected requests. The current access matrix is in OPERATIONS.md; no donor self-service or representative access was added.

## Suggestions and current coverage

| Suggestion | Interpretation / proposed behavior | Current connected system | Decisions before implementation |
| --- | --- | --- | --- |
| Blood nearing expiry should come first | Propose first-expiry-first-out ordering among permitted, verified, available, unexpired units matching approved selection criteria. Staff remain responsible for suitability and release. | Inventory exists only in the fictional demo. No connected selection/release API. | Component types, expiry precision/timezone, staff approval authority, tie handling and any permitted override. |
| Spoiled blood should be clearly labeled | Record an authorized spoilage/unusable determination, remove affected units from available stock, and preserve reason, actor, timestamp and history. Use text and icons as well as color. | Demo has quarantine/discard actions; no persistent inventory workflow. | Meaning of spoiled versus suspected/unverified, quarantine, expired and discarded; allowed transitions and responsible staff. |
| Show whether something was found in a donor's blood | Confirmed scope: screening status, detailed laboratory results, and eligibility/deferral notes, entered/reviewed by authorized personnel. Keep these categories distinct. The application does not diagnose or infer a result from blood type. | No clinical or laboratory fields/API. | Exact fields/result formats, reviewers, viewers, correction procedure, disclosure and retention. No new sensitive fields until these are resolved. |
| Show how many times a person has donated | Persist donation events and derive a count from qualifying completed events; show history and define the covered date/source scope. Corrected, duplicate, cancelled or voided events must not inflate counts. | Only the demo has donation history/counts. | What counts as completed, imported history handling, duplicate-event rules, correction/voiding authority and required event fields. |
| Only the connected person sees the donor list | Confirmed: authorized staff assigned by the Red Cross. Require approved role/action permission plus an active assignment in the new access model. | Operational staff have role access; coordinators are limited to records they created. Explicit Red Cross assignments are not implemented. | Assignment ownership/granularity, authorized administrator exceptions, reassignment/revocation and migration of existing records. |
| Donation records are visible only to assigned workers | Enforce approved role plus record-assignment checks in API policies, queries, aggregates and exports. Hiding navigation alone is insufficient. | No connected donations or staff-assignment module. | Assignment unit (donor, event, team, barangay), assigning authority and any supervisory access. |
| Warn assigned staff when stock is low or empty | Compute usable stock for an approved blood type/component/location scope, compare with configured thresholds, and show restricted in-app alerts. Record acknowledgement and resolution; prevent repeated alerts for the same unresolved condition. | No connected stock calculation, alert rules or delivery service. | Stock definition, units of quantity, threshold per scope, recipients, evaluation schedule and notification channels. Do not invent threshold values. |
| Search and filters | Preserve connected donor name/municipality/reference search, blood type, page size and pagination. Add approved date, status, expiry and location filters to future donation/inventory modules. | Donor search/filtering and exact audit search already connected. Other operational filters are demo-only. | Allowed filter fields and authorization scope for each future module. |
| Backup and recovery | Create actual protected backups with run status, verified restore to an isolated target, reconciliation and recorded recovery results. A backup metadata row is not a backup. | Demo metadata/checklist only. No backup or restore jobs. | Hosting/database engine, destination/access, schedule, retention, recovery owner, acceptable data loss/downtime, required files/configuration and protection of recovery secrets. |

## Expiry ordering proposal

Proposed example: if two otherwise permitted units expire on 12 and 15 November, list the 12 November unit first. This differs from ordering solely by arrival/collection date. An earlier-expiring unit that is expired, quarantined, marked unusable, unverified or otherwise excluded by approved policy must not become a release candidate just because it sorts first.

The expiry boundary, same-day behavior, supported components and any override are approval decisions. Do not copy the demo's fixed date or entered-date rules into the connected backend as an approved medical rule. Ordering a candidate does not authorize its use.

## Proposed implementation sequence

### 1. Confirm fields and access

Use the confirmed Red Cross staff-assignment scope and all three clinical information categories above. Update the field dictionary and action-level permission matrix, including who may view, enter, review and correct each category. Define assignment authority/granularity and migration of existing creator-based access. Confirm donation completion/counting rules, inventory transitions and expiry semantics. Keep unresolved sensitive fields out of the schema.

Deliverable: reviewed data definitions, permission cases, transition rules and acceptance criteria, followed by versioned API contracts for the next slice. Preserve existing development records and the independent demo.

### 2. Donation history, counts and approved assignment access

Add migrations, authenticated APIs, server-scoped list/detail queries, safe resources, transaction-backed create/correction history and connected profile screens. Derive counts from qualifying saved events instead of maintaining an editable lifetime counter. Clearly label incomplete historical coverage.

Acceptance: records and counts survive reload; retry does not duplicate an event; corrections/voids reconcile totals; assignment and role restrictions apply to URLs, counts and API calls; another worker's records are not disclosed.

### 3. Inventory, unusable status and expiry ordering

Add persistent units, approved component/location fields, recorded verification, controlled stock transitions and restricted history. Store entered approved expiry data; do not invent component shelf-life calculations. Protect transitions and quantity reconciliation against concurrent requests. Add the connected list, unit detail, filters and expiry-ordered candidate view.

Acceptance: excluded units cannot be released or counted as available; repeated/concurrent release cannot decrement twice; expiry ordering includes only approved candidates; every adjustment includes an operator, reason and timestamp. Quarantine, unusable and discarded states remain distinguishable according to the approved workflow.

### 4. Low-stock / out-of-stock alerts

Build on verified stock calculations. Scope thresholds and recipients, evaluate after stock-changing events and on a scheduled reconciliation so time-based expiry can change counts even without a user write. Use durable, deduplicated alert state and staff-visible acknowledgement/resolution. Notifications should contain only the information recipients are authorized to receive.

Acceptance: a threshold crossing alerts the intended recipients, out-of-stock is represented explicitly, unchanged conditions do not flood staff, replenishment resolves the condition, and a later shortage can generate a new alert. Time-driven expiry also updates stock/alert state. Unconfigured thresholds are shown as unconfigured rather than silently using invented values.

Start with in-app alerts. Email/SMS are separate channel integrations with approved ownership, recipients, templates, credentials and delivery/retry tracking; no external messages are sent as part of this planning work.

### 5. Restricted screening, laboratory and eligibility records

The user confirmed all three categories: screening status, detailed laboratory results, and eligibility/deferral notes. Define the approved fields and action permissions for each before migrations. Implement them as distinct records/workflows linked to the appropriate donor and, where defined, donation event. Separate clinical access from routine donor-directory and inventory access. Define review/correction, provenance and appropriate access auditing; avoid sensitive values in application logs, aggregate alerts or public responses. Do not automatically include attachments or infer diagnoses from recorded results.

Acceptance: unauthorized staff cannot read or update clinical information through any endpoint or export. The UI accurately identifies pending/recorded/reviewed information and never infers a diagnosis or approval from administrative data. Clinical authority remains with designated personnel.

If approved screening status is required for inventory availability, implement that restricted prerequisite before enabling stock verification/release in steps 3–4. Detailed laboratory records can remain a separate later slice.

### 6. Backup and tested recovery

Design recovery with the target deployment before release, and implement the actual backup workflow once the storage/database environment is selected. Include database and other required recovery material, controlled destinations, failure visibility and integrity verification. The current SQLite development environment is not evidence of a working MySQL backup/restore process.

Acceptance: an authorized operator can restore a test backup to an isolated environment, reconcile required records and relationships, verify application access, and record recovery time and recoverable data age. Do not overwrite an existing database during a demonstration. Production restore/cutover requires its own concrete, reviewed procedure.

## Tests required for each slice

- Server-enforced role and record access, including reassignment/revocation and direct endpoint calls.
- Validation, retry/idempotency, concurrent changes, transactions and audit rollback.
- Donation-count reconciliation; permitted stock calculations and expiry-boundary cases using a controlled clock.
- Restricted data omitted from public responses, broad summaries, logs and unauthorized exports.
- Connected frontend loading, error, empty and permission-denied states; mobile/keyboard review and a live browser workflow.
- Migrations and feature tests against the intended MySQL configuration before claiming MySQL verification.
- Actual backup/restore and alert scheduler/delivery tests where those capabilities are introduced.

The [local testing guide](../frontend/TESTING.md) remains the checklist for features implemented now. Add new acceptance checks when each slice is delivered; the presence of this plan does not make any proposed feature pass.

## Existing boundaries

The [foundation decisions](backend/FOUNDATION.md) record the earlier milestone; [OPERATIONS.md](backend/OPERATIONS.md) supersedes its access matrix. The [master prompt](../IT-SIA%20-%20Synthesized-Project/Master%20Development%20Prompt%20-%20Blood%20Repository%20System.md) continues to require explicit decisions/configuration for unresolved rules. Additive migrations preserved existing donor/account records; the documented creator-assignment backfill was applied.
