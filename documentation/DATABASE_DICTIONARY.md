# Integrated Disaster Management System (IDMS)
## Enterprise Database Data Dictionary & Complete Schema Specification

| Document Information | Details |
| :--- | :--- |
| **System** | Integrated Disaster Management System (IDMS) |
| **Document Purpose** | Exhaustive Data Dictionary, Data Types, Constraints & Index Specifications |
| **Target Engine** | PostgreSQL 16.x + PostGIS 3.4+ |
| **Identifier Standard**| UUIDv7 (RFC 9562) |
| **Document Version** | 1.0.0-PROPOSED |
| **Status** | Prepared for Architecture Review (No Migrations Generated) |

---

## 1. Schema: `core` (Administration & IAM)

### 1.1 `core.administrative_units`
Stores the official geographic hierarchy of Bangladesh (Division, District, Upazila, Union/Ward).

| Column Name | Data Type | Null | Default / Constraints | Indexes | Description |
| :--- | :--- | :---: | :--- | :--- | :--- |
| `id` | `uuid` | NO | PK | B-Tree (PK) | Record identifier (UUIDv7) |
| `parent_id` | `uuid` | YES | FK &rarr; `core.administrative_units(id)` ON DELETE RESTRICT | B-Tree | Parent administrative unit |
| `code` | `varchar(20)` | NO | UNIQUE | B-Tree (UQ) | BBS Official GeoCode |
| `name_en` | `varchar(100)`| NO | | B-Tree | English name |
| `name_bn` | `varchar(150)`| NO | | | Bangla name (Unicode UTF-8) |
| `tier_level` | `varchar(30)` | NO | CHECK in ('DIVISION', 'DISTRICT', 'UPAZILA', 'UNION', 'WARD') | B-Tree | Governance tier level |
| `ancestry_path`| `varchar(255)`| NO | DEFAULT '/' | B-Tree (`varchar_pattern_ops`) | Materialized hierarchy path (e.g. `/10/20/30/`) |
| `boundary_geom`| `geometry(MultiPolygon, 4326)`| YES | | GIST | PostGIS administrative boundary polygon |
| `created_at` | `timestamptz` | NO | CURRENT_TIMESTAMP | | Audit record creation timestamp |
| `updated_at` | `timestamptz` | NO | CURRENT_TIMESTAMP | | Audit record modification timestamp |

---

### 1.2 `core.users`
Internal system user registry synchronized with Keycloak IdP.

| Column Name | Data Type | Null | Default / Constraints | Indexes | Description |
| :--- | :--- | :---: | :--- | :--- | :--- |
| `id` | `uuid` | NO | PK | B-Tree (PK) | User identifier (UUIDv7) |
| `keycloak_sub` | `varchar(255)`| NO | UNIQUE | B-Tree (UQ) | Keycloak Subject ID (`sub` claim) |
| `email` | `varchar(150)`| NO | UNIQUE | B-Tree (UQ) | Official government email address |
| `mobile_number`| `varchar(20)` | YES | | | Contact phone number |
| `full_name_en`| `varchar(150)`| NO | | | User name in English |
| `full_name_bn`| `varchar(200)`| YES | | | User name in Bangla |
| `role_code` | `varchar(50)` | NO | | B-Tree | Primary role (e.g. `UNO_PIO`, `DC_DRRO`) |
| `administrative_unit_id` | `uuid` | NO | FK &rarr; `core.administrative_units(id)` ON DELETE RESTRICT | B-Tree | Assigned administrative jurisdiction scope |
| `designation` | `varchar(100)`| YES | | | Official designation / job title |
| `is_active` | `boolean` | NO | true | B-Tree | Account active flag |
| `last_login_at`| `timestamptz` | YES | | | Timestamp of latest successful authentication |
| `created_at` | `timestamptz` | NO | CURRENT_TIMESTAMP | | Audit timestamp |
| `updated_at` | `timestamptz` | NO | CURRENT_TIMESTAMP | | Audit timestamp |
| `deleted_at` | `timestamptz` | YES | NULL | B-Tree | Soft delete timestamp |

---

## 2. Schema: `beneficiary` (Vulnerability & Identity)

### 2.1 `beneficiary.households`
Master registry of vulnerable and disaster-affected households.

| Column Name | Data Type | Null | Default / Constraints | Indexes | Description |
| :--- | :--- | :---: | :--- | :--- | :--- |
| `id` | `uuid` | NO | PK | B-Tree (PK) | Household identifier (UUIDv7) |
| `household_code`| `varchar(30)` | NO | UNIQUE | B-Tree (UQ) | Unique national household code |
| `administrative_unit_id`| `uuid`| NO | FK &rarr; `core.administrative_units(id)` ON DELETE RESTRICT | B-Tree | Union / Ward of primary residence |
| `village_ward_name` | `varchar(150)`| YES | | | Local village / neighborhood name |
| `hoh_name_en` | `varchar(150)`| NO | | | Head of Household name (English) |
| `hoh_name_bn` | `varchar(200)`| NO | | | Head of Household name (Bangla) |
| `hoh_nid_hash` | `char(64)` | NO | UNIQUE | B-Tree (UQ) | Keyed HMAC-SHA256 hash for deduplication |
| `hoh_nid_encrypted`| `text` | NO | | | AES-256-GCM encrypted NID string (PII) |
| `hoh_gender` | `varchar(20)` | NO | CHECK in ('MALE', 'FEMALE', 'OTHER') | B-Tree | Gender of HoH |
| `hoh_mobile_encrypted`| `text`| YES | | | AES-256-GCM encrypted contact mobile (PII) |
| `family_member_count` | `int` | NO | CHECK (family_member_count > 0) | | Total individuals residing in household |
| `vulnerability_score` | `decimal(5,2)`| NO | 0.00 | B-Tree | Socioeconomic vulnerability rating (0-100) |
| `is_displaced` | `boolean` | NO | false | B-Tree | Whether displaced into emergency shelter |
| `dwelling_geom` | `geometry(Point, 4326)`| YES | | GIST | Exact GPS location of household dwelling |
| `created_by` | `uuid` | YES | FK &rarr; `core.users(id)` | B-Tree | Enrolling officer |
| `created_at` | `timestamptz` | NO | CURRENT_TIMESTAMP | B-Tree | Audit creation timestamp |
| `updated_at` | `timestamptz` | NO | CURRENT_TIMESTAMP | | Audit modification timestamp |
| `deleted_at` | `timestamptz` | YES | NULL | B-Tree | Soft delete timestamp |

---

### 2.2 `beneficiary.household_members`
Individual household members.

| Column Name | Data Type | Null | Default / Constraints | Indexes | Description |
| :--- | :--- | :---: | :--- | :--- | :--- |
| `id` | `uuid` | NO | PK | B-Tree (PK) | Member identifier (UUIDv7) |
| `household_id` | `uuid` | NO | FK &rarr; `beneficiary.households(id)` ON DELETE CASCADE | B-Tree | Parent household |
| `full_name_en` | `varchar(150)`| NO | | | Full name (English) |
| `full_name_bn` | `varchar(200)`| NO | | | Full name (Bangla) |
| `nid_hash` | `char(64)` | YES | | B-Tree | HMAC-SHA256 NID hash (if adult) |
| `gender` | `varchar(20)` | NO | CHECK in ('MALE', 'FEMALE', 'OTHER') | | Gender |
| `age` | `int` | NO | CHECK (age >= 0 AND age <= 130) | B-Tree | Age in years |
| `has_disability`| `boolean` | NO | false | B-Tree | Disability flag |
| `is_pregnant_lactating`| `boolean`| NO | false | B-Tree | Pregnant or lactating mother flag |
| `created_at` | `timestamptz` | NO | CURRENT_TIMESTAMP | | Audit timestamp |
| `updated_at` | `timestamptz` | NO | CURRENT_TIMESTAMP | | Audit timestamp |

---

### 2.3 `beneficiary.nid_verifications`
Audit trail and workflow state of National ID verification requests across authorized providers and mock adapters.

| Column Name | Data Type | Null | Default / Constraints | Indexes | Description |
| :--- | :--- | :---: | :--- | :--- | :--- |
| `id` | `uuid` | NO | PK | B-Tree (PK) | Verification transaction ID |
| `household_id` | `uuid` | YES | FK &rarr; `beneficiary.households(id)` ON DELETE SET NULL | B-Tree | Linked beneficiary household (optional) |
| `household_member_id` | `uuid` | YES | FK &rarr; `beneficiary.household_members(id)` ON DELETE SET NULL | B-Tree | Linked household member (optional) |
| `administrative_unit_id` | `uuid` | YES | FK &rarr; `core.administrative_units(id)` ON DELETE SET NULL | B-Tree | Jurisdictional unit for 7-tier hierarchical scoping |
| `nid_hash` | `char(64)` | NO | | B-Tree | Keyed HMAC-SHA256 hash of queried NID |
| `nid_masked` | `varchar(30)` | YES | | | Masked display NID (e.g. `1990*******123`) |
| `nid_encrypted_payload` | `text` | YES | | | Temporary AES-256 cipher for queued async processing (wiped upon completion) |
| `date_of_birth` | `date` | YES | | | Submitted date of birth |
| `submitted_name_en` | `varchar(150)` | YES | | | Submitted citizen name (English) for matching |
| `verification_status` | `varchar(30)` | NO | DEFAULT 'PENDING' CHECK in ('PENDING', 'PROCESSING', 'VERIFIED', 'FAILED', 'MANUAL_REVIEW_REQUIRED') | B-Tree | Current verification workflow state |
| `verification_reference` | `varchar(100)` | YES | UNIQUE | B-Tree (UQ) | Human-readable verification reference (e.g. `NID-REF-20261125-...`) |
| `verification_token` | `varchar(255)` | YES | | | Cryptographic verification proof from adapter |
| `provider_code` | `varchar(50)` | NO | DEFAULT 'MOCK_ADAPTER' | B-Tree | Service adapter used (`MOCK_ADAPTER`, `GOV_NID_API`) |
| `is_mock` | `boolean` | NO | true | B-Tree | Explicitly distinguishes mock development verifications from official government responses |
| `response_payload_hash` | `char(64)` | YES | | | SHA-256 checksum of sanitized upstream response |
| `matched_fields` | `jsonb` | YES | | | Minimum necessary verification outcome flags (never raw API payload) |
| `attempt_count` | `int` | NO | 0 | | Number of verification provider attempts executed |
| `last_error_code` | `varchar(50)` | YES | | | Latest sanitized provider error code |
| `last_error_message` | `text` | YES | | | Latest sanitized non-PII error summary |
| `manual_override` | `boolean` | NO | false | B-Tree | True if resolved via authorized manual review override |
| `override_reason` | `text` | YES | | | Mandatory officer justification for manual review decision |
| `previous_status` | `varchar(30)` | YES | | | Status prior to manual override |
| `requested_by_user_id` | `uuid` | YES | FK &rarr; `core.users(id)` ON DELETE SET NULL | B-Tree | Officer who initiated verification request |
| `verified_by_user_id` | `uuid` | YES | FK &rarr; `core.users(id)` ON DELETE SET NULL | B-Tree | Officer/processor finalizing verification |
| `reviewed_by_user_id` | `uuid` | YES | FK &rarr; `core.users(id)` ON DELETE SET NULL | B-Tree | Designated reviewing officer for manual override (4-eye SoD enforced) |
| `verified_at` | `timestamptz` | YES | | B-Tree | Verification completion timestamp |
| `reviewed_at` | `timestamptz` | YES | | | Manual review completion timestamp |
| `created_at` | `timestamptz` | NO | CURRENT_TIMESTAMP | B-Tree | Audit creation timestamp |
| `updated_at` | `timestamptz` | NO | CURRENT_TIMESTAMP | | Audit modification timestamp |
| `deleted_at` | `timestamptz` | YES | NULL | B-Tree | Soft delete timestamp |

---

### 2.4 `beneficiary.nid_verification_attempts`
Per-attempt execution log for NID provider requests, retries, timeouts, and availability telemetry.

| Column Name | Data Type | Null | Default / Constraints | Indexes | Description |
| :--- | :--- | :---: | :--- | :--- | :--- |
| `id` | `uuid` | NO | PK | B-Tree (PK) | Attempt record ID |
| `nid_verification_id` | `uuid` | NO | FK &rarr; `beneficiary.nid_verifications(id)` ON DELETE CASCADE | B-Tree | Parent verification record |
| `attempt_number` | `int` | NO | 1 | | Sequential attempt number (1, 2, ...) |
| `provider_code` | `varchar(50)` | NO | | B-Tree | Adapter identifier executed for this attempt |
| `is_mock` | `boolean` | NO | true | | Whether attempt used mock adapter |
| `status` | `varchar(30)` | NO | CHECK in ('VERIFIED', 'FAILED', 'MANUAL_REVIEW_REQUIRED', 'TIMEOUT', 'ERROR') | B-Tree | Outcome of this specific provider call |
| `http_status` | `int` | YES | | | Upstream HTTP status code (if applicable) |
| `response_time_ms` | `int` | YES | | | Round-trip provider latency in milliseconds |
| `error_code` | `varchar(50)` | YES | | B-Tree | Sanitized error code (never contains NID or secrets) |
| `error_message` | `text` | YES | | | Sanitized error description |
| `attempted_by_user_id` | `uuid` | YES | FK &rarr; `core.users(id)` ON DELETE SET NULL | | Triggering officer (null for queue worker) |
| `attempted_at` | `timestamptz` | NO | CURRENT_TIMESTAMP | B-Tree | Attempt execution timestamp |
| `created_at` | `timestamptz` | NO | CURRENT_TIMESTAMP | | Record creation timestamp |
| `updated_at` | `timestamptz` | NO | CURRENT_TIMESTAMP | | Record modification timestamp |

---

## 3. Schema: `disaster` (Disasters & Shelters)

### 3.0 `disaster.disaster_categories`
Configurable catalog of disaster categories and subtypes (Flood, Cyclone, Drought, Earthquake, Landslide, Fire, River Erosion, Lightning, Flash Flood, Cold Wave, etc.).

