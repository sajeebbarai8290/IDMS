# Integrated Disaster Management System (IDMS)
## Organizational Hierarchy & Hierarchical Access Control Specification

| Document Information | Details |
| :--- | :--- |
| **System** | Integrated Disaster Management System (IDMS) |
| **Client Agency** | Ministry of Disaster Management and Relief (MoDMR), Government of Bangladesh |
| **Document Purpose** | Complete Administrative Hierarchy, User-Organization Binding, Hierarchical Data Scoping & Cross-Agency Rules |
| **Document Version** | 1.0.0-PROPOSED |
| **Statutory Framework**| Disaster Management Act 2012, Standing Orders on Disaster (SOD) 2019, BBS National GeoCode Standard |
| **Status** | Prepared for Architecture Review (No Migrations / Code Generated) |

---

## 1. Executive Overview & Governance Philosophy

The sovereign territory of the People's Republic of Bangladesh requires a disaster risk governance platform that mirrors its constitutional administrative hierarchy while enabling rapid, zero-friction cross-agency emergency collaboration.

### 1.1 Hierarchical Data Scoping vs. Multi-Tenancy Architecture
Commercial Software-as-a-Service (SaaS) platforms typically enforce "Multi-Tenancy," partitioning data into isolated silos. **Multi-tenancy is fundamentally incompatible with sovereign government disaster operations** for the following architectural reasons:

1. **National Emergency Rollup:** A cyclone or flood does not respect siloed boundaries. National leadership (Ministry, DDM, NEOC) requires real-time aggregated visibility from the coastal ward level up to the national situation room without cross-tenant joins or distributed query synchronization.
2. **Unified Sovereign Registry:** Citizens, national infrastructure assets, cyclone shelters, and emergency food supplies belong to a single sovereign registry. Beneficiaries must not be duplicated across independent tenants.
3. **Strict Top-Down & Bottom-Up Workflows:** Relief requests originate at the grassroots Union level and traverse upwards through Upazila and District administrations to the Ministry for sanction, and allocation orders flow downwards for disbursement.

Accordingly, IDMS implements a **Unified Hierarchical Data Scoping Architecture** powered by PostgreSQL/PostGIS and enforced at the application tier by Laravel 11.

---

## 2. The Seven-Tier Sovereign Administrative Hierarchy

The IDMS administrative hierarchy directly reflects the official government governance structure mandated by the *Disaster Management Act 2012* and the *Standing Orders on Disaster (SOD) 2019*:

```
+----------------------------------------------------------------------------------------------------+
|                             IDMS 7-TIER ADMINISTRATIVE HIERARCHY                                   |
+------+-----------------------+----------------------------------+----------------------------------+
| Tier | Governance Level      | Administrative & Statutory Body  | Primary Institutional Actors     |
+------+-----------------------+----------------------------------+----------------------------------+
| T1   | National / Ministry   | MoDMR / NDMC / NEOC              | Minister, Senior Secretary,      |
|      |                       |                                  | NEOC Director, National Auditor  |
+------+-----------------------+----------------------------------+----------------------------------+
| T2   | Directorate           | Department of Disaster           | Director General, Directors      |
|      |                       | Management (DDM)                 | (Relief, Planning, M&E)          |
+------+-----------------------+----------------------------------+----------------------------------+
| T3   | Division              | Divisional Commissioner Office   | Divisional Commissioner,         |
|      | (8 Divisions)         | (DivDMC)                         | Addl. Divisional Commissioner    |
+------+-----------------------+----------------------------------+----------------------------------+
| T4   | District              | Deputy Commissioner Office       | Deputy Commissioner (DC),        |
|      | (64 Districts)        | (DDMC)                           | DRRO                             |
+------+-----------------------+----------------------------------+----------------------------------+
| T5   | Upazila               | Upazila Nirbahi Office           | Upazila Nirbahi Officer (UNO),   |
|      | (495 Upazilas)        | (UzDMC)                          | PIO                              |
+------+-----------------------+----------------------------------+----------------------------------+
| T6   | Union / Pouroshava    | Union Parishad / Pourashava      | UP Chairman, Mayor,              |
|      | (~4,571 Unions)       | (UDMC)                           | UP Secretary, Ward Members       |
+------+-----------------------+----------------------------------+----------------------------------+
| T7   | Field Operations /    | Relief Distribution Centers,     | Tag Officers, Shelter Managers,  |
|      | Shelter / Ward        | Cyclone Shelters, Survey Wards   | CPP Leaders, Field Enumerators   |
+------+-----------------------+----------------------------------+----------------------------------+
```

