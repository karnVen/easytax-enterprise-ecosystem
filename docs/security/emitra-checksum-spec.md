# 🛡️ Rajasthan e-Mitra e-Governance Checksum & Gateway Specification

## 1. Context & Purpose

The Rajasthan Government **e-Mitra** network connects tens of thousands of citizen service kiosks across 33 districts. To deliver Income Tax and GST filing services via EasyTax, the platform interfaces directly with the state e-Governance switchboard.

Every transaction request dispatched between e-Mitra and EasyTax must carry a tamper-evident cryptographic checksum to guarantee authenticity, non-repudiation, and payload integrity.

---

## 2. Checksum Algorithm Specification

### Data Fields Involved
| Parameter | Description | Example Value |
| :--- | :--- | :--- |
| `SSOID` | Departmental Single Sign-On Account | `EASYTAXSERVICES` |
| `SERVICEID` | Government Service Code | `11088` |
| `EMITRATIMESTAMP` | State Transaction Timestamp | `20260430190633291` (YYYYMMDDHHmmssfff) |
| `SSOTOKEN` | Encrypted Single Sign-On Session Key | Base64 Encoded Token |
| `CHECKSUM` | 32-character hexadecimal digest | MD5 Hash Output |

### Hash Formula
```
RawPayload = SSOID + SERVICEID + EMITRATIMESTAMP + SSOTOKEN
Checksum   = MD5(RawPayload)
```

> **Engineering Note:** Characters must be trimmed of surrounding whitespace. Delimiters (such as `|` or `,`) are strictly disallowed by the state gateway specification.

---

## 3. Threat Mitigation Matrix

| Threat Vector | Attack Scenario | Implemented Countermeasure |
| :--- | :--- | :--- |
| **Payload Tampering** | Attacker modifies `SERVICEID` to bypass service fees. | Cryptographic hash invalidation; request immediately dropped with HTTP 403. |
| **Replay Attacks** | Attacker captures a legitimate transaction payload and re-transmits it later. | Timestamp freshness window enforces a strict 300-second acceptance ceiling (`TIMESTAMP_TOLERANCE_SECONDS`). |
| **Session Bleed** | Kiosk operator opens concurrent tabs for multiple citizens. | Request header `X-Financial-Cycle` dynamically scopes all Eloquent queries to the active citizen's fiscal year. |
| **Unauthorized Reverse Proxies** | Kiosk URL accessed via unverified local proxy. | Root-relative media pathing and domain origin white-listing. |