| Column Name | Data Type | Null | Default / Constraints | Indexes | Description |
| :--- | :--- | :---: | :--- | :--- | :--- |
| `id` | `uuid` | NO | PK | B-Tree (PK) | Category identifier |
| `code` | `varchar(50)` | NO | UNIQUE | B-Tree (UQ) | Uppercase category code (e.g. `FLOOD`, `CYCLONE`) |
| `name_en` | `varchar(120)` | NO | | | English category name |
| `name_bn` | `varchar(150)` | NO | | | Bangla category name |
| `subtypes` | `jsonb` | YES | | | Supported subtypes (`[{code, name_en, name_bn}]`) |
| `icon` | `varchar(60)` | YES | | | UI Bootstrap icon class |
| `color_hex` | `varchar(20)` | YES | | | Map/badge color token |
| `is_system` | `boolean` | NO | true | | Built-in MoDMR category indicator |
| `is_active` | `boolean` | NO | true | B-Tree | Active flag |
| `created_at` | `timestamptz` | NO | CURRENT_TIMESTAMP | | Creation timestamp |
| `updated_at` | `timestamptz` | NO | CURRENT_TIMESTAMP | | Modification timestamp |

---

### 3.1 `disaster.disaster_events`
National and regional disaster declarations, lifecycle state, spatial epicenter/boundary, and reporting metadata.

| Column Name | Data Type | Null | Default / Constraints | Indexes | Description |
| :--- | :--- | :---: | :--- | :--- | :--- |
| `id` | `uuid` | NO | PK | B-Tree (PK) | Disaster identifier (UUIDv7) |
| `category_id` | `uuid` | YES | FK &rarr; `disaster.disaster_categories(id)` ON DELETE SET NULL | B-Tree | Category catalog reference |
| `disaster_code` | `varchar(30)` | NO | UNIQUE | B-Tree (UQ) | Official identification code (e.g. `CYC-REMAL-2024`) |
| `name_en` | `varchar(150)` | NO | | B-Tree | Event title (English) |
| `name_bn` | `varchar(200)` | NO | | | Event title (Bangla) |
| `disaster_type` | `varchar(50)` | NO | | B-Tree | Category code (`CYCLONE`, `FLOOD`, `DROUGHT`, `EARTHQUAKE`, `LANDSLIDE`, `FIRE`, `RIVER_EROSION`, `LIGHTNING`, etc.) |
| `subtype` | `varchar(80)` | YES | | B-Tree | Subtype classification (e.g. `MONSOON_FLOOD`, `SUPER_CYCLONE`) |
| `description_en` | `text` | YES | | | Detailed event description (English) |
| `description_bn` | `text` | YES | | | Detailed event description (Bangla) |
| `severity_level` | `varchar(30)` | NO | CHECK in ('ADVISORY', 'WARNING', 'DANGER', 'GREAT_DANGER', 'CATASTROPHIC') | B-Tree | Crisis alert level |
| `lifecycle_phase` | `varchar(30)` | NO | CHECK in ('REPORTED', 'UNDER_ASSESSMENT', 'VERIFIED', 'ACTIVE', 'RESPONSE_ONGOING', 'RECOVERY', 'CLOSED') | B-Tree | 7-stage disaster lifecycle state |
| `declaration_date` | `timestamptz` | NO | | B-Tree | Reporting / declaration timestamp |
| `start_date` | `date` | YES | | B-Tree | Disaster onset date |
| `end_date` | `date` | YES | | | Disaster conclusion date |
| `epicenter_lat` | `decimal(10,7)` | YES | | | Primary latitude (WGS84) |
| `epicenter_lng` | `decimal(10,7)` | YES | | | Primary longitude (WGS84) |
| `affected_areas_summary` | `text` | YES | | | Human-readable summary of affected areas |
| `affected_zone_geojson` | `jsonb` | YES | | | Portable GeoJSON boundary polygon(s) |
| `location_geom` | `geometry(Point, 4326)` | YES | | GIST | PostGIS epicenter point geometry |
| `affected_zone_geom` | `geometry(MultiPolygon, 4326)` | YES | | GIST | PostGIS composite spatial footprint |
| `is_national` | `boolean` | NO | false | B-Tree | True for nationwide events visible to all tiers |
| `is_emergency_declared` | `boolean` | NO | false | B-Tree | Emergency state declaration flag |
| `created_by` | `uuid` | YES | FK &rarr; `core.users(id)` ON DELETE SET NULL | B-Tree | Reporting officer |
| `updated_by` | `uuid` | YES | FK &rarr; `core.users(id)` ON DELETE SET NULL | B-Tree | Last updating officer |
| `verified_by` | `uuid` | YES | FK &rarr; `core.users(id)` ON DELETE SET NULL | B-Tree | Verifying officer |
| `verified_at` | `timestamptz` | YES | | | Verification timestamp |
| `created_at` | `timestamptz` | NO | CURRENT_TIMESTAMP | B-Tree | Audit timestamp |
| `updated_at` | `timestamptz` | NO | CURRENT_TIMESTAMP | | Audit timestamp |
| `deleted_at` | `timestamptz` | YES | NULL | B-Tree | Soft delete timestamp |

---

### 3.1a `disaster.disaster_locations`
Affected administrative units (Division, District, Upazila, Union, Ward) and localized coordinates/boundaries linked to each disaster.

| Column Name | Data Type | Null | Default / Constraints | Indexes | Description |
| :--- | :--- | :---: | :--- | :--- | :--- |
| `id` | `uuid` | NO | PK | B-Tree (PK) | Location mapping ID |
| `disaster_event_id` | `uuid` | NO | FK &rarr; `disaster.disaster_events(id)` ON DELETE CASCADE | B-Tree | Parent disaster event |
| `administrative_unit_id` | `uuid` | NO | FK &rarr; `core.administrative_units(id)` ON DELETE RESTRICT | B-Tree | Linked Division/District/Upazila/Union/Ward |
| `latitude` | `decimal(10,7)` | YES | | | Local marker latitude |
| `longitude` | `decimal(10,7)` | YES | | | Local marker longitude |
| `boundary_geojson` | `jsonb` | YES | | | Local affected boundary GeoJSON |
| `location_geom` | `geometry(Point, 4326)` | YES | | GIST | PostGIS local point |
| `severity_override` | `varchar(30)` | YES | | | Localized severity if different from overall event |
| `notes` | `text` | YES | | | Location-specific remarks |
| `created_at` | `timestamptz` | NO | CURRENT_TIMESTAMP | | Creation timestamp |
| `updated_at` | `timestamptz` | NO | CURRENT_TIMESTAMP | | Modification timestamp |

---

### 3.1b `disaster.disaster_impacts`
Aggregated humanitarian, housing, infrastructure, agriculture, livestock, and economic impact estimates per disaster event.

| Column Name | Data Type | Null | Default / Constraints | Indexes | Description |
| :--- | :--- | :---: | :--- | :--- | :--- |
| `id` | `uuid` | NO | PK | B-Tree (PK) | Impact record ID |
| `disaster_event_id` | `uuid` | NO | UNIQUE FK &rarr; `disaster.disaster_events(id)` ON DELETE CASCADE | B-Tree (UQ) | Parent disaster event |
| `affected_population` | `int` | NO | 0 | | Estimated affected individuals |
| `affected_households` | `int` | NO | 0 | | Estimated affected families |
| `deaths_count` | `int` | NO | 0 | | Confirmed fatalities |
| `injured_count` | `int` | NO | 0 | | Confirmed injured persons |
| `missing_count` | `int` | NO | 0 | | Reported missing persons |
| `damaged_houses_fully` | `int` | NO | 0 | | Fully damaged houses |
| `damaged_houses_partially` | `int` | NO | 0 | | Partially damaged houses |
| `damaged_infrastructure_count` | `int` | NO | 0 | | Damaged roads, bridges, embankments, institutions |
| `agricultural_loss_acres` | `decimal(12,2)` | NO | 0.00 | | Damaged crop land in acres |
| `agricultural_loss_bdt` | `decimal(15,2)` | NO | 0.00 | | Estimated crop loss (BDT) |
| `livestock_loss_count` | `int` | NO | 0 | | Livestock/poultry lost |
| `livestock_loss_bdt` | `decimal(15,2)` | NO | 0.00 | | Estimated livestock loss (BDT) |
| `total_economic_loss_bdt` | `decimal(16,2)` | NO | 0.00 | | Total estimated economic impact (BDT) |
| `remarks` | `text` | YES | | | Assessment notes |
| `updated_by` | `uuid` | YES | FK &rarr; `core.users(id)` ON DELETE SET NULL | | Updating officer |
| `created_at` | `timestamptz` | NO | CURRENT_TIMESTAMP | | Creation timestamp |
| `updated_at` | `timestamptz` | NO | CURRENT_TIMESTAMP | | Modification timestamp |

---

### 3.1c `disaster.disaster_warnings`
Early warning bulletins, alert levels, expiry windows, affected areas, and notification dispatch tracking.

| Column Name | Data Type | Null | Default / Constraints | Indexes | Description |
| :--- | :--- | :---: | :--- | :--- | :--- |
| `id` | `uuid` | NO | PK | B-Tree (PK) | Warning ID |
| `disaster_event_id` | `uuid` | NO | FK &rarr; `disaster.disaster_events(id)` ON DELETE CASCADE | B-Tree | Associated disaster event |
| `warning_code` | `varchar(40)` | NO | UNIQUE | B-Tree (UQ) | Official bulletin reference |
| `warning_level` | `varchar(30)` | NO | CHECK in ('ADVISORY', 'WATCH', 'WARNING', 'DANGER', 'GREAT_DANGER', 'EVACUATION_ORDER') | B-Tree | Alert severity |
| `title_en` | `varchar(180)` | NO | | | Warning headline (English) |
| `title_bn` | `varchar(220)` | NO | | | Warning headline (Bangla) |
| `message_en` | `text` | NO | | | Warning bulletin content (English) |
| `message_bn` | `text` | NO | | | Warning bulletin content (Bangla) |
| `issuing_authority` | `varchar(150)` | NO | | | e.g. `BMD`, `FFWC`, `NEOC / MoDMR`, `DDMC` |
| `issued_at` | `timestamptz` | NO | | B-Tree | Warning issue timestamp |
| `expires_at` | `timestamptz` | YES | | B-Tree | Warning expiration timestamp |
| `affected_unit_ids` | `jsonb` | YES | | | Targeted administrative unit UUIDs |
| `status` | `varchar(30)` | NO | DEFAULT 'ACTIVE' | B-Tree | `ACTIVE`, `EXPIRED`, `CANCELLED` |
| `notification_dispatch_status` | `varchar(30)` | NO | DEFAULT 'QUEUED' | | `QUEUED`, `DISPATCHED`, `FAILED` |
| `notification_reference` | `varchar(100)` | YES | | | Adapter dispatch reference |
| `issued_by` | `uuid` | YES | FK &rarr; `core.users(id)` ON DELETE SET NULL | B-Tree | Officer issuing warning |
| `created_at` | `timestamptz` | NO | CURRENT_TIMESTAMP | | Creation timestamp |
| `updated_at` | `timestamptz` | NO | CURRENT_TIMESTAMP | | Modification timestamp |

---

### 3.1d `disaster.disaster_timelines`
Chronological audit timeline of lifecycle changes, field situation updates, and attached reports/images/documents.

| Column Name | Data Type | Null | Default / Constraints | Indexes | Description |
| :--- | :--- | :---: | :--- | :--- | :--- |
| `id` | `uuid` | NO | PK | B-Tree (PK) | Timeline entry ID |
| `disaster_event_id` | `uuid` | NO | FK &rarr; `disaster.disaster_events(id)` ON DELETE CASCADE | B-Tree | Parent disaster event |
| `event_type` | `varchar(40)` | NO | | B-Tree | `CREATED`, `STATUS_CHANGE`, `SITUATION_UPDATE`, `IMPACT_UPDATE`, `WARNING_ISSUED`, `ATTACHMENT_ADDED` |
| `from_phase` | `varchar(30)` | YES | | | Previous lifecycle state |
| `to_phase` | `varchar(30)` | YES | | | New lifecycle state |
| `title_en` | `varchar(180)` | NO | | | Entry summary (English) |
| `title_bn` | `varchar(220)` | YES | | | Entry summary (Bangla) |
| `notes` | `text` | YES | | | Detailed update or transition rationale |
| `attachment_original_name` | `varchar(255)` | YES | | | Original uploaded filename |
| `attachment_path` | `varchar(500)` | YES | | | Private storage path (UUID-obfuscated) |
| `attachment_mime` | `varchar(120)` | YES | | | Validated MIME type |
| `attachment_size_bytes` | `bigint` | YES | | | File size in bytes |
| `attachment_sha256` | `char(64)` | YES | | | SHA-256 integrity hash |
| `recorded_by` | `uuid` | YES | FK &rarr; `core.users(id)` ON DELETE SET NULL | B-Tree | Recording officer |
| `occurred_at` | `timestamptz` | NO | CURRENT_TIMESTAMP | B-Tree | Chronological timestamp |
| `created_at` | `timestamptz` | NO | CURRENT_TIMESTAMP | | Creation timestamp |
| `updated_at` | `timestamptz` | NO | CURRENT_TIMESTAMP | | Modification timestamp |

---

### 3.2 `disaster.shelters`
Emergency cyclone, flood, and multipurpose shelters.