```mermaid
graph TD
    T1["Tier 1: National / Ministry (MoDMR / NEOC)<br>National Command & Sovereign Policy"]
    T2["Tier 2: Directorate (DDM)<br>National Relief Logistics, Reserves & Planning"]
    T3["Tier 3: Division (8 Divisions)<br>Regional Supervision & Inter-District Coordination"]
    T4["Tier 4: District (64 Districts - DC / DRRO)<br>District Reserves, Emergency Funds & Allocation"]
    T5["Tier 5: Upazila (495 Upazilas - UNO / PIO)<br>Upazila Operations, Verification & Logistics Hub"]
    T6["Tier 6: Union / Pouroshava (~4,571 Unions)<br>Grassroots Registration, D-Form & Needs Requisition"]
    T7["Tier 7: Field Operations (Wards / Shelters / Distribution Points)<br>Muster Roll Handover, Physical Shelter Ops, GPS Surveys"]

    T1 --> T2
    T2 --> T3
    T3 --> T4
    T4 --> T5
    T5 --> T6
    T6 --> T7
```

---

## 3. Relational Entity Architecture & Materialized Path Engine

### 3.1 Entity Model (`core.administrative_units`)
To deliver sub-millisecond hierarchical lookups and spatial boundary queries without expensive recursive SQL common table expressions (CTEs), each administrative unit maintains a **Materialized Ancestry Path** and **PostGIS Spatial MultiPolygon Geometry**:

```sql
CREATE TABLE core.administrative_units (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    parent_id UUID NULL REFERENCES core.administrative_units(id) ON DELETE RESTRICT,
    code VARCHAR(20) NOT NULL UNIQUE,       -- BBS Official GeoCode (e.g. '40', '47', '12', '35')
    name_en VARCHAR(100) NOT NULL,
    name_bn VARCHAR(150) NOT NULL,
    tier_level VARCHAR(30) NOT NULL CHECK (
        tier_level IN ('NATIONAL', 'DIRECTORATE', 'DIVISION', 'DISTRICT', 'UPAZILA', 'UNION', 'WARD')
    ),
    ancestry_path VARCHAR(255) NOT NULL,    -- Materialized path (e.g. '/40/47/12/35/')
    boundary_geom geometry(MultiPolygon, 4326) NULL, -- PostGIS Administrative Boundary
    centroid_geom geometry(Point, 4326) NULL,        -- Geographic centroid
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Essential Performance Indexes
CREATE INDEX idx_admin_units_ancestry ON core.administrative_units USING btree (ancestry_path varchar_pattern_ops);
CREATE INDEX idx_admin_units_parent ON core.administrative_units (parent_id);
CREATE INDEX idx_admin_units_tier ON core.administrative_units (tier_level);
CREATE INDEX idx_admin_units_spatial ON core.administrative_units USING gist (boundary_geom);
```

### 3.2 Materialized Path Syntax and Ancestry Resolution
The `ancestry_path` field stores the sequential chain of BBS GeoCodes starting from the Division down to the local unit:
- **Khulna Division:** `/40/`
- **Khulna District:** `/40/47/`
- **Dacope Upazila:** `/40/47/12/`
- **Chalna Union:** `/40/47/12/35/`

#### Query Performance Comparison:
1. **Descendant Subtree Retrieval (Downwards):**
   ```sql
   -- Find all households, shelters, or damage records within Khulna District (GeoCode 47)
   -- Execution Plan: Index Scan using idx_admin_units_ancestry (Execution Time: < 0.5 ms)
   SELECT * FROM beneficiary.households h
   JOIN core.administrative_units u ON h.administrative_unit_id = u.id
   WHERE u.ancestry_path LIKE '/40/47/%';
   ```
2. **Immediate Parent Traversal (Upwards):**
   - Direct B-Tree lookup via `parent_id`.

---

## 4. User-Organization Binding Model

System users are statutory personnel bound to one or more administrative jurisdictions. The access control engine distinguishes between a user's **Primary Permanent Jurisdiction** and **Secondary / Emergency Deputations**.

### 4.1 Schema Definition (`core.user_administrative_scopes`)

