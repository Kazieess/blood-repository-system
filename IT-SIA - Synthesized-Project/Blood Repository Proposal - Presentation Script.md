# Blood Repository Proposal

## Presentation Script

**Presenters:** Cindy, Airon, Erich, Kyla, and Carlze

**Presentation title:** Blood Repository and Donor Monitoring System

## Speaker Assignment

| Speaker | Slides | Main topic |
| --- | --- | --- |
| Carlze | 1-2 | Introduction and current problem |
| Airon | 3-4 | Proposed solution, objectives, and targets |
| Cindy | 5-6 | Users, access, system flow, and core modules |
| Erich | 7-8 | Public locator and implementation timeline |
| Kyla | 9-10 | Core modules, conclusion, and questions |

---

## Cindy: Slides 1-2

### Slide 1: Title

Good day, everyone.

We are presenting our proposed project, the **Blood Repository and Donor Monitoring System**.

This project is a proposal for requirements validation. It focuses on blood repository management, donor monitoring, public blood availability visibility, support alerts, and audit-ready records.

The proposed system is intended to help organize blood-related information and make it easier for authorized users to manage records.

### Slide 2: Current Problem

At present, blood availability information needs a clear central reference.

Donor records and eligibility status also need more organized monitoring. Inventory and expiry details must be consistently visible to authorized users.

Another concern is that public blood-seeking information may come from different or unverified sources. This can make it difficult for people to know which information is current.

The main problem is that important information is not always easy to find, verify, or maintain. Our proposed system is intended to address this problem through one organized platform.

**Transition to Airon:**

To explain how our project can respond to these problems, I will now turn the presentation over to Airon.

---

## Airon: Slides 3-4

### Slide 3: Proposed Solution Overview

Thank you, Zie.

Our proposed solution is a responsive web-based system for blood repository and donor monitoring.

It will provide a central place for authorized users to manage blood repository records, donor information, inventory details, and related reports.

The system will also support public visibility of blood availability through a separate view-only locator. This means that public users may check availability, but they will not access staff operations or private donor records.

The system is designed to support better record management, donor monitoring, inventory visibility, notifications, backup, and audit records.

### Slide 4: Objectives and Performance Targets

The main objectives are to centralize authorized access to blood repository records, monitor donor information and eligibility status, and track inventory and expiry information.

The system will also provide a public availability locator without exposing staff operations. In addition, it will support reporting, notifications, backup, and audit readiness.

The project will not only focus on building features. We also need to check whether the system can meet practical performance and usability targets during testing.

These targets include reliable access, fast public availability viewing, responsive donor searches, tested backups, usability testing, and an accessible mobile-friendly interface.

**Transition to Erich:**

Now that we have discussed the proposed solution and its objectives, Erich will explain who will use the system and how the main parts will work together.

---

## Erich: Slides 5-6

### Slide 5: Users and Role Access Model

Thank you, Airon.

The system separates authorized operations from public viewing.

Authorized staff and Barangay Health Coordinators will manage records through authenticated, role-based access. This means that users will only receive the permissions needed for their responsibilities.

Public blood seekers will use a separate availability locator. They can view blood availability, but they cannot enter staff operations, change inventory, or view private donor information.

This separation is important because blood records and donor information must be handled carefully.

### Slide 6: System Flow and Core Modules

The system flow begins with the users.

Authorized staff and Barangay Health Coordinators manage records through the central web application. The application connects to authentication and role access.

The main management modules are donor management and eligibility, blood inventory and expiry monitoring, and analytics and reports.

The supporting services include the public availability locator, SMS notifications and alerts, and backup and audit records.

The important interactions are:

- Authorized users manage records.
- Public users view availability.
- Inventory provides current stock information.
- Donor management supports donor reminders.
- Inventory supports expiry alerts.
- Backup and audit records help protect system continuity.

**Transition to Kyla:**

The next part will focus on the public availability locator and the planned implementation stages. Kyla will continue the presentation.

---

## Kyla: Slides 7-8

### Slide 7: Public Availability Locator

Thank you, Erich.

The public availability locator is designed for blood seekers who need to view current availability information.

The information path is simple: public blood seekers open the public availability locator, and the locator displays the approved current stock information.

Public access remains separate from staff operations. The public locator will not expose donor records or protected staff information.

This feature is still part of the proposal for requirements validation. The stakeholders must still confirm the exact information to display, how often it should be updated, and the wording that explains that availability is not a reservation or a guarantee of release.

### Slide 8: Implementation Plan Timeline

The project will be completed in stages.

First is requirements validation, where the team confirms the scope, unresolved decisions, and success measures.

Next is design and structure, where the screens, module structure, and access separation are finalized.

After that, the team will build the core modules for authorized access, donor management, inventory, and reporting.

The next stage is testing and validation. This is where the team checks the targets and prepares for stakeholder review.

Finally, the support services will be added or completed, including the public locator, SMS alerts, backup, and audit records.

The project should move to the next stage only after the previous stage has been reviewed and accepted.

**Transition to Carlze:**

For the final part, Carlze will summarize the core modules and close the presentation.

---

## Carlze: Slides 9-10

### Slide 9: Core Modules

Thank you, Kyla.

The core modules of the proposed system are authentication and role access, donor management and eligibility, blood inventory and expiry monitoring, and analytics and reports.

Authentication and role access will help control which users can enter and manage the system.

Donor management will organize donor records and eligibility status.

Inventory monitoring will help authorized users view current stock and expiry information.

Analytics and reports will provide useful views of system records for planning and decision-making.

These modules form the core of the system. The public locator, notifications, backup, and audit records support the main operations.

### Slide 10: Closing

To conclude, the proposed Blood Repository and Donor Monitoring System aims to make blood-related information more organized, easier to monitor, and more accessible to the right users.

It separates staff operations from public viewing, supports donor and inventory monitoring, and provides a foundation for reports, alerts, backup, and audit records.

However, this is still a proposal for requirements validation. Before development begins, the stakeholders must confirm the final scope, user permissions, data rules, public locator details, technical targets, and implementation plan.

Thank you for your time and consideration.

We are now ready for your questions and suggestions.

---

## Optional Q&A Responses

### What is the main purpose of the system?

The main purpose is to organize donor and blood inventory information in one system and make approved availability information easier to access.

### Can the public edit the blood inventory?

No. Public users have view-only access through the public availability locator.

### Will the system make medical decisions?

No. Medical and blood-safety decisions remain with authorized personnel. The system only supports record management and monitoring.

### Is the project already final and approved?

No. It is a proposal for requirements validation. The unresolved requirements must be approved before development.

### What should happen next?

The next step is to validate the requirements with the stakeholders, finalize the scope and permissions, and then prepare the detailed design and testing plan.

## Delivery Notes

- Speak naturally instead of reading every word too quickly.
- Do not add features that are not shown in the presentation.
- Keep the phrase **proposal for requirements validation** when explaining the project status.
- Emphasize that public access is view-only.
- Pause briefly after the system-flow explanation so the audience can study the diagram.
- During questions, answer only from the approved project scope.
