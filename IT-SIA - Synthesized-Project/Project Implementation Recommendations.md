# Project Implementation Recommendations

## Davao del Sur Blood Repository and Donor Monitoring System

This document gives practical recommendations for completing the proposed project. The goal is to build a useful and safe system without making the first version too large.

## 1. Start with Requirements Validation

Before writing code, meet with the Red Cross Administration, Red Cross Personnel, Barangay Health Coordinators, and the IT/Development Team.

Confirm the following:

- What information must be stored for donors.
- What information must be stored for blood inventory.
- Who may create, view, edit, approve, and delete records.
- Which reports are needed every day, every week, and every month.
- How often the public availability page should be updated.
- What information must never be shown to the public.
- What should happen when the internet or electricity is unavailable.
- How long records should be kept.
- Who owns and approves the information.

**Recommendation:** Do not begin full development until these rules are written and approved.

## 2. Keep the First Version Focused

The first version should focus on the most important work:

1. User login and access control.
2. Donor profiles and donation history.
3. Donor eligibility and deferral dates.
4. Blood inventory and expiry dates.
5. Stock changes with a reason and user record.
6. Public view-only blood availability.
7. Basic reports and alerts.
8. Backup and activity records.

Do not add online reservations, payment features, native mobile applications, laboratory device connections, or national system connections in the first version.

Donor badges or recognition features should remain optional and should not delay the main system.

## 3. Assign Clear Responsibilities

| Role | Suggested responsibility |
| --- | --- |
| Project sponsor | Approves direction, budget, and major decisions |
| Project owner | Coordinates the organization and confirms business rules |
| Project manager | Tracks tasks, schedule, risks, and communication |
| Database lead | Plans tables, relationships, backups, and data quality |
| Backend developer | Builds rules, login, permissions, reports, and alerts |
| Frontend developer | Builds pages, forms, responsive layouts, and public views |
| Tester or QA lead | Checks functions, security, speed, and user workflows |
| Change or training lead | Prepares guides, training, feedback, and adoption support |
| Data owner | Approves who may add, correct, or remove official records |

One person may have more than one role in a student project, but every responsibility should still have an assigned owner.

## 4. Prepare the Documents Before Coding

Create these documents first:

- Approved project scope.
- List of users and their permissions.
- Functionality matrix.
- Donor data dictionary.
- Blood inventory data dictionary.
- Simple database relationship diagram.
- System flow diagram.
- User stories or use cases.
- Acceptance criteria.
- Privacy and retention rules.
- Backup and recovery procedure.
- Test plan.
- Training and rollout plan.

These documents will reduce confusion and prevent the team from building features that were never requested.

## 5. Recommended System Structure

Use the approved structure:

### System Users

- Authorized Red Cross staff.
- Barangay Health Coordinators.
- Public blood seekers.

### Web Application

Use the Laravel web application as the central place for staff activities.

### Core Management Modules

- Authentication and Role Access.
- Donor Management and Eligibility.
- Blood Inventory and Expiry Monitoring.
- Analytics and Reports.

### Public and Support Services

- Public Availability Locator.
- SMS Notifications and Alerts.
- Backup and Audit Records.

Keep the public locator separate from staff operations. Public users should only see approved blood availability information.

## 6. Recommended Database Information

### Donor records

Store only information that is needed and approved, such as:

- Donor identification details.
- Contact details.
- Blood type.
- Donation history.
- Eligibility status.
- Deferral reason and end date, where allowed.
- Next possible donation date.
- Record status.
- Created and updated dates.

### Blood inventory records

Store:

- Blood type.
- Blood bag or stock reference.
- Quantity.
- Collection date.
- Expiry date.
- Verification status.
- Current status.
- Location, if approved.
- Reason for every stock change.
- User and time of every stock change.

**Recommendation:** Avoid storing sensitive information that is not needed for the project.

## 7. Use Safe Record Rules

The system should:

- Require login for staff functions.
- Give each user only the access needed for their role.
- Validate required fields and valid dates.
- Prevent negative or impossible stock values.
- Require a reason for stock corrections.
- Keep a history of important changes.
- Avoid permanently deleting official records without approval.
- Mark records inactive when appropriate.
- Use database rules to protect required relationships.
- Show clear messages when an action fails.

For stock handling, use the oldest suitable stock first when the authorized process allows it. Staff must still follow official blood-safety and laboratory procedures.

## 8. Protect Private Information

Recommended protections include:

- Strong passwords with secure password hashing.
- HTTPS for the live website.
- Role-based access control.
- Server-side validation, not only browser validation.
- Protection against common web attacks.
- Safe session handling.
- Limited access to backups.
- Regular review of user accounts.
- Audit records for important actions.
- No donor names, phone numbers, or private details on the public locator.

The team should not claim that security targets are complete until they are tested in the actual hosting environment.

## 9. Build the Project in Small Phases

### Phase 1: Requirements

- Interview stakeholders.
- Confirm the scope.
- List all required fields.
- Approve user roles.
- Resolve the decisions listed in the proposal.