| Column Name | Data Type | Null | Default / Constraints | Indexes | Description |
| :--- | :--- | :---: | :--- | :--- | :--- |
| `id` | `uuid` | NO | PK | B-Tree (PK) | Shelter identifier |
| `administrative_unit_id`| `uuid`| NO | FK &rarr; `core.administrative_units(id)` ON DELETE RESTRICT | B-Tree | Union / Upazila location |
| `shelter_code` | `varchar(30)` | NO | UNIQUE | B-Tree (UQ) | Official shelter asset code |
| `shelter_name_en`| `varchar(150)`| NO | | B-Tree | Facility name (English) |
| `shelter_name_bn`| `varchar(200)`| NO | | | Facility name (Bangla) |
| `shelter_type` | `varchar(50)` | NO | CHECK in ('CYCLONE_SHELTER', 'FLOOD_SHELTER', 'SCHOOL_CUM_SHELTER', 'COMMUNITY_CENTER') | B-Tree | Classification |
| `capacity_persons`| `int` | NO | CHECK (capacity_persons > 0) | | Maximum human capacity |
| `capacity_livestock`| `int` | NO | 0 | | Killa / livestock shelter capacity |
| `has_potable_water`| `boolean`| NO | true | | Drinking water availability |
| `has_solar_power`| `boolean` | NO | false | | Emergency solar backup flag |
| `location_geom` | `geometry(Point, 4326)`| NO | | GIST | Exact GPS location of shelter |
| `created_at` | `timestamptz` | NO | CURRENT_TIMESTAMP | | Audit timestamp |
| `updated_at` | `timestamptz` | NO | CURRENT_TIMESTAMP | | Audit timestamp |
| `deleted_at` | `timestamptz` | YES | NULL | | Soft delete timestamp |

---

### 3.3 `disaster.shelter_occupancies`
Time-series log of shelter utilization during active disasters.

| Column Name | Data Type | Null | Default / Constraints | Indexes | Description |
| :--- | :--- | :---: | :--- | :--- | :--- |
| `id` | `uuid` | NO | PK | B-Tree (PK) | Occupancy record ID |
| `shelter_id` | `uuid` | NO | FK &rarr; `disaster.shelters(id)` ON DELETE RESTRICT | B-Tree | Target shelter |
| `disaster_event_id`| `uuid` | NO | FK &rarr; `disaster.disaster_events(id)` ON DELETE RESTRICT | B-Tree | Associated disaster |
| `current_men` | `int` | NO | 0 | | Count of adult male occupants |
| `current_women` | `int` | NO | 0 | | Count of adult female occupants |
| `current_children`| `int` | NO | 0 | | Count of child occupants |
| `current_livestock`| `int`| NO | 0 | | Count of sheltered livestock |
| `recorded_at` | `timestamptz` | NO | CURRENT_TIMESTAMP | B-Tree | Timestamp of headcount report |
| `recorded_by_user_id`| `uuid`| NO | FK &rarr; `core.users(id)` | | Reporting shelter manager / volunteer |

---

## 4. Schema: `damage` (D-Form Damage & Needs Assessment)

### 4.0 `damage.damage_categories`
Configurable damage categories (`HOUSING`, `ROADS_BRIDGES`, `EDUCATION`, `HEALTHCARE`, `AGRICULTURE`, `LIVESTOCK_FISHERIES`, `WATER_SANITATION`, `ELECTRICITY_COMM`, `PUBLIC_INFRASTRUCTURE`, plus custom categories).

| Column Name | Data Type | Null | Default / Constraints | Indexes | Description |
| :--- | :--- | :---: | :--- | :--- | :--- |
| `id` | `uuid` | NO | PK | B-Tree (PK) | Category ID |
| `code` | `varchar(50)` | NO | UNIQUE | B-Tree (UQ) | Uppercase category code |
| `name_en` | `varchar(120)` | NO | | | English category name |
| `name_bn` | `varchar(160)` | NO | | | Bangla category name |
| `description` | `text` | YES | | | Category description |
| `default_unit` | `varchar(40)` | NO | `'UNITS'` | | Default damage unit |
| `color_hex` | `varchar(20)` | YES | | | GIS/chart color hex |
| `sort_order` | `int` | NO | 100 | | Display sort order |
| `is_system` | `boolean` | NO | true | | Built-in category flag |
| `is_active` | `boolean` | NO | true | B-Tree | Active status flag |

### 4.1 `damage.damage_assessments`
Official Government of Bangladesh D-Form master records with spatial coverage and Flutter offline sync metadata.

| Column Name | Data Type | Null | Default / Constraints | Indexes | Description |
| :--- | :--- | :---: | :--- | :--- | :--- |
| `id` | `uuid` | NO | PK | B-Tree (PK) | Assessment identifier |
| `disaster_event_id`| `uuid` | NO | FK &rarr; `disaster.disaster_events(id)` ON DELETE RESTRICT | B-Tree | Associated disaster |
| `administrative_unit_id`| `uuid`| NO | FK &rarr; `core.administrative_units(id)` ON DELETE RESTRICT | B-Tree | Primary assessed jurisdiction |
| `d_form_code` | `varchar(50)` | NO | UNIQUE | B-Tree (UQ) | D-form serial reference number |
| `reference_no` | `varchar(80)` | YES | UNIQUE | B-Tree (UQ) | Official dispatch reference |
| `assessment_type`| `varchar(40)`| NO | `'PRELIMINARY_D_FORM'` | B-Tree | `INITIAL_RAPID`, `PRELIMINARY_D_FORM`, `DETAILED_SECTORAL`, `FINAL_CONSOLIDATED` |
| `assessment_date`| `date` | NO | | B-Tree | Assessment date |
| `assessed_at` | `timestamptz` | YES | | B-Tree | Field collection date & time |
| `assessing_organization`| `varchar(180)`| NO | | | Organization conducting assessment |
| `team_name` | `varchar(150)` | YES | | | Assigned assessment team |
| `assessor_name` | `varchar(150)` | NO | | | Primary assessor name |
| `severity_level` | `varchar(30)` | NO | `'MODERATE'` | B-Tree | `LOW`, `MODERATE`, `SEVERE`, `CRITICAL` |
| `affected_households`| `int` | NO | 0 | | Total households affected |
| `affected_population`| `int` | NO | 0 | | Total persons affected |
| `displaced_population`| `int`| NO | 0 | | Displaced persons |
| `deaths_count` | `int` | NO | 0 | | Confirmed human deaths |
| `injured_count` | `int` | NO | 0 | | Confirmed injured persons |
| `missing_count` | `int` | NO | 0 | | Missing persons |
| `total_fully_damaged` | `int` | NO | 0 | | Sum of fully damaged units |
| `total_partially_damaged` | `int` | NO | 0 | | Sum of partially damaged units |
| `total_minor_damaged` | `int` | NO | 0 | | Sum of minor damaged units |
| `total_estimated_loss_bdt`| `decimal(15,2)`| NO | 0.00 | B-Tree | Total monetary loss in BDT |
| `verification_status`| `varchar(30)`| NO | `'DRAFT'` | B-Tree | `DRAFT`, `SUBMITTED`, `UNDER_VERIFICATION`, `CORRECTION_REQUESTED`, `VERIFIED`, `REJECTED`, `APPROVED` |
| `location_geom` | `geometry(Point, 4326)` | YES | | GIST | Primary GPS point |
| `damage_area_geom`| `geometry(MultiPolygon, 4326)`| YES | | GIST | Spatial polygon of surveyed damage |
| `client_uuid` | `varchar(64)` | YES | UNIQUE | B-Tree (UQ) | Mobile client UUID for offline sync |
| `idempotency_key`| `varchar(80)` | YES | UNIQUE | B-Tree (UQ) | Sync deduplication key |
| `sync_status` | `varchar(25)` | NO | `'ONLINE'` | B-Tree | `ONLINE`, `SYNCED`, `CONFLICT` |
| `sync_version` | `int` | NO | 1 | | Optimistic concurrency version |
| `created_by` | `uuid` | NO | FK &rarr; `core.users(id)` | B-Tree | Creating field officer |
| `created_at` | `timestamptz` | NO | CURRENT_TIMESTAMP | B-Tree | Submission timestamp |
| `updated_at` | `timestamptz` | NO | CURRENT_TIMESTAMP | | Modification timestamp |

---

### 4.2 `damage.damage_locations`, `damage.damage_sector_items`, `damage.damage_attachments`, `damage.damage_verification_logs`
Supporting child tables capturing multi-unit geographical coverage (`damage_locations`), sectoral damage breakdowns (`damage_sector_items` linked via `category_id` FK &rarr; `damage.damage_categories`), SHA-256 geo-tagged evidence files (`damage_attachments`), and immutable workflow transition logs (`damage_verification_logs`).

---

## 5. Schema: `logistics` (Warehouses, Stock, Requisitions & Allocations)

### 5.0 `logistics.relief_categories`
Configurable relief assistance categories (`FOOD`, `DRINKING_WATER`, `CASH_ASSISTANCE`, `CLOTHING`, `SHELTER_MATERIALS`, `MEDICINE_SUPPLIES`, plus custom categories).

| Column Name | Data Type | Null | Default / Constraints | Indexes | Description |
| :--- | :--- | :---: | :--- | :--- | :--- |
| `id` | `uuid` | NO | PK | B-Tree (PK) | Category ID |
| `code` | `varchar(50)` | NO | UNIQUE | B-Tree (UQ) | Uppercase category code |
| `name_en` | `varchar(120)` | NO | | | English name |
| `name_bn` | `varchar(150)` | NO | | | Bangla name |
| `default_unit` | `varchar(30)` | NO | DEFAULT 'UNIT' | | Default unit of measure |
| `duplicate_window_days` | `int` | NO | 14 | | Default cooldown window (days) for duplicate assistance checks |
| `is_system` | `boolean` | NO | true | | Built-in category flag |
| `is_active` | `boolean` | NO | true | B-Tree | Active flag |
| `created_at` | `timestamptz` | NO | CURRENT_TIMESTAMP | | Creation timestamp |
| `updated_at` | `timestamptz` | NO | CURRENT_TIMESTAMP | | Update timestamp |

---

### 5.1 `logistics.commodities`
Standardized catalog of disaster relief goods linked to relief categories.

| Column Name | Data Type | Null | Default / Constraints | Indexes | Description |
| :--- | :--- | :---: | :--- | :--- | :--- |
| `id` | `uuid` | NO | PK | B-Tree (PK) | Commodity ID |
| `relief_category_id` | `uuid` | YES | FK &rarr; `logistics.relief_categories(id)` ON DELETE SET NULL | B-Tree | Parent relief category |
| `commodity_code` | `varchar(30)` | NO | UNIQUE | B-Tree (UQ) | e.g. `COM-RICE-GR`, `COM-CASH-BDT` |
| `name_en` | `varchar(150)` | NO | | B-Tree | English item name |
| `name_bn` | `varchar(200)` | NO | | | Bangla item name |
| `category` | `varchar(50)` | NO | | B-Tree | Category code |
| `unit_of_measure` | `varchar(20)` | NO | e.g. `METRIC_TON`, `KG`, `LITER`, `PACKET`, `BDT`, `PIECE` | | Measurement unit |
| `description` | `text` | YES | NULL | | Item specification / description |
| `minimum_stock_level` | `decimal(12,2)` | NO | 0, CHECK (minimum_stock_level >= 0) | | Minimum stock threshold triggering low-stock alert |
| `is_perishable` | `boolean` | NO | false | | Perishability indicator |
| `is_active` | `boolean` | NO | true | B-Tree | Active flag |
| `created_by` | `uuid` | YES | FK &rarr; `core.users(id)` | | Creator user |
| `updated_by` | `uuid` | YES | FK &rarr; `core.users(id)` | | Last updater user |
| `created_at` | `timestamptz` | NO | CURRENT_TIMESTAMP | | Creation timestamp |
| `updated_at` | `timestamptz` | NO | CURRENT_TIMESTAMP | | Update timestamp |
| `deleted_at` | `timestamptz` | YES | NULL | | Soft delete timestamp |

---

### 5.2 `logistics.warehouses`
Storage depots across the national hierarchy.

| Column Name | Data Type | Null | Default / Constraints | Indexes | Description |
| :--- | :--- | :---: | :--- | :--- | :--- |
| `id` | `uuid` | NO | PK | B-Tree (PK) | Warehouse ID |
| `administrative_unit_id`| `uuid`| NO | FK &rarr; `core.administrative_units(id)` ON DELETE RESTRICT | B-Tree | Jurisdiction location |
| `warehouse_code`| `varchar(30)` | NO | UNIQUE | B-Tree (UQ) | Godown asset code |
| `warehouse_name`| `varchar(150)`| NO | | B-Tree | Facility name (EN) |
| `warehouse_name_bn`| `varchar(200)`| YES | | | Facility name (BN) |
| `tier` | `varchar(30)` | NO | CHECK in ('CENTRAL_CSD', 'REGIONAL_LSD', 'DISTRICT_GODOWN', 'UPAZILA_STORE') | B-Tree | Godown echelon |
| `warehouse_type` | `varchar(40)` | YES | 'RELIEF_GODOWN' | B-Tree | Functional facility type (e.g., `RELIEF_GODOWN`, `COLD_STORAGE`, `FOOD_SILO`, `EMERGENCY_HUB`) |
| `owning_organization` | `varchar(160)` | YES | NULL | | Owning / operating organization (MoDMR, DDM, DRRO, PIO, NGO partner) |
| `responsible_officer_name` | `varchar(120)` | YES | NULL | | Name of warehouse in-charge |
| `responsible_officer_contact` | `varchar(40)` | YES | NULL | | Contact number of responsible officer |
| `responsible_user_id` | `uuid` | YES | FK &rarr; `core.users(id)` | B-Tree | System user assigned as officer in charge |
| `address` | `text` | YES | NULL | | Street / facility physical address |
| `contact_phone` | `varchar(40)` | YES | NULL | | Facility phone |
| `contact_email` | `varchar(150)` | YES | NULL | | Facility email |
| `capacity_mt` | `decimal(10,2)`| NO | | | Storage capacity in Metric Tons |
| `latitude` | `decimal(10,7)` | YES | | | GPS latitude |
| `longitude` | `decimal(10,7)` | YES | | | GPS longitude |
| `location_geom` | `geometry(Point, 4326)`| YES | Populated on PostgreSQL/PostGIS | GIST | Warehouse GPS coordinates |
| `is_active` | `boolean` | NO | true | B-Tree | Operational flag |
| `created_by` | `uuid` | YES | FK &rarr; `core.users(id)` | | Creator user |
| `updated_by` | `uuid` | YES | FK &rarr; `core.users(id)` | | Last updater user |
| `created_at` | `timestamptz` | NO | CURRENT_TIMESTAMP | | Audit timestamp |
| `updated_at` | `timestamptz` | NO | CURRENT_TIMESTAMP | | Audit timestamp |
| `deleted_at` | `timestamptz` | YES | NULL | | Soft delete timestamp |

