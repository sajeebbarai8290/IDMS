# Integrated Disaster Management System (IDMS)
## Enterprise Database Architecture Design Decisions & Technical Specifications

| Document Information | Details |
| :--- | :--- |
| **System** | Integrated Disaster Management System (IDMS) |
| **Client Agency** | Ministry of Disaster Management and Relief (MoDMR), Government of Bangladesh |
| **Document Purpose** | Comprehensive Architectural Design Decisions & PostgreSQL/PostGIS Engineering Standards |
| **Document Version** | 1.0.0-PROPOSED |
| **Author** | Senior PostgreSQL Database Architect & Enterprise Solution Team |
| **Target Engine** | PostgreSQL 16.x with PostGIS 3.4+ Extension |
| **Status** | Prepared for Technical Architecture Review (No Migrations Generated) |

---

## 1. Domain Identification & Schema Organization

To avoid a single crowded `public` schema with hundreds of unsegregated tables, IDMS implements a clean **PostgreSQL Schema-based Modular Architecture**. Each domain is strictly isolated inside its own database schema:

```
+--------------------------------------------------------------------------------------------------+
|                                  IDMS POSTGRESQL SCHEMA TOPOLOGY                                 |
+-------------------+------------------------------------------------------------------------------+
| Schema Name       | Domain Scope & Functional Responsibilities                                   |
+-------------------+------------------------------------------------------------------------------+
| `core`            | Administrative Hierarchy, System Users, Roles, Base Jurisdictions            |
| `beneficiary`     | Households, Members, Vulnerability Indexing, NID Verifications & Hashes      |
| `disaster`        | Disaster Events, Early Warnings, Shelters, Shelter Occupancy, SAR Incidents  |
| `damage`          | Official D-Form Damage Assessments, Sectoral Loss Breakdown (Housing, Agro)  |
| `logistics`       | Warehouses, Commodities, Stock Ledgers, Relief Requisitions & Allocations    |
| `distribution`    | Beneficiary Hand-off Distributions, Verification Proofs (QR/OTP/Biometric)   |
| `project`         | Post-Disaster Reconstruction Projects, Milestones, Contractor Disbursements  |
| `volunteer`       | CPP & Red Crescent Rosters, Skill Matrices, Emergency Deployments            |
| `workflow`        | Multi-Tier Approval State Machines, Transition Logs, Approval Gates          |
| `notification`    | Multi-Channel Notification Templates, Dispatch Queue, Delivery Receipts      |
| `document`        | Document Metadata, Security Hashes, Virus Scan Status, Pre-signed Storage    |
| `audit`           | Immutable Monthly-Partitioned Event Audit Trail                              |
+-------------------+------------------------------------------------------------------------------+
```

---

## 2. Identifier Strategy: Strict UUIDv7 Standard

### Decision: Adopt RFC 9562 UUIDv7 for All Relational Primary Keys
Traditional auto-incrementing integers (`SERIAL`/`BIGSERIAL`) and random UUIDs (`UUIDv4`) present severe trade-offs in a national emergency platform:
- **Flaws of `BIGSERIAL`:** Sequential IDs expose sensitive operational volumes to enumerative scraping (IDOR), cause distributed merge collisions when syncing offline field mobile apps, and complicate cross-environment seeding.
- **Flaws of `UUIDv4`:** Completely random generation creates severe **B-Tree index page splitting** and cache thrashing in PostgreSQL when tables exceed millions of records.
- **The UUIDv7 Solution:** Embeds an epoch timestamp with millisecond resolution in the leading 48 bits, followed by cryptographically random bits:
  $$\text{UUIDv7} = \underbrace{\text{Unix Timestamp (48 bits)}}_{\text{Time-ordered B-Tree locality}} \,+\, \underbrace{\text{Version (4 bits)}}_{0111_2} \,+\, \underbrace{\text{Random Sequence (76 bits)}}_{\text{Collision-proof uniqueness}}$$

### Benefits for IDMS:
1. **Zero Index Page Fragmentation:** Guaranteed sequential sorting preserves PostgreSQL buffer cache efficiency.
2. **Offline Generation:** Field officers can generate record IDs on mobile devices without centralized network roundtrips.
3. **Collision Resistance:** $2^{76}$ random bits per millisecond eliminates collision risk across concurrent national write bursts.

---

## 3. Organizational Hierarchy & Geographic Boundary Architecture

