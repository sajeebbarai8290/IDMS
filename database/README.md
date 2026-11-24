# IDMS Database Architecture & Schema Administration

## Overview
The Integrated Disaster Management System (IDMS) utilizes **PostgreSQL 16.x** paired with the **PostGIS 3.4+** spatial engine.

In adherence to sovereign data governance standards and the *Disaster Management Act 2012*, data is partitioned across 12 domain schemas instead of a single crowded `public` schema.

---

## 1. Modular Database Schemas

| Schema | Domain Description |
| :--- | :--- |
| `core` | Administrative Hierarchy, Users, Roles, Base Jurisdictions, Permissions |
| `beneficiary` | Households, Members, Vulnerability Indexing, Encrypted NID Hashes |
| `disaster` | Disaster Incidents, Meteorological Bulletins, Cyclone/Flood Shelters |
| `damage` | Standardized D-Form Damage Assessments and Sectoral Losses |
| `logistics` | Warehouses, Commodities, Stock Ledgers, Requisitions & Allocations |
| `distribution` | Beneficiary Hand-over Vouchers and Verification Proofs |
| `project` | Post-Disaster Infrastructure Rehabilitation Schemes & Milestones |
| `volunteer` | Cyclone Preparedness Programme (CPP) and Red Crescent Rosters |
| `workflow` | State Machine Approval Instances, Transition History & Sign-offs |
| `notification`| Multi-Channel Notification Templates, SMS Queues & Delivery Logs |
| `document` | Document Metadata, Cryptographic SHA-256 Hashes, Antivirus Scans |
| `audit` | Immutable Append-Only Audit Logs |

---

## 2. Identifier Strategy
- **UUIDv7 (RFC 9562):** Time-ordered 128-bit identifiers preserving B-Tree index locality without fragmenting buffer cache.
- Native PostgreSQL `uuid-ossp` or application-level UUID generation.

---

## 3. Spatial Reference Systems (SRID)
- **EPSG:4326 (WGS 84):** Storage standard for GPS coordinates and administrative boundaries.
- **EPSG:3106 (Bangladesh Transverse Mercator - BTM):** Applied for high-precision national cadastral area calculations (e.g. inundated cropland square kilometers, damaged road linear meters).

---

## 4. Manual Database Initialization (For DBAs)
To initialize the database schemas manually on a PostgreSQL instance:
```bash
psql -U idms_user -d idms_db -f database/sql/01_init_postgis_and_schemas.sql
```