---

### 5.3 `logistics.warehouse_inventories`
Stock on hand per warehouse, commodity, and batch (FIFO/FEFO tracked).

| Column Name | Data Type | Null | Default / Constraints | Indexes | Description |
| :--- | :--- | :---: | :--- | :--- | :--- |
| `id` | `uuid` | NO | PK | B-Tree (PK) | Lot ID |
| `warehouse_id` | `uuid` | NO | FK &rarr; `logistics.warehouses(id)` ON DELETE RESTRICT | B-Tree | Facility holding stock |
| `commodity_id` | `uuid` | NO | FK &rarr; `logistics.commodities(id)` ON DELETE RESTRICT | B-Tree | Commodity stocked |
| `batch_number` | `varchar(50)` | NO | | B-Tree | Lot / Batch serial |
| `opening_quantity` | `decimal(12,2)` | NO | 0, CHECK (opening_quantity >= 0) | | Initial opening quantity recorded for this batch |
| `current_quantity`| `decimal(12,2)`| NO | CHECK (current_quantity >= 0) | B-Tree | Physical usable stock balance |
| `reserved_quantity`| `decimal(12,2)`| NO | 0, CHECK (reserved_quantity >= 0) | | Quantity reserved for approved allocations |
| `damaged_quantity` | `decimal(12,2)` | NO | 0, CHECK (damaged_quantity >= 0) | | Written-off / damaged stock quantity for this batch |
| `expired_quantity` | `decimal(12,2)` | NO | 0, CHECK (expired_quantity >= 0) | | Quarantined / expired stock quantity for this batch |
| `unit_cost_bdt` | `decimal(12,2)` | YES | | | Unit valuation in BDT |
| `received_date` | `date` | YES | | B-Tree | Batch receipt date |
| `expiry_date` | `date` | YES | | B-Tree | Expiration date for food/medicines (FEFO ordering) |
| `status` | `varchar(30)` | NO | 'ACTIVE' | B-Tree | Lot status (`ACTIVE`, `EXHAUSTED`, `EXPIRED`, `QUARANTINED`) |
| `notes` | `text` | YES | NULL | | Batch remarks |
| `created_at` | `timestamptz` | NO | CURRENT_TIMESTAMP | | Lot creation timestamp |
| `updated_at` | `timestamptz` | NO | CURRENT_TIMESTAMP | | Lot modification timestamp |

*Unique Constraint:* `UNIQUE (warehouse_id, commodity_id, batch_number)`

---

### 5.4 `logistics.stock_transactions` (Immutable Ledger)
Double-entry inventory audit ledger. Historical rows cannot be updated or deleted once written; corrections require an authorized adjustment transaction.

| Column Name | Data Type | Null | Default / Constraints | Indexes | Description |
| :--- | :--- | :---: | :--- | :--- | :--- |
| `id` | `uuid` | NO | PK | B-Tree (PK) | Transaction ID |
| `warehouse_inventory_id`| `uuid`| NO | FK &rarr; `logistics.warehouse_inventories(id)` ON DELETE RESTRICT | B-Tree | Affected inventory lot |
| `warehouse_id` | `uuid` | YES | FK &rarr; `logistics.warehouses(id)` ON DELETE RESTRICT | B-Tree | Denormalized warehouse reference for ledger queries |
| `commodity_id` | `uuid` | YES | FK &rarr; `logistics.commodities(id)` ON DELETE RESTRICT | B-Tree | Denormalized commodity reference for ledger queries |
| `stock_receipt_id` | `uuid` | YES | FK &rarr; `logistics.stock_receipts(id)` ON DELETE SET NULL | B-Tree | Source verified GRN receipt |
| `warehouse_transfer_id` | `uuid` | YES | FK &rarr; `logistics.warehouse_transfers(id)` ON DELETE SET NULL | B-Tree | Source inter-warehouse transfer order |
| `transaction_type`| `varchar(35)`| NO | CHECK in ('OPENING_STOCK', 'STOCK_IN_GRN', 'INTER_WAREHOUSE_TRANSFER', 'DISTRIBUTION_ISSUE', 'STOCK_ISSUE', 'ALLOCATION_RETURN', 'RETURN_IN', 'STOCK_ADJUSTMENT', 'WRITE_OFF_DAMAGED', 'EXPIRED_STOCK') | B-Tree | Ledger movement type |
| `reference_type` | `varchar(40)` | YES | NULL | B-Tree | Reference origin (`GRN`, `TRANSFER`, `RELIEF_ALLOCATION`, `DISTRIBUTION_VOUCHER`, `ADJUSTMENT`, `OPENING_BALANCE`, `RETURN`) |
| `reason_code` | `varchar(60)` | YES | NULL | | Standardized reason code for adjustments / write-offs / returns |
| `quantity` | `decimal(12,2)`| NO | CHECK (quantity != 0) | | Quantity moved (+ for in, - for out) |
| `balance_after` | `decimal(12,2)`| NO | CHECK (balance_after >= 0) | | Running balance post-transaction |
| `reference_voucher_no`| `varchar(60)`| NO | | B-Tree | Source invoice, transfer order, or distribution voucher |
| `idempotency_key` | `varchar(120)` | YES | UNIQUE | B-Tree (UQ) | Optional request idempotency token preventing duplicate ledger postings |
| `relief_allocation_item_id`| `uuid` | YES | FK &rarr; `logistics.relief_allocation_items(id)` ON DELETE SET NULL | B-Tree | Related allocation item (if applicable) |
| `distribution_voucher_id`| `uuid` | YES | FK &rarr; `distribution.distribution_vouchers(id)` ON DELETE SET NULL | B-Tree | Related distribution voucher (if applicable) |
| `notes` | `text` | YES | | | Movement remarks |
| `authorized_by` | `uuid` | NO | FK &rarr; `core.users(id)` | B-Tree | Authorizing officer |
| `approved_by` | `uuid` | YES | FK &rarr; `core.users(id)` | B-Tree | Second approving officer (4-eye dual auth) |
| `created_by` | `uuid` | YES | FK &rarr; `core.users(id)` | | Recording officer |
| `created_at` | `timestamptz` | NO | CURRENT_TIMESTAMP | B-Tree | Ledger record timestamp (Immutable) |
| `updated_at` | `timestamptz` | NO | CURRENT_TIMESTAMP | | Record timestamp |

---

### 5.5 `logistics.relief_requisitions`
Bottom-up relief requests from field/unions/upazilas/districts.

| Column Name | Data Type | Null | Default / Constraints | Indexes | Description |
| :--- | :--- | :---: | :--- | :--- | :--- |
| `id` | `uuid` | NO | PK | B-Tree (PK) | Requisition ID |
| `disaster_event_id`| `uuid` | NO | FK &rarr; `disaster.disaster_events(id)` ON DELETE RESTRICT | B-Tree | Associated disaster |
| `administrative_unit_id`| `uuid`| NO | FK &rarr; `core.administrative_units(id)` ON DELETE RESTRICT | B-Tree | Requisitioning jurisdiction |
| `requisition_no`| `varchar(50)` | NO | UNIQUE | B-Tree (UQ) | e.g. `REQ-2026-DAC-001` |
| `requesting_organization` | `varchar(150)` | YES | | | Requesting office / committee |
| `estimated_beneficiaries` | `integer` | NO | 0 | | Estimated affected households/people |
| `priority` | `varchar(20)` | NO | 'MEDIUM', CHECK in ('LOW', 'MEDIUM', 'HIGH', 'CRITICAL') | B-Tree | Operational urgency |
| `request_date` | `date` | NO | | B-Tree | Official request date |
| `needed_by_date` | `date` | YES | | | Target delivery date |
| `status` | `varchar(30)` | NO | 'DRAFT', CHECK in ('DRAFT', 'SUBMITTED', 'UNDER_REVIEW', 'APPROVED', 'PARTIALLY_APPROVED', 'REJECTED', 'COMPLETED') | B-Tree | 7-state approval lifecycle |
| `justification` | `text` | YES | | | Needs justification summary |
| `review_notes` | `text` | YES | | | Reviewer / approver remarks or rejection reason |
| `supporting_doc_path` | `varchar(255)` | YES | | | Private disk path for supporting letter/assessment |
| `supporting_doc_name` | `varchar(255)` | YES | | | Original filename |
| `supporting_doc_mime` | `varchar(100)` | YES | | | Validated MIME type |
| `supporting_doc_checksum` | `varchar(64)` | YES | | | SHA-256 checksum |
| `submitted_by` | `uuid` | YES | FK &rarr; `core.users(id)` | B-Tree | Submitting officer |
| `submitted_at` | `timestamptz` | YES | | B-Tree | Submission timestamp |
| `reviewed_by` | `uuid` | YES | FK &rarr; `core.users(id)` | | Reviewing officer |
| `reviewed_at` | `timestamptz` | YES | | | Review timestamp |
| `approved_by` | `uuid` | YES | FK &rarr; `core.users(id)` | | Approving officer (must differ from creator/submitter) |
| `approved_at` | `timestamptz` | YES | | | Approval timestamp |
| `created_by` | `uuid` | NO | FK &rarr; `core.users(id)` | B-Tree | Record creator (4-eye SoD checked) |
| `updated_by` | `uuid` | YES | FK &rarr; `core.users(id)` | | Last modifier |
| `created_at` | `timestamptz` | NO | CURRENT_TIMESTAMP | | Audit timestamp |
| `updated_at` | `timestamptz` | NO | CURRENT_TIMESTAMP | | Audit timestamp |
| `deleted_at` | `timestamptz` | YES | NULL | | Soft delete timestamp |

---

### 5.6 `logistics.relief_requisition_items`
Line items requested and approved within a requisition.

| Column Name | Data Type | Null | Default / Constraints | Indexes | Description |
| :--- | :--- | :---: | :--- | :--- | :--- |
| `id` | `uuid` | NO | PK | B-Tree (PK) | Item ID |
| `relief_requisition_id`| `uuid`| NO | FK &rarr; `logistics.relief_requisitions(id)` ON DELETE CASCADE | B-Tree | Parent requisition |
| `commodity_id` | `uuid` | NO | FK &rarr; `logistics.commodities(id)` ON DELETE RESTRICT | B-Tree | Commodity requested |
| `quantity_requested`| `decimal(12,2)`| NO | CHECK (quantity_requested > 0) | | Quantity requested |
| `quantity_approved`| `decimal(12,2)`| NO | 0, CHECK (quantity_approved >= 0) | | Quantity sanctioned on approval/partial approval |
| `notes` | `text` | YES | | | Line item notes |
| `created_at` | `timestamptz` | NO | CURRENT_TIMESTAMP | | Audit timestamp |
| `updated_at` | `timestamptz` | NO | CURRENT_TIMESTAMP | | Audit timestamp |

---

### 5.7 `logistics.relief_allocations`
Official government sanction/allocation orders tied to disaster, warehouse, and distribution center.

| Column Name | Data Type | Null | Default / Constraints | Indexes | Description |
| :--- | :--- | :---: | :--- | :--- | :--- |
| `id` | `uuid` | NO | PK | B-Tree (PK) | Allocation order ID |
| `relief_requisition_id`| `uuid`| YES | FK &rarr; `logistics.relief_requisitions(id)` ON DELETE RESTRICT | B-Tree | Source requisition (optional for top-down emergency dispatch) |
| `disaster_event_id`| `uuid` | NO | FK &rarr; `disaster.disaster_events(id)` ON DELETE RESTRICT | B-Tree | Disaster event |
| `administrative_unit_id`| `uuid`| NO | FK &rarr; `core.administrative_units(id)` ON DELETE RESTRICT | B-Tree | Target distribution jurisdiction |
| `distribution_point_id`| `uuid`| YES | FK &rarr; `distribution.distribution_points(id)` ON DELETE SET NULL | B-Tree | Assigned relief distribution center |
| `warehouse_id` | `uuid` | NO | FK &rarr; `logistics.warehouses(id)` ON DELETE RESTRICT | B-Tree | Source warehouse issuing stock |
| `allocation_order_no`| `varchar(50)`| NO | UNIQUE | B-Tree (UQ) | Government order memo number |
| `status` | `varchar(30)` | NO | 'DRAFT', CHECK in ('DRAFT', 'SANCTIONED', 'DISPATCHED', 'PARTIALLY_DISTRIBUTED', 'COMPLETED', 'CANCELLED') | B-Tree | Allocation lifecycle state |
| `scheduled_start_at` | `timestamptz` | YES | | B-Tree | Scheduled distribution window start |
| `scheduled_end_at` | `timestamptz` | YES | | | Scheduled distribution window end |
| `duplicate_window_days` | `integer` | NO | 30 | | Cooldown window in days for duplicate-assistance checks |
| `remarks` | `text` | YES | | | Allocation remarks |
| `created_by` | `uuid` | NO | FK &rarr; `core.users(id)` | B-Tree | Creator (cannot sanction own allocation) |
| `updated_by` | `uuid` | YES | FK &rarr; `core.users(id)` | | Last modifier |
| `approved_by` | `uuid` | YES | FK &rarr; `core.users(id)` | B-Tree | Sanctioning authority (must differ from creator) |
| `approved_at` | `timestamptz` | YES | | B-Tree | Sanction timestamp |
| `created_at` | `timestamptz` | NO | CURRENT_TIMESTAMP | | Audit timestamp |
| `updated_at` | `timestamptz` | NO | CURRENT_TIMESTAMP | | Audit timestamp |
| `deleted_at` | `timestamptz` | YES | NULL | | Soft delete timestamp |

---

### 5.8 `logistics.relief_allocation_items`
Allocated, distributed, and cancelled commodity quantities per allocation order.