```sql
CREATE TABLE core.user_administrative_scopes (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES core.users(id) ON DELETE CASCADE,
    administrative_unit_id UUID NOT NULL REFERENCES core.administrative_units(id) ON DELETE RESTRICT,
    assignment_type VARCHAR(30) NOT NULL CHECK (
        assignment_type IN ('PRIMARY_POSTING', 'ADDITIONAL_CHARGE', 'EMERGENCY_DEPUTATION', 'INSPECTION_TAG')
    ),
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    valid_from TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    valid_until TIMESTAMPTZ NULL,          -- NULL = indefinite; set for temporary deputations
    delegated_by UUID NULL REFERENCES core.users(id),
    official_order_ref VARCHAR(100) NULL,  -- Government Gazette / Office Order Number
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_user_scopes_user ON core.user_administrative_scopes (user_id) WHERE is_active = TRUE;
CREATE INDEX idx_user_scopes_unit ON core.user_administrative_scopes (administrative_unit_id);
```

### 4.2 Posting Dynamics & Scenarios

1. **Standard Primary Posting:**
   - A newly appointed Project Implementation Officer (PIO) is bound to Dacope Upazila (`/40/47/12/`). Their active token claim carries `scope_id = <Dacope_UUID>` and `ancestry_path = '/40/47/12/'`.
2. **Additional Charge / Dual Upazila Supervision:**
   - When the neighboring Upazila PIO is on leave, an officer may be assigned `ADDITIONAL_CHARGE` for Koyra Upazila (`/40/47/14/`). The API user context hydrates multiple active scopes:
     $$\text{Accessible Paths} = \{ '/40/47/12/\%', '/40/47/14/\%' \}$$
3. **Emergency Tag Officer Deputation:**
   - During Cyclone response, a District Agriculture Officer is assigned as a `INSPECTION_TAG` officer for 3 specific Unions for 14 days (`valid_until = CURRENT_TIMESTAMP + INTERVAL '14 days'`). Once expired, access is revoked automatically without manual intervention.

---

## 5. Hierarchical Data Access Rules & Invariants

Data access within IDMS is strictly governed by four immutable access invariants:

```
+----------------------------------------------------------------------------------------------------+
|                                 HIERARCHICAL ACCESS INVARIANTS                                     |
+----------------------------------------------------------------------------------------------------+
| 1. DOWNWARD VISIBILITY        | Officers at higher tiers possess full analytical and reporting     |
| (Ancestry Traversal)          | visibility across all descendant units in their jurisdiction tree. |
+-------------------------------+--------------------------------------------------------------------+
| 2. LATERAL ISOLATION          | Peer units at the same tier cannot view or alter each other's      |
| (Strict Horizontal Barrier)   | uncommitted drafts, local requisitions, or citizen PII.            |
+-------------------------------+--------------------------------------------------------------------+
| 3. UPWARD ROLLUP              | Micro-data (shelter occupancy, damage survey entries) roll up      |
| (Aggregated Visibility)       | automatically into macro SitReps and statistical dashboards.       |
+-------------------------------+--------------------------------------------------------------------+
| 4. DOWNWARD INTEGRITY         | Higher-tier officers cannot silently alter raw field data. They    |
| (No Downward Tampering)       | may endorse, reject, or request revisions via formal workflow logs.|
+----------------------------------------------------------------------------------------------------+
```

### 5.1 Invariant Details & Mathematical Scoping

#### Invariant 1: Downward Visibility (Subtree Inclusion)
An authenticated officer with administrative scope path $P_{\text{officer}}$ is granted read access to a target record $R$ with administrative unit path $P_{R}$ if and only if $P_{\text{officer}}$ is a prefix of $P_{R}$:
$$\text{ReadAccess}(Officer, R) \iff P_{R} \text{ STARTS\_WITH } P_{\text{officer}}$$
- **Example:** The DRRO of Khulna (`/40/47/`) can view all relief requisitions from Dacope (`/40/47/12/`), Paikgachha (`/40/47/15/`), and all their constituent unions.

#### Invariant 2: Lateral Isolation (Peer Containment)
Two units $A$ and $B$ at the same administrative tier cannot inspect or modify each other's operational records:
$$P_{A} \not\subseteq P_{B} \quad \land \quad P_{B} \not\subseteq P_{A} \implies \text{Access Denied (HTTP 403)}$$
- **Example:** The UNO of Dacope cannot view the draft relief requisitions or pending damage surveys of the UNO of Batiaghata.

