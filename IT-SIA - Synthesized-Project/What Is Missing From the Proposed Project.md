# What Is Missing From the Proposed Project

## Davao del Sur Blood Repository and Donor Monitoring System

**Review purpose:** Identify the information, decisions, documents, controls, and proof still needed before the project can safely move from proposal to development.

**Review basis:** The 15 professor-provided PDFs in `IT SIA - Documents`, the synthesized project reference, `Proposed Project Draft.md`, and `Project Implementation Recommendations.md`.

## 1. Important Review Note

The current project has a good planning foundation. It already describes the problem, objectives, users, modules, scope, budget, timeline, change plan, communication needs, risks, and quality targets.

However, it is not yet a complete implementation specification. The documents describe what the project should achieve, but several details are still missing or inconsistent. These details must be approved before the team writes production code or promises that the system is ready for real blood-record operations.

## 2. Highest-Priority Missing Items

These items should be resolved first because they affect the project scope, budget, design, and testing.

### 2.1 Final Phase 1 functionality list

The documents need one official list of the functions that belong in Phase 1.

Still unclear:

- Is the final functionality count nine or ten?
- Which function is included in the 60% rule calculation?
- Are analytics, notifications, backup, and audit counted separately or together?
- Is donor recognition included, deferred, or removed?
- Which functions are required for launch and which are future enhancements?

**Needed:** One approved functionality matrix with function name, priority, user, input, output, and acceptance condition.

### 2.2 Final project scope

The pilot area is generally described as the Philippine Red Cross Davao del Sur Chapter with selected Barangay Health Coordinators, but the exact boundary needs written approval.

Still unclear:

- Which offices or barangays participate in the pilot?
- How many users will be registered?
- Is the system for one chapter only or several localities?
- Is the public locator limited to Davao del Sur?
- What is the official launch date and end date for the pilot?

**Needed:** A signed Phase 1 scope statement with included locations, users, records, and excluded areas.

### 2.3 Official process maps

The subsystem flowchart is useful, but it does not fully describe the real work process.

Still needed:

- Current paper-based donor registration process.
- Current donation recording process.
- Blood verification process.
- Blood release, removal, or disposal process.
- Deferral and return-to-eligibility process.
- Stock correction and reconciliation process.
- Public availability update process.
- Backup and restore process.
- Incident and error-reporting process.

**Needed:** One current-process map and one approved future-process map for every major workflow.

### 2.4 Complete data dictionary

The proposal names the records but does not define every field.

Still needed for each field:

- Field name.
- Meaning.
- Data type.
- Required or optional status.
- Allowed values.
- Example value.
- Privacy classification.
- Who may view or edit it.
- Validation rule.
- Retention period.

This is especially important for donor identity, contact details, blood type, deferral information, donation history, blood bag details, verification status, expiry date, location, and stock adjustments.

### 2.5 Acceptance criteria

The project has targets, but many features do not yet have a clear pass or fail condition.

Examples still needed:

- What proves that a donor was successfully registered?
- What proves that an eligibility change is correct?
- What proves that a stock correction is traceable?
- What exactly must the public locator display?
- When is an expiry alert considered successful?
- What must happen when an SMS fails?
- What makes a backup valid?

**Needed:** Testable acceptance criteria for every Phase 1 function.

## 3. Requirements That Need Clarification

### 3.1 Donor rules

The project mentions eligibility and deferral tracking, but the exact rules are not defined.

Clarify:

- Who decides whether a donor is eligible?
- Is eligibility entered manually or calculated by the system?
- What are the approved deferral reasons?
- Can a user override an eligibility result?
- Who approves an override?
- What information may be shown to a donor?
- How are duplicate donor profiles handled?
- Can a donor request correction of their information?

The system should support staff decisions and approved rules. It should not invent medical decisions.

### 3.2 Blood inventory rules

The project mentions FIFO, expiry monitoring, verification, stock adjustments, and active bags. The exact inventory life cycle is still missing.

Clarify:

- When does a blood bag enter the inventory?
- What makes it verified?
- What statuses can a bag have?
- Can stock be divided or combined?
- What happens when a bag is released, transferred, damaged, expired, or discarded?
- Who may correct an inventory entry?
- Is location tracked for every bag?
- What is the official rule for FIFO exceptions?
- How often is a physical count compared with the digital count?

### 3.3 Public availability rules