| Column Name | Data Type | Null | Default / Constraints | Indexes | Description |
| :--- | :--- | :---: | :--- | :--- | :--- |
| `id` | `uuid` | NO | PK | B-Tree (PK) | Allocation item ID |
| `relief_allocation_id`| `uuid`| NO | FK &rarr; `logistics.relief_allocations(id)` ON DELETE CASCADE | B-Tree | Parent allocation order |
| `commodity_id` | `uuid` | NO | FK &rarr; `logistics.commodities(id)` ON DELETE RESTRICT | B-Tree | Allocated commodity |
| `quantity_allocated`| `decimal(12,2)`| NO | CHECK (quantity_allocated > 0) | | Quantity sanctioned |
| `quantity_distributed`| `decimal(12,2)`| NO | 0, CHECK (quantity_distributed >= 0) | | Cumulative quantity distributed |
| `quantity_cancelled`| `decimal(12,2)`| NO | 0, CHECK (quantity_cancelled >= 0) | | Cancelled / returned quantity |
| `created_at` | `timestamptz` | NO | CURRENT_TIMESTAMP | | Audit timestamp |
| `updated_at` | `timestamptz` | NO | CURRENT_TIMESTAMP | | Audit timestamp |

---

### 5.9 `logistics.relief_allocation_beneficiaries`
Pre-selected eligible beneficiary households assigned to an allocation round.

| Column Name | Data Type | Null | Default / Constraints | Indexes | Description |
| :--- | :--- | :---: | :--- | :--- | :--- |
| `id` | `uuid` | NO | PK | B-Tree (PK) | Assignment ID |
| `relief_allocation_id`| `uuid`| NO | FK &rarr; `logistics.relief_allocations(id)` ON DELETE CASCADE | B-Tree | Parent allocation order |
| `household_id` | `uuid` | NO | FK &rarr; `beneficiary.households(id)` ON DELETE RESTRICT | B-Tree | Assigned eligible household |
| `selection_status` | `varchar(20)` | NO | 'SELECTED', CHECK in ('SELECTED', 'DISTRIBUTED', 'ABSENT', 'REMOVED') | B-Tree | Roster status |
| `assigned_by` | `uuid` | YES | FK &rarr; `core.users(id)` | | Assigning officer |
| `notes` | `text` | YES | | | Roster remarks |

---

### 5.10 `logistics.stock_receipts`
Goods Received Note (GRN) procurement and incoming supply receiving headers requiring verification before adding to usable inventory.

| Column Name | Data Type | Null | Default / Constraints | Indexes | Description |
| :--- | :--- | :---: | :--- | :--- | :--- |
| `id` | `uuid` | NO | PK | B-Tree (PK) | Receipt ID |
| `receipt_no` | `varchar(60)` | NO | UNIQUE | B-Tree (UQ) | Unique GRN reference serial (`GRN-YYYYMMDD-XXXX`) |
| `warehouse_id` | `uuid` | NO | FK &rarr; `logistics.warehouses(id)` ON DELETE RESTRICT | B-Tree | Receiving warehouse |
| `supplier_name` | `varchar(180)` | NO | | B-Tree | Supplier / donor / vendor organization name |
| `supplier_contact` | `varchar(80)` | YES | NULL | | Supplier phone / contact person |
| `purchase_order_no` | `varchar(80)` | YES | NULL | B-Tree | Procurement PO / chalan / dispatch ref |
| `received_date` | `date` | NO | | B-Tree | Physical delivery date |
| `status` | `varchar(20)` | NO | 'PENDING_VERIFICATION', CHECK in ('PENDING_VERIFICATION', 'VERIFIED', 'REJECTED') | B-Tree | Verification workflow status |
| `verification_notes` | `text` | YES | NULL | | Receiving verification / rejection notes |
| `attachment_path` | `varchar(255)` | YES | NULL | | Private storage path for scanned chalan / GRN |
| `attachment_original_name` | `varchar(200)` | YES | NULL | | Original uploaded filename |
| `attachment_mime` | `varchar(100)` | YES | NULL | | Verified MIME type |
| `attachment_size_bytes` | `bigint` | YES | NULL | | File byte size |
| `attachment_sha256` | `varchar(64)` | YES | NULL | | SHA-256 file integrity checksum |
| `idempotency_key` | `varchar(120)` | YES | UNIQUE | B-Tree (UQ) | Duplicate submission prevention key |
| `received_by` | `uuid` | NO | FK &rarr; `core.users(id)` | B-Tree | Receiving storekeeper |
| `verified_by` | `uuid` | YES | FK &rarr; `core.users(id)` | B-Tree | Verifying officer (must differ from `received_by` when 4-eye enforced) |
| `verified_at` | `timestamptz` | YES | NULL | | Verification timestamp |
| `notes` | `text` | YES | NULL | | General remarks |
| `created_at` | `timestamptz` | NO | CURRENT_TIMESTAMP | | Creation timestamp |
| `updated_at` | `timestamptz` | NO | CURRENT_TIMESTAMP | | Update timestamp |
| `deleted_at` | `timestamptz` | YES | NULL | | Soft delete timestamp |

---

### 5.11 `logistics.stock_receipt_items`
Line items belonging to a GRN receipt, mapping each batch/expiry to its resulting inventory lot upon verification.

| Column Name | Data Type | Null | Default / Constraints | Indexes | Description |
| :--- | :--- | :---: | :--- | :--- | :--- |
| `id` | `uuid` | NO | PK | B-Tree (PK) | Receipt line ID |
| `stock_receipt_id` | `uuid` | NO | FK &rarr; `logistics.stock_receipts(id)` ON DELETE CASCADE | B-Tree | Parent GRN receipt |
| `commodity_id` | `uuid` | NO | FK &rarr; `logistics.commodities(id)` ON DELETE RESTRICT | B-Tree | Commodity received |
| `batch_number` | `varchar(50)` | NO | | B-Tree | Lot / batch serial |
| `quantity_received` | `decimal(12,2)` | NO | CHECK (quantity_received > 0) | | Quantity received |
| `unit_cost_bdt` | `decimal(12,2)` | YES | NULL, CHECK (unit_cost_bdt >= 0) | | Unit valuation in BDT |
| `expiry_date` | `date` | YES | NULL | | Expiry date for perishables |
| `warehouse_inventory_id` | `uuid` | YES | FK &rarr; `logistics.warehouse_inventories(id)` ON DELETE SET NULL | B-Tree | Credited lot after verification |
| `notes` | `text` | YES | NULL | | Line remarks |
| `created_at` | `timestamptz` | NO | CURRENT_TIMESTAMP | | Creation timestamp |
| `updated_at` | `timestamptz` | NO | CURRENT_TIMESTAMP | | Update timestamp |

---

### 5.12 `logistics.warehouse_transfers`
Controlled inter-warehouse stock transfer orders (Request &rarr; 4-Eye Approval &rarr; Dispatch &rarr; Receipt Confirmation).

| Column Name | Data Type | Null | Default / Constraints | Indexes | Description |
| :--- | :--- | :---: | :--- | :--- | :--- |
| `id` | `uuid` | NO | PK | B-Tree (PK) | Transfer order ID |
| `transfer_no` | `varchar(60)` | NO | UNIQUE | B-Tree (UQ) | Unique transfer order number (`TRF-YYYYMMDD-XXXX`) |
| `source_warehouse_id` | `uuid` | NO | FK &rarr; `logistics.warehouses(id)` ON DELETE RESTRICT | B-Tree | Dispatching source warehouse |
| `destination_warehouse_id` | `uuid` | NO | FK &rarr; `logistics.warehouses(id)` ON DELETE RESTRICT | B-Tree | Receiving destination warehouse |
| `status` | `varchar(20)` | NO | 'REQUESTED', CHECK in ('REQUESTED', 'APPROVED', 'DISPATCHED', 'RECEIVED', 'CANCELLED') | B-Tree | Transfer workflow state |
| `requested_date` | `date` | NO | | B-Tree | Request date |
| `expected_delivery_date` | `date` | YES | NULL | | Expected arrival date |
| `vehicle_info` | `varchar(180)` | YES | NULL | | Transport truck/boat/driver reference |
| `dispatch_reference` | `varchar(100)` | YES | NULL | | Waybill / chalan number on dispatch |
| `idempotency_key` | `varchar(120)` | YES | UNIQUE | B-Tree (UQ) | Request idempotency guard |
| `requested_by` | `uuid` | NO | FK &rarr; `core.users(id)` | B-Tree | Requesting officer |
| `approved_by` | `uuid` | YES | FK &rarr; `core.users(id)` | B-Tree | Approving officer (must differ from `requested_by`) |
| `approved_at` | `timestamptz` | YES | NULL | | Approval timestamp |
| `dispatched_by` | `uuid` | YES | FK &rarr; `core.users(id)` | B-Tree | Dispatching officer at source warehouse |
| `dispatched_at` | `timestamptz` | YES | NULL | | Dispatch timestamp |
| `received_by` | `uuid` | YES | FK &rarr; `core.users(id)` | B-Tree | Receiving officer at destination warehouse |
| `received_at` | `timestamptz` | YES | NULL | | Receipt confirmation timestamp |
| `cancellation_reason` | `text` | YES | NULL | | Reason if cancelled |
| `notes` | `text` | YES | NULL | | Transfer remarks |
| `created_at` | `timestamptz` | NO | CURRENT_TIMESTAMP | | Creation timestamp |
| `updated_at` | `timestamptz` | NO | CURRENT_TIMESTAMP | | Update timestamp |
| `deleted_at` | `timestamptz` | YES | NULL | | Soft delete timestamp |

---

### 5.13 `logistics.warehouse_transfer_items`
Line items within an inter-warehouse transfer order, tracking requested, dispatched, and confirmed received quantities per batch.

| Column Name | Data Type | Null | Default / Constraints | Indexes | Description |
| :--- | :--- | :---: | :--- | :--- | :--- |
| `id` | `uuid` | NO | PK | B-Tree (PK) | Transfer item ID |
| `warehouse_transfer_id` | `uuid` | NO | FK &rarr; `logistics.warehouse_transfers(id)` ON DELETE CASCADE | B-Tree | Parent transfer order |
| `commodity_id` | `uuid` | NO | FK &rarr; `logistics.commodities(id)` ON DELETE RESTRICT | B-Tree | Commodity transferred |
| `batch_number` | `varchar(50)` | YES | NULL | | Specific batch dispatched (auto-assigned from FEFO if omitted) |
| `quantity_requested` | `decimal(12,2)` | NO | CHECK (quantity_requested > 0) | | Requested transfer quantity |
| `quantity_dispatched` | `decimal(12,2)` | NO | 0, CHECK (quantity_dispatched >= 0) | | Dispatched quantity deducted from source |
| `quantity_received` | `decimal(12,2)` | NO | 0, CHECK (quantity_received >= 0) | | Confirmed quantity credited to destination |
| `unit_cost_bdt` | `decimal(12,2)` | YES | NULL | | Unit cost carried forward |
| `expiry_date` | `date` | YES | NULL | | Batch expiry date carried forward |
| `notes` | `text` | YES | NULL | | Line remarks / variance note |
| `created_at` | `timestamptz` | NO | CURRENT_TIMESTAMP | | Creation timestamp |
| `updated_at` | `timestamptz` | NO | CURRENT_TIMESTAMP | | Update timestamp |
| `created_at` | `timestamptz` | NO | CURRENT_TIMESTAMP | | Audit timestamp |
| `updated_at` | `timestamptz` | NO | CURRENT_TIMESTAMP | | Audit timestamp |

*Unique Constraint:* `UNIQUE (relief_allocation_id, household_id)`

---

## 6. Schema: `distribution` (Beneficiary Relief Hand-off)

### 6.1 `distribution.distribution_points`
Physical sites/centers where relief distributions are staged.

| Column Name | Data Type | Null | Default / Constraints | Indexes | Description |
| :--- | :--- | :---: | :--- | :--- | :--- |
| `id` | `uuid` | NO | PK | B-Tree (PK) | Point ID |
| `administrative_unit_id`| `uuid`| NO | FK &rarr; `core.administrative_units(id)` ON DELETE RESTRICT | B-Tree | Union/Upazila location |
| `point_code` | `varchar(30)` | NO | UNIQUE | B-Tree (UQ) | Center code |
| `point_name` | `varchar(150)`| NO | | B-Tree | Site name (EN) |
| `point_name_bn` | `varchar(200)`| YES | | | Site name (BN) |
| `address` | `text` | YES | | | Landmark / site address |
| `latitude` | `decimal(10,7)` | YES | | | Site GPS latitude |
| `longitude` | `decimal(10,7)` | YES | | | Site GPS longitude |
| `location_geom` | `geometry(Point, 4326)`| YES | Populated on PostgreSQL/PostGIS | GIST | Site GPS coordinates |
| `is_active` | `boolean` | NO | true | B-Tree | Active flag |
| `created_at` | `timestamptz` | NO | CURRENT_TIMESTAMP | | Audit timestamp |
| `updated_at` | `timestamptz` | NO | CURRENT_TIMESTAMP | | Audit timestamp |
| `deleted_at` | `timestamptz` | YES | NULL | | Soft delete timestamp |

---

### 6.2 `distribution.distribution_vouchers`
Official hand-off record to beneficiary household with atomic stock deduction, GPS/evidence capture, and idempotency protection.