The system models Bangladesh's sovereign administrative structure across 7 operational tiers:
```
[Tier 1] Ministry (MoDMR / NEOC)
   └── [Tier 2] Directorate (DDM)
         └── [Tier 3] Division (8 Divisions)
               └── [Tier 4] District (64 Districts)
                     └── [Tier 5] Upazila (495 Upazilas)
                           └── [Tier 6] Union / Pouroshava (~4,571 Unions)
                                 └── [Tier 7] Field Operation / Ward / Shelter
```

### Self-Referential Adjacency with Materialized Path
The `core.administrative_units` table implements an adjacency list (`parent_id`) augmented with a **Materialized Path** (`ancestry_path` of BBS GeoCodes, e.g., `/10/20/30/45/`):
- **O(1) Descendant Filtering:** Allows instantaneous retrieval of all sub-units for an administrative tier using SQL prefix matching:
  ```sql
  -- Retrieve all entities under Khulna Division (GeoCode '40')
  SELECT * FROM core.administrative_units 
  WHERE ancestry_path LIKE '/40/%';
  ```
- **Recursive CTE Fallback:** PostgreSQL `WITH RECURSIVE` queries provide dynamic path validation and tree rebuilds without locking.
- **BBS GeoCode Compliance:** All administrative nodes enforce Bangladesh Bureau of Statistics (BBS) official GeoCodes with unique constraints.

---

## 4. PostGIS Spatial Architecture & Standards

### 4.1 Projections & Spatial Reference Identifiers (SRID)
- **Primary Storage SRID (EPSG:4326 - WGS 84):** All coordinates from GPS devices, mobile apps, and field surveys are stored as WGS 84 (`geometry(..., 4326)`).
- **Presentation SRID (EPSG:3857 - Web Mercator):** Transformed on-the-fly for GeoServer WMS/WFS map tile serving to web browsers.
- **Measurement SRID (EPSG:3106 - Bangladesh Transverse Mercator / BTM):** Applied for high-precision national cadastral area calculations (e.g. flooded cropland square kilometers, damaged road linear meters).

### 4.2 Spatial Geometry Types by Entity
| Table | Column Name | PostGIS Type | Purpose |
| :--- | :--- | :--- | :--- |
| `core.administrative_units` | `boundary_geom` | `geometry(MultiPolygon, 4326)` | Official administrative jurisdictional boundaries |
| `disaster.disaster_events` | `affected_zone_geom` | `geometry(MultiPolygon, 4326)` | Composite flood/cyclone hazard footprint |
| `disaster.shelters` | `location_geom` | `geometry(Point, 4326)` | Exact GPS coordinates of cyclone/flood shelter |
| `beneficiary.households` | `dwelling_geom` | `geometry(Point, 4326)` | Dwelling location for vulnerability & evacuation modeling |
| `logistics.warehouses` | `location_geom` | `geometry(Point, 4326)` | Godown facility geographic coordinates |
| `distribution.distribution_points` | `location_geom` | `geometry(Point, 4326)` | Relief distribution center / staging site |
| `damage.damage_assessments` | `damage_area_geom` | `geometry(MultiPolygon, 4326)` | Spatial boundary of localized destruction |
| `project.projects` | `site_geom` | `geometry(Point, 4326)` | Reconstruction project site location |

### 4.3 Spatial Indexing Strategy
Every spatial column is indexed with a **Generalized Search Tree (GIST)** index:
```sql
CREATE INDEX idx_admin_units_boundary_gist ON core.administrative_units USING GIST (boundary_geom);
CREATE INDEX idx_shelters_location_gist ON disaster.shelters USING GIST (location_geom);
CREATE INDEX idx_households_dwelling_gist ON beneficiary.households USING GIST (dwelling_geom);
CREATE INDEX idx_disaster_affected_zone_gist ON disaster.disaster_events USING GIST (affected_zone_geom);
```

---

## 5. Comprehensive Relational Cardinality Mapping