The public locator needs stricter rules before publication.

Clarify:

- Which blood types are shown?
- Is the number of units shown, or only available/not available?
- Who approves public information?
- How often is it refreshed?
- What happens when the information is old?
- What is shown when the system is offline?
- What exact no-reservation message is required?
- Can the public contact the chapter through the page?
- What information must never be displayed?

### 3.4 SMS rules

The proposal names Semaphor SMS but does not define the complete notification behavior.

Clarify:

- Which events send messages?
- Who receives each message?
- Who approves the message templates?
- Which language is used?
- What happens if the message is not delivered?
- How are opt-outs handled?
- Is a message log stored?
- What is the monthly SMS budget?
- What happens when the SMS service is unavailable?

### 3.5 Reports and analytics

The proposal lists useful reports, but the actual report definitions are missing.

Define for every report:

- Report name.
- User who can access it.
- Date filters.
- Data included.
- Calculation formula.
- Display format.
- Export format.
- Refresh frequency.
- Data owner who approves the result.

Do not build analytics until the organization agrees on the meaning of each number.

## 4. Missing Technical Design

### 4.1 Approved technology versions

The project mentions Laravel, PHP, and MySQL, but the exact versions are not finalized.

Needed:

- PHP version.
- Laravel version.
- MySQL version.
- Web server.
- Operating system.
- Supported browsers.
- Hosting environment.
- SSL/TLS configuration.
- SMS integration method.

### 4.2 System architecture

The flowchart shows modules, but the implementation architecture is not yet documented.

Still needed:

- Application architecture diagram.
- Database architecture.
- Hosting and network diagram.
- Storage locations.
- External service connections.
- Email or SMS failure path.
- Backup destination.
- Monitoring method.
- Deployment environments for development, testing, and production.

### 4.3 Database design

A database relationship diagram and migration plan are still needed.

The design should define:

- Tables and relationships.
- Primary and foreign keys.
- Unique rules.
- Indexes for donor and inventory searches.
- Status values.
- Historical records.
- Soft deletion or inactive-record behavior.
- Audit tables.
- Backup and restore compatibility.

### 4.4 Integration specifications

The project names Semaphor and hosting, but does not provide integration details.

Needed:

- API or service documentation.
- Authentication method.
- Required credentials and ownership.
- Request and response format.
- Timeout behavior.
- Retry behavior.
- Rate limits.
- Cost per message or service.
- Test environment.
- Vendor support contact.

### 4.5 Offline behavior

Offline operation is mentioned as a decision, but no approved behavior exists.

The team must decide:

- Is the system online-only or partly usable offline?
- Which actions are allowed without internet?
- Where is offline data stored?
- How is the device protected?
- How is data synchronized?
- How are conflicts resolved?
- Who approves conflicting records?
- What happens if a device is lost?

Do not promise offline-first operation until these rules are designed and tested.

## 5. Missing Security, Privacy, and Safety Controls

### 5.1 Privacy rules

The proposal says private donor data must be protected, but the complete privacy procedure is missing.

Needed:

- Privacy notice.
- Legal basis for collecting personal data.
- Consent or acknowledgment process.
- Data owner.
- Data protection contact.
- Retention period for each record type.
- Correction request process.
- Access request process.
- Deletion or archival process.
- Breach notification process.
- Rules for printed reports and exported files.

### 5.2 Permission matrix

Role-based access is required, but the exact permission matrix is not yet approved.

Define access for:

- System administrator.
- Red Cross administrator.
- Red Cross personnel.
- Barangay Health Coordinator.
- Report-only user.
- Technical support user.
- Public user.

For each role, specify whether it may view, create, edit, approve, export, deactivate, or restore records.

### 5.3 Security threat review

No complete threat model or security risk review is documented.

Still needed:

- Main threats.
- Sensitive data locations.
- Abuse cases.
- Account takeover risks.
- Unauthorized stock changes.
- Public data exposure risks.
- Backup theft risks.
- SMS abuse risks.
- Mitigations and owners.

### 5.4 Medical and operational safety boundary

The system must clearly state what it does not decide.

Needed:

- Written confirmation that medical eligibility decisions remain with authorized personnel.
- Written confirmation that laboratory and blood-safety verification remains manual or belongs to approved staff.
- Rule that the system cannot guarantee blood release or medical suitability.
- Procedure for correcting unsafe or incorrect information.
- Named authority for approving blood-related rules.