| Column Name | Data Type | Null | Default / Constraints | Indexes | Description |
| :--- | :--- | :---: | :--- | :--- | :--- |
| `id` | `uuid` | NO | PK | B-Tree (PK) | Voucher ID (UUID) |
| `relief_allocation_item_id`| `uuid`| NO | FK &rarr; `logistics.relief_allocation_items(id)` ON DELETE RESTRICT | B-Tree | Source allocation item |
| `household_id` | `uuid` | NO | FK &rarr; `beneficiary.households(id)` ON DELETE RESTRICT | B-Tree | Recipient household |
| `distribution_point_id`| `uuid`| NO | FK &rarr; `distribution.distribution_points(id)` ON DELETE RESTRICT | B-Tree | Handover center |
| `voucher_no` | `varchar(60)` | NO | UNIQUE | B-Tree (UQ) | Official voucher serial number |
| `idempotency_key` | `varchar(100)` | NO | UNIQUE | B-Tree (UQ) | Client/request idempotency token blocking duplicate submissions |
| `quantity_disbursed`| `decimal(12,2)`| NO | CHECK (quantity_disbursed > 0) | | Exact quantity received |
| `verification_method`| `varchar(30)`| NO | CHECK in ('QR_SCAN', 'SMS_OTP', 'SIGNATURE_MANUAL', 'NID_BIOMETRIC') | B-Tree | Proof of distribution method |
| `verification_proof` | `varchar(255)`| YES | | | OTP code hash, QR token, or acknowledgment reference |
| `acknowledgment_signature` | `text` | YES | | | Digital signature SVG/data or receiver thumbprint/ack text |
| `photo_evidence_path` | `varchar(255)` | YES | | | Private disk path for handoff photo evidence |
| `photo_evidence_mime` | `varchar(100)` | YES | | | Validated image MIME type |
| `photo_evidence_checksum` | `varchar(64)` | YES | | | SHA-256 checksum |
| `distribution_lat` | `decimal(10,7)` | YES | | | Captured distribution GPS latitude |
| `distribution_lng` | `decimal(10,7)` | YES | | | Captured distribution GPS longitude |
| `duplicate_override` | `boolean` | NO | false | B-Tree | True if authorized duplicate-assistance exception used |
| `duplicate_override_reason` | `text` | YES | | | Mandatory documented reason for duplicate override |
| `distributed_by`| `uuid` | NO | FK &rarr; `core.users(id)` | B-Tree | Distributing officer / volunteer |
| `distributed_at`| `timestamptz` | NO | CURRENT_TIMESTAMP | B-Tree | Hand-off timestamp |
| `verified_by_tag_officer`| `uuid` | YES | FK &rarr; `core.users(id)` | B-Tree | Verifying witness/tag officer (cannot equal `distributed_by`) |
| `verified_at` | `timestamptz` | YES | | | Witness verification timestamp |
| `remarks` | `text` | YES | | | Field remarks |
| `is_voided` | `boolean` | NO | false | B-Tree | Void flag for erroneous vouchers |
| `void_reason` | `text` | YES | | | Void justification |
| `voided_by` | `uuid` | YES | FK &rarr; `core.users(id)` | | Voiding authority |
| `voided_at` | `timestamptz` | YES | | | Void timestamp |
| `created_at` | `timestamptz` | NO | CURRENT_TIMESTAMP | | Audit timestamp |
| `updated_at` | `timestamptz` | NO | CURRENT_TIMESTAMP | | Audit timestamp |

---

## 7. Schema: `project` (Module 06: Disaster Management Projects)

### 7.1 `project.project_categories`
Configurable catalog of disaster management project categories (`DEVELOPMENT`, `DRR`, `REHABILITATION`, `RECOVERY`, `TR_KABITA`, `SHELTER_INFRA`, `EMBANKMENT`).

| Column Name | Data Type | Null | Default / Constraints | Indexes | Description |
| :--- | :--- | :---: | :--- | :--- | :--- |
| `id` | `uuid` | NO | PK | B-Tree (PK) | Category ID |
| `code` | `varchar(50)` | NO | UNIQUE | B-Tree (UQ) | Uppercase category code |
| `name_en` | `varchar(120)` | NO | | | English category name |
| `name_bn` | `varchar(160)` | NO | | | Bangla category name |
| `description` | `text` | YES | | | Category description |
| `color_hex` | `varchar(20)` | YES | | | Map/UI badge color |
| `is_system` | `boolean` | NO | true | | Seeded system category flag |
| `is_active` | `boolean` | NO | true | B-Tree | Active flag |

---

### 7.2 `project.projects`
Development, disaster risk reduction (DRR), rehabilitation, and recovery initiatives.

| Column Name | Data Type | Null | Default / Constraints | Indexes | Description |
| :--- | :--- | :---: | :--- | :--- | :--- |
| `id` | `uuid` | NO | PK | B-Tree (PK) | Project ID |
| `category_id` | `uuid` | NO | FK &rarr; `project.project_categories(id)` ON DELETE RESTRICT | B-Tree | Project category |
| `disaster_event_id` | `uuid` | YES | FK &rarr; `disaster.disaster_events(id)` ON DELETE RESTRICT | B-Tree | Linked disaster event (nullable for standalone DRR) |
| `administrative_unit_id` | `uuid` | NO | FK &rarr; `core.administrative_units(id)` ON DELETE RESTRICT | B-Tree | Primary project jurisdiction |
| `project_code` | `varchar(50)` | NO | UNIQUE | B-Tree (UQ) | Government project code |
| `reference_no` | `varchar(80)` | YES | UNIQUE | B-Tree (UQ) | Official DPP/TAPP/Memo reference |
| `title_en` | `varchar(220)` | NO | | | Project title (English) |
| `title_bn` | `varchar(260)` | NO | | | Project title (Bangla) |
| `description` | `text` | YES | | | Scope & description |
| `objectives` | `text` | YES | | | Key objectives |
| `implementing_organization` | `varchar(180)` | NO | | | Implementing agency/office |
| `project_manager_name` | `varchar(150)` | NO | | | Assigned project manager |
| `project_manager_contact` | `varchar(60)` | YES | | | Contact (masked for external observers) |
| `funding_source` | `varchar(150)` | NO | | | GoB / ADP / Donor source |
| `approved_budget_bdt` | `decimal(15,2)` | NO | | | Approved budget in BDT |
| `revised_budget_bdt` | `decimal(15,2)` | YES | | | Revised budget ceiling in BDT |
| `released_funds_bdt` | `decimal(15,2)` | NO | 0 | | Cumulative approved released funds |
| `expenditure_bdt` | `decimal(15,2)` | NO | 0 | | Cumulative reconciled expenditure |
| `physical_progress_pct` | `decimal(5,2)` | NO | 0 | | Weighted physical completion percentage |
| `financial_progress_pct` | `decimal(5,2)` | NO | 0 | | Financial utilization percentage |
| `start_date` | `date` | NO | | B-Tree | Planned start date |
| `expected_end_date` | `date` | NO | | B-Tree | Planned end date |
| `actual_end_date` | `date` | YES | | | Actual completion date |
| `ward_no` | `varchar(40)` | YES | | | Target ward(s) |
| `latitude` | `decimal(10,7)` | YES | | | Primary site GPS latitude |
| `longitude` | `decimal(10,7)` | YES | | | Primary site GPS longitude |
| `boundary_geojson` | `json` | YES | | | Normalized GeoJSON Polygon/MultiPolygon |
| `site_geom` | `geometry(Point, 4326)` | YES | Populated on PostgreSQL/PostGIS | GIST | Worksite GPS point |
| `boundary_geom` | `geometry(MultiPolygon, 4326)` | YES | Populated on PostgreSQL/PostGIS | GIST | Project boundary polygon |
| `status` | `varchar(30)` | NO | 'PROPOSED' (`PROPOSED`, `UNDER_REVIEW`, `APPROVED`, `ONGOING`, `SUSPENDED`, `COMPLETED`, `CLOSED`) | B-Tree | 7-stage lifecycle status |
| `is_delayed` | `boolean` | NO | false | B-Tree | Delay flag |
| `delay_days` | `integer` | NO | 0 | | Observed delay in days |
| `delay_reason` | `text` | YES | | | Documented delay explanation |
| `created_by`, `updated_by`, `reviewed_by`, `approved_by`, `closed_by` | `uuid` | YES | FK &rarr; `core.users(id)` | B-Tree | Lifecycle actor audit columns |
| `created_at`, `updated_at`, `deleted_at` | `timestamptz` | YES | | | Audit & soft delete timestamps |

---

### 7.3 `project.project_locations`
Multiple geographical coverage points/wards/sites per project.

| Column Name | Data Type | Null | Default / Constraints | Indexes | Description |
| :--- | :--- | :---: | :--- | :--- | :--- |
| `id` | `uuid` | NO | PK | B-Tree (PK) | Location ID |
| `project_id` | `uuid` | NO | FK &rarr; `project.projects(id)` ON DELETE CASCADE | B-Tree | Parent project |
| `administrative_unit_id` | `uuid` | NO | FK &rarr; `core.administrative_units(id)` ON DELETE RESTRICT | B-Tree | Division/District/Upazila/Union |
| `ward_no` | `varchar(40)` | YES | | | Ward number |
| `site_name_en`, `site_name_bn` | `varchar` | YES | | | Bilingual site name |
| `latitude`, `longitude` | `decimal(10,7)` | YES | | | Site GPS coordinates |
| `location_geom` | `geometry(Point, 4326)` | YES | Populated on PostgreSQL/PostGIS | GIST | Site GPS geometry |

---

### 7.4 `project.project_milestones`
Work Breakdown Structure (WBS) phases, activities, deliverables, timelines, and certified milestones.

| Column Name | Data Type | Null | Default / Constraints | Indexes | Description |
| :--- | :--- | :---: | :--- | :--- | :--- |
| `id` | `uuid` | NO | PK | B-Tree (PK) | Milestone ID |
| `project_id` | `uuid` | NO | FK &rarr; `project.projects(id)` ON DELETE CASCADE | B-Tree | Parent project |
| `parent_milestone_id` | `uuid` | YES | FK &rarr; `project.project_milestones(id)` ON DELETE SET NULL | B-Tree | Parent WBS node |
| `wbs_code` | `varchar(40)` | NO | UNIQUE (`project_id`, `wbs_code`) | B-Tree | WBS hierarchy code |
| `milestone_title`, `milestone_title_bn` | `varchar` | NO/YES | | | Bilingual milestone/activity title |
| `item_type` | `varchar(20)` | NO | 'MILESTONE' (`PHASE`, `ACTIVITY`, `MILESTONE`) | B-Tree | WBS item type |
| `deliverables` | `text` | YES | | | Expected deliverables |
| `responsible_organization`, `assigned_officer_name` | `varchar` | YES | | | Execution responsibility |
| `planned_start_date`, `planned_end_date`, `actual_start_date`, `actual_end_date` | `date` | YES | | | Planned vs actual schedule |
| `weight_percentage` | `decimal(5,2)` | NO | 10.00 | | Weight for physical progress rollup |
| `target_percentage` | `decimal(5,2)` | NO | 100.00 | | Target completion % |
| `completion_percentage` | `decimal(5,2)` | NO | 0.00 | | Actual physical progress % |
| `approval_status` | `varchar(30)` | NO | 'PENDING' (`PENDING`, `SUBMITTED`, `VERIFIED_ENGINEER`, `APPROVED_PIO`, `REJECTED`) | B-Tree | 4-eye inspection certification state |

---

### 7.5 `project.project_disbursements`
Financial tracking ledger (budget revisions, fund releases, and recorded expenditures — no unrestricted payment execution).

| Column Name | Data Type | Null | Default / Constraints | Indexes | Description |
| :--- | :--- | :---: | :--- | :--- | :--- |
| `id` | `uuid` | NO | PK | B-Tree (PK) | Financial record ID |
| `project_id` | `uuid` | NO | FK &rarr; `project.projects(id)` ON DELETE CASCADE | B-Tree | Parent project |
| `project_milestone_id` | `uuid` | YES | FK &rarr; `project.project_milestones(id)` ON DELETE RESTRICT | B-Tree | Optional linked milestone |
| `record_type` | `varchar(30)` | NO | 'EXPENDITURE' (`BUDGET_REVISION`, `FUND_RELEASE`, `EXPENDITURE`) | B-Tree | Transaction classification |
| `voucher_no` | `varchar(60)` | NO | UNIQUE | B-Tree (UQ) | Voucher / release reference |
| `fiscal_year` | `varchar(20)` | YES | | | Government fiscal year |
| `amount_bdt` | `decimal(15,2)` | NO | | | Amount in BDT |
| `payment_status` | `varchar(30)` | NO | 'PENDING' (`PENDING`, `APPROVED`, `RELEASED`, `RECONCILED`, `REJECTED`) | B-Tree | Authorization status |
| `supporting_doc_path`, `supporting_doc_name`, `supporting_doc_sha256` | `varchar` | YES | | | Private supporting document & checksum |
| `disbursed_at` | `timestamptz` | NO | | B-Tree | Record date/timestamp |
| `created_by`, `approved_by` | `uuid` | NO/YES | FK &rarr; `core.users(id)` | B-Tree | 4-eye SoD preparer & authorizer |

---

### 7.6 `project.project_monitoring_reports` & `project.project_risks`
- `project.project_monitoring_reports`: Site visit inspections (`report_no` UQ, `visit_date`, `inspector_name`, `observed_physical_pct`, `observed_financial_pct`, `delay_observed`, `delay_days`, `delay_reason`, `findings`, `recommendations`, `latitude`, `longitude`, private SHA-256 `attachment_*`).
- `project.project_risks`: Project risks and issues register (`risk_code`, `record_kind` in `RISK`/`ISSUE`, `title`, `severity` in `LOW`/`MEDIUM`/`HIGH`/`CRITICAL`, `likelihood`, `mitigation_plan`, `owner_name`, `status` in `OPEN`/`MITIGATING`/`RESOLVED`/`CLOSED`, `identified_date`, `resolved_date`).

---

## 8. Schema: `volunteer` (Volunteer Rosters, Organizations, Deployments, Attendance & Training)

### 8.1 `volunteer.volunteer_categories` & `volunteer.skill_catalog`
- `volunteer.volunteer_categories`: Configurable volunteer categories (`SEARCH_AND_RESCUE`, `FIRST_AID`, `MEDICAL_SUPPORT`, `EVACUATION_SUPPORT`, `RELIEF_DISTRIBUTION`, `COMMUNICATION`, `COMMUNITY_AWARENESS`, and custom categories).
- `volunteer.skill_catalog`: Bilingual skills & qualifications (`skill_code`, `category_id`, `name_en`, `name_bn`, `requires_certification`, `is_system`, `is_active`).

### 8.2 `volunteer.volunteer_organizations` & `volunteer.volunteer_organization_coverages`
- `volunteer.volunteer_organizations`: Sponsoring organization profiles (`org_code`, `name_en`, `name_bn`, `org_type` in `CPP`/`BDRCS`/`SCOUT`/`BNCC`/`NGO`/`INGO`/`COMMUNITY`/`GOVERNMENT`/`OTHER`, `registration_no`, `administrative_unit_id`, contact person details, `verification_status` in `PENDING`/`VERIFIED`/`REJECTED`, `is_active`).
- `volunteer.volunteer_organization_coverages`: Multi-unit geographic coverage mapping (`organization_id`, `administrative_unit_id`).

