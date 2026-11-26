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

### 4.1 `damage.damage_assessments`
Official Government of Bangladesh D-Form master records.

| Column Name | Data Type | Null | Default / Constraints | Indexes | Description |
| :--- | :--- | :---: | :--- | :--- | :--- |
| `id` | `uuid` | NO | PK | B-Tree (PK) | Assessment identifier |
| `disaster_event_id`| `uuid` | NO | FK &rarr; `disaster.disaster_events(id)` ON DELETE RESTRICT | B-Tree | Associated disaster |
| `administrative_unit_id`| `uuid`| NO | FK &rarr; `core.administrative_units(id)` ON DELETE RESTRICT | B-Tree | Assessed administrative jurisdiction |
| `d_form_code` | `varchar(50)` | NO | UNIQUE | B-Tree (UQ) | D-form serial number |
| `assessment_stage`| `varchar(30)`| NO | CHECK in ('INITIAL_SOS', 'PRELIMINARY_D_FORM', 'FINAL_CONSOLIDATED') | B-Tree | Assessment phase |
| `validation_status`| `varchar(30)`| NO | CHECK in ('DRAFT', 'SUBMITTED_UNION', 'VERIFIED_PIO', 'ENDORSED_DRRO', 'APPROVED_DDM') | B-Tree | Workflow validation state |
| `affected_population`| `int` | NO | 0 | | Total persons affected |
| `displaced_population`| `int`| NO | 0 | | Displaced persons in camps/shelters |
| `deaths_count` | `int` | NO | 0 | | Confirmed human deaths |
| `injured_count` | `int` | NO | 0 | | Confirmed injured persons |
| `missing_count` | `int` | NO | 0 | | Missing persons |
| `total_estimated_loss_bdt`| `decimal(15,2)`| NO | 0.00 | B-Tree | Total monetary loss in BDT |
| `damage_area_geom`| `geometry(MultiPolygon, 4326)`| YES | | GIST | Spatial polygon of surveyed damage |
| `assessed_by` | `uuid` | NO | FK &rarr; `core.users(id)` | | Submitting officer |
| `created_at` | `timestamptz` | NO | CURRENT_TIMESTAMP | B-Tree | Submission timestamp |
| `updated_at` | `timestamptz` | NO | CURRENT_TIMESTAMP | | Modification timestamp |

---

### 4.2 `damage.damage_sector_items`
Granular sectoral breakdown of losses (Housing, Agriculture, Livestock, Fisheries, Infrastructure).

| Column Name | Data Type | Null | Default / Constraints | Indexes | Description |
| :--- | :--- | :---: | :--- | :--- | :--- |
| `id` | `uuid` | NO | PK | B-Tree (PK) | Sector item ID |
| `damage_assessment_id`| `uuid`| NO | FK &rarr; `damage.damage_assessments(id)` ON DELETE CASCADE | B-Tree | Parent D-form record |
| `sector_code` | `varchar(50)` | NO | CHECK in ('HOUSING', 'AGRICULTURE', 'LIVESTOCK', 'FISHERIES', 'ROADS_BRIDGES', 'EMBANKMENT', 'TUBE_WELLS') | B-Tree | Damaged sector |
| `fully_damaged_count`| `int` | NO | 0 | | Fully destroyed units/structures/acres |
| `partially_damaged_count`| `int`| NO | 0 | | Partially damaged units |
| `estimated_loss_bdt`| `decimal(14,2)`| NO | 0.00 | | Economic loss in BDT for this sector |
| `remarks` | `text` | YES | | | Field notes and descriptions |

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
| `is_perishable` | `boolean` | NO | false | | Perishability indicator |
| `is_active` | `boolean` | NO | true | B-Tree | Active flag |
| `created_at` | `timestamptz` | NO | CURRENT_TIMESTAMP | | Creation timestamp |
| `updated_at` | `timestamptz` | NO | CURRENT_TIMESTAMP | | Update timestamp |

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
| `capacity_mt` | `decimal(10,2)`| NO | | | Storage capacity in Metric Tons |
| `latitude` | `decimal(10,7)` | YES | | | GPS latitude |
| `longitude` | `decimal(10,7)` | YES | | | GPS longitude |
| `location_geom` | `geometry(Point, 4326)`| YES | Populated on PostgreSQL/PostGIS | GIST | Warehouse GPS coordinates |
| `is_active` | `boolean` | NO | true | B-Tree | Operational flag |
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
| `current_quantity`| `decimal(12,2)`| NO | CHECK (current_quantity >= 0) | B-Tree | Available stock balance |
| `reserved_quantity`| `decimal(12,2)`| NO | 0, CHECK (reserved_quantity >= 0) | | Quantity reserved for approved allocations |
| `unit_cost_bdt` | `decimal(12,2)` | YES | | | Unit valuation in BDT |
| `received_date` | `date` | YES | | B-Tree | Batch receipt date |
| `expiry_date` | `date` | YES | | B-Tree | Expiration date for food/medicines (FEFO ordering) |
| `created_at` | `timestamptz` | NO | CURRENT_TIMESTAMP | | Lot creation timestamp |
| `updated_at` | `timestamptz` | NO | CURRENT_TIMESTAMP | | Lot modification timestamp |