## 6. Missing Data Migration Plan

The current documents mention active donors, initial inventory, and possible historical records, but the migration plan is not final.

Needed:

- Exact records to migrate.
- Source paper forms or files.
- Data-cleaning rules.
- Duplicate handling.
- Missing-data handling.
- Verification responsibility.
- Migration test run.
- Final migration cut-off date.
- Approval before import.
- Rollback plan.
- Paper-record storage after migration.

The safest initial approach is to migrate only approved active donor records and verified launch inventory, then add older records in a later controlled phase.

## 7. Missing Testing and Proof

### 7.1 Test plan

The quality targets are present, but a complete test plan is not.

Needed:

- Unit tests.
- Integration tests.
- End-to-end workflow tests.
- Permission tests.
- Privacy tests.
- Security tests.
- Performance tests.
- Accessibility tests.
- Mobile-browser tests.
- Backup restoration tests.
- SMS failure tests.
- User acceptance tests.
- Regression tests after fixes.

### 7.2 Performance proof

Targets such as 500 ms donor search, 2-second public access, and 100 concurrent users need a measurement method.

Define:

- Test hardware and network.
- Test dataset size.
- Number of users.
- Request type.
- Measurement tool.
- Acceptable average and worst-case result.
- Test date.
- Person who approves the result.

### 7.3 Accessibility proof

The proposal mentions accessible forms, labels, keyboard navigation, contrast, and clear errors. It still needs a checklist and evidence.

Test:

- Keyboard-only navigation.
- Form labels.
- Focus visibility.
- Error messages.
- Color contrast.
- Text resizing.
- Mobile layout.
- Screen-reader landmarks where applicable.
- No color-only instructions.

### 7.4 Usability proof

The target of at least 4.0/5 needs a defined survey and participant plan.

Needed:

- Participant count.
- User groups.
- Tasks to perform.
- Survey questions.
- Scoring method.
- Success threshold.
- Person responsible for analyzing results.

## 8. Missing Deployment and Operations Plan

### 8.1 Deployment

Still needed:

- Deployment checklist.
- Environment variables list.
- Secret-management method.
- Database migration procedure.
- SSL setup.
- Domain and DNS ownership.
- Release approval process.
- Rollback procedure.
- Maintenance window.

### 8.2 Backup and recovery

The proposal mentions daily backups and recovery targets, but the operational procedure is incomplete.

Clarify:

- Exact backup frequency.
- Full or incremental backup.
- Backup location.
- Encryption.
- Retention period.
- Who checks backup success.
- How often restoration is tested.
- Recovery point objective.
- Recovery time objective.
- Who declares an incident.
- Who performs restoration.

### 8.3 Monitoring and support

Needed:

- Uptime monitoring.
- Error logging.
- Failed SMS monitoring.
- Failed backup alerts.
- Storage monitoring.
- User support channel.
- Incident priority levels.
- Response times.
- Escalation path.
- Maintenance ownership.

### 8.4 Handover

The project needs a formal handover package:

- Administrator guide.
- User guide.
- Technical setup guide.
- Database documentation.
- Backup guide.
- Recovery guide.
- Support contacts.
- Known limitations.
- Open issues.
- Ownership sign-off.

## 9. Missing Change and Communication Details

The documents include change-management and communication planning, but implementation details still need completion.

Needed:

- Final stakeholder list.
- One approved communication owner.
- Communication calendar.
- Message templates.
- Language plan.
- Training schedule.
- Attendance tracking.
- Feedback form.
- Resistance indicators.
- Adoption measures.
- Escalation path for repeated resistance.
- Definition of when paper records may be retired.

The two stakeholder files should also be compared and merged into one approved stakeholder-and-roles document. Duplicate documents can create conflicting responsibilities.

## 10. Missing Budget Details

The total budget is stated as **PHP 2,060,000**, but the assumptions behind each amount need to be recorded.

Still needed:

- Quantity and unit price for each hardware item.
- Hosting period and service level.
- Domain and SSL cost.
- SMS volume and price estimate.
- Development staffing assumptions.
- Training participant count.
- Travel assumptions.
- Support period included in the budget.
- Contingency rules.
- Procurement owner.
- Approval authority.
- Renewal costs after the first year.

The project should separate one-time costs from recurring costs so stakeholders know what must be funded after launch.