### Phase 2: Design

- Create the database plan.
- Draw the system flow.
- Create simple screen layouts.
- Review the design with actual users.
- Check mobile layouts early.

### Phase 3: Basic System

- Set up the Laravel project and database.
- Build login and user roles.
- Build donor records.
- Build blood inventory.
- Add validation and activity history.

### Phase 4: Supporting Features

- Add public availability.
- Add reports.
- Add expiry and shortage alerts.
- Add approved SMS notifications.
- Add backups and restore procedures.

### Phase 5: Testing

- Test each important function.
- Test complete staff workflows.
- Test public access and privacy.
- Test invalid data and unauthorized actions.
- Test on phone, tablet, and computer.
- Test slow internet conditions.
- Test backup restoration.
- Conduct user acceptance testing.

### Phase 6: Training and Pilot

- Prepare simple user guides.
- Train staff and coordinators.
- Begin with a limited pilot.
- Use a two-week period where paper and digital records are compared.
- Collect feedback every week.
- Correct serious problems before wider use.

### Phase 7: Handover

- Approve the final system.
- Confirm the data owner.
- Provide administrator and user guides.
- Turn over backup and recovery instructions.
- Record known limitations.
- Set a support contact and response process.
- Retire paper records only after formal approval.

## 10. Recommended Testing Checklist

### Function testing

- Can an authorized user create a donor record?
- Can a user update a donor's eligibility?
- Can staff add blood inventory?
- Can staff record a correction with a reason?
- Does the system show expiry warnings?
- Does the public locator show only approved information?
- Do reports match the saved records?

### Access testing

- Can each role access only its allowed pages?
- Can a public user open a staff page?
- Can a public user change inventory information?
- Are inactive users blocked?

### Data testing

- Are required fields checked?
- Are invalid dates rejected?
- Are impossible quantities rejected?
- Are duplicate records handled properly?
- Are important changes recorded?

### Recovery testing

- Can a backup be created?
- Can a backup be restored?
- Is the restored data complete?
- Can the team follow the recovery instructions without guessing?

### Usability testing

- Can a new user understand the main pages?
- Can a staff member find blood availability quickly?
- Can a coordinator use the system on a phone?
- Are labels, errors, and buttons easy to understand?
- Is the contrast readable?

## 11. Recommended Success Targets

Use the targets in the proposal, but measure the current situation first.

- Reduce the 15-20 minute blood search time.
- Reduce blood-product waste by 15%.
- Complete training for 100% of core users and coordinators.
- Reach at least 90% successful UAT workflows.
- Keep the public locator easy to access.
- Keep donor information private.
- Test daily backup and restoration.
- Complete handover within 60 days after launch.

Do not report a target as achieved without evidence from testing or actual use.

## 12. Recommended Change and Training Plan

Some users may prefer paper because it is familiar. To help them:

- Explain the reason for the project in simple words.
- Show the system using real work examples.
- Give each role only the training it needs.
- Use simple Bisaya or Tagalog guides where helpful.
- Provide practice accounts or a safe training area.
- Assign a Digital Buddy or peer helper.
- Hold weekly feedback sessions during the pilot.
- Keep support available after launch.
- Record repeated questions and turn them into guide updates.

## 13. Recommended Risk Plan

| Risk | Recommended action |
| --- | --- |
| Requirements keep changing | Require written approval for changes and record their effect on time and budget |
| Staff do not use the system | Train early, listen to feedback, and use a limited pilot |
| Incorrect blood information | Require verification, approvals, stock history, and regular reconciliation |
| Internet or power failure | Approve an offline procedure, provide UPS support, and define data re-entry rules |
| Data leak | Limit access, protect the public page, secure backups, and review accounts |
| Lost or damaged data | Schedule backups and test restoration regularly |
| Too many features | Protect the Phase 1 scope and move optional features to a later list |
| Small development team | Keep documentation current and assign clear support duties |
| Public misunderstands availability | Show the refresh time and a clear no-reservation message |

## 14. Suggested Decisions Before Development

The stakeholders should approve these decisions in writing:

1. The final Phase 1 functionality list.
2. The approved user roles and permissions.
3. The exact donor and inventory fields.
4. The data migration limit.
5. The public availability refresh schedule.
6. The backup schedule and recovery process.
7. The offline procedure.
8. The privacy, retention, and ownership rules.
9. The Laravel and PHP versions.
10. The final pilot location and participating users.
11. The acceptance criteria for launch.
12. The date for paper-record retirement, if approved.

## 15. Final Recommendation

Build a small, secure, and usable first version. Concentrate on donor records, blood inventory, eligibility, public availability, access control, reports, alerts, backups, and audit history.

Do not treat the proposal as an approved final specification yet. First validate the requirements with the actual stakeholders. Then design, build, test, pilot, train, and hand over the system in stages.

The best result is not the system with the most features. It is the system that staff can trust, use correctly, and maintain after the project team leaves.