### 8.3 `volunteer.volunteers`
Cyclone Preparedness Programme (CPP), Red Crescent, and community volunteer registry with AES-256 encrypted PII (`nid_encrypted`, `mobile_encrypted`, `email_encrypted`, `emergency_contact_encrypted`), HMAC-SHA256 deduplication hashes (`nid_hash`, `mobile_hash`), masked display columns, private SHA-256 photograph metadata, 3-pillar verification statuses (`identity_verification_status`, `organization_verification_status`, `training_verification_status`, `overall_verification_status`), `nid_verification_id` (nullable FK to `beneficiary.nid_verifications`), `availability_status` (`AVAILABLE`, `STANDBY`, `DEPLOYED`, `ON_LEAVE`, `UNAVAILABLE`, `INACTIVE`), consent-gated `latitude`/`longitude`, and PostGIS `location_geom`.

### 8.4 `volunteer.volunteer_skill_assignments` & `volunteer.volunteer_verification_logs`
- `volunteer.volunteer_skill_assignments`: Volunteer skill mappings (`volunteer_id`, `skill_id`, `proficiency_level`, `certification_level`, `certified_by`, `issued_at`, `expires_at`, `verification_status`).
- `volunteer.volunteer_verification_logs`: Immutable verification & approval audit trail (`volunteer_id`, `pillar`, `action`, `from_status`, `to_status`, `remarks`, `actor_user_id`, `created_at`).

### 8.5 `volunteer.volunteer_deployments`
Emergency assignments linked to `disaster.disaster_events`, `response.response_operations`, `response.response_teams`, and `core.administrative_units` (`deployment_no`, `deployment_location`, PostGIS `location_geom`, `assigned_role`, `assigned_duties`, `start_date`, `end_date`, `actual_end_date`, supervisor details, `deployment_status` in `ASSIGNED`/`ACTIVE`/`COMPLETED`/`CANCELLED`).

### 8.6 `volunteer.volunteer_attendances`
Check-in/check-out records (`check_in_at`, `check_out_at`, `duration_minutes`), explicit `gps_consent_given` coordinates (`check_in_lat`/`lng`, `check_out_lat`/`lng`), activity reports (`activity_summary`, `tasks_completed`, `beneficiaries_assisted`), SHA-256 private evidence attachment, and supervisor confirmation (`confirmation_status` in `PENDING`/`CONFIRMED`/`DISPUTED`, `supervisor_confirmed_by`, `supervisor_confirmed_at`).

### 8.7 `volunteer.volunteer_training_programs`, `volunteer.volunteer_training_sessions` & `volunteer.volunteer_training_enrollments`
- `volunteer.volunteer_training_programs`: Training curriculum definitions (`program_code`, `title_en`, `title_bn`, `category_id`, `skill_id`, `provider_name`, `duration_hours`, `validity_months`).
- `volunteer.volunteer_training_sessions`: Scheduled sessions (`session_code`, `program_id`, `administrative_unit_id`, `venue`, `start_date`, `end_date`, `trainer_name`, `max_participants`, `status`).
- `volunteer.volunteer_training_enrollments`: Attendance, assessment, and certification records (`enrollment_status`, `attendance_hours`, `assessment_score`, `passed`, `certificate_no`, `issued_at`, `expires_at`, private SHA-256 certificate file metadata).

---

## 9. Schema: `workflow` (Configurable Workflow & Multi-Tier Approvals)

### 9.1 `workflow.workflow_definitions`
Configurable workflow templates per module and target entity (`code`, `name_en`, `name_bn`, `module_code`, `entity_type`, `is_active`, `is_default`, `is_system`, `allow_self_approval`, `allow_delegation`, `sync_entity_status`, `sla_hours`, `escalation_enabled`, `version`, `config_metadata`).

### 9.2 `workflow.workflow_stages` & `workflow.workflow_stage_assignments`
- `workflow.workflow_stages`: Ordered sequential or parallel (`SEQUENTIAL`, `PARALLEL_ALL`, `PARALLEL_ANY`) approval gates across levels (`SUBMITTED`, `INITIAL_REVIEW`, `SUPERVISOR_APPROVAL`, `DISTRICT_APPROVAL`, `DIRECTORATE_APPROVAL`, `MINISTRY_APPROVAL`, `FINAL_APPROVAL`), `required_approvals`, `sla_hours`, `escalation_role_code`, and action permissions (`allow_delegation`, `allow_return_for_correction`, `allow_info_request`, `allow_forward`).
- `workflow.workflow_stage_assignments`: Flexible stage assignee bindings (`USER`, `ROLE`, `ADMINISTRATIVE_UNIT`) and authority (`REVIEWER`, `APPROVER`, `ENDORSE`, `OBSERVER`).

### 9.3 `workflow.workflow_delegations`
Authorized time-bound approval delegations (`workflow_definition_id`, `delegator_user_id`, `delegate_user_id`, `administrative_unit_id`, `delegated_role_code`, `valid_from`, `valid_until`, `reason`, `is_active`, `revoked_by`, `revoked_at`).

### 9.4 `workflow.workflow_instances`
Runtime workflow instances across integrated IDMS modules (`instance_no`, `workflow_definition_id`, `module_code`, `entity_type`, `entity_id`, `entity_reference`, `administrative_unit_id`, `current_stage_id`, `current_state`, `priority`, `submitted_by`, `submitted_at`, `assigned_to_user_id`, `assigned_role_code`, `due_at`, `escalated_at`, `escalation_count`, `completed_at`, `metadata`).

### 9.5 `workflow.workflow_stage_tasks`
Active and historical stage-level reviewer/approver tasks supporting parallel dual-authorization (`step_sequence`, `assignee_type`, `assignee_user_id`, `assignee_role_code`, `delegated_from_user_id`, `authority_type`, `status`, `acted_by_user_id`, `acted_at`, `comments`).

### 9.6 `workflow.workflow_transitions` (Immutable Permanent Audit)
Append-only immutable log of all state changes and decisions (`workflow_instance_id`, `workflow_stage_task_id`, `from_stage_id`, `to_stage_id`, `action`, `from_state`, `to_state`, `action_by_user_id`, `acted_on_behalf_of_user_id`, `actor_name`, `actor_role`, `remarks`, `metadata`, `transitioned_at`).

### 9.7 `workflow.workflow_attachments` & `workflow.workflow_notifications`
- `workflow.workflow_attachments`: Private disk supporting documents/files with MIME validation and SHA-256 checksums (`workflow_instance_id`, `workflow_transition_id`, `original_name`, `storage_path`, `mime_type`, `file_size_bytes`, `sha256_hash`).
- `workflow.workflow_notifications`: Queued/dispatched assignment notices, status updates, pending approval reminders, and SLA escalation alerts (`notification_type`, `channel`, `title_en`, `title_bn`, `message_en`, `message_bn`, `delivery_status`, `read_at`).

---

## 10. Schema: `notification` (Module 13: Notification & Alert Management) & `document`

### 10.1 `notification.notification_templates`
Reusable bilingual notification templates (`IN_APP`, `EMAIL`, `SMS`, `PUSH`, or `MULTI`) across disaster warnings, emergency alerts, workflow updates, inventory alerts, and system announcements.
- Key columns: `id` (UUID PK), `template_code` (unique), `category`, `channel`, `default_severity`, `title_bn`, `title_en`, `subject_bn`, `subject_en`, `body_bn`, `body_en`, `variables` (`jsonb`), `is_system`, `is_active`, `created_by`, `updated_by`, timestamps, `deleted_at`.

### 10.1A `notification.notifications`
Master notification campaigns, scheduled dispatches, and event-driven notices targeted by user, role, organization, geography (`administrative_unit_id`), or system-wide broadcast.
- Key columns: `id` (UUID PK), `notification_no` (unique), `template_id` (FK &rarr; `notification.notification_templates`), `category`, `severity`, `priority`, `channels` (`jsonb`), `title_bn`, `title_en`, `message_bn`, `message_en`, `target_type`, `target_criteria` (`jsonb`), `administrative_unit_id` (FK &rarr; `core.administrative_units`), `related_entity_type`, `related_entity_id`, `scheduled_at`, `dispatched_at`, `status`, `idempotency_key` (unique), `total_recipients`, `delivered_count`, `failed_count`, `created_by`, `updated_by`, timestamps, `deleted_at`.

### 10.1B `notification.notification_dispatches`
Per-recipient, per-channel outbound delivery queue, retry state, provider receipt logs, and in-app read status.

| Column Name | Data Type | Null | Default / Constraints | Indexes | Description |
| :--- | :--- | :---: | :--- | :--- | :--- |
| `id` | `uuid` | NO | PK | B-Tree (PK) | Dispatch ID |
| `notification_id` | `uuid` | NO | FK &rarr; `notification.notifications(id)` | B-Tree | Parent notification record |
| `template_id` | `uuid` | YES | FK &rarr; `notification.notification_templates(id)` | B-Tree | Template used |
| `recipient_user_id`| `uuid` | YES | FK &rarr; `core.users(id)` | B-Tree | Recipient user |
| `administrative_unit_id`| `uuid` | YES | FK &rarr; `core.administrative_units(id)` | B-Tree | Jurisdiction unit |
| `channel` | `varchar(20)` | NO | CHECK in ('IN_APP', 'EMAIL', 'SMS', 'PUSH') | B-Tree | Dispatch channel |
| `destination_address`| `varchar(255)`| YES | | | Masked phone/email/token ref (never exposed raw in logs/API) |
| `title_bn` / `title_en` | `varchar(250)` | YES | | | Rendered bilingual title |
| `message_body` | `text` | NO | | | Rendered notification content |
| `message_body_bn` / `message_body_en` | `text` | YES | | | Bilingual rendered payloads |
| `delivery_status`| `varchar(30)`| NO | CHECK in ('QUEUED', 'RETRYING', 'SENT', 'DELIVERED', 'FAILED', 'SKIPPED') | B-Tree | Delivery lifecycle status |
| `provider_name` | `varchar(60)` | YES | | | Adapter identifier (e.g., `mock_sms`, `teletalk_sms`, `smtp`, `fcm_push`, `in_app`) |
| `provider_reference` | `varchar(120)` | YES | | | External gateway message ID |
| `attempt_count` | `integer` | NO | DEFAULT 0 | | Number of delivery attempts |
| `last_error` | `text` | YES | | | Sanitized transient/permanent failure reason |
| `read_at` | `timestamptz` | YES | | B-Tree | In-app read receipt timestamp |
| `dispatched_at` | `timestamptz` | YES | | B-Tree | Dispatch timestamp |
| `delivered_at` | `timestamptz` | YES | | | Delivery confirmation timestamp |

### 10.1C `notification.alert_rules` & `notification.alerts`
- `notification.alert_rules`: Configurable alert rules, threshold conditions (`condition_config` JSONB), severity, target channels, escalation delay (`escalation_delay_minutes`), `escalation_role_code`, and `administrative_unit_id`.
- `notification.alerts`: Active, acknowledged, escalated, and resolved alert instances (`alert_no`, `alert_rule_id`, `category`, `severity`, `status`, `source_module`, `source_entity_type`, `source_entity_id`, `administrative_unit_id`, `triggered_at`, `acknowledged_by`, `acknowledged_at`, `acknowledgement_notes`, `escalated_at`, `resolved_by`, `resolved_at`, `resolution_notes`).

### 10.1D `notification.notification_preferences` & `notification.notification_devices`
- `notification.notification_preferences`: Per-user, per-category channel toggles (`in_app_enabled`, `email_enabled`, `sms_enabled`, `push_enabled`, `quiet_hours_start`, `quiet_hours_end`, `policy_locked` for mandatory critical emergency alerts).
- `notification.notification_devices`: Encrypted Flutter/mobile push device registrations (`user_id`, `device_uuid`, `platform`, `push_provider`, `push_token_encrypted`, `push_token_hash`, `device_name`, `app_version`, `is_active`, `last_seen_at`).

---

### 10.2 `document.document_categories` (Module 14: Document Management)
Configurable bilingual document categories (`GOVERNMENT_ORDER`, `DISASTER_REPORT`, `ASSESSMENT_REPORT`, `APPROVAL_DOCUMENT`, `PROJECT_DOCUMENT`, `RELIEF_DISTRIBUTION_DOCUMENT`, `AGREEMENT`, `PHOTOGRAPH`, `OTHER`, and custom categories) with `default_classification`, `allowed_formats` (JSON), `retention_days`, `requires_approval`, `is_system`, `is_active`, `sort_order`, and soft deletes.

### 10.3 `document.documents`
Centralized electronic document metadata (e-Docs) with version pointers, classification, expiry, approval workflow, and cross-module entity integration.