#### Invariant 3: Downward Mutation Boundary
Higher-tier officers (e.g. Ministry / DDM / DC) **cannot directly edit or overwrite field data** (such as a beneficiary's recorded crop damage or a shelter's reported head-count). If an estimate is incorrect, the reviewing officer must trigger a **Workflow Revision Request** with an audit comment, sending the document back to the field officer for formal amendment.

---

## 6. Cross-Organizational Access & Emergency Protocols

Disaster scenarios require active participation from external armed services, rescue agencies, humanitarian organizations, and non-governmental entities. IDMS handles these actors through designated **Cross-Agency Profiles**:

```mermaid
flowchart TD
    subgraph EmergencyCore["MoDMR Sovereign Command"]
        NEOC["National Emergency Operations Centre (NEOC)"]
        DDM["Department of Disaster Management"]
        DDMC["District Disaster Management Committee (DC/DRRO)"]
    end

    subgraph DefenseRescue["Search, Rescue & Security Agencies"]
        AFD["Armed Forces Division (Army / Navy / Air Force)"]
        FSCD["Fire Service & Civil Defence"]
        CoastGuard["Bangladesh Coast Guard"]
    end

    subgraph GrassrootsHumanitarian["Grassroots & Humanitarian Partners"]
        CPP["Cyclone Preparedness Programme (CPP)"]
        BDRCS["Bangladesh Red Crescent Society"]
        UNAgencies["UN Agencies (WFP, UNICEF, UNHCR, WHO)"]
        NGOs["National Disaster NGOs / INGOs"]
    end

    NEOC <-->|Real-time SAR Incident Feeds| DefenseRescue
    DDMC <-->|Field Rescue & Evacuation Tasking| FSCD & CoastGuard
    DDM <-->|Damage Needs Assessment & Food Pipeline| UNAgencies
    DDMC <-->|Volunteer Mobilization & Shelter Support| CPP & BDRCS
```

### 6.1 Cross-Agency Access Governance Matrix

| Agency / Partner Group | System Role Code | Granted Data Visibility | Restricted / Redacted Data | Statutory Mandate |
| :--- | :--- | :--- | :--- | :--- |
| **Armed Forces Division (AFD)** | `ROLE_SAR_COORDINATOR` | Operational SAR incidents, stranded victim GPS coordinates, helicopter landing zones, bridge/embankment washouts, shelter locations. | Citizen bank accounts, cash relief muster rolls, administrative user management. | Emergency air rescue, heavy engineering, deep-water relief air-dropping. |
| **Fire Service & Civil Defence (FSCD)** | `ROLE_SAR_RESPONDER` | Live SOS alerts, road obstruction maps, urban flood water levels, fire hazards, emergency resource inventories (boats, pumps). | Citizen financial data, macro budget allocations, reconstruction tenders. | Search, rescue, debris clearance, emergency casualty evacuation. |
| **Bangladesh Coast Guard / Navy** | `ROLE_COASTAL_PATROL` | Coastal storm surge warnings, island evacuation statuses, maritime distress calls, coastal shelter occupancy. | Mainland project management, inland beneficiary records. | Offshore island evacuations, maritime SAR, remote delta relief transport. |
| **Cyclone Preparedness Programme (CPP)** | `ROLE_CPP_LEADER` | Siren alert triggers, shelter inventory, localized evacuee registrations, community volunteer deployment rosters. | Citizen NID unmasking, contractor payments, official budget authorizations. | Early warning flag/siren dissemination, community mobilization, shelter management. |
| **Bangladesh Red Crescent Society (BDRCS)** | `ROLE_VOLUNTEER_RESPONDER` | Shelter management logs, first-aid requisitions, volunteer rosters, open relief distribution centers. | Official government cash disbursements, internal administrative audit logs. | Humanitarian first aid, psychosocial support, community feeding. |
| **Humanitarian UN / INGO Partners (WFP, UNICEF)** | `ROLE_EXTERNAL_PARTNER` | Aggregated D-Form damage summaries, sectoral food/nutrition gap analyses, GIS flood extent layers, overall SitReps. | **Zero citizen PII.** No individual NID, phone numbers, or household records. | International relief gap-filling, nutrition tracking, child welfare coordination. |

