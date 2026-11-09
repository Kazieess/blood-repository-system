# Proposed Project Presentation

## Presentation Title

### Project Identity

**Proposal for requirements validation**  
**Pilot area:** Philippine Red Cross Davao del Sur Chapter  
**Platform:** Responsive Laravel and MySQL web application

---

## Slide 1: Title

### Davao del Sur Blood Repository and Donor Monitoring System

A centralized platform for faster blood availability checks, safer donor records, and better inventory monitoring.

**Presented by:** [Team Name]  
**Course/Section:** [Course and Section]  
**Date:** [Presentation Date]

**Visual:** Clean blood-drop or repository symbol. Use restrained red, white, charcoal, and soft neutral tones.

---

## Slide 2: The Current Problem

- Blood inventory searches take approximately **15-20 minutes**.
- Donor and donation records remain scattered across paper logs.
- Emergency blood appeals may rely on unverified social-media posts.
- Staff lack one reliable view of donor eligibility, stock, and expiry risk.

**Core problem:** Important blood information is slow to find, difficult to verify, and hard to maintain.

---

## Slide 3: Proposed Solution

A responsive web system that provides one reliable source for:

- Donor profiles and donation history
- Eligibility and deferral tracking
- Blood inventory and expiry monitoring
- Controlled staff access
- Public, view-only availability information
- Reports, analytics, alerts, backups, and audit records

**Expected result:** Faster decisions with more reliable information.

---

## Slide 4: Project Objectives

1. Centralize donor and blood-stock records.
2. Reduce blood availability search time.
3. Improve FIFO stock handling and expiry monitoring.
4. Protect private records through role-based access.
5. Support evidence-based inventory and donor planning.
6. Improve adoption through training, feedback, and staged rollout.

---

## Slide 5: Target Users

### Operational Users

- Red Cross Administration
- Red Cross Personnel
- Barangay Health Coordinators
- IT and Development Team

### Information Users

- Donors
- Blood seekers
- External suppliers and regulators

**Access principle:** Staff manage records. Public users view availability only.

---

## Slide 6: Core System Modules

### Donor Management

Profiles, donation history, eligibility, deferrals, and future donation dates.

### Blood Inventory

Blood types, active bags, stock adjustments, verification status, FIFO handling, and expiry data.

### Access Control

Role-based permissions for staff, coordinators, administrators, and public users.

### Analytics

Stock turnover, donor trends, shortages, expiry exposure, and planning reports.

### Notifications and Recovery

Approved SMS alerts, scheduled backups, audit records, and restoration procedures.

---

## Slide 7: Public Availability Locator

The public locator will:

- Show current availability by blood type.
- Display the last refresh timestamp.
- Require no staff login for view-only access.
- Hide donor information and protected operational records.
- Clearly state that availability is not a reservation or guaranteed release.

**Purpose:** Replace uncertain public appeals with controlled availability information.

---

## Slide 8: System Flow

1. Authorized staff manage records through the web application.
2. The web application connects to authentication and role access.
3. Staff manage donor eligibility and blood inventory.
4. Inventory sends current stock to the public availability locator.
5. Donor management sends approved donor reminders.
6. Inventory sends expiry and shortage alerts.
7. Analytics and reports support operational decisions.
8. Backup and audit records protect system continuity.

**Visual:** Use the companion file `Blood Repository System Flowchart.md` as the official subsystem interaction diagram.

---

## Slide 9: Phase 1 Scope

### Included

- Davao del Sur Chapter pilot
- Designated Barangay Health Coordinators
- Responsive browser-based system
- Active eligible donor records
- Launch blood inventory
- Manual laboratory and blood-safety verification
- Public view-only availability

### Excluded

- National or multi-chapter integration
- Laboratory system integration
- Blood analyzer integration
- Online reservations or payments
- Native mobile applications
- Large-scale historical paper-record digitization

---

## Slide 10: Quality Targets

- **99.9%** service availability target
- Public locator under **2 seconds** on 3G/4G
- Donor search under **500 ms** with 100 concurrent users
- Daily backup with tested restoration
- Recovery point objective of **24 hours or less**
- Recovery time objective of **4 hours or less**
- At least **80%** automated test coverage
- At least **4.0/5** usability score during UAT
- Accessible, responsive, and mobile-friendly interface

**Note:** Final technical targets require validation against the actual Laravel deployment environment.

---

## Slide 11: Implementation Plan

| Period | Focus |
| --- | --- |
| Aug-Sep 2026 | Requirements validation and workflow mapping |
| Oct-Nov 2026 | Database design, architecture, and prototypes |
| Dec 2026-Feb 2027 | Laravel and MySQL development |
| Mar-Apr 2027 | Testing and user acceptance testing |
| May-Jun 2027 | Training, approved migration, and pilot deployment |
| Jul 2027 onward | Evaluation, maintenance, and handover |

**Change approach:** Training, bilingual materials, two-week dual processing, peer coaching, weekly feedback, and 60-day hyper-care.

---

## Slide 12: Budget

### Total one-year budget: PHP 2,060,000

| Category | Allocation |
| --- | ---: |
| Development and change management | PHP 1,230,000 |
| Training | PHP 150,000 |
| Hardware and infrastructure | PHP 240,000 |
| Software, hosting, and SMS tools | PHP 95,000 |
| Consultation, travel, and support | PHP 145,000 |
| Contingency | PHP 120,000 |

