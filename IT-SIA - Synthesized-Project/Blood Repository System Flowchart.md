# Blood Repository System Flowchart

This is the proposed system flow. It shows the subsystems and essential interactions, with the web application as the central interface and the public locator as view-only.

```mermaid
flowchart TB
    subgraph USERS[System Users]
        direction LR
        A[Authorized Staff and<br/>Barangay Coordinators]
        B[Public Blood Seekers]
    end

    C[Web Application<br/>(Central Interface)]

    subgraph CORE[Core Management Modules]
        direction LR
        D[Authentication and<br/>Role Access]
        E[Donor Management<br/>and Eligibility]
        F[Blood Inventory<br/>and Expiry Monitoring]
        G[Analytics and<br/>Reports]
    end

    subgraph SERVICES[Public and Support Services]
        direction LR
        J[Public Availability<br/>Locator]
        H[SMS Notifications<br/>and Alerts]
        I[Backup and<br/>Audit Records]
    end

    A -->|Manage records| C
    B -->|View availability| J
    C --> D
    C --> E
    C --> F
    C --> G
    F -->|Current stock| J
    E -->|Donor reminders| H
    F -->|Expiry alerts| H
    C -->|Audit and backup| I

    classDef user fill:#f4f4f4,stroke:#333,stroke-width:1px,color:#111;
    classDef core fill:#e8f1f8,stroke:#24516b,stroke-width:1px,color:#111;
    classDef service fill:#f8f3e6,stroke:#806b35,stroke-width:1px,color:#111;
    class A,B user;
    class C,D,E,F,G core;
    class H,I,J service;
```

## Flow Rules

- Public users can view availability only; they cannot modify stock, reserve blood, or view donor records.
- Authorized roles control donor, inventory, report, and administrative actions.
- Inventory changes retain the operator, reason, quantity, and timestamp.
- The public locator displays a refresh timestamp and no-reservation notice.
- Notification and backup failures must be logged and visible to the technical owner.