*Unique Constraint:* `UNIQUE (warehouse_id, commodity_id, batch_number)`

---

### 5.4 `logistics.stock_transactions` (Immutable Ledger)
Double-entry inventory audit ledger.

| Column Name | Data Type | Null | Default / Constraints | Indexes | Description |
| :--- | :--- | :---: | :--- | :--- | :--- |
| `id` | `uuid` | NO | PK | B-Tree (PK) | Transaction ID |
| `warehouse_inventory_id`| `uuid`| NO | FK &rarr; `logistics.warehouse_inventories(id)` ON DELETE RESTRICT | B-Tree | Affected inventory lot |
| `transaction_type`| `varchar(35)`| NO | CHECK in ('STOCK_IN_GRN', 'INTER_WAREHOUSE_TRANSFER', 'DISTRIBUTION_ISSUE', 'ALLOCATION_RETURN', 'WRITE_OFF_DAMAGED') | B-Tree | Ledger movement type |
| `quantity` | `decimal(12,2)`| NO | CHECK (quantity != 0) | | Quantity moved (+ for in, - for out) |
| `balance_after` | `decimal(12,2)`| NO | CHECK (balance_after >= 0) | | Running balance post-transaction |
| `reference_voucher_no`| `varchar(60)`| NO | | B-Tree | Source invoice, transfer order, or distribution voucher |
| `relief_allocation_item_id`| `uuid` | YES | FK &rarr; `logistics.relief_allocation_items(id)` ON DELETE SET NULL | B-Tree | Related allocation item (if applicable) |
| `distribution_voucher_id`| `uuid` | YES | FK &rarr; `distribution.distribution_vouchers(id)` ON DELETE SET NULL | B-Tree | Related distribution voucher (if applicable) |
| `notes` | `text` | YES | | | Movement remarks |
| `authorized_by` | `uuid` | NO | FK &rarr; `core.users(id)` | B-Tree | Authorizing officer |
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

## 8. Schema: `volunteer` (Volunteer Rosters & Mobilization)

### 8.1 `volunteer.volunteers`
Cyclone Preparedness Programme (CPP) and Red Crescent volunteer registry.

| Column Name | Data Type | Null | Default / Constraints | Indexes | Description |
| :--- | :--- | :---: | :--- | :--- | :--- |
| `id` | `uuid` | NO | PK | B-Tree (PK) | Volunteer ID |
| `administrative_unit_id`| `uuid`| NO | FK &rarr; `core.administrative_units(id)` ON DELETE RESTRICT | B-Tree | Base union / unit |
| `volunteer_code`| `varchar(30)` | NO | UNIQUE | B-Tree (UQ) | e.g. `CPP-VOL-4047-01` |
| `nid_hash` | `char(64)` | NO | UNIQUE | B-Tree (UQ) | HMAC-SHA256 NID hash |
| `full_name_en` | `varchar(150)`| NO | | B-Tree | Volunteer name |
| `organization` | `varchar(50)` | NO | CHECK in ('CPP', 'BDRCS', 'SCOUT', 'COMMUNITY') | B-Tree | Sponsoring body |
| `mobile_encrypted`| `text` | NO | | | AES-256-GCM contact mobile (PII) |
| `status` | `varchar(30)` | NO | CHECK in ('ACTIVE', 'STANDBY', 'DEPLOYED', 'INACTIVE') | B-Tree | Mobilization status |
| `created_at` | `timestamptz` | NO | CURRENT_TIMESTAMP | | Audit timestamp |
| `updated_at` | `timestamptz` | NO | CURRENT_TIMESTAMP | | Audit timestamp |

---

### 8.2 `volunteer.volunteer_deployments`
Emergency assignments during crisis response.

| Column Name | Data Type | Null | Default / Constraints | Indexes | Description |
| :--- | :--- | :---: | :--- | :--- | :--- |
| `id` | `uuid` | NO | PK | B-Tree (PK) | Deployment ID |
| `volunteer_id` | `uuid` | NO | FK &rarr; `volunteer.volunteers(id)` ON DELETE CASCADE | B-Tree | Mobilized volunteer |
| `disaster_event_id`| `uuid` | NO | FK &rarr; `disaster.disaster_events(id)` ON DELETE RESTRICT | B-Tree | Disaster operation |
| `administrative_unit_id`| `uuid`| NO | FK &rarr; `core.administrative_units(id)` ON DELETE RESTRICT | B-Tree | Deployment zone |
| `assignment_role`| `varchar(100)`| NO | e.g. `SHELTER_MANAGER`, `SEARCH_AND_RESCUE` | | Assigned role |
| `start_date` | `date` | NO | | B-Tree | Mobilization start date |
| `end_date` | `date` | YES | | | Demobilization date |

