# Blood Repository design foundation

Status: proposed Phase 1 frontend design, 6 November 2026. This extends the first three-screen slice into the public site and full staff navigation. It remains a reviewable local prototype pending approved fields, workflows and backend integration.

## Visual direction

Calm institutional design using the supplied wordmark and palette: repository navy #132938, availability red #B5262D, warm paper #F7F3EE, and record mist #D8E0E2. Neutral surfaces carry most of the interface. Red marks primary actions, active navigation and blood symbols. Green and amber have explicit status meanings and always accompany written labels.

The landing page has a large editorial hero, a code-native blood-drop/repository illustration, two clear paths into the locator and staff demo, a short purpose section, distinct visitor pathways, and a no-reservation explanation. It uses no invented success statistics, testimonials or stock photographs.

Public pages use a wide, warm canvas. Staff pages use a compact sidebar and work-focused tables. Mobile staff navigation exposes common tasks plus a More menu; public links remain visible and wrap on narrow screens. Tables scroll inside labeled containers rather than widening the document.

## Tokens and components

css/styles.css is the base token source; css/experience.css adds public and expanded workspace layouts. System sans-serif fonts avoid external requests. Large hero headings scale from 44px to 76px; section headings scale from 30px to 44px; staff headings remain smaller. Shared components include primary/secondary buttons, labeled fields, native dialogs, text status badges, paginated tables, summary strips, alerts and visible focus rings.

Text/background contrast checks cover main, muted, button, status and hero panel colors. Interactive controls use at least 44px height except native checkboxes, which sit inside clickable labels. Keyboard focus is visible. Reduced-motion preferences disable transitions and loading rotation. Formal accessibility conformance and complete device coverage are not claimed.

## Screen tree

    Public home / hero
    ├── View-only availability
    │   ├── Filter by blood type
    │   └── Published / outdated / unavailable condition previews
    ├── Help / FAQ
    ├── Prototype privacy explanation
    └── Staff sign-in / recovery designs and demo role selection
        ├── Overview
        ├── Donor registry
        │   └── Profile / donation history
        │       ├── Correct details
        │       ├── Record staff assessment
        │       └── Record sample donation
        ├── Blood inventory
        │   └── Unit record / activity
        │       ├── Record verification
        │       └── Quarantine / release / disposal
        ├── Reports / filters / CSV
        ├── Notifications / compose / inspect / simulate outcome
        ├── Users / proposed roles
        ├── Session audit / CSV
        ├── Backup metadata / recovery rehearsal / health
        └── Proposed configuration

## Behavior and data boundaries

All records are fictional and held in memory. Reload resets the entire demonstration. Data is never written to localStorage. The selected role is a UI simulation, and the complete static bundle is accessible to anyone who can read the files. Hiding a link or denying a client route is not production authorization or protection of real data.

Public rendering uses a narrow aggregate snapshot: blood type and unit count only. Published counts do not change until an explicit sample publication. Outdated and unavailable states hide counts. The snapshot timestamp is the actual sample publication time. No reservation, guaranteed release, real medical assessment, contact number, or official live-service claim is included.

Operational rules in the prototype are proposals: one record per whole-blood unit, pending verification on entry, explicit manual staff assessments, unique bag references, reasoned stock actions, and terminal release/disposal states. The fixed fixture date is 6 November 2026. Staff-entered dates and statuses do not establish clinical suitability. No FIFO/FEFO or deferral interval is invented.

Reports calculate directly from current in-memory records. Donation counts reconcile to individual fixtures. Donor status counts update when a sample assessment is recorded. CSV exports quote content and protect leading formula characters. The log records demo mutations and exports, but has no durability or tamper resistance.

Notifications simulate queue/outcome screens; no provider request is sent. Recovery screens create metadata and checklist activity only. Health indicators show absent services as Not connected. Configuration edits do not create jobs, send alerts or approve operational policies.

## State handling

Included: initial loading fallback, no JavaScript, no search results, empty donation history, absent stock, outdated publication, unavailable locator, invalid/duplicate input, missing donor/unit, role denial, and unexpected script-error fallback. The staff toolbar previews loading, error and denied states. It does not fake a successful server save.

Native dialogs provide browser focus containment and Escape dismissal. Close returns focus to the trigger if still present. Route navigation focuses main content. Search retains focus. Validation uses browser constraints plus explicit model checks, and failures appear inside the active form. Status feedback is announced in a visible live region.

## Applying the 11 local AI policies

Scope preservation, verified APIs, component architecture, error handling, performance, responsive design, accessibility, deliberate design, senior engineering practice, TypeScript strictness where applicable, and Git standards remain the working guidance. This prototype has no React, Next.js or TypeScript dependency, so their framework-specific examples are not applied literally. The responsive policy's stated 44px target supersedes its smaller example. Reduced-motion behavior takes precedence over decorative animation. Existing branding assets are reused.

## Laravel integration boundary

Native HTML/CSS/ES modules keep the design review independent of the framework version decision. Move templates into approved Blade/Livewire components or the selected frontend framework while retaining tokens and patterns. Replace the store with versioned API requests or server props, backend authorization and validation, durable audit, database transactions, secure sessions, real pagination, safe exports, queued notifications and monitored recovery jobs.

Before production, approve the field dictionary, role/area access, eligibility authority, inventory life cycle, public publication policy, notification consent and templates, retention, hosting, backup schedule, and acceptance criteria. Real sign-in and password recovery must use Laravel, not a browser-only substitute.

## Connected workspace Phase 2

The separate `/workspace` entry now uses the existing Laravel authentication, donor and audit APIs. Its overview shows server totals within the user's permitted scope; donor records use desktop tables and mobile cards. Forms separate entry, review and save, and stale corrections require explicit field choices against the latest record. Account access, audit inspection, session expiry and offline feedback share the established tokens and branding. The scoped `css/workspace.css` leaves the fictional demo independent. See `PHASE-2.md` for the screen tree and `QA.md` for verified behavior and outstanding visual checks.