## 11. Missing Risk Ownership

The risk list is useful, but each risk needs an owner and a trigger.

For every risk, add:

- Risk owner.
- Likelihood.
- Impact.
- Warning sign.
- Preventive action.
- Response action.
- Due date.
- Current status.

Examples that need explicit ownership:

- Incorrect stock data.
- Privacy breach.
- Failed backup.
- Internet outage.
- Staff resistance.
- Unapproved scope growth.
- SMS service failure.
- Delayed migration.
- Lack of technical support.

## 12. Missing Traceability

The documents need one traceability table connecting the original problem to the objective, feature, test, and success measure.

Recommended columns:

| Problem | Objective | Function | Requirement | Test | Success measure | Owner |
| --- | --- | --- | --- | --- | --- | --- |
| Slow blood search | Reduce search time | Inventory search | Search returns approved stock quickly | Timed search test | Faster than 15-20 minute baseline | Project owner |
| Scattered paper records | Centralize records | Donor and inventory records | Approved fields are stored digitally | Data and workflow test | Complete active records | Data owner |
| Unverified public appeals | Improve public information | View-only locator | Show approved stock with refresh time | Public privacy test | Reduced reliance on unverified appeals | Communications owner |

Without traceability, it is difficult to prove that every stated problem is addressed.

## 13. Missing Decisions and Approvals

Before development, obtain written approval for:

1. Final Phase 1 features.
2. Final pilot locations and users.
3. Donor and inventory fields.
4. User permissions.
5. Eligibility and deferral rules.
6. Inventory status and correction rules.
7. Public locator content and refresh frequency.
8. SMS events and templates.
9. Privacy and retention rules.
10. Data migration limit.
11. Offline behavior.
12. Backup frequency and restoration schedule.
13. Technology versions and hosting.
14. Acceptance criteria.
15. Training completion requirement.
16. Paper-record retirement date.
17. Budget assumptions and recurring costs.
18. System owner after handover.

## 14. Conflicts or Areas That Need Reconciliation

The following should be compared across the PDFs and the current draft before final approval:

- Nine versus ten functionality count.
- The 60% rule and its calculation.
- Duplicate stakeholder-and-roles documents.
- Backup wording: daily backup versus the final approved schedule.
- Pilot area: Davao del Sur only versus wider localities.
- Active records only versus wider historical migration.
- Online-only behavior versus possible offline operation.
- Public refresh frequency.
- Exact Laravel and PHP versions.
- Budget category names and assumptions.
- Whether donor recognition is part of the project or deferred.
- Whether SMS is required at launch or only planned.
- Whether analytics are required at launch or can begin as basic reports.

Do not silently choose one version. Record the decision, approver, date, and reason.

## 15. Recommended Order of Work

### Step 1: Freeze the project decision list

Resolve the conflicts and obtain stakeholder approval for scope, users, features, data, privacy, and pilot location.

### Step 2: Produce the requirements package

Complete the functionality matrix, data dictionary, process maps, permission matrix, acceptance criteria, and traceability table.

### Step 3: Design before coding

Create the database design, architecture diagram, screen prototypes, public locator rules, backup plan, and migration plan.

### Step 4: Build the smallest safe Phase 1

Prioritize login and permissions, donor records, eligibility, inventory, expiry monitoring, public availability, audit history, reports, and backup.

### Step 5: Test with realistic records

Use approved test data. Test complete workflows, privacy boundaries, mobile use, performance, backup restoration, and failure paths.

### Step 6: Pilot and train

Use a limited pilot, provide simple guides, collect weekly feedback, and compare digital records with paper records during the approved transition period.

### Step 7: Approve launch and handover

Launch only after acceptance criteria, training, backup restoration, privacy checks, and ownership sign-off are complete.

## 16. Final Assessment

The project is strong as a proposal and requirements-validation document, but it is not yet ready for full production development.

The largest missing pieces are:

- Final approved scope.
- Complete field-level requirements.
- Permission and privacy rules.
- Detailed donor and inventory workflows.
- Acceptance criteria and test evidence.
- Database and deployment design.
- Migration, backup, recovery, and support procedures.
- Reconciled stakeholder, budget, functionality, and timeline documents.

**Recommendation:** Keep the current proposal as the high-level project plan. Add the missing requirements package before implementation begins. This prevents scope confusion, protects donor privacy, and makes the finished system testable and maintainable.
