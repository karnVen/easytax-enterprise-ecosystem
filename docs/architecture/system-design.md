# 🏛️ EasyTax Enterprise Ecosystem: System Design & Architecture Specification

## 1. Executive Architecture Overview

EasyTax operates as an interconnected, multi-tier FinTech and e-Governance ecosystem serving retail taxpayers, corporate enterprises, and thousands of rural/urban kiosk operators across Rajasthan.

```mermaid
flowchart TD
    subgraph Channels["Client Access Channels"]
        Citizen["Retail Citizen / Taxpayer"]
        Kiosk["e-Mitra Kiosk Agent"]
        Corporate["Corporate Business / MSME"]
        CA["Chartered Accountant / Reviewer"]
    end

    subgraph C1["Channel 1: Corporate Marketing and Intake"]
        Citizen -->|HTTPS Browsing| DrupalLive["easytax.live - Drupal 10 CMS"]
        DrupalLive --> TwigTheme["Twig 3 Atomic Paragraphs Theme"]
        DrupalLive --> CalcModules["Custom Tax Slabs and Regime Calculators"]
        DrupalLive --> LeadIntake["Form 16 Dropzone and Lead Capture"]
    end

    subgraph C2["Channel 2: B2B Operational Core"]
        Corporate -->|Secure Auth| B2BLaravel["b2b.easytax.live - Laravel 12"]
        CA -->|CA Review Queue| B2BLaravel
        LeadIntake -.->|Internal Dispatch| B2BLaravel
        B2BLaravel --> RoleScoper{"4-Tier Role Middleware"}
        RoleScoper --> AdminTower["Super Admin Tower"]
        RoleScoper --> AgentDesk["Agent Filing Desk"]
        RoleScoper --> TeamManager["Regional Team Desk"]
        RoleScoper --> MarketerDesk["Growth and Affiliate Desk"]
    end

    subgraph C3["Channel 3: Government e-Governance Gateway"]
        Kiosk -->|Rajasthan SSO Dispatch| EMitraGateway["Rajasthan e-Mitra Kiosk Gateway"]
        EMitraGateway --> ChecksumEngine["MD5 / HMAC Cryptographic Checksum Engine"]
        ChecksumEngine --> CycleScoper["Financial Cycle Scoper - FY 24-25 and FY 25-26"]
        CycleScoper --> LedgerService["Atomic Double-Entry Commission Ledger"]
    end

    subgraph C4["Channel 4: Next-Gen Mobile and Computation"]
        Citizen -->|Native App| AppDev["Easytax_AppDev - iOS and Android"]
        AppDev --> DeterministicMath["Paise-Precise Deterministic Tax Engine"]
    end

    subgraph C5["Persistence and Audit"]
        B2BLaravel --> DB[("MySQL / Transactional Relational Data")]
        LedgerService --> DB
        DB --> ImmutableAudit["Audit-Proof Regulatory Logging"]
    end
```

---

## 2. Multi-Portal Operational Isolation

The operational core enforces strict role and temporal isolation across four dimensions:

### 1. Role-Based Portals (RBAC)
- **Super Admin**: System configuration, fee schedules, tax regime updates, and aggregate ledger oversight.
- **Kiosk Agent**: Citizen application intake, document vaulting, real-time margin ledger, and sub-agent commission distribution.
- **Regional Team**: Supervisory audits, dispute resolution, and district-level volume analytics.
- **Marketing & Partner**: Growth attribution, affiliate link performance, and milestone rewards.

### 2. Financial Cycle Isolation
Kiosk operators handle both current-year filings and prior-year audits concurrently. To prevent multi-tab session state contamination:
- Every request carries an `X-Financial-Cycle` token (e.g. `2025-2026`).
- Application middleware dynamically binds the cycle to the service container and injects global query scopes across all Eloquent queries.

---

## 3. Cryptographic Gateway Security (e-Mitra)

The Government of Rajasthan e-Mitra integration requires deterministic request signing and bidirectional payload verification:
1. **SSO Token Authenticity**: Verifies incoming government session tokens against the state SSO authority.
2. **Byte-Exact Checksum Validation**: Concatenates `SSOID`, `SERVICEID`, `EMITRATIMESTAMP`, and `SSOTOKEN` without delimiters, generating an MD5 digest matching the government verification server.
3. **Replay-Attack Protection**: Rejects payloads with timestamps deviating by more than 300 seconds from UTC/IST synchronized clocks.

---

## 4. Double-Entry Commission Accounting

Agent payouts and kiosk margins are processed through an atomic double-entry ledger service:
- No raw balance mutations (`$agent->balance += 100`).
- Every completed filing generates paired credit and debit entries in a database transaction (`DB::transaction`).
- Balances are computed through indexed ledger aggregation, preventing race conditions under high concurrent kiosk usage.