```
+-----------------------------------+---------------+-------------------------------------+-----------------------------------+
| Parent Entity                     | Cardinality   | Child Entity                        | Business Context                  |
+-----------------------------------+---------------+-------------------------------------+-----------------------------------+
| core.administrative_units         | 1 : 1 (Self)  | core.administrative_units (Parent)  | Recursive administrative tree     |
| core.administrative_units         | 1 : N         | core.users                          | User jurisdiction assignment      |
| core.administrative_units         | 1 : N         | beneficiary.households              | Household geographic jurisdiction |
| core.administrative_units         | 1 : N         | disaster.shelters                   | Shelters in union/upazila         |
| core.administrative_units         | 1 : N         | logistics.warehouses                | Godowns in district/upazila       |
| beneficiary.households            | 1 : N         | beneficiary.household_members       | Family members in household       |
| beneficiary.households            | 1 : N         | beneficiary.nid_verifications       | Verification history of HoH/adult |
| disaster.disaster_events          | 1 : N         | disaster.shelter_occupancies        | Shelter occupancy per disaster    |
| disaster.disaster_events          | 1 : N         | damage.damage_assessments           | D-Form damage records             |
| disaster.disaster_events          | 1 : N         | logistics.relief_requisitions       | Requisitions triggered by crisis  |
| damage.damage_assessments         | 1 : N         | damage.damage_sector_items          | Breakdown: Housing, Crops, etc.   |
| logistics.relief_requisitions     | 1 : N         | logistics.relief_requisition_items  | Line items requested (Rice, Cash) |
| logistics.relief_requisitions     | 1 : N         | logistics.relief_allocations        | Sanction orders generated         |
| logistics.relief_allocations      | 1 : N         | logistics.relief_allocation_items   | Approved commodity quantities     |
| logistics.warehouses              | 1 : N         | logistics.warehouse_inventories     | Lots stocked per facility         |
| logistics.warehouse_inventories   | 1 : N         | logistics.stock_transactions        | Stock In/Out/Transfer audit trail |
| logistics.relief_allocation_items | 1 : N         | distribution.distribution_vouchers  | Field relief hand-off records     |
| beneficiary.households            | 1 : N         | distribution.distribution_vouchers  | Aid received by household         |
| project.projects                  | 1 : N         | project.project_milestones          | Infrastructure milestones         |
| project.project_milestones        | 1 : N         | project.project_disbursements       | Contractor milestone payments     |
| volunteer.volunteers              | N : M         | volunteer.volunteer_deployments     | Volunteer deployment assignments  |
| workflow.workflow_instances       | 1 : N         | workflow.workflow_transitions       | Historical approval steps         |
+-----------------------------------+---------------+-------------------------------------+-----------------------------------+
```

---

## 6. Sensitive Personal Information (PII) Protection & Cryptography

Beneficiary records contain vulnerable citizen demographics, National ID numbers, mobile numbers, and bank/MFS payment credentials.

### 6.1 Cryptographic Standards
1. **Deterministic Hashing for Duplicate Detection:**
   - Raw NID numbers are never stored in plaintext.
   - For fast equality lookups without exposing raw numbers, a **keyed cryptographic hash (HMAC-SHA256)** using a server-side pepper key is stored in `nid_hash`:
     $$\text{nid\_hash} = \text{HMAC-SHA256}(\text{raw\_nid}, \text{SECRET\_PEPPER})$$
   - A `UNIQUE INDEX` on `nid_hash` allows $O(1)$ duplicate prevention without decrypting millions of database rows.
2. **Column-Level Encryption for Data-at-Rest:**
   - Columns containing reversible citizen data (`nid_encrypted`, `mobile_number_encrypted`, `bank_account_encrypted`) are encrypted using **AES-256-GCM** with authenticated data (AEAD).
   - Encryption keys are managed outside the database (via KMS / Keycloak / Laravel encrypted keystore).
3. **Role-Based Data Masking:**
   - Database views mask NID strings: `1985********1234` for general operators. Full decryption is audited and restricted to certified officers.

---

## 7. Soft Deletion & Legal Data Retention Rules

### 7.1 Soft Deletion Application
- Soft delete (`deleted_at TIMESTAMPTZ NULL`) is implemented on master records (`beneficiary.households`, `disaster.shelters`, `logistics.warehouses`, `project.projects`, `core.users`).
- **Strictly Prohibited on Transactional Accounting:** Soft deletes are **never** permitted on:
  - `logistics.stock_transactions` (Inventory ledger)
  - `distribution.distribution_vouchers` (Relief hand-off records)
  - `audit.audit_logs` (System activity logs)
  - `workflow.workflow_transitions` (Official approval audit logs)
  Any correction to an inventory or distribution error requires a compensatory reversal transaction, preserving an unbroken legal audit trail.

### 7.2 Data Retention & Partitioning
- **Monthly Declarative Partitioning:**
  - Table `audit.audit_logs` is range-partitioned by month:
    ```sql
    CREATE TABLE audit.audit_logs (
        id UUID NOT NULL,
        created_at TIMESTAMPTZ NOT NULL,
        ...
    ) PARTITION BY RANGE (created_at);
    ```