| Column Name | Data Type | Null | Default / Constraints | Indexes | Description |
| :--- | :--- | :---: | :--- | :--- | :--- |
| `id` | `uuid` | NO | PK | B-Tree (PK) | Document ID |
| `document_no` | `varchar(60)` | NO | UNIQUE | B-Tree (UK) | System document identifier (`DOC-YYYYMMDD-XXXXXX`) |
| `reference_no` | `varchar(120)` | YES | | B-Tree | Official memo / GO / circular reference number |
| `category_id` | `uuid` | NO | FK &rarr; `document.document_categories(id)` | B-Tree | Configurable document category |
| `document_type` | `varchar(60)` | NO | DEFAULT `'OTHER'` | B-Tree | `GOVERNMENT_ORDER`, `CIRCULAR`, `SITREP`, `SURVEY_REPORT`, `APPROVAL_LETTER`, `MUSTER_ROLL`, `VOUCHER`, `AGREEMENT`, `BLUEPRINT`, `PHOTOGRAPH`, `CERTIFICATE`, `OTHER` |
| `title_bn` / `title_en` | `varchar(300)` | NO | | | Bilingual document title |
| `description_bn` / `description_en` | `text` | YES | | | Bilingual summary / description |
| `issuing_organization` | `varchar(200)` | YES | | B-Tree | Issuing ministry/office/agency |
| `organization_id` | `uuid` | YES | FK &rarr; `core.organizations(id)` | B-Tree | Linked sovereign organization |
| `administrative_unit_id` | `uuid` | YES | FK &rarr; `core.administrative_units(id)` | B-Tree | 7-tier jurisdiction unit |
| `owner_user_id` | `uuid` | YES | FK &rarr; `core.users(id)` | B-Tree | Custodian / document owner |
| `uploaded_by` | `uuid` | YES | FK &rarr; `core.users(id)` | B-Tree | Uploader officer |
| `uploaded_at` | `timestamptz` | YES | | B-Tree | Upload timestamp |
| `effective_date` / `expires_at` | `date` / `timestamptz` | YES | | B-Tree (`expires_at`) | Validity window & expiry tracking |
| `module_code` | `varchar(40)` | NO | DEFAULT `'GENERAL'` | Composite | Integrated IDMS module (`GENERAL`, `BENEFICIARY`, `DISASTER`, `DAMAGE`, `RELIEF`, `PROJECT`, `WORKFLOW`, etc.) |
| `entity_type` / `entity_id` | `varchar(80)` / `uuid` | YES | | Composite `(entity_type, entity_id)` | Polymorphic target record (`HOUSEHOLD`, `DISASTER_EVENT`, `DAMAGE_ASSESSMENT`, `RELIEF_REQUISITION`, `RELIEF_ALLOCATION`, `DISTRIBUTION_VOUCHER`, `PROJECT`, `PROJECT_DISBURSEMENT`, `WORKFLOW_INSTANCE`) |
| `entity_reference` | `varchar(120)` | YES | | B-Tree | Cached target code (e.g. `HH-DAC-001`) |
| `classification` | `varchar(30)` | NO | DEFAULT `'INTERNAL'` | B-Tree | `PUBLIC`, `INTERNAL`, `CONFIDENTIAL`, `RESTRICTED` |
| `tags` | `jsonb` | YES | | | Searchable keyword tags |
| `approval_status` | `varchar(30)` | NO | DEFAULT `'DRAFT'` | Composite | `DRAFT`, `PENDING_APPROVAL`, `APPROVED`, `REJECTED`, `ARCHIVED` |
| `submitted_by`, `approved_by`, `rejected_by` | `uuid` | YES | FK &rarr; `core.users(id)` | | 4-eye SoD approval/rejection actor tracking |
| `current_version_number` / `current_version_id` | `integer` / `uuid` | NO/YES | DEFAULT 1 | B-Tree | Active version pointer |
| `original_filename` | `varchar(255)` | NO | | | Sanitized original uploaded filename |
| `storage_disk` / `storage_path` | `varchar(40)` / `varchar(500)` | NO | | | Private storage disk & UUID-obfuscated object key (hidden from API JSON) |
| `mime_type` / `file_extension` | `varchar(120)` / `varchar(20)` | NO | | B-Tree | Verified magic-byte MIME & extension |
| `file_size_bytes` | `bigint` | NO | CHECK (`file_size_bytes > 0`) | | Size in bytes |
| `file_hash_sha256` | `char(64)` | NO | | B-Tree | Cryptographic SHA-256 integrity checksum |
| `scan_status` | `varchar(30)` | NO | DEFAULT `'PENDING'` | B-Tree | `PENDING`, `CLEAN`, `INFECTED`, `FAILED` |
| `is_confidential` | `boolean` | NO | DEFAULT FALSE | B-Tree | Confidential/restricted access flag |

### 10.4 `document.document_versions` & `document.document_access_logs`
- `document.document_versions`: Immutable file version history (`document_id`, `version_number` unique per document, `version_label`, `change_notes`, `original_filename`, `storage_disk`, `storage_path`, `mime_type`, `file_extension`, `file_size_bytes`, `file_hash_sha256`, `scan_status`, `scanned_at`, `scan_engine`, `is_current`, `uploaded_by`).
- `document.document_access_logs`: Complete document access & modification trail (`document_id`, `document_version_id`, `user_id`, `administrative_unit_id`, `action` — `UPLOADED`, `VIEWED_METADATA`, `PREVIEWED`, `DOWNLOADED`, `SIGNED_URL_ISSUED`, `VERSION_UPLOADED`, `VERSION_RESTORED`, `UPDATED`, `SUBMITTED_FOR_APPROVAL`, `APPROVED`, `REJECTED`, `ARCHIVED`, `INTEGRITY_VERIFIED`, `DELETED`, `ip_address`, `user_agent`, `details`, `created_at`).

---

## 11. Schema: `audit` (Immutable Audit Trail)

### 11.1 `audit.audit_logs` (Partitioned by Month)
Central system event ledger capturing all state mutations.

| Column Name | Data Type | Null | Default / Constraints | Indexes | Description |
| :--- | :--- | :---: | :--- | :--- | :--- |
| `id` | `uuid` | NO | PK | B-Tree (PK) | Log record ID |
| `user_id` | `uuid` | YES | FK &rarr; `core.users(id)` ON DELETE SET NULL | B-Tree | Acting user (NULL for automated cron) |
| `action_name` | `varchar(100)`| NO | | B-Tree | e.g. `VOUCHER_DISBURSED`, `ALLOCATION_SANCTIONED` |
| `auditable_type`| `varchar(150)`| NO | | B-Tree | Mutated Eloquent model |
| `auditable_id` | `uuid` | NO | | B-Tree | Mutated record ID |
| `administrative_unit_id`| `uuid`| YES | FK &rarr; `core.administrative_units(id)` | B-Tree | Scope of user at moment of action |
| `old_values` | `jsonb` | YES | | GIN | Record state before mutation |
| `new_values` | `jsonb` | YES | | GIN | Record state after mutation |
| `ip_address` | `varchar(45)` | YES | | | IPv4 / IPv6 client address |
| `user_agent` | `text` | YES | | | Browser / Device user agent string |
| `created_at` | `timestamptz` | NO | Partition Key | B-Tree | Event timestamp (Partition criteria) |

---

## 12. Schema: `response` & `resource` (Module 05: Disaster Response Management)

### 12.1 `response.response_activity_types`
Configurable response activity taxonomy (`SEARCH_RESCUE`, `EMERGENCY_EVACUATION`, `MEDICAL_ASSISTANCE`, `EMERGENCY_SHELTER`, `FOOD_WATER_SUPPLY`, `ROAD_COMM_RESTORATION`, `EMERGENCY_TRANSPORT`, and custom types).

### 12.2 `response.response_centers`
Emergency Operations Centers (EOCs) and field command posts with `administrative_unit_id`, `center_code`, `center_type`, bilingual names, contact phone, `latitude`/`longitude`, and PostGIS `location_geom` (Point, 4326).

### 12.3 `resource.resource_assets`
Deployable emergency assets (rescue boats, 4WD vehicles, ambulances, water purification units, generators) with `administrative_unit_id`, `asset_code`, `asset_type`, `status` (`AVAILABLE`, `DEPLOYED`, `MAINTENANCE`, `RETIRED`).

### 12.4 `response.response_operations`
Core response operations linked to `disaster.disaster_events`, `core.administrative_units`, and optional `response.response_centers` with `operation_no`, bilingual title, objectives, priority, severity, 6-state lifecycle (`PLANNED`, `ACTIVATED`, `ONGOING`, `SUSPENDED`, `COMPLETED`, `CLOSED`), schedules, responsible agency/commander, and soft deletes.

### 12.5 `response.response_operation_agencies` & `response.response_teams`
Participating government agencies/NGOs and field response teams (`FIRE_SERVICE`, `ARMED_FORCES`, `COAST_GUARD`, `CPP_VOLUNTEERS`, `MEDICAL_TEAM`, `BD_POLICE`, `SAR_UNIT`) with member counts, leader contact, `status` (`STANDBY`, `DEPLOYED`, `ACTIVE`, `DEMOBILIZED`), GPS coordinates, and PostGIS `location_geom`.

### 12.6 `response.response_tasks` & `response.response_task_attachments`
Operational tasks linked to operations, activity types, teams, and assignee officers with deadlines, priorities, `progress_percentage` (0–100), `status` (`PENDING`, `IN_PROGRESS`, `BLOCKED`, `COMPLETED`, `CANCELLED`), and SHA-256 checksummed private evidence attachments.

### 12.7 `response.response_resource_allocations`
Resource requirements and deployed vehicle/equipment links tied to operations and `resource.resource_assets` (`REQUESTED`, `ALLOCATED`, `DEPLOYED`, `RETURNED`).

### 12.8 `response.response_situation_reports`
Emergency incident reports, field updates, casualty/missing/rescued/evacuated figures, emergency requirements, GPS coordinates + PostGIS `location_geom`, and `privacy_level` (`PUBLIC_AREA`, `RESTRICTED_EXACT`).

---

## 13. Schema: `resource` (Module 07: Resource Management)

### 13.1 `resource.resource_categories`
Configurable resource category catalog supporting system categories (`VEHICLES`, `BOATS`, `RESCUE_EQUIPMENT`, `MEDICAL_EQUIPMENT`, `COMMUNICATION_EQUIPMENT`, `HEAVY_MACHINERY`, `EMERGENCY_RESPONSE_EQUIPMENT`, `HUMAN_RESOURCES`) and custom categories (`is_system = false`), with `is_human_resource`, icon, and color metadata.

### 13.2 `resource.resource_assets` (Extended from Module 05 Foundation)
Centralized sovereign resource register sharing the `resource.resource_assets` foundation with Module 05. Adds `category_id` FK &rarr; `resource.resource_categories(id)`, `resource_type`, `description`, `specifications` (JSON), `owning_organization`, `current_location`, `responsible_officer_name`, `responsible_officer_contact`, `responsible_user_id` FK &rarr; `core.users(id)`, `acquisition_date`, `acquisition_value_bdt`, `next_maintenance_due_at`, `created_by`, `updated_by`, and PostGIS `location_geom` (`geometry(Point, 4326)` with GIST index). Supports availability states `AVAILABLE`, `ASSIGNED`, `IN_USE`, `UNDER_MAINTENANCE`, `DAMAGED`, `RETIRED` (plus Module 05 `DEPLOYED`/`MAINTENANCE` compatibility).

### 13.3 `resource.resource_attachments`
Private supporting documents and photographs (`DOCUMENT`, `PHOTOGRAPH`) for registered resources, stored on private local storage with MIME validation, byte size, and SHA-256 integrity hash.

### 13.4 `resource.resource_allocations`
Transaction-safe resource assignment and utilization history linking `resource_asset_id` to `disaster.disaster_events`, optional `response.response_operations`, and `core.administrative_units`, tracking `allocation_no`, `allocation_location`, GPS coordinates + PostGIS `location_geom`, `assignment_date`, `expected_return_date`, `actual_return_date`, `responsible_officer_name`, `responsible_officer_contact`, `status` (`ASSIGNED`, `IN_USE`, `RETURNED`, `CANCELLED`), and return condition notes.

### 13.5 `resource.resource_maintenances`
Maintenance schedules, preventive/corrective maintenance records, inspection reports (`PASS`, `CONDITIONAL`, `FAIL`), repair costs (`repair_cost_bdt`), vendor/workshop details, `next_due_date` + `reminder_days_before` for maintenance reminders, and SHA-256 hashed private inspection attachments.

### 13.6 `resource.human_resource_deployments`
Human resource deployment tracking linking personnel (`resource_asset_id`, `user_id`, or named personnel) and/or `response.response_teams` to `response.response_operations` and/or `disaster.disaster_events`, recording `deployment_no`, `assigned_duty`, `deployment_location`, `deployment_start_date`, `deployment_end_date`, `actual_return_date`, and `availability_status` (`ASSIGNED`, `IN_USE`, `COMPLETED`, `CANCELLED`).

---

## 14. Schema: `core` Extensions (Module 12: User Management, RBAC & Organizational Scoping)

### 14.1 `core.organizations`
Statutory ministries, directorates, divisional/district/upazila/union disaster offices, defense/rescue agencies, and humanitarian partner organizations (`org_code` unique, bilingual `name_en`/`name_bn`, `org_type`, `tier_level`, `administrative_unit_id` FK &rarr; `core.administrative_units(id)`, self-referencing `parent_organization_id`, contact fields, `is_active`, and soft deletes).

### 14.2 `core.users` (Module 12 Extensions)
Extends the passwordless Keycloak-federated registry with `employee_id` (unique nullable), `designation_bn`, `organization_id` FK &rarr; `core.organizations(id)`, `department_en`, `department_bn`, lifecycle status `account_status` (`PENDING`, `ACTIVE`, `SUSPENDED`, `DEACTIVATED`, `LOCKED`), `status_reason`, `status_changed_at`, `status_changed_by`, private profile photograph metadata (`photo_path`, `photo_mime`, `photo_hash`), Keycloak IdP sync tracking (`keycloak_username`, `idp_sync_status`, `idp_synced_at`, `idp_sync_error`), MFA policy & verification flags (`mfa_enforced`, `mfa_verified`, `mfa_enrolled_at`), and nullable `keycloak_sub` for pre-provisioned pending accounts. Zero passwords are stored.

### 14.3 `core.roles`, `core.permissions`, `core.role_permissions`, `core.user_roles`
Dynamic RBAC tables storing statutory (`is_system = true`) and configurable custom roles (`code`, bilingual names, `tier_level`, `hierarchy_rank` 1–7 for privilege-escalation prevention, `mfa_required`), granular module/action permissions (`code`, `module_code`, `action_type`, `is_sensitive`), role-permission mappings, and multi-role user assignments (`is_primary`, `assigned_by`, `assigned_at`).

### 14.4 `core.user_administrative_scopes`
Organizational and temporary geographical assignments linking `user_id` to `core.administrative_units(id)` with `assignment_type` (`PRIMARY_POSTING`, `ADDITIONAL_CHARGE`, `EMERGENCY_DEPUTATION`, `INSPECTION_TAG`), `is_active`, `valid_from`, `valid_until` (auto-expiring temporary deputations), `delegated_by`, `official_order_ref`, and `notes`.