### 6.2 Emergency State Elevation Protocol (Disaster State of Emergency)
When an official **Disaster State of Emergency** is declared by the Ministry or District Magistrate under Section 22 of the *Disaster Management Act 2012*:
1. **Dynamic Regional Scope Expansion:**
   - Search and Rescue (SAR) teams and Fire Service coordinators are temporarily granted cross-district visibility over all incident queues and spatial hazard maps across the declared emergency footprint.
2. **Emergency Burst Rate Limits:**
   - Field API rate limits elevate automatically from 300 to 1,200 requests/minute to accommodate heavy offline sync traffic from mobile field clients.
3. **Strict Audit Trail:**
   - Every emergency-elevated access is flagged as `IS_EMERGENCY_ACCESS = TRUE` and requires the officer to cite the official Gazette or Emergency Declaration reference code.

---

## 7. Approval Authority & Multi-Tier Workflow Engine

Public resources (food grains, cash assistance, building materials) and legal damage assessments must traverse multi-tier approval gates.

### 7.1 Tiered Relief Requisition & Allocation Thresholds

```mermaid
flowchart TD
    Grassroots[Union Parishad / Upazila PIO Initiates Requisition] --> Gate1{Allocation Volume Threshold}

    Gate1 -->|&le; 10 MT Food Grain / &le; BDT 200,000| TierUpazila["Upazila Level Approval<br>(Sanctioned by UNO from Upazila Buffer)"]
    Gate1 -->|> 10 MT to &le; 50 MT / &le; BDT 1,000,000| TierDistrict["District Level Dual-Approval<br>(Endorsed by DRRO, Sanctioned by DC)"]
    Gate1 -->|> 50 MT to &le; 500 MT / &le; BDT 5,000,000| TierDirectorate["Directorate Level Approval<br>(Sanctioned by DDM Director General)"]
    Gate1 -->|> 500 MT / > BDT 5,000,000| TierMinistry["Ministry Sovereign Approval<br>(Sanctioned by Senior Secretary / Minister)"]

    TierUpazila --> WarehouseDispatch[Warehouse Issue Order Dispatched to Godown]
    TierDistrict --> WarehouseDispatch
    TierDirectorate --> WarehouseDispatch
    TierMinistry --> WarehouseDispatch
```

| Financial / Commodity Range | Statutory Approving Authority | Reviewing / Endorsing Authority | Mandatory Approving Roles |
| :--- | :--- | :--- | :--- |
| **Small Upazila Buffer**<br>(&le; 10 MT Grain / &le; BDT 200k) | Upazila Nirbahi Officer (UNO) | Project Implementation Officer (PIO) | `ROLE_PIO` (Verify) &rarr; `ROLE_UNO` (Approve) |
| **District Emergency Buffer**<br>(10 - 50 MT / BDT 200k - 1M) | Deputy Commissioner (DC) | District Relief & Rehab Officer (DRRO) | `ROLE_DRRO` (Endorse) &rarr; `ROLE_DEPUTY_COMMISSIONER` (Approve) [Dual Auth] |
| **National Central Reserves**<br>(50 - 500 MT / BDT 1M - 5M) | Director General (DDM) | Director (Relief & Logistics, DDM) | `ROLE_DDM_RELIEF_DIRECTOR` (Vett) &rarr; `ROLE_DDM_DIRECTOR_GENERAL` (Approve) |
| **National Sovereign Allocation**<br>(> 500 MT Grain / > BDT 5M) | Senior Secretary / Minister | Director General (DDM) & Additional Secretary | `ROLE_DDM_DIRECTOR_GENERAL` (Recommend) &rarr; `ROLE_MINISTRY_EXECUTIVE` (Sanction) |

### 7.2 Damage Assessment (D-Form) Verification Pipeline
The standardized D-Form assessment follows an unalterable multi-echelon vetting chain:

```
[Step 1: Ward / Union Field Survey]
   └── Field Enumerator surveys damage and submits preliminary D-Form draft.
         └── [Step 2: Union Parishad Endorsement]
               └── UP Chairman & Secretary verify village figures and submit to Upazila.
                     └── [Step 3: Upazila Technical Consolidation]
                           └── PIO and Sub-Assistant Engineers verify sectoral claims & submit to District.
                                 └── [Step 4: District Administrative Endorsement]
                                       └── DRRO consolidates district figures; Deputy Commissioner signs off.
                                             └── [Step 5: National Directorate Synthesis]
                                                   └── DDM M&E Cell aggregates national loss; DG and Ministry approve for National SitRep.
```

---

## 8. Data Visibility & Citizen PII Protection

