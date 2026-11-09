# Connected improvements: use and verification

Delivered for local SQLite development on 9 November 2026. Open http://127.0.0.1:8000/workspace after starting Laravel. The independent public/staff demo at `/` remains fictional.

## Start and migrate

Additive migrations have been applied to this workspace without resetting existing records. On another checkout, from the project root:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File '.\scripts\backend.ps1' -Action setup
powershell -NoProfile -ExecutionPolicy Bypass -File '.\scripts\backend.ps1' -Action serve
```

Existing records are assigned to their original creator, without clinical grants. New registrations inherit the creator assignment under their Red Cross-provisioned create permission. Other chapter-wide donor access is removed. Administrators manage further access through the reference-only Staff assignments queue. Sign out/in or use Refresh access after upgrading to refresh navigation.

## Screens and access

| Workspace route | Purpose |
| --- | --- |
| `#/donors` | Assigned donor search/profile and recorded completed-donation count |
| `#/assignments` | Red Cross Administrator reference-only queue, without unassigned donor names |
| `#/assignments/{donor UUID}` | Grant/revoke assignment and separate clinical actions |
| `#/donations/{donor UUID}` | Assigned donation history, date/state filters, correction and voiding |
| `#/inventory` | Inventory staff search/filters, entered expiry order and mobile cards |
| `#/inventory/{unit UUID}` | Unit history and controlled verification/quarantine/spoil/release/discard |
| `#/alerts` | Recipient-scoped shortage episodes, awareness and resolution |
| `#/stock-rules` | Red Cross Administrator thresholds and responsible recipients |
| `#/catalog` | Administrator laboratory field definitions; no patient results |
| `#/clinical/{donor UUID}?category=screening` | Assigned screening/laboratory/eligibility grants and revision history |
| `#/backups` | System Administrator encrypted snapshots and isolated recovery |

On mobile, Workspace guide links to permitted operational pages. Desktop tables have corresponding mobile cards. Entry dialogs use review-before-save, protect unsaved entries and retain drafts/recording keys after unconfirmed requests. Conflicts require a fresh record before preparing another correction. No write is retried automatically.

## Assignments and donations

1. As Red Cross Administrator, open Staff assignments and select a donor reference. Grant an active operational worker access, choose only their authorized clinical actions, add a reason, review and save.
2. As that worker, confirm the donor appears. Open Donation history from the profile and record an actual completed documentary event with a unique reference, date and collection location.
3. Reload to verify persistence and count. Correct or void the event with a reason. Voided events remain visible, stop contributing to the count and cannot be reactivated.
4. Revoke the assignment as administrator. Subsequent donor/history/clinical requests must be denied. Re-grant retains an increasing version, protecting against stale forms.

Counts describe recorded completed events, not guaranteed lifetime history. No duplicate-person reconciliation or medical donation interval is inferred. Administrators do not bypass assignment checks for patient details.

## Inventory and shortage alerts

1. As Red Cross Administrator or Personnel, register a fictional bag with recorded blood type, approved component/location labels, collection timestamp and entered expiry timestamp. Form times use Philippine time; no shelf-life calculation/default expiry is supplied.
2. Confirm the unit starts Pending and is absent from Available. Record authorized verification with its explicit confirmation and reason.
3. Register a matching verified unit with a later expiry. Available results must list the earlier expiry first. Ordering does not authorize suitability or release.
4. Mark a unit Spoiled. Its text state remains visible and it leaves usable stock. Pending/quarantined/spoiled/expired/released/discarded units are excluded from Available. Stale or repeated terminal actions fail.
5. As administrator, configure an approved threshold, exact blood type/component/location and responsible inventory staff. No threshold is seeded. Zero usable stock is out-of-stock; a positive count at or below the threshold is low stock.
6. As a recipient, acknowledge an alert. Awareness does not resolve the shortage. Replenishment above threshold resolves it; a later shortage opens another episode.

The workspace notice checks unacknowledged open alerts every 60 seconds while an inventory user is online and the page is visible, and after acknowledgement/relevant stock changes. Refresh failure is explicit. No email/SMS is sent.

For expiry reconciliation without a user write, run the scheduler in a separate terminal from the project root:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File '.\scripts\backend.ps1' -Action schedule
```

The helper uses the portable runtime when available, otherwise compatible PHP on PATH, and enters the backend directory before starting the worker. Keep the terminal open during local testing; stop with Ctrl+C. Stock evaluation runs every minute. Administrators can also use Reconcile now. Production needs its own scheduler configuration.

## Restricted clinical records

1. Configure the approved Laboratory catalog as Red Cross Administrator. It starts empty. Definitions have stable keys, display names, text/number/choice formats, optional units/reference text, and required/active settings. No panel, reference range or diagnostic interpretation is supplied.
2. Grant separate category View/Write/Review permissions on the donor. Review also requires View. Ordinary assignment and System Administrator access alone do not permit clinical reads.
3. From the assigned donor profile, open Restricted clinical records and enter the source reference, observation/source time, category values and notes. Review then save.
4. A second authorized assigned worker reviews the documentary source and entry. Self-review of one's latest edit is disabled by the explicit development setting.
5. Correct an entry with a reason. It returns to Recorded, clears approval and requires review again. Encrypted revision snapshots preserve definitions/provenance and correction reasons. Routine donor resources, stock alerts and general audit records omit clinical values.

Screening is staff-recorded status/notes. Eligibility is a staff decision and optional deferral date; reaching that date does not automatically establish eligibility. Laboratory entries retain their definition snapshots. No analyzer integration, attachments, automatic diagnosis or automatic eligibility/release rule is implemented.

## Encrypted backup and isolated recovery

1. As System Administrator, open Backup & recovery and create an encrypted snapshot with a reason. After an interrupted response, check run status or retry the unchanged entry. Failed runs are explicit.
2. For a completed snapshot, select Restore isolated copy, read the notice, confirm and review. The server creates a new private copy and checks integrity, relationships and snapshot counts. It never replaces the running database.
3. Check the verified timestamp and copy reference. Files are under `backend/storage/app/private/repository-backups` and `repository-restores`, outside Git/public assets. Restored copies contain private database data; restrict filesystem access. Keep the original application key separately to recover backups and encrypted clinical entries.

Set `REPOSITORY_BACKUP_AT=HH:MM` in the ignored local `.env` only for an approved daily Philippine-time schedule, then run the scheduler. Blank means no configured daily backup. Snapshot capacity is explicitly limited to 16 MiB for this synchronous local adapter. CLI commands `repository:backup` and `repository:restore-copy {backup UUID}` support the same workflow for authorized server operators.

Production cutover, retention deletion, key escrow, offsite storage, recovery objectives, MySQL backup/restore and large-database streaming remain deployment-specific work. Code and environment secrets are separate recovery responsibilities.

## Verification

```powershell
npm.cmd ci --ignore-scripts --no-audit --no-fund
npm.cmd run test:all
```

Tests cover assignment isolation, grants/revocation, donation reconciliation, retry/version protection, expiry boundaries, stock transitions, recipient alerts, encrypted clinical revisions/review, actual snapshot/recovery and tamper rejection, and real HTTP cookies/CSRF. DOM tests exercise the connected screens, form review, uncertain drafts, clinical controls, alert acknowledgement and session clearing.

Visual/browser/device/screen-reader review remains unverified: the native/browser automation hosts were unavailable. DOM tests are not pixel rendering or formal accessibility certification. MySQL and production hosting/recovery are not claimed.

See [testing guide](TESTING.md), [current decisions/contracts](../docs/backend/OPERATIONS.md) and [OpenAPI](../docs/backend/openapi.yaml).