- **Retention Lifecycle:**
  - Active transactional data (Relief, Disasters): Retained online indefinitely (minimum 10 years per Government of Bangladesh financial audit rules).
  - Monthly Audit partitions older than 3 years: Automatically detached and moved to cold compressed archive tablespaces.

---

## 8. Concurrency, Locking & Inventory Ledger Integrity

Relief distribution during mass crises involves rapid concurrent requests. Without strict concurrency control, multiple field officers could inadvertently allocate the same warehouse stock or grant duplicate relief to the same household.

### 8.1 Pessimistic Row-Level Locking on Stock Allocations
When issuing relief from a warehouse inventory lot:
```sql
BEGIN;

-- Lock the specific warehouse inventory lot to prevent double-spending
SELECT id, current_quantity 
FROM logistics.warehouse_inventories 
WHERE warehouse_id = :warehouse_id AND commodity_id = :commodity_id AND batch_number = :batch_no
FOR UPDATE;

-- Validate stock availability
-- Decrement inventory and record stock transaction ledger entry
UPDATE logistics.warehouse_inventories 
SET current_quantity = current_quantity - :allocation_qty, updated_at = CURRENT_TIMESTAMP
WHERE id = :inventory_id;

INSERT INTO logistics.stock_transactions (...) VALUES (...);

COMMIT;
```

### 8.2 Household Distribution Idempotency Constraint
To mathematically prevent double-distribution of the same disaster relief allocation to a household:
```sql
CREATE UNIQUE INDEX uq_dist_household_allocation_commodity 
ON distribution.distribution_vouchers (allocation_id, household_id, commodity_id)
WHERE is_voided = false;
```
If two volunteers attempt to log relief delivery for the same family at the same moment, PostgreSQL rejects the second write at the database engine level with a unique constraint violation.

---

## 9. Performance Bottlenecks & Optimization Blueprint

| Potential Bottleneck | Technical Root Cause | Architectural Mitigation Strategy |
| :--- | :--- | :--- |
| **National Boundary Spatial Queries** | Complex MultiPolygons with 50,000+ vertices evaluated against point clusters | 1. `ST_Subdivide()` on large boundary geometries into convex tiles.<br>2. GIST spatial indexing on bounding boxes.<br>3. GeoServer GeoWebCache caching for static layers. |
| **Hierarchical Downward Scope Queries** | Filtering millions of records across 7 administrative tiers | 1. Materialized `ancestry_path` column with B-Tree text pattern ops index (`varchar_pattern_ops`).<br>2. Composite indexes on `(administrative_unit_id, created_at DESC)`. |
| **High Write Concurrency on Audit Logs** | Every database mutation writing to a single monolithic table | 1. Native PostgreSQL declarative range partitioning by month.<br>2. Dedicated SSD tablespace for the `audit` schema.<br>3. Asynchronous queue ingestion via Redis Horizon. |
| **NID Duplicate Checking During Mass Influx** | Full table scans on citizen NID columns | 1. Keyed HMAC-SHA256 deterministic hash stored in fixed-length `char(64)`.<br>2. Unique B-Tree index on `nid_hash`. Query latency: &le; 2 ms. |
| **Connection Exhaustion During Crisis Spikes** | Thousands of web/mobile clients creating direct database connections | PgBouncer connection pooler in Transaction Pooling mode, capping active PostgreSQL backends at 150 while servicing 5,000+ client connections. |

---

## 10. Database Backup and Disaster Recovery Strategy

1. **Continuous WAL Archiving:** Write-Ahead Logs (WAL) are shipped every 60 seconds to secondary storage using `pg_backrest`.
2. **Physical Hot Standby Replication:** Continuous asynchronous/synchronous streaming replication from the primary database at the Bangladesh National Data Center (Dhaka) to the Disaster Recovery Center (Jashore).
3. **Backup Matrix:**
   - *Hourly:* Differential backups of active schemas.
   - *Daily:* Full cluster physical snapshot at 01:00 UTC.
   - *Weekly:* Checksum verification and automated test restore onto an isolated verification instance.
4. **Target Metrics:**
   - **Recovery Point Objective (RPO):** &le; 0 minutes for committed relief vouchers (sync replication); &le; 5 minutes for general system data.
   - **Recovery Time Objective (RTO):** &le; 15 minutes for standby database promotion.