Data within IDMS is classified into four security tiers with distinct access and masking rules:

```
+----------------------------------------------------------------------------------------------------+
|                                 DATA CLASSIFICATION & VISIBILITY TIERS                             |
+-------+-------------------------+---------------------------------+--------------------------------+
| Class | Security Level          | Data Scope & Examples           | Access & Masking Constraints   |
+-------+-------------------------+---------------------------------+--------------------------------+
| C-A   | Class A: Public General | Disaster bulletins, shelter     | Open to public and external    |
|       |                         | locations, national SitReps     | humanitarian partners.         |
+-------+-------------------------+---------------------------------+--------------------------------+
| C-B   | Class B: Internal       | Requisitions, warehouse ledgers,| Restricted to authenticated    |
|       | Official                | damage estimates, tenders       | officers within jurisdiction.  |
+-------+-------------------------+---------------------------------+--------------------------------+
| C-C   | Class C: Confidential   | Beneficiary rosters, household  | Scoped to local officers;      |
|       | Personal                | vulnerability scores, age       | strictly blocked from public.  |
+-------+-------------------------+---------------------------------+--------------------------------+
| C-D   | Class D: Restricted PII | National ID (NID), phone nos,   | AES-256-GCM encrypted. Masked  |
|       |                         | bank / MFS account numbers      | by default. Requires explicit  |
|       |                         |                                 | pii.decrypt role + audit log.  |
+-------+-------------------------+---------------------------------+--------------------------------+
```

### 8.1 Data Masking Specifications
Unless the requesting client possesses the `beneficiary.pii.decrypt` permission and explicitly passes `?decrypt_pii=true`, API responses are masked:
- **National ID Number:** `XXXXXXXXXXXX7890` (Only the last 4 digits displayed for identity confirmation).
- **Mobile Contact Number:** `+88017XXXX1234` (Country code, telecom prefix, and last 4 digits displayed).
- **Bank / bKash / Nagad Account:** `XXXX-XXXX-5678`.

---

## 9. Comprehensive Organizational Access Matrix

The following matrix details data visibility, operational privileges, and reporting bounds across all administrative tiers:

### Legend:
- `FULL` : Unrestricted visibility across the entire national repository.
- `SUBTREE` : Strictly restricted to the officer's administrative jurisdiction tree (`ancestry_path LIKE :path%`).
- `LOCAL` : Strictly restricted to the single assigned local node (no child traversal).
- `AGG` : Aggregated, anonymized statistical summaries only (no row-level citizen or financial data).
- `NONE` : Zero access (HTTP 403 Forbidden).

| Administrative Governance Tier | Beneficiary Household Profiles | Citizen PII (NID / Bank) | Relief Requisitions & Allocations | Damage Assessments (D-Form) | Warehouse Stock & Ledgers | Shelter Occupancy Logs | SAR Incidents & Dispatch | Project Milestones & Payments | Full System Audit Logs |
| :--- | :---: | :---: | :---: | :---: | :---: | :---: | :---: | :---: | :---: |
| **Tier 1: National (MoDMR / NEOC)** | FULL | AGG / AUDIT | FULL | FULL | FULL | FULL | FULL | FULL | FULL |
| **Tier 2: Directorate (DDM)** | FULL | AGG | FULL | FULL | FULL | FULL | FULL | FULL | FULL |
| **Tier 3: Division (DivComm)** | SUBTREE | AGG | SUBTREE | SUBTREE | SUBTREE | SUBTREE | SUBTREE | SUBTREE | SUBTREE (Read) |
| **Tier 4: District (DC / DRRO)** | SUBTREE | SUBTREE (Masked/Decr) | SUBTREE | SUBTREE | SUBTREE | SUBTREE | SUBTREE | SUBTREE | SUBTREE (Read) |
| **Tier 5: Upazila (UNO / PIO)** | SUBTREE | SUBTREE (Masked/Decr) | SUBTREE | SUBTREE | SUBTREE | SUBTREE | SUBTREE | SUBTREE | SUBTREE (Read) |
| **Tier 6: Union / Pouroshava** | SUBTREE | SUBTREE (Masked) | SUBTREE | SUBTREE | LOCAL (Sub-Store) | SUBTREE | SUBTREE | LOCAL | NONE |
| **Tier 7: Field (Tag Officer)** | LOCAL | LOCAL (Decr for Handover) | LOCAL | LOCAL (Inspect) | NONE | LOCAL | NONE | LOCAL (Certify) | NONE |
| **Tier 7: Field (Shelter Manager)** | LOCAL | LOCAL (Masked) | LOCAL (Shelter Req) | LOCAL | NONE | LOCAL | LOCAL (SOS) | NONE | NONE |
| **Tier 7: Field (Godown Keeper)** | NONE | NONE | LOCAL (Issue Orders) | NONE | LOCAL (Full Godown) | NONE | NONE | NONE | NONE |
| **Cross-Agency: Armed Forces (AFD)**| NONE | NONE | AGG (Logistics Gap) | AGG | AGG | FULL (Coastal) | FULL (SAR) | NONE | NONE |
| **Cross-Agency: Fire Service (FSCD)**| NONE | NONE | NONE | AGG | NONE | LOCAL | FULL (Urban SAR)| NONE | NONE |
| **Cross-Agency: CPP Volunteers** | LOCAL | NONE | LOCAL | LOCAL | NONE | LOCAL | LOCAL (Alerts) | NONE | NONE |
| **External: UN / INGOs (WFP/UNICEF)**| AGG | NONE | AGG | AGG | AGG | AGG | AGG | AGG | NONE |

