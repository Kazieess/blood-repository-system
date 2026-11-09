# Proposed Project Plan

## Davao del Sur Blood Repository and Donor Monitoring System

**Document status:** Proposal for requirements validation  
**Proposed pilot area:** Philippine Red Cross Davao del Sur Chapter  
**Proposed platform:** Responsive Laravel and MySQL web application

## 1. Executive Summary

This proposal presents a responsive Laravel and MySQL web platform for the Philippine Red Cross Davao del Sur Chapter and designated Barangay Health Coordinators. The system will provide one reliable source for donor eligibility, donation history, blood inventory, stock adjustments, and public blood availability information.

The project addresses three documented problems: blood inventory searches take approximately 15-20 minutes, donor and donation records are scattered across paper logs, and patients and families rely on unverified social-media appeals during emergencies. The proposed improvement combines faster lookup, reliable records, expiry monitoring, controlled access, and a planned transition away from parallel paper records.

This document is for requirements validation. Incomplete or inconsistent requirements are listed in Section 13 for approval before development.

## 2. Background and Problem Statement

The current process makes three time-sensitive questions difficult to answer:

1. Which blood types and active blood bags are currently available?
2. Which donors are eligible, deferred, or due for a future donation?
3. Which stock is approaching expiry and needs priority handling?

The proposed system will reduce manual searching and provide current, controlled information to authorized staff through a view-only public availability locator. Public availability will be presented as information, not as a reservation or guaranteed release.

## 3. General Objective

To improve the speed, accuracy, security, and operational reliability of donor and blood-inventory management for the initial Davao del Sur pilot through a responsive Laravel web application.

## 4. Specific Objectives

- Centralize active donor, donation, eligibility, deferral, and blood-stock records.
- Reduce the time required to search blood availability and donor history.
- Support FIFO-based stock handling and expiry alerts.
- Provide a public, view-only blood availability locator without exposing private donor data.
- Enforce role-based access for Red Cross personnel and trained Barangay Health Coordinators.
- Provide administrative analytics for stock turnover, donor trends, shortages, and planning.
- Send donor reminders, urgent shortage notices, and expiry-related alerts through the approved SMS gateway.
- Protect records with validation, audit-friendly updates, encryption, backups, and recovery procedures.
- Support training, pilot adoption, dual processing, feedback, and formal retirement of parallel paper records.

## 5. Users and Responsibilities

| User or stakeholder | Main responsibility |
| --- | --- |
| Red Cross Administration | Sponsorship, authorization, budget, policy governance, and operational ownership |
| IT/Development Team | Architecture, database, security, deployment, maintenance, and support |
| Red Cross Personnel | Donor and inventory entry, stock accuracy, verification, and reporting |
| Barangay Health Coordinators | Field registration, donor recruitment, local data entry, and community adoption |
| Donors | Provide accurate information and participate in donation activities |
| Blood seekers | Use the public locator to view current availability information |
| External suppliers and regulators | Hosting, hardware, privacy compliance, support, and audits |

## 6. Functional Scope

### Core Modules

1. **Centralized Blood Stock Repository**
   - Record blood type, active blood bags, stock adjustments, verification status, and expiry data.
   - Keep a traceable history of additions, corrections, and removals.

2. **Public Blood Availability Locator**
   - Provide view-only availability information without login.
   - Display a refresh timestamp and a clear statement that availability is not a reservation or guaranteed release.

3. **Donor Profile and History Repository**
   - Store active donor profiles, donation frequency, eligibility status, and donation history.
   - Track automatic deferral dates and future eligibility.

4. **Role-Based Access Control**
   - Restrict create, update, and administrative actions to authorized roles.
   - Separate public viewing from protected operational records.

5. **Backup and Recovery**
   - Run scheduled backups, verify backup integrity, and document restoration procedures.

6. **Administrative Analytics Dashboard**
   - Show inventory turnover, donor trends, shortages, expiry exposure, and planning data.

7. **SMS Notifications and Expiry Alerts**
   - Send approved donor reminders, urgent shortage notices, and expiry monitoring alerts through the Semaphor SMS gateway.

### Deferred Feature

Donor recognition or milestone badges are deferred. They must not delay delivery of the core operational modules.

## 7. Non-Functional Requirements

The following targets are carried forward from the project reference. The team must verify them during testing:

- 99.9% service availability target.
- Public locator load time below 2 seconds on 3G/4G conditions.
- Donor search response below 500 ms with 100 concurrent users.
- AES-256 encryption for data at rest, subject to deployment verification.
- TLS 1.3 for data in transit, subject to hosting configuration verification.
- Bcrypt password hashing.
- Zero critical vulnerabilities in automated security scans.
- Daily backups, with an agreed backup schedule and documented restoration test.
- Recovery point objective of 24 hours or less.
- Recovery time objective of 4 hours or less.
- At least 80% automated test coverage for the agreed application scope.
- At least 4.0/5 usability score during UAT.
- 90% of non-technical users completing registration within 3 minutes.
- Capacity target of 50,000 donor records and 1,000 blood-stock entries.
- Responsive mobile-browser access for field coordinators.
- Accessible forms, labels, keyboard navigation, readable contrast, and clear validation feedback.

## 8. Scope Boundaries

### Included in Phase 1

- Philippine Red Cross Davao del Sur Chapter.
- Designated Barangay Health Coordinators.
- Responsive web application accessed through a browser.
- Active eligible donor records and launch inventory.
- Manual verification of laboratory and blood-safety information.
- View-only public availability access.

### Excluded from Phase 1

- Integration with other Red Cross chapters or national registries.
- Laboratory Information Management System integration.
- Blood analyzer or screening-device integration.
- Financial transactions or payment processing.
- Direct online blood reservations.
- Public inventory modification.
- Native Android or iOS applications.
- Bulk digitization of decades-old paper records.

## 9. Implementation Approach

The project will combine SDLC activities with organizational change management. The change plan will use role-specific training, bilingual Bisaya and Tagalog materials where appropriate, a two-week dual-processing period, Digital Buddy peer coaching, weekly feedback sessions, and a 60-day post-launch hyper-care period.

### Target Timeline

| Period | Major output |
| --- | --- |
| August-September 2026 | Planning, requirements validation, and workflow mapping |
| October-November 2026 | Database design, architecture, and prototypes |
| December 2026-February 2027 | Laravel and MySQL development |
| March-April 2027 | Unit testing, integration testing, and UAT |
| May-June 2027 | Training, approved data migration, and pilot deployment |
| July 2027 onward | Evaluation, maintenance, and paper-ledger retirement |

## 10. Budget Reference

The documented one-year budget is **PHP 2,060,000**.

| Category | Allocation |
| --- | ---: |
| Development and change-management team | PHP 1,230,000 |
| Training | PHP 150,000 |
| Hardware and infrastructure | PHP 240,000 |
| Software, hosting, and SMS tools | PHP 95,000 |
| Consultation, travel, and support | PHP 145,000 |
| Contingency | PHP 120,000 |

The hardware allocation includes two enterprise laptops, ten ruggedized Android tablets, UPS units, routers, and local storage.

## 11. Key Risks and Mitigations

| Risk | Proposed mitigation |
| --- | --- |
| Staff resistance and technology anxiety | Role-specific training, sandbox practice, peer coaching, and feedback sessions |
| Low digital literacy | Bilingual materials, guided registration, and local coordinator support |
| Continued shadow paper records | Defined dual-processing period followed by an approved retirement date |
| Connectivity and power failures | UPS units, recovery procedures, and an explicitly approved offline policy |
| Incorrect or corrupted inventory data | Validation, role-based updates, audit-friendly history, backups, and reconciliation |
| Small technical team | Documentation, support procedures, training, and operational handover |
| Public misunderstanding of availability | Prominent view-only wording, refresh timestamp, and no reservation function |

## 12. Success Measures

- 90% reduction in reliance on unverified social-media blood appeals.
- 15% reduction in blood-product waste.
- Faster blood-availability searches than the current 15-20 minute baseline.
- Complete digital records for active donor and launch-inventory data.
- 100% core staff and Barangay Health Coordinator training completion.
- At least 90% UAT workflow passing rate.
- Zero critical downtime during the hyper-care period.
- Elimination of parallel paper records by Day 30 after launch, subject to approval.
- Full operational handover by Day 60.

## 13. Decisions Required Before Development

The project team and stakeholders must resolve these items during requirements validation:

1. Confirm whether the approved functionality count is nine or ten and correct the 60% rule calculation.
2. Choose one backup frequency and align it with the non-functional requirement.
3. Define migration precisely: active eligible donors and baseline inventory only, or a larger historical set.
4. Confirm whether the first phase is limited to Davao del Sur or includes wider Red Cross localities.
5. Define offline behavior, synchronization, conflict handling, and local-device security before promising offline-first operation.
6. Define public inventory refresh frequency and the wording that availability does not guarantee reservation or release.
7. Confirm privacy, retention, audit, and operational ownership procedures.
8. Confirm the Laravel and PHP minor versions from the existing project before implementation-specific code is written.

## 14. Recommendation

Approve this document as a requirements-validation proposal, not as a final implementation specification. After the decisions above are approved, the team can produce the final scope, functionality matrix, data dictionary, acceptance criteria, test plan, and Laravel implementation plan.