---

## 9. Schema: `workflow` (State Machines & Approvals)

### 9.1 `workflow.workflow_instances`
State machine instances governing relief, damage, and project approvals.

| Column Name | Data Type | Null | Default / Constraints | Indexes | Description |
| :--- | :--- | :---: | :--- | :--- | :--- |
| `id` | `uuid` | NO | PK | B-Tree (PK) | Instance ID |
| `entity_type` | `varchar(100)`| NO | | B-Tree | Eloquent model class |
| `entity_id` | `uuid` | NO | | B-Tree | Target entity UUID |
| `current_state` | `varchar(50)` | NO | | B-Tree | Active workflow state |
| `created_at` | `timestamptz` | NO | CURRENT_TIMESTAMP | | Instance creation timestamp |
| `updated_at` | `timestamptz` | NO | CURRENT_TIMESTAMP | | Latest transition timestamp |

---

### 9.2 `workflow.workflow_transitions` (Permanent Audit)
Sequential log of state changes.

| Column Name | Data Type | Null | Default / Constraints | Indexes | Description |
| :--- | :--- | :---: | :--- | :--- | :--- |
| `id` | `uuid` | NO | PK | B-Tree (PK) | Transition ID |
| `workflow_instance_id`| `uuid`| NO | FK &rarr; `workflow.workflow_instances(id)` ON DELETE RESTRICT | B-Tree | Target instance |
| `from_state` | `varchar(50)` | NO | | | Previous state |
| `to_state` | `varchar(50)` | NO | | B-Tree | New approved state |
| `action_by_user_id`| `uuid` | NO | FK &rarr; `core.users(id)` | B-Tree | Officer signing off |
| `remarks` | `text` | YES | | | Official comments / reasons |
| `transitioned_at`| `timestamptz` | NO | CURRENT_TIMESTAMP | B-Tree | Timestamp of approval |

---

## 10. Schema: `notification` & `document`

### 10.1 `notification.notification_dispatches`
Outbound alerting queue and logs (SMS, Web Push, Email).

| Column Name | Data Type | Null | Default / Constraints | Indexes | Description |
| :--- | :--- | :---: | :--- | :--- | :--- |
| `id` | `uuid` | NO | PK | B-Tree (PK) | Dispatch ID |
| `recipient_user_id`| `uuid` | YES | FK &rarr; `core.users(id)` | B-Tree | Recipient user |
| `channel` | `varchar(20)` | NO | CHECK in ('SMS', 'WEB_PUSH', 'EMAIL') | B-Tree | Dispatch channel |
| `destination_address`| `varchar(150)`| NO | | | Phone number, token, or email |
| `message_body` | `text` | NO | | | Notification content |
| `delivery_status`| `varchar(30)`| NO | CHECK in ('QUEUED', 'SENT', 'DELIVERED', 'FAILED') | B-Tree | Telecom delivery status |
| `dispatched_at` | `timestamptz` | NO | CURRENT_TIMESTAMP | B-Tree | Dispatch timestamp |

---

### 10.2 `document.documents`
Secure electronic document metadata (e-Docs).

| Column Name | Data Type | Null | Default / Constraints | Indexes | Description |
| :--- | :--- | :---: | :--- | :--- | :--- |
| `id` | `uuid` | NO | PK | B-Tree (PK) | Document ID |
| `entity_type` | `varchar(100)`| NO | | B-Tree | Related model (e.g. `DamageAssessment`) |
| `entity_id` | `uuid` | NO | | B-Tree | Related entity UUID |
| `original_filename`| `varchar(255)`| NO | | | Original uploaded filename |
| `file_hash_sha256`| `char(64)` | NO | | B-Tree | File integrity checksum |
| `mime_type` | `varchar(100)`| NO | | | Validated MIME type |
| `file_size_bytes` | `bigint` | NO | CHECK (file_size_bytes > 0) | | Size in bytes |
| `storage_path` | `varchar(500)`| NO | | | Obfuscated private storage key |
| `scan_status` | `varchar(30)` | NO | CHECK in ('PENDING', 'CLEAN', 'INFECTED') | B-Tree | Antivirus scan outcome |
| `uploaded_by` | `uuid` | NO | FK &rarr; `core.users(id)` | B-Tree | Uploader officer |
| `created_at` | `timestamptz` | NO | CURRENT_TIMESTAMP | | Upload timestamp |

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