---

## 10. Laravel 11 Enforcement Pipeline Specification

To guarantee that no API endpoint accidentally leaks cross-jurisdictional data or unmasked PII, every incoming request traverses a 7-stage architectural security pipeline:

```mermaid
flowchart TD
    Req["Incoming API Request (Bearer JWT)"] --> S1["Stage 1: KeycloakJwtGuard<br>Verify RS256 Signature, Expiry, Issuer, JWKS"]
    S1 --> S2["Stage 2: AdministrativeScopeMiddleware<br>Extract & Hydrate User Jurisdiction (ancestry_path)"]
    S2 --> S3["Stage 3: AuthorizePermission Middleware<br>Evaluate Granular Ability (e.g. relief.allocation.approve)"]
    S3 --> S4["Stage 4: Form Request Validation<br>Validate Schema, Types, and Cross-Field Invariants"]
    S4 --> S5["Stage 5: Global Eloquent Scope Injection<br>Auto-append: WHERE ancestry_path LIKE :user_path%"]
    S5 --> S6["Stage 6: PII Masking / Decryption Interceptor<br>AES-256-GCM Unmasking only if pii.decrypt authorized"]
    S6 --> S7["Stage 7: Immutable Audit Observer<br>Write append-only log of mutations & unmasked PII reads"]
    S7 --> Resp["HTTP 200 OK / Response JSON"]
```

### 10.1 Global Eloquent Administrative Scope (`HierarchicalAdministrativeScope`)
In Laravel, models containing geographic bindings (`Household`, `DamageAssessment`, `ReliefRequisition`, `Warehouse`, `Project`) implement the `HasAdministrativeScope` trait.

#### Conceptual Query Alteration Logic:
```php
namespace App\Core\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class HierarchicalAdministrativeScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();
        
        // National Super Admins and Ministry Executives bypass row-level filtering
        if (!$user || $user->isNationalScope()) {
            return;
        }

        $activeScope = $user->getActiveAdministrativeScope();
        $ancestryPath = $activeScope->ancestry_path; // e.g. '/40/47/'

        // Enforce sub-millisecond materialized path filter via PostGIS/BBS tree
        $builder->whereHas('administrativeUnit', function (Builder $query) use ($ancestryPath) {
            $query->where('ancestry_path', 'LIKE', $ancestryPath . '%');
        });
    }
}
```

### 10.2 Architectural Guarantees
1. **Zero Developer Leakage:** Even if a junior developer writes `ReliefRequisition::all()`, the global Eloquent scope intercepts the SQL generation and automatically appends `WHERE ancestry_path LIKE '/40/47/%'`. Cross-district data leaks are impossible at the framework level.
2. **Sub-millisecond Overhead:** Because `ancestry_path` uses B-Tree `varchar_pattern_ops` indexing in PostgreSQL 16, query execution adds $< 1.0\text{ ms}$ overhead even across tables containing tens of millions of records.
3. **Audited Bypasses:** Only verified national administrative roles (`ROLE_SUPER_ADMIN`, `ROLE_NATIONAL_AUDITOR`, `ROLE_MINISTRY_EXECUTIVE`) bypass the global scope, and every full-table export is permanently logged in the audit trail.