**Visual:** Use one clean horizontal bar or proportion chart. Do not use a crowded 3D chart.

---

## Slide 13: Risks and Mitigations

- **Staff resistance:** Training, sandbox practice, peer coaching, and feedback.
- **Low digital literacy:** Bilingual guides and coordinator support.
- **Paper records continue:** Defined dual-processing period and retirement date.
- **Connectivity or power failure:** UPS units and recovery procedures.
- **Incorrect inventory data:** Validation, permissions, audit history, backups, and reconciliation.

---

## Slide 14: Success Measures

- 90% reduction in reliance on unverified social-media blood appeals.
- 15% reduction in blood-product waste.
- Faster availability searches than the 15-20 minute baseline.
- Complete digital records for active donors and launch inventory.
- 100% training completion for core users and coordinators.
- At least 90% UAT workflow pass rate.
- Paper-ledger retirement by Day 30 after launch, subject to approval.
- Full operational handover by Day 60.

---

## Slide 15: Decisions Before Development

Stakeholders must approve:

1. Final functionality count and priority calculation.
2. Backup frequency and restoration schedule.
3. Historical data migration limit.
4. Geographic scope of Phase 1.
5. Offline behavior and synchronization rules.
6. Public inventory refresh frequency.
7. Privacy, retention, audit, and ownership rules.
8. Existing Laravel and PHP minor versions.

**Decision needed:** Approve the proposal for requirements validation.

---

## Slide 16: Closing

### Faster Information. Safer Records. Better Blood-Inventory Decisions

The proposed system gives Davao del Sur a practical foundation for reliable donor coordination and blood-stock visibility.

**Next step:** Validate requirements with stakeholders before development.

---

## Figma Make Presentation Prompt

Copy this prompt into Figma Make:

```text
Create a polished 16:9 academic proposal deck titled "Blood Repository and Donor Monitoring System".

Create 16 concise slides:
1 Title; 2 Current Problem; 3 Proposed Solution; 4 Objectives; 5 Users; 6 Core Modules; 7 Public Availability Locator; 8 System Flow; 9 Phase 1 Scope; 10 Quality Targets; 11 Implementation Plan; 12 Budget; 13 Risks; 14 Success Measures; 15 Decisions Before Development; 16 Closing.

Use only facts here. Do not invent features, statistics, integrations, or medical capabilities.

Style: clean institutional healthcare technology. Warm white, charcoal, deep blood red, muted rose, gray borders. No purple, neon, rainbow, glassmorphism, 3D, blobs, emoji, or stock photos. Use whitespace, left alignment, short text, one visual per slide, five bullets maximum.

Use editable vectors, line icons, clear diagrams, one timeline, and one budget bar chart. Layout: System Users; Web Application (Central Interface); Core Management Modules; Public and Support Services. Labels: Authorized Staff and Barangay Health Coordinators, Public Blood Seekers, Authentication and Role Access, Donor Management and Eligibility, Blood Inventory and Expiry Monitoring, Analytics and Reports, Public Availability Locator, SMS Notifications and Alerts, Backup and Audit Records. Arrows: Manage Records, View Availability, Current Stock, Donor Reminders, Expiry Alerts, Audit and Backup. Separate public access from staff operations. Add slide number and short title footer.

Typography: Plus Jakarta Sans, DM Sans, or IBM Plex Sans. Clear hierarchy, readable body text.

Accessibility: WCAG AA contrast, chart labels, no color-only meaning.

Preserve exact figures: 15-20 minutes, PHP 2,060,000, 99.9%, 2 seconds, 500 ms, 80%, 4.0/5, 90%, 15%. Label unresolved items "Decisions Before Development". State "proposal for requirements validation". End with stakeholder validation. Do not present proposal as approved.
```

---

## Logo Prompt for Figma Make or an Image Generator

Copy this prompt:

```text
Design a professional editable vector logo for "Blood Repository and Donor Monitoring System".

Create one simple, memorable symbol combining a clean blood drop, secure repository, and connected community. Integrate a subtle shield or rounded database layer inside/behind the drop. Add a small connection motif only if readable. Communicate trust, organized records, blood availability, and public service.

Style: flat vector, minimal geometric construction, calm, trustworthy healthcare technology identity. Clear silhouette at favicon and presentation sizes. No realistic blood, wounds, needles, hospital clichés, frightening imagery, complex gradients, 3D, shadows, mockups, tiny details, or text inside the symbol. Do not use a medical cross as the main symbol.

Colors: deep blood red accent; charcoal or deep navy structure; warm white background. Provide full-color, dark one-color, light one-color, and transparent versions. No purple, neon, rainbow, or excessive colors.

Optional wordmark: use a clean geometric sans-serif similar to Plus Jakarta Sans, DM Sans, or IBM Plex Sans. Use this two-line lockup:
"Blood Repository"
"and Donor Monitoring System"
Keep wordmark secondary. No condensed, script, playful, or decorative lettering.

Output symbol-only, horizontal lockup, stacked lockup, and favicon/app-icon versions. Use SVG-style editable shapes, transparent background, and strong legibility at 24px, 48px, and 256px.
```

---

## Presentation Rules

- Keep slides concise. Explain details verbally.
- Do not show every non-functional requirement on one slide.
- Use the companion flowchart for architecture discussion.
- Present unresolved requirements as decisions, not completed features.
- Replace bracketed placeholders before presenting.
