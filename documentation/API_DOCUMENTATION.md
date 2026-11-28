# Integrated Disaster Management System (IDMS)
## Enterprise API Architecture & Government Integration Specification

| Document Information | Details |
| :--- | :--- |
| **Document Version** | 1.0.0-PROPOSED |
| **Protocol** | RESTful JSON over HTTPS (TLS 1.3) |
| **API Version Base** | `/api/v1` |
| **Architectural Pattern**| Decoupled Adapter Pattern with Mock Fallback for External Government APIs |

---

## 1. RESTful API Architectural Standards & Envelope Contract

All endpoints follow strict RESTful naming conventions and return a deterministic, predictable response envelope.

### 1.1 Success Response Schema (HTTP 200 / 201)
```json
{
  "success": true,
  "message": "Relief requisition retrieved successfully.",
  "data": {
    "id": "018f3a5e-1a2b-7c3d-8e4f-5a6b7c8d9e0f",
    "requisition_no": "REQ-2024-DAC-001",
    "status": "APPROVED_BY_PIO",
    "disaster_name": "Cyclone Remal",
    "items": [
      {
        "commodity_name": "Rice (GR)",
        "quantity_requested": 50.0,
        "quantity_approved": 45.0,
        "unit": "METRIC_TON"
      }
    ]
  },
  "meta": {
    "timestamp": "2026-11-23T16:30:00Z",
    "correlation_id": "req-991283-abc"
  }
}
```

### 1.2 Paginated Response Schema (HTTP 200)
```json
{
  "success": true,
  "message": "Beneficiary households list.",
  "data": [
    {
      "id": "018f3a5e-...",
      "household_code": "HH-404712-0001",
      "hoh_name": "আব্দুল করিম",
      "vulnerability_score": 88.5
    }
  ],
  "meta": {
    "pagination": {
      "total": 1250,
      "count": 25,
      "per_page": 25,
      "current_page": 1,
      "total_pages": 50,
      "links": {
        "next": "/api/v1/beneficiaries?page=2",
        "prev": null
      }
    },
    "timestamp": "2026-11-23T16:30:00Z"
  }
}
```

### 1.3 Error Response Schema (HTTP 400 / 401 / 403 / 404 / 422 / 500)
```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": {
    "hoh_nid": [
      "The national ID number format is invalid."
    ],
    "family_member_count": [
      "The family member count must be at least 1."
    ]
  },
  "error_code": "VALIDATION_FAILED",
  "meta": {
    "timestamp": "2026-11-23T16:30:00Z",
    "correlation_id": "err-991283-xyz"
  }
}
```

---

## 2. API Versioning Strategy & Core Authentication Endpoints

- **URL Prefix:** All public and internal endpoints are prefixed with `/api/v1/`.
- **Breaking Changes:** Breaking modifications to payloads or response structures will introduce `/api/v2/` without deprecating `/api/v1/` until all clients (web and mobile) have migrated through scheduled deprecation windows.
- **Header Version Negotiation:** Minor non-breaking additions are delivered transparently via semantic versioning headers (`X-API-Version: 1.1`).

### 2.1 Centralized Authentication & Scoping Endpoints

All protected endpoints require an `Authorization: Bearer <Keycloak_RS256_JWT>` header.

#### 2.1.1 Get Authenticated User Profile (`GET /api/v1/auth/user`)
- **Headers:** `Authorization: Bearer <token>`
- **Response (200 OK):**
```json
{
  "success": true,
  "user": {
    "id": "4652e2e7-c611-46ff-ad0c-fa7a2302c6c9",
    "keycloak_sub": "92d6a561-bef1-46f5-9ca8-a80f54ca244b",
    "email": "uno.dacope@modmr.gov.bd",
    "name": "Mohammad Faruk (UNO)",
    "role": "ROLE_UNO",
    "designation": "Upazila Nirbahi Officer",
    "is_active": true,
    "administrative_scope": {
      "tier_level": "UPAZILA",
      "ancestry_path": "/40/47/12/",
      "scope_id": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d",
      "designation": "Upazila Nirbahi Officer"
    },
    "last_login_at": "2026-11-24T13:16:00+06:00"
  },
  "timestamp": "2026-11-24T13:16:00+06:00"
}
```

#### 2.1.2 Verify Administrative Scope Jurisdiction (`GET /api/v1/auth/scope-check`)
Evaluates the 7-tier hierarchical data access rules between the authenticated officer's jurisdiction and a target node.
- **Query Parameter:** `target_path` (e.g. `/40/47/12/35/`)
- **Response (200 OK - Access Allowed):**
```json
{
  "success": true,
  "target_path": "/40/47/12/35/",
  "officer_path": "/40/47/12/",
  "has_access": true,
  "reason": "Target within officer subtree or national jurisdiction.",
  "timestamp": "2026-11-24T13:16:00+06:00"
}
```
- **Response (403 Forbidden - Lateral or Upward Boundary Enforced):**
```json
{
  "success": false,
  "target_path": "/40/47/13/",
  "officer_path": "/40/47/12/",
  "has_access": false,
  "reason": "Target is outside officer jurisdiction (Lateral or Upward Boundary Enforced).",
  "timestamp": "2026-11-24T13:16:00+06:00"
}
```

---

## 3. NID Verification Integration Contract & Mock Adapter

In accordance with Section 6 of `AGENTS.md`, developers must **never** invent undocumented government endpoints or assume active credentials to the Election Commission / Porichoy API. Instead, an adapter pattern is enforced.

### 3.1 Service Interface Definition
```php
namespace App\Modules\NidVerification\Contracts;

use App\Modules\NidVerification\DTOs\NidVerificationRequestDto;
use App\Modules\NidVerification\DTOs\NidVerificationResultDto;
use App\Modules\NidVerification\DTOs\ProviderHealthDto;

interface NidVerificationServiceInterface
{
    public function verifyNid(NidVerificationRequestDto $requestDto): NidVerificationResultDto;
    public function getHealthStatus(): ProviderHealthDto;
    public function isProviderAvailable(): bool;
    public function getProviderCode(): string;
    public function isMockProvider(): bool;
}
```

### 3.2 Implemented Adapters & Separation of Mock vs. Production
- **`MockNidAdapter` (`MOCK_ADAPTER`):** Active only in non-production environments (`local`, `testing`) when `NID_VERIFICATION_DRIVER=mock`. Always marks records `is_mock = true` and simulates 10/13/17-digit formats, `NID_NOT_FOUND` (`0000000000`), `CITIZEN_DECEASED` (`9999999999`), `MANUAL_REVIEW_REQUIRED` (`8888888888`), and retryable `PROVIDER_TIMEOUT` (`7777777777`).
- **`AuthorizedGovernmentNidAdapter` (`GOV_NID_API`):** Enforced automatically in `production` or when `NID_VERIFICATION_DRIVER=government`. Reads endpoint/API key/mTLS certs from `config('services.nid')` and **fails closed** (`PROVIDER_NOT_CONFIGURED`) until official MoDMR / Election Commission API documentation and credentials are supplied.

### 3.3 Module 02 Implemented Endpoints (`/api/v1/nid/*`)
- `GET /api/v1/nid/dashboard` — Scoped verification KPI summary (`total_requests`, `verified_count`, `failed_count`, `pending_count`, `manual_review_count`, `manual_override_count`), provider health, and attempt latency/error telemetry (`nid.verification.view_status`).
- `GET /api/v1/nid/provider-health` — Live adapter availability and mock vs. official mode indicator.
- `GET /api/v1/nid/verifications` — Scoped, paginated, searchable, filterable verification history (`search`, `verification_status`, `provider_code`, `is_mock`, `manual_override`, `administrative_unit_id`, `date_from`, `date_to`).
- `POST /api/v1/nid/verifications` — Validate format (10/13/17 digits), enforce Redis-backed rate limit, and process verification (`PENDING` &rarr; `PROCESSING` &rarr; `VERIFIED` | `FAILED` | `MANUAL_REVIEW_REQUIRED`) synchronously with bounded retries or asynchronously (`async: true`) via `ProcessNidVerificationJob` (`nid.verification.initiate`).
- `GET /api/v1/nid/verifications/{verification}` — Detailed verification record with minimized matched flags, manual review audit fields, and provider attempt timeline.
- `POST /api/v1/nid/verifications/{verification}/retry` — Re-submit a `FAILED` or `MANUAL_REVIEW_REQUIRED` verification (requires matching NID re-entry).
- `POST /api/v1/nid/verifications/{verification}/manual-review` — Audited officer manual override (`VERIFIED` | `FAILED`) with mandatory reason (min 10 chars) and 4-eye Separation of Duties (`nid.verification.manual_review`; initiator cannot override own request).
- `POST /api/v1/beneficiary/verify-nid` — Backward-compatible Beneficiary facade delegating to Module 02.

---

## 4. External Government Systems Integration Roadmap

| External Authority | Target Protocol | Integration Purpose | Fallback / Adapter Strategy |
| :--- | :--- | :--- | :--- |
| **BTRC / Teletalk SMS Gateway** | REST / SOAP | Early warning and distribution OTP SMS | `MockSmsAdapter` logs SMS to database & Redis queue in development |
| **Bangladesh Met Dept (BMD)** | REST JSON / GeoJSON | Real-time cyclone track & depression feeds | Simulated storm track GeoJSON fixtures |
| **Flood Forecasting (FFWC)** | REST / XML | River level gauge readings & 5-day flood forecasts | Mock water level gauge simulation |
| **Election Commission / Porichoy**| REST mTLS | Official citizen verification | `MockNidAdapter` until formal API keys/mTLS certs are provisioned |
| **Bangladesh Bank / MFS (bKash/Nagad)**| REST API | Direct Cash Relief transfers (GR Cash) | Mock disbursement transaction generator |

---

## 5. Module 01: Beneficiary Management Implemented Endpoints (`/api/v1/beneficiary/*`)

### 5.1 Dashboard & Analytics
- `GET /api/v1/beneficiary/dashboard/metrics` — Aggregate counts: total households, total individuals, verified/approved, high vulnerability, female-headed, disabled, displaced, and poverty categories.
- `GET /api/v1/beneficiary/dashboard/geo-breakdown` — Geographic distribution breakdown by Union, Upazila, District.
- `GET /api/v1/beneficiary/dashboard/disaster-breakdown` — Household enrollments and aid disbursements per declared disaster event.

### 5.2 Household Master Registry
- `GET /api/v1/beneficiary/households` — Scoped, paginated master registry. Supports search by code/name/NID/mobile, filtering by `verification_status`, `administrative_unit_id`, `min_vulnerability`, `hoh_gender`, `female_headed_only`, `has_disabled_only`, `is_displaced_only`.
- `POST /api/v1/beneficiary/households` — Register household. Form request validation, AES-256-GCM encryption of PII, HMAC-SHA256 deduplication hashing, automated vulnerability calculation (0-100), duplicate suspect scanning, and audit logging.
- `GET /api/v1/beneficiary/households/{id}` — Full household detail profile including family members roster, verification timeline, disaster eligibilities, and duplicate suspect records.
- `PUT /api/v1/beneficiary/households/{id}` — Update household demographic and vulnerability attributes. Automatically recalculates vulnerability score and duplicate flags.
- `DELETE /api/v1/beneficiary/households/{id}` — Soft delete household master record (restricted to PIO, DRRO, SuperAdmin).
- `POST /api/v1/beneficiary/households/{id}/reveal-pii` — Decrypt and reveal unmasked NID & mobile for authorized officers (`beneficiary.pii.decrypt`). Every event is recorded in `audit.audit_logs`.

### 5.3 Dependent Family Members
- `POST /api/v1/beneficiary/households/{id}/members` — Add dependent family member (relationship, gender, age, disability, pregnancy). Automatically updates `family_member_count` and recalculates household vulnerability score.
- `DELETE /api/v1/beneficiary/households/{id}/members/{memberId}` — Remove dependent family member and recalculates vulnerability score.

### 5.4 Verification & Statutory Approval
- `POST /api/v1/beneficiary/verify-nid` — Verify National ID format and authenticity via Mock Adapter / Porichoy gateway contract. Generates cryptographic verification token.
- `POST /api/v1/beneficiary/households/{id}/verify-step` — Record intermediate stage verification (`FIELD_SURVEY`, `ADDRESS_CHECK`, `NID_CHECK`).
- `POST /api/v1/beneficiary/households/{id}/approval` — Statutory final approval (`APPROVE` / `REJECT`). Enforces four-eye invariant: Field Enumerators cannot approve; PIO/DRRO/SuperAdmin required.

### 5.5 Duplicate Detection & Override
- `GET /api/v1/beneficiary/duplicates` — List suspected duplicate pairs filtered by status (`PENDING_REVIEW`, `CONFIRMED_DUPLICATE`, `FALSE_POSITIVE_RESOLVED`).
- `POST /api/v1/beneficiary/duplicates/{id}/resolve` — Human review resolution of duplicate flags with mandatory reason notes and audit logging.

### 5.6 Disaster Linking & Assistance Tracking
- `POST /api/v1/beneficiary/households/{id}/disaster-eligibility` — Link household to declared crisis (e.g. Cyclone Remal 2024).
- `POST /api/v1/beneficiary/households/{id}/disaster-eligibility/{eligibilityId}/disburse` — Record physical aid handover and increment assistance count.

### 5.7 Batch Import & Export
- `POST /api/v1/beneficiary/import` — Batch CSV import. Validates row-by-row, computes vulnerability scores, detects duplicates, and returns line-by-line validation error report.
- `GET /api/v1/beneficiary/export` — Download filtered beneficiary muster roll as CSV.
- `GET /api/v1/beneficiary/template` — Download standard government CSV import template.

---

## 6. Module 03: Disaster Information Management Implemented Endpoints (`/api/v1/disaster/*`)

### 6.1 Dashboard, Categories & GIS
- `GET /api/v1/disaster/dashboard` — Scoped analytics: active disasters, total events, category/severity/lifecycle breakdowns, humanitarian & economic impact totals, geographic distribution, and monthly trends.
- `GET /api/v1/disaster/gis/geojson` — RFC 7946 `FeatureCollection` of scoped disaster epicenters and affected polygon boundaries; supports `disaster_type`, `severity_level`, `lifecycle_phase`, `bbox=minLng,minLat,maxLng,maxLat`, and `lat,lng,radius_km` spatial filters (PostGIS `ST_Intersects`/`ST_DWithin`).
- `GET /api/v1/disaster/categories` — Cached configurable disaster categories and subtypes (`FLOOD`, `CYCLONE`, `DROUGHT`, `EARTHQUAKE`, `LANDSLIDE`, `FIRE`, `RIVER_EROSION`, `LIGHTNING`, `FLASH_FLOOD`, `COLD_WAVE`).
- `POST /api/v1/disaster/categories` — Create new disaster category/subtypes (`disaster.category.manage`).

### 6.2 Disaster Lifecycle, Impact, Warnings & Timeline
- `GET /api/v1/disaster/events` — Scoped, paginated, searchable, filterable, sortable disaster registry.
- `POST /api/v1/disaster/events` — Register disaster with Bangla/English titles, auto-generated or custom unique code, multi-location administrative unit mappings, GPS coordinates, GeoJSON boundary polygon, and initial impact estimate.
- `GET /api/v1/disaster/events/{event}` — Full disaster detail with locations, impact, warnings, and chronological timeline.
- `PUT /api/v1/disaster/events/{event}` — Update event metadata, locations, GIS geometries, and impact.
- `DELETE /api/v1/disaster/events/{event}` — Soft-delete/archive disaster event (`disaster.event.verify` / National/District roles).
- `PUT /api/v1/disaster/events/{event}/impact` — Update humanitarian, housing, infrastructure, agriculture, livestock, and economic impact estimates.
- `POST /api/v1/disaster/events/{event}/boundary` — Upload and normalize Shapefile (`.zip`) / GeoJSON Polygon/MultiPolygon boundaries onto the disaster event and PostGIS `affected_zone_geom`.
- `POST /api/v1/disaster/events/{event}/lifecycle` — Transition across 7 lifecycle phases (`REPORTED` &rarr; `UNDER_ASSESSMENT` &rarr; `VERIFIED` &rarr; `ACTIVE` &rarr; `RESPONSE_ONGOING` &rarr; `RECOVERY` &rarr; `CLOSED`); `VERIFIED`, `ACTIVE`, and `CLOSED` enforce higher-tier verification authorization.
- `GET /api/v1/disaster/warnings` — Scoped early warning bulletins across accessible events.
- `POST /api/v1/disaster/events/{event}/warnings` — Issue early warning bulletin and dispatch via queued `DisasterNotificationInterface` (`MockDisasterNotificationAdapter` until Module 13).
- `POST /api/v1/disaster/events/{event}/timelines` — Record chronological update with optional private file attachment (MIME-validated, SHA-256 hashed, UUID-obfuscated path).
- `GET /api/v1/disaster/events/{event}/timelines/{timeline}/attachment` — Authenticated, scope-enforced private attachment download.

---

## 7. Module 04: Relief Distribution Management Implemented Endpoints (`/api/v1/relief/*`)

### 7.1 Dashboard, Reference Lookups & Relief Categories
- `GET /api/v1/relief/dashboard` — Scoped analytics: total requests, pending approvals, approved allocations, allocated vs distributed totals & progress percentage, beneficiaries served, vouchers issued, requisition status breakdown, area-wise statistics, and warehouse stock summary.
- `GET /api/v1/relief/lookups` — Scoped active commodities, supplying warehouses (with physical, reserved, and unreserved stock balances), active distribution centers, and disaster events.
- `GET /api/v1/relief/eligible-households` — Scoped lookup of verified/approved beneficiary households filtered by target jurisdiction and search query (`household_code`, `hoh_name_en`, `hoh_name_bn`).
- `GET /api/v1/relief/categories` — List configured relief categories (`FOOD`, `DRINKING_WATER`, `CASH_ASSISTANCE`, `CLOTHING`, `SHELTER_MATERIALS`, `MEDICINE_SUPPLIES`, plus custom categories).
- `POST /api/v1/relief/categories` & `PUT /api/v1/relief/categories/{category}` — Create/update relief categories (`relief.category.manage`).

### 7.2 Relief Requisitions (7-State Approval Workflow & 4-Eye SoD)
- `GET /api/v1/relief/requisitions` — Scoped, paginated, searchable, filterable list (`status`, `priority`, `disaster_event_id`).
- `POST /api/v1/relief/requisitions` — Create requisition (`DRAFT` or immediate `SUBMITTED`) with multi-line commodity items and optional private supporting document (`pdf`, `jpg`, `png`). Blocked for `ROLE_SUPER_ADMIN` under operational blackout.
- `GET /api/v1/relief/requisitions/{requisition}` — Full requisition detail with line items and audit metadata.
- `PUT /api/v1/relief/requisitions/{requisition}` & `DELETE /api/v1/relief/requisitions/{requisition}` — Modify `DRAFT`/`SUBMITTED` or soft-delete `DRAFT` requisitions.
- `POST /api/v1/relief/requisitions/{requisition}/submit` — Transition `DRAFT` &rarr; `SUBMITTED`.
- `POST /api/v1/relief/requisitions/{requisition}/review` — Transition `SUBMITTED` &rarr; `UNDER_REVIEW` (enforces 4-eye SoD).
- `POST /api/v1/relief/requisitions/{requisition}/approve` — Transition to `APPROVED` or `PARTIALLY_APPROVED` with per-item `quantity_approved` (enforces 4-eye SoD & Super Admin blackout).
- `POST /api/v1/relief/requisitions/{requisition}/reject` — Transition to `REJECTED` with mandatory `review_notes` (enforces 4-eye SoD).
- `GET /api/v1/relief/requisitions/{requisition}/document` — Authenticated private supporting document download.

### 7.3 Relief Allocations & Warehouse Stock Reservation
- `GET /api/v1/relief/allocations` — Scoped, paginated allocation orders (`status`, `disaster_event_id`, search).
- `POST /api/v1/relief/allocations` — Create draft allocation order linked to warehouse, distribution center, disaster, schedules, duplicate cooldown window (`duplicate_window_days`), line items, and optional eligible household roster.
- `GET /api/v1/relief/allocations/{allocation}` — Full allocation order detail with distributed/remaining item balances and beneficiary roster.
- `POST /api/v1/relief/allocations/{allocation}/beneficiaries` — Assign/update verified eligible beneficiary households on the allocation roster.
- `POST /api/v1/relief/allocations/{allocation}/approve` — Sanction allocation (`DRAFT` &rarr; `SANCTIONED`), reserve unreserved warehouse stock atomically, and enforce 4-eye SoD (`created_by !== actor`).
- `POST /api/v1/relief/allocations/{allocation}/dispatch` — Mark allocation `DISPATCHED` to its distribution center.
- `POST /api/v1/relief/allocations/{allocation}/cancel` — Cancel remaining undelivered quantities and release reserved warehouse stock.

### 7.4 Physical Distribution, Atomic Inventory Deduction & Muster Roll
- `GET /api/v1/relief/distributions` — Scoped distribution voucher ledger (`relief_allocation_id`, `disaster_event_id`, `is_voided`, `duplicate_override_only`, search).
- `GET /api/v1/relief/distributions/check-duplicate` — Pre-flight check for existing distribution under the same allocation item or disaster + category cooldown window.
- `POST /api/v1/relief/distributions` — Record physical beneficiary handover with unique `idempotency_key` replay guard, FEFO atomic stock deduction (`InventoryLedgerService::deductStock()`), optional duplicate-assistance exception override (`relief.distribution.override_duplicate` + mandatory `exception_reason`), acknowledgment signature/proof, GPS coordinates, private handoff photo upload, and `disaster_eligibilities` synchronization.
- `POST /api/v1/relief/distributions/{voucher}/verify` — Tag Officer witness sign-off enforcing 4-eye SoD (`verified_by_tag_officer !== distributed_by`).
- `POST /api/v1/relief/distributions/{voucher}/void` — Void erroneous voucher and atomically restore physical + reserved stock via `ALLOCATION_RETURN` ledger entries.
- `GET /api/v1/relief/distributions/{voucher}/photo` — Authenticated private handoff photo download.
- `GET /api/v1/relief/distributions/export-muster` — Download UTF-8 CSV official relief muster roll with formula-injection sanitization.

---

## 8. Module 05: Disaster Response Management Implemented Endpoints (`/api/v1/response/*`)

### 8.1 Dashboard, Configurable Activities, Lookups & GIS Map
- `GET /api/v1/response/dashboard` — Scoped command analytics: active operations, total operations, pending/completed tasks, response teams (active/total), rescued/evacuated/casualty totals, geographical coverage, and emergency requirements.
- `GET /api/v1/response/lookups` — Active response activity types, scoped EOC/response centers, scoped deployable assets (`resource.resource_assets`), and disaster events.
- `GET /api/v1/response/gis/geojson` — Scoped RFC 7946 `FeatureCollection` containing affected areas, response centers, response teams, and situation/incident reports with **location privacy coordinate masking** (~2 decimal precision) for external partners and non-responder viewers or `RESTRICTED_EXACT` reports.
- `POST /api/v1/response/activities` — Add custom response activity type (`response.activity.manage`).

### 8.2 Response Operations & 6-State Lifecycle
- `GET /api/v1/response/operations` — Scoped, paginated, searchable, filterable (`status`, `priority`, `disaster_event_id`) response operations.
- `POST /api/v1/response/operations` — Create planned operation linked to disaster, jurisdiction, and optional response center (`ROLE_SUPER_ADMIN` blocked by operational blackout).
- `GET /api/v1/response/operations/{operation}` — Full operation command detail including agencies, teams, tasks with attachments, resource allocations/assets, and situation reports.
- `PUT /api/v1/response/operations/{operation}` & `DELETE /api/v1/response/operations/{operation}` — Update active/planned operation metadata or soft-delete a `PLANNED` operation.
- `POST /api/v1/response/operations/{operation}/lifecycle` — Transition across `PLANNED`, `ACTIVATED`, `ONGOING`, `SUSPENDED`, `COMPLETED`, `CLOSED` (District/UNO/NEOC+ authority required for `ACTIVATED`, `SUSPENDED`, `COMPLETED`, `CLOSED`; closing automatically returns linked `ResourceAsset` units).

### 8.3 Coordination, Tasks & Situation Reporting
- `POST /api/v1/response/operations/{operation}/agencies` — Register coordinating government/NGO agencies.
- `POST /api/v1/response/operations/{operation}/teams` & `PUT /api/v1/response/teams/{team}` — Deploy response teams with member count and GPS coordinates.
- `POST /api/v1/response/operations/{operation}/resources` & `PUT /api/v1/response/resources/{allocation}` — Record resource requirements or link/deploy/return available `resource_assets` atomically.
- `POST /api/v1/response/operations/{operation}/tasks`, `PUT /api/v1/response/tasks/{task}`, `POST /api/v1/response/tasks/{task}/attachments`, & `GET /api/v1/response/tasks/{task}/attachments/{attachment}` — Task assignment, progress updates, and private evidence upload/download.
- `GET /api/v1/response/reports` & `POST /api/v1/response/operations/{operation}/reports` — Incident reports, field updates, casualty/rescue figures, emergency requirements, and privacy-scoped GPS.

---

## 9. Module 06: Project Management Implemented Endpoints (`/api/v1/project/*`)

### 9.1 Dashboard, Lookups, Categories, Reports & GIS Map
- `GET /api/v1/project/dashboard` — Scoped portfolio analytics (Redis cached with version invalidation): total projects, active projects, completed projects, delayed projects, total approved/effective budget, released funds, expenditure, remaining balance, financial & physical progress percentages, category breakdown, and progress by location.
- `GET /api/v1/project/lookups` — Active project categories, disaster events, scoped administrative units, and lifecycle status constants.
- `GET /api/v1/project/gis/geojson` — Scoped RFC 7946 `FeatureCollection` of project sites, multi-location points, and boundary polygons; supports `status`, `category_id`, and `bbox=minLng,minLat,maxLng,maxLat` spatial filters.
- `GET /api/v1/project/reports?type={summary|financial|physical|milestones|monitoring}` — Scoped analytical report views across project summary, financial progress, physical progress & delays, milestone certifications, and monitoring visit reports.
- `GET /api/v1/project/export` — Download UTF-8 BOM CSV project portfolio export (`project.scheme.export`).
- `POST /api/v1/project/categories` — Create configurable project category (`DEVELOPMENT`, `DRR`, `REHABILITATION`, `RECOVERY`, etc.).

### 9.2 Project Registration, Multi-Location Coverage & 7-Stage Lifecycle
- `GET /api/v1/project/schemes` — Scoped, paginated, searchable, sortable, filterable (`status`, `category_id`, `disaster_event_id`, `is_delayed`, `search`) project registry.
- `POST /api/v1/project/schemes` — Register a disaster management / DRR / rehabilitation / recovery project with Bangla & English titles, code, reference number, optional linked disaster event, primary jurisdiction, multi-location coordinates/wards, funding source, approved/revised budget, timeline, and GIS boundary (`ROLE_SUPER_ADMIN` blocked by operational blackout).
- `GET /api/v1/project/schemes/{project}` — Full project detail including locations, WBS milestones, financial disbursements, monitoring reports, and risks (`ROLE_EXTERNAL_PARTNER` receives anonymized manager contact and cannot access private internal attachments).
- `PUT /api/v1/project/schemes/{project}` & `DELETE /api/v1/project/schemes/{project}` — Update non-closed project metadata/locations/GIS or soft-delete `PROPOSED`/`UNDER_REVIEW` drafts.
- `POST /api/v1/project/schemes/{project}/lifecycle` — Transition across 7 lifecycle states (`PROPOSED` &rarr; `UNDER_REVIEW` &rarr; `APPROVED` &rarr; `ONGOING` &rarr; `SUSPENDED` &rarr; `COMPLETED` &rarr; `CLOSED`); enforces 4-eye Separation of Duties on `APPROVED` (`approved_by !== created_by`) and terminal immutability on `CLOSED`.
- `POST /api/v1/project/schemes/{project}/boundary` — Upload/update GeoJSON Polygon/MultiPolygon boundary and PostGIS `boundary_geom`.

### 9.3 WBS Activities, Financial Tracking, Monitoring Visits & Risks
- `POST /api/v1/project/schemes/{project}/milestones` — Create WBS phase/activity/milestone with deliverables, timeline, weight, and assigned officer.
- `PUT /api/v1/project/milestones/{milestone}/progress` — Update physical completion percentage (0–100) and automatically recalculate weighted project `physical_progress_pct`.
- `POST /api/v1/project/milestones/{milestone}/certify` — Engineer/PIO physical inspection certification (`VERIFIED_ENGINEER`, `APPROVED_PIO`, `REJECTED`) enforcing 4-eye SoD (`certified_by !== updated_by`).
- `POST /api/v1/project/schemes/{project}/financials` — Record `FUND_RELEASE`, `EXPENDITURE`, or `BUDGET_REVISION` voucher with optional SHA-256 hashed private supporting document; enforces budget ceiling guards (no unrestricted payment execution).
- `POST /api/v1/project/financials/{disbursement}/approve` — Authorize/reconcile/reject financial voucher enforcing 4-eye SoD (`approved_by !== created_by`) and recalculating project `released_funds_bdt`, `expenditure_bdt`, `remaining_balance_bdt`, and `financial_progress_pct`.
- `GET /api/v1/project/financials/{disbursement}/document` — Authenticated private supporting financial document download.
- `POST /api/v1/project/schemes/{project}/monitoring-reports` & `GET /api/v1/project/monitoring-reports/{report}/attachment` — Submit site monitoring visit reports (with observed physical/financial %, delay flags/reasons, geo-tagged coordinates, and private attachment) and download evidence.
- `POST /api/v1/project/schemes/{project}/risks` & `PUT /api/v1/project/risks/{risk}` — Log and update project risks and issues (`OPEN`, `MITIGATING`, `RESOLVED`, `CLOSED`).

---

## 10. Module 07: Resource Management Implemented Endpoints (`/api/v1/resource/*`)

### 10.1 Dashboard, Categories, Reminders, GIS & CSV Export
- `GET /api/v1/resource/dashboard` — Scoped resource analytics (Redis cached with version invalidation): total resources, available, assigned/in-use, under maintenance, damaged, retired, active human resource deployments, asset valuation & repair costs, category breakdown, utilization by disaster, geographical distribution, and upcoming/overdue maintenance reminders.
- `GET /api/v1/resource/lookups` — Active configurable resource categories, disaster events, active response operations, response teams, scoped administrative units, and personnel list (`ROLE_EXTERNAL_PARTNER` receives redacted personnel).
- `GET /api/v1/resource/gis/geojson` — Scoped RFC 7946 `FeatureCollection` of registered resource points and active allocations (supports `category_id`, `status`, `disaster_event_id`, `bbox`); coordinates are privacy-masked for external partners.
- `GET /api/v1/resource/reminders?days=30` — Overdue and upcoming maintenance due assets and scheduled maintenance tasks within the horizon window.
- `GET /api/v1/resource/export` — Download UTF-8 BOM CSV resource register (`resource.asset.export`) with formula-injection sanitization and external partner officer redaction.
- `GET /api/v1/resource/categories` & `POST /api/v1/resource/categories` — List and add configurable resource categories (`VEHICLES`, `BOATS`, `RESCUE_EQUIPMENT`, `MEDICAL_EQUIPMENT`, `COMMUNICATION_EQUIPMENT`, `HEAVY_MACHINERY`, `EMERGENCY_RESPONSE_EQUIPMENT`, `HUMAN_RESOURCES`, and custom categories).

### 10.2 Resource Registration, Specifications & Supporting Attachments
- `GET /api/v1/resource/assets` — Scoped, paginated, searchable, filterable (`category_id`, `status`, `administrative_unit_id`, `search`), and sortable resource register.
- `POST /api/v1/resource/assets` — Register resource with unique `asset_code`, category, `resource_type`, specifications JSON, owning organization, administrative unit, current location, GPS coordinates + PostGIS `location_geom`, responsible officer, acquisition date/value, and next maintenance due date.
- `GET /api/v1/resource/assets/{asset}` — Full resource profile with attachments, allocation utilization history, maintenance/inspection logs, and human resource deployments.
- `PUT /api/v1/resource/assets/{asset}` & `DELETE /api/v1/resource/assets/{asset}` — Update resource metadata (prevents manual status override while active allocations exist) or retire/soft-delete non-deployed assets.
- `POST /api/v1/resource/assets/{asset}/attachments` & `GET /api/v1/resource/assets/{asset}/attachments/{attachment}` — Upload private supporting documents/photographs (`DOCUMENT`, `PHOTOGRAPH`) with SHA-256 checksums and authenticated download (blocked for `ROLE_EXTERNAL_PARTNER`).

### 10.3 Transaction-Safe Resource Allocation & Utilization History
- `GET /api/v1/resource/allocations` — Scoped, paginated allocation ledger (`status`, `disaster_event_id`, `response_operation_id`, `resource_asset_id`, `search`).
- `POST /api/v1/resource/allocations` — Transaction-safe allocation (`lockForUpdate()`) linking an `AVAILABLE` resource to a disaster event and/or response operation with location, assignment date, expected return date, and responsible officer; rejects unavailable (`ASSIGNED`, `IN_USE`, `DEPLOYED`, `UNDER_MAINTENANCE`, `DAMAGED`, `RETIRED`) resources with HTTP 422 and enforces `ROLE_SUPER_ADMIN` operational blackout (403).
- `POST /api/v1/resource/allocations/{allocation}/in-use` — Transition `ASSIGNED` allocation and resource asset to `IN_USE`.
- `POST /api/v1/resource/allocations/{allocation}/release` — Transaction-safe release (`RETURNED`/`CANCELLED`), recording `actual_return_date` and return condition (`GOOD` &rarr; `AVAILABLE`, `MAINTENANCE_REQUIRED` &rarr; `UNDER_MAINTENANCE`, `DAMAGED` &rarr; `DAMAGED`).

### 10.4 Maintenance Schedules, Inspection Reports & Human Resource Deployments
- `GET /api/v1/resource/maintenances`, `POST /api/v1/resource/assets/{asset}/maintenances`, `PUT /api/v1/resource/maintenances/{maintenance}`, & `GET /api/v1/resource/maintenances/{maintenance}/attachment` — Manage maintenance schedules, preventive/corrective logs, inspection findings/results (`PASS`, `CONDITIONAL`, `FAIL`), repair costs (`repair_cost_bdt`), maintenance reminders, and SHA-256 private inspection report uploads/downloads.
- `GET /api/v1/resource/human-deployments`, `POST /api/v1/resource/human-deployments`, & `POST /api/v1/resource/human-deployments/{deployment}/release` — Deploy registered HR specialists, system officers, or response teams to response operations/disasters with duty descriptions and deployment periods, preventing double-deployment conflicts and tracking full assignment history.

---

## 11. Module 08: Warehouse & Inventory Management Implemented Endpoints (`/api/v1/warehouse/*`)

### 11.1 Dashboard, Lookups, Alerts, GIS & Reports
- `GET /api/v1/warehouse/dashboard` — Scoped warehouse portfolio analytics (Redis cached with version invalidation): active/total warehouses, total capacity (MT), physical/reserved/available/damaged/expired stock totals, active batch valuation (BDT), pending GRN verifications, active inter-warehouse transfers, stock alerts summary, and recent immutable transactions.
- `GET /api/v1/warehouse/lookups` — Tier/type/status/transaction constants, scoped warehouses (including supplying upstream depots), active commodities, relief categories, scoped administrative units, and eligible 4-eye co-authorizers (`ROLE_DDM_DIRECTOR_GENERAL`, `ROLE_DDM_RELIEF_DIRECTOR`, `ROLE_DEPUTY_COMMISSIONER`, `ROLE_DRRO`).
- `GET /api/v1/warehouse/gis/geojson` — RFC 7946 `FeatureCollection` of warehouse coordinates (`PostGIS` `location_geom`) with live physical/reserved/available inventory totals.
- `GET /api/v1/warehouse/alerts?days=30` — Real-time stock alerts across low stock (`available < minimum_stock_level`), out of stock, expiring soon, expired batches, and unusual adjustments/write-offs.
- `GET /api/v1/warehouse/reports?type={STOCK_BALANCE|STOCK_MOVEMENT|WAREHOUSE_INVENTORY|ITEM_INVENTORY|EXPIRY|TRANSACTION_HISTORY}` — Scoped operational reporting datasets.
- `GET /api/v1/warehouse/export-ledger` — Download UTF-8 BOM CSV of immutable stock transactions (`warehouse.stock.export_ledger`; blocked for `ROLE_EXTERNAL_PARTNER`).

### 11.2 Warehouses, Items & Immutable Stock Ledger
- `GET /api/v1/warehouse/facilities`, `POST /api/v1/warehouse/facilities`, `GET /api/v1/warehouse/facilities/{id}`, `PUT /api/v1/warehouse/facilities/{id}` — Register and update multi-echelon depots (`CENTRAL_CSD`, `REGIONAL_LSD`, `DISTRICT_GODOWN`, `UPAZILA_STORE`) with responsible officer, owning organization, address, contact details, capacity, and PostGIS GPS coordinates (`ROLE_EXTERNAL_PARTNER` receives redacted officer/facility contact info).
- `GET /api/v1/warehouse/items`, `POST /api/v1/warehouse/items`, `GET /api/v1/warehouse/items/{id}`, `PUT /api/v1/warehouse/items/{id}` — Manage bilingual commodity definitions (`name_bn`, `name_en`), unique `commodity_code`, unit of measure, description, `minimum_stock_level`, and perishability flag.
- `GET /api/v1/warehouse/inventories` — Scoped, FEFO-ordered batch inventory register showing `opening_quantity`, `current_quantity`, `reserved_quantity`, `available_quantity`, `damaged_quantity`, `expired_quantity`, unit valuation, and days to expiry.
- `GET /api/v1/warehouse/transactions` & `POST /api/v1/warehouse/transactions/adjustment` — Inspect immutable `logistics.stock_transactions` ledger (`StockTransaction` blocks Eloquent updates/deletes via `LogicException`) and post authorized ledger entries (`OPENING_STOCK`, `RETURN_IN`, `STOCK_ADJUSTMENT`, `WRITE_OFF_DAMAGED`, `EXPIRED_STOCK`). Write-offs and adjustments require `warehouse.stock.writeoff`, valid `reason_code`, and 4-eye dual authorization (`approved_by !== authorized_by`); `ROLE_SUPER_ADMIN` is blocked by operational blackout.

### 11.3 Procurement (GRN), Inter-Warehouse Transfers & Relief Integration
- `GET /api/v1/warehouse/receipts`, `POST /api/v1/warehouse/receipts`, `GET /api/v1/warehouse/receipts/{id}`, `POST /api/v1/warehouse/receipts/{id}/verify`, `POST /api/v1/warehouse/receipts/{id}/reject`, `GET /api/v1/warehouse/receipts/{id}/attachment` — Two-step GRN receiving workflow with supplier metadata, batch/expiry tracking, idempotency guard, SHA-256 private document upload/download, and 4-eye verification (`verified_by !== received_by`) required before stock enters usable inventory.
- `GET /api/v1/warehouse/transfers`, `POST /api/v1/warehouse/transfers`, `GET /api/v1/warehouse/transfers/{id}`, `POST /api/v1/warehouse/transfers/{id}/approve`, `POST /api/v1/warehouse/transfers/{id}/dispatch`, `POST /api/v1/warehouse/transfers/{id}/receive`, `POST /api/v1/warehouse/transfers/{id}/cancel` — Controlled 4-stage transfer order workflow (`REQUESTED` &rarr; `APPROVED` [4-eye SoD] &rarr; `DISPATCHED` [deducts unreserved source stock] &rarr; `RECEIVED` [credits destination batch]).
- `GET /api/v1/warehouse/relief-allocations` & `POST /api/v1/warehouse/relief-allocations/{allocationId}/issue` — Issue warehouse stock directly against `SANCTIONED` or `DISPATCHED` Module 04 `ReliefAllocation` items, updating `quantity_distributed` and blocking excess issuance (`quantity_to_issue <= remaining_quantity`) to prevent double-deduction.

---

## 12. Module 09: Damage Assessment (D-Form) Implemented Endpoints (`/api/v1/damage/*`)

### 12.1 Dashboard, Configurable Categories, GIS & Reports
- `GET /api/v1/damage/dashboard` — Scoped damage analytics (Redis cached with key-list invalidation): total assessments, verified/approved counts, pending verification, affected households & population, damaged unit totals (fully/partially/minor), total estimated loss (BDT), breakdown by category, status, type, severity, and geographical area.
- `GET /api/v1/damage/lookups` — Active configurable damage categories, disaster events, scoped administrative units, and assessment/verification/severity constants.
- `GET /api/v1/damage/categories`, `POST /api/v1/damage/categories`, `PUT /api/v1/damage/categories/{category}` — List, create, and update configurable damage categories (9 built-in sectors + custom categories).
- `GET /api/v1/damage/gis/geojson` — Scoped RFC 7946 `FeatureCollection` of damage assessment points, polygons, coverage locations, and geo-tagged damaged infrastructure items (supports `disaster_event_id`, `verification_status`, `severity_level`, `category_id`, `bbox`).
- `GET /api/v1/damage/gis/heatmap` — Weighted damage heatmap intensity points (`0.15–1.0` normalized by severity, financial loss, and affected households) plus geographical area severity summary.
- `GET /api/v1/damage/gis/spatial-aggregation` — Administrative area-level spatial aggregation of assessments, verified counts, households, and estimated losses.
- `GET /api/v1/damage/reports?type={summary|disaster|location|category|financial}` — Disaster-wise, location-wise, category-wise, and financial loss reports (defaults to `VERIFIED` & `APPROVED` assessments).
- `GET /api/v1/damage/export` — Download UTF-8 BOM CSV export of scoped damage assessments (`damage.assessment.export`; assessor PII redacted for `ROLE_EXTERNAL_PARTNER`).

### 12.2 Assessment Registration, Field Collection, Attachments & Offline Sync
- `GET /api/v1/damage/assessments` — Scoped, paginated, searchable, filterable, and sortable list of D-Form assessments (`ROLE_EXTERNAL_PARTNER` sees `VERIFIED`/`APPROVED` only with assessor contact/name redacted).
- `POST /api/v1/damage/assessments` & `PUT /api/v1/damage/assessments/{assessment}` — Register or update a D-Form (editable only in `DRAFT` or `CORRECTION_REQUESTED` states) with multi-location geographical coverage, sectoral damage items, GPS/GeoJSON, and optimistic `sync_version` checking.
- `GET /api/v1/damage/assessments/{assessment}` & `DELETE /api/v1/damage/assessments/{assessment}` — Full D-Form detail (with locations, sector items, attachments, verification logs) or soft-delete `DRAFT` records.
- `POST /api/v1/damage/assessments/sync` — Batch offline mobile synchronization endpoint for Flutter app compatibility (`client_uuid`, `idempotency_key` deduplication, `device_id`, `offline_created_at`, `sync_version` conflict detection returning `CREATED`, `UPDATED`, `DUPLICATE_SKIPPED`, or `CONFLICT`).
- `POST /api/v1/damage/assessments/{assessment}/boundary` — Update GeoJSON Polygon/MultiPolygon footprint and PostGIS `damage_area_geom`.
- `POST /api/v1/damage/assessments/{assessment}/attachments`, `GET /api/v1/damage/assessments/{assessment}/attachments/{attachment}`, `DELETE /api/v1/damage/assessments/{assessment}/attachments/{attachment}` — Geo-tagged private photograph/document upload with SHA-256 checksums and authenticated streamed download.

### 12.3 Verification & Approval Workflow (4-Eye SoD + Audit Trail)
- `POST /api/v1/damage/assessments/{assessment}/submit` — Field officer submits `DRAFT` or `CORRECTION_REQUESTED` assessment (`SUBMITTED`).
- `POST /api/v1/damage/assessments/{assessment}/start-verification` — Supervisor begins review (`UNDER_VERIFICATION`); enforces 4-eye SoD (`actor !== created_by && actor !== submitted_by`).
- `POST /api/v1/damage/assessments/{assessment}/request-correction` — Supervisor returns assessment with mandatory `notes` (`CORRECTION_REQUESTED`) so the field officer can amend and resubmit.
- `POST /api/v1/damage/assessments/{assessment}/verify` — Supervisor verifies assessment (`VERIFIED`), making it available for official reporting.
- `POST /api/v1/damage/assessments/{assessment}/reject` — Supervisor rejects assessment with mandatory `reason` (`REJECTED`).
- `POST /api/v1/damage/assessments/{assessment}/approve` — District/Directorate/Ministry authority grants final approval (`APPROVED`) enforcing 4-eye SoD and `ROLE_SUPER_ADMIN` operational blackout.

---

## 13. Module 10: Volunteer Management Implemented Endpoints (`/api/v1/volunteer/*`)

### 13.1 Dashboard, Lookups, Categories, Skills, GIS & Reports
- `GET /api/v1/volunteer/dashboard` — Scoped volunteer KPIs (Redis cached with version invalidation): total registered, verified, pending verification, available, deployed, active/completed deployments, organizations, training sessions, certificates issued, geographical area breakdown, category distribution, and skills distribution.
- `GET /api/v1/volunteer/lookups` — Active categories, skills catalog, scoped organizations, disasters, response operations, scoped administrative units, and status constants.
- `GET/POST/PUT /api/v1/volunteer/categories` & `GET/POST/PUT /api/v1/volunteer/skills` — Manage configurable volunteer categories (`SEARCH_AND_RESCUE`, `FIRST_AID`, `MEDICAL_SUPPORT`, `EVACUATION_SUPPORT`, `RELIEF_DISTRIBUTION`, `COMMUNICATION`, `COMMUNITY_AWARENESS`, plus custom) and skills catalog.
- `GET /api/v1/volunteer/gis/geojson` — Scoped RFC 7946 `FeatureCollection` of consented volunteer base points and active deployments (anonymized and coarsened for `ROLE_EXTERNAL_PARTNER` / unauthorized viewers).
- `GET /api/v1/volunteer/reports` & `GET /api/v1/volunteer/export?report_type={register|deployments|attendance|training}` — Scoped reports and UTF-8 BOM CSV downloads (PII redacted for `ROLE_EXTERNAL_PARTNER`).

### 13.2 Volunteer Organizations, Registration, PII Protection & 3-Pillar Verification
- `GET/POST /api/v1/volunteer/organizations`, `GET/PUT/DELETE /api/v1/volunteer/organizations/{organization}`, `POST /api/v1/volunteer/organizations/{organization}/verify` — Manage volunteer organization profiles, contact persons, org types, multi-unit geographic coverages, and verification status.
- `GET/POST /api/v1/volunteer/volunteers`, `GET/PUT/DELETE /api/v1/volunteer/volunteers/{volunteer}` — Scoped, paginated, searchable, filterable volunteer roster (`nid_encrypted`, `mobile_encrypted`, `email_encrypted`, `emergency_contact_encrypted` encrypted at rest and masked by default; duplicate NID hash rejected).
- `POST/GET /api/v1/volunteer/volunteers/{volunteer}/photo` — Private SHA-256 photograph upload and authorized streamed download (blocked for `ROLE_EXTERNAL_PARTNER`).
- `POST /api/v1/volunteer/volunteers/{volunteer}/reveal-pii` — Audited cleartext PII unmasking gated by `VolunteerPolicy::decryptPii`.
- `POST /api/v1/volunteer/volunteers/{volunteer}/skills`, `POST /submit`, `POST /verify-pillar`, `POST /approve`, `POST /reject` — Skill synchronization and 3-pillar (`IDENTITY`, `ORGANIZATION`, `TRAINING`) + overall 4-eye SoD verification/approval workflow (with optional Module 02 `NidVerificationService` integration and `ROLE_SUPER_ADMIN` operational blackout).

### 13.3 Deployments, Check-In/Out Attendance & Training Management
- `GET/POST /api/v1/volunteer/deployments`, `GET /api/v1/volunteer/deployments/{deployment}`, `POST /{deployment}/activate`, `POST /{deployment}/complete`, `POST /{deployment}/cancel` — Transaction-safe deployment (`lockForUpdate()`) to disasters and response operations, enforcing verified + available status, overlap prevention, and automatic availability release.
- `GET /api/v1/volunteer/attendances`, `POST /api/v1/volunteer/deployments/{deployment}/check-in`, `POST /api/v1/volunteer/attendances/{attendance}/check-out`, `POST /api/v1/volunteer/attendances/{attendance}/confirm`, `POST/GET /api/v1/volunteer/attendances/{attendance}/evidence` — Explicit GPS-consent-gated check-in/out, activity reports, duration calculation, private evidence files, and 4-eye supervisor confirmation.
- `GET/POST/PUT /api/v1/volunteer/training-programs`, `GET/POST/PUT /api/v1/volunteer/training-sessions`, `POST /api/v1/volunteer/training-sessions/{session}/enroll`, `PUT /api/v1/volunteer/training-enrollments/{enrollment}`, `POST/GET /api/v1/volunteer/training-enrollments/{enrollment}/certificate` — Training curricula, schedules, attendance, assessment pass/fail, and certificate issuance/download.

---

## 14. Module 11: Workflow Management Implemented Endpoints (`/api/v1/workflow/*`)

### 14.1 Dashboard, Lookups, Target Entity Integration, Notifications & SLA Escalations
- `GET /api/v1/workflow/dashboard` — Scoped workflow KPIs (Redis cached with version invalidation): `my_pending_approvals`, `submitted_requests`, `approved_requests`, `rejected_requests`, `returned_requests`, `info_requested`, `overdue_workflows`, `escalated_workflows`, `avg_turnaround_hours`, `sla_compliance_rate_pct`, plus module, state, and approval-level performance breakdowns.
- `GET /api/v1/workflow/lookups` — Active workflow definitions, supported modules, entity types, approval levels, execution modes, assignee/authority types, workflow states, priorities, actions, system roles, scoped administrative units, and scoped active users.
- `GET /api/v1/workflow/target-entities?entity_type=...&search=...` — Scoped live lookup across integrated IDMS modules (`HOUSEHOLD`, `RELIEF_REQUISITION`, `RELIEF_ALLOCATION`, `DAMAGE_ASSESSMENT`, `PROJECT`, `PROJECT_DISBURSEMENT`, `RESOURCE_ALLOCATION`, `WAREHOUSE_TRANSFER`, `VOLUNTEER`).
- `GET /api/v1/workflow/notifications`, `POST /api/v1/workflow/notifications/{notification}/read`, `POST /api/v1/workflow/notifications/read-all` — Scoped workflow assignment, status-change, reminder, and escalation notifications.
- `POST /api/v1/workflow/escalations/run` — Trigger SLA overdue reminder & escalation sweep (also scheduled via `ProcessWorkflowEscalationsJob` / `php artisan workflow:escalate-overdue`).

### 14.2 Configurable Workflow Definitions & Delegations
- `GET/POST /api/v1/workflow/definitions`, `GET/PUT/DELETE /api/v1/workflow/definitions/{definition}`, `POST /api/v1/workflow/definitions/{definition}/toggle-status` — Configure module workflows, sequential or parallel (`SEQUENTIAL`, `PARALLEL_ALL`, `PARALLEL_ANY`) stages across approval levels (`SUBMITTED`, `INITIAL_REVIEW`, `SUPERVISOR_APPROVAL`, `DISTRICT_APPROVAL`, `DIRECTORATE_APPROVAL`, `MINISTRY_APPROVAL`, `FINAL_APPROVAL`), SLA hours, escalation fallback roles, and user/role/administrative-unit stage assignments (`workflow.configuration.manage`, audited).
- `GET/POST /api/v1/workflow/delegations`, `POST /api/v1/workflow/delegations/{delegation}/revoke` — Manage authorized time-bound delegations enforcing self-delegation blocks, role restrictions, and administrative jurisdiction boundaries (audited).

### 14.3 Workflow Instances, Actions, Attachments & Immutable History
- `GET/POST /api/v1/workflow/instances`, `GET /api/v1/workflow/instances/{instance}` — Scoped, paginated, searchable, and filterable approval queues (`my_pending`, `submitted_by_me`, `approved`, `rejected`, `overdue`, `all`) and full instance detail with stage tasks, immutable decision history, live entity summary, and computed `available_actions`.
- `GET /api/v1/workflow/entities/{entityType}/{entityId}/history` — Retrieve workflow instances and complete transition history for any integrated module entity.
- `POST /api/v1/workflow/instances/{instance}/approve`, `POST /reject`, `POST /return-correction`, `POST /request-info`, `POST /provide-info`, `POST /resubmit`, `POST /forward`, `POST /cancel`, `POST /remind` — Transaction-safe (`lockForUpdate()`) state transitions enforcing 4-eye SoD, `ROLE_SUPER_ADMIN` operational blackout, delegation tracking (`acted_on_behalf_of_user_id`), and `AuditLog` entries.
- `POST/GET /api/v1/workflow/instances/{instance}/attachments` & `/attachments/{attachment}` — Private supporting file uploads with MIME verification, SHA-256 checksums, and authorized streamed download.

---

## 15. Module 12: User Management Implemented Endpoints (`/api/v1/user-management/*`)

### 15.1 Dashboard, Lookups, Activity Logs & Keycloak Health
- `GET /api/v1/user-management/dashboard` — Scoped user analytics (Redis cached with version invalidation): `total_users`, `active_users`, `inactive_users`, `pending_users`, `suspended_users`, `deactivated_users`, `locked_users`, `mfa_enforced_users`, `mfa_verified_users`, `idp_linked_users`, `idp_failed_users`, `by_organization`, `by_geographical_level`, `role_distribution`, `keycloak_health`, and `recent_activity`.
- `GET /api/v1/user-management/lookups` — Active roles, scoped organizations, scoped administrative units, lifecycle/sync/scope/org constants.
- `GET /api/v1/user-management/activity-logs` & `GET /api/v1/user-management/users/{user}/activity` — Scoped RBAC and user lifecycle audit logs from `audit.audit_logs`.
- `GET /api/v1/user-management/keycloak-health` — Keycloak Admin adapter connectivity and configuration status.

### 15.2 User Provisioning, Profile, Photo, Lifecycle, Roles, Scopes & Keycloak Sync
- `GET/POST /api/v1/user-management/users`, `GET/PUT/DELETE /api/v1/user-management/users/{user}` — Scoped, paginated, searchable, filterable user directory & CRUD with Keycloak Admin provisioning/linking (`KeycloakAdminServiceInterface`), zero local password storage, and `AuditLog` tracking.
- `POST/GET /api/v1/user-management/users/{user}/photo` — Private SHA-256 profile photograph upload and authorized streamed download.
- `POST /api/v1/user-management/users/{user}/status` — Lifecycle transitions (`PENDING`, `ACTIVE`, `SUSPENDED`, `DEACTIVATED`, `LOCKED`) with self-lockout guard, Keycloak enable/disable sync, and `KeycloakAuthenticate` enforcement (`403 ACCOUNT_RESTRICTED`).
- `POST /api/v1/user-management/users/{user}/roles` — Multi-role assignment enforcing hierarchy-rank privilege-escalation prevention and Super Admin self-demotion protection.
- `POST /api/v1/user-management/users/{user}/scopes` & `POST /api/v1/user-management/users/{user}/scopes/{scope}/revoke` — Primary and time-bound temporary organizational/geographical scope assignments (`PRIMARY_POSTING`, `ADDITIONAL_CHARGE`, `EMERGENCY_DEPUTATION`, `INSPECTION_TAG`).
- `POST /api/v1/user-management/users/{user}/sync-keycloak` — Synchronize profile, roles, status, or MFA enforcement (`CONFIGURE_TOTP`) with Keycloak and record `idp_sync_status` safely on IdP outage (`502` with `SYNC_FAILED`).

### 15.3 Roles, Permissions Matrix & Organizations
- `GET/POST /api/v1/user-management/roles`, `GET/PUT/DELETE /api/v1/user-management/roles/{role}` — Manage statutory and configurable custom roles (system roles protected from deletion).
- `GET /api/v1/user-management/permissions` & `POST /api/v1/user-management/roles/{role}/permissions` — Module/action permission catalog and audited role-permission matrix sync with Redis cache invalidation (`rbac:role_permissions:{role_code}`).
- `GET/POST /api/v1/user-management/organizations`, `GET/PUT/DELETE /api/v1/user-management/organizations/{organization}` — Scoped organization registry CRUD (blocks archiving organizations with assigned users).

---

## 16. Module 13: Notification & Alert Management Implemented Endpoints (`/api/v1/notifications/*`)

### 16.1 Dashboard, Lookups, In-App Inbox, User Preferences & Flutter Push Devices
- `GET /api/v1/notifications/dashboard` — Scoped notification & alert KPIs (Redis cached with version invalidation): `total_notifications`, `total_dispatches`, `delivered_notifications`, `failed_notifications`, `pending_notifications`, `active_alerts`, `unacknowledged_alerts`, `escalated_alerts`, `resolved_alerts`, `unread_in_app`, channel/category/severity breakdowns, recent items, and adapter readiness (`SmsGatewayInterface`, `PushNotificationInterface`, Email).
- `GET /api/v1/notifications/lookups` — Categories, channels, severities, priorities, target types, statuses, event types, active bilingual templates, scoped alert rules, roles, organizations, administrative units, and scoped active users.
- `GET /api/v1/notifications/inbox`, `POST /api/v1/notifications/inbox/{dispatch}/read`, `POST /api/v1/notifications/inbox/read-all` — Self-service In-App notification inbox with unread counts, category/severity filters, and read receipts.
- `GET /api/v1/notifications/preferences` & `PUT /api/v1/notifications/preferences` — Per-user, per-category channel preferences (`in_app_enabled`, `email_enabled`, `sms_enabled`, `push_enabled`, quiet hours) enforcing MoDMR mandatory policy locks on critical emergency categories (`EMERGENCY_ALERT`, `DISASTER_WARNING`).
- `GET /api/v1/notifications/devices`, `POST /api/v1/notifications/devices`, `DELETE /api/v1/notifications/devices/{device}` — Secure Flutter/mobile push device registration (`ANDROID`, `IOS`, `WEB` / `FCM`, `APNS`, `WEB_PUSH`) encrypting push tokens at rest and hiding raw tokens from all API responses.

### 16.2 Bilingual Templates, Multi-Channel Dispatch & Delivery Logs
- `GET/POST /api/v1/notifications/templates`, `GET/PUT/DELETE /api/v1/notifications/templates/{template}` — Manage reusable Bangla and English notification templates with `{{variable}}` interpolation (`notification.template.manage`, audited; system templates protected from deletion).
- `GET/POST /api/v1/notifications`, `GET /api/v1/notifications/{notification}` — Scoped notification campaigns & broadcasts targeting `USER`, `ROLE`, `ORGANIZATION`, `GEOGRAPHY`, or `ALL` across `IN_APP`, `EMAIL`, `SMS`, and `PUSH`, supporting `scheduled_at`, `idempotency_key` deduplication, priority Redis queues (`notifications-urgent`, `notifications-default`), `ROLE_SUPER_ADMIN` operational blackout, and 7-tier jurisdiction validation.
- `GET /api/v1/notifications/dispatches`, `POST /api/v1/notifications/dispatches/{dispatch}/retry`, `POST /api/v1/notifications/process-scheduled` — Scoped delivery logs with strictly masked contact destinations (`masked_destination`), automated exponential backoff retries (`DeliverNotificationDispatchJob`), manual retry for `FAILED` dispatches, and scheduled dispatch execution.

### 16.3 Alert Rules, Threshold Triggering, Acknowledgement & SLA Escalations
- `GET/POST /api/v1/notifications/alert-rules`, `PUT/DELETE /api/v1/notifications/alert-rules/{rule}` — Configure threshold alert rules (`condition_config`), target roles, cooldown windows, and escalation policies (`escalation_delay_minutes`, `escalation_role_code`).
- `GET /api/v1/notifications/alerts`, `GET /api/v1/notifications/alerts/{alert}`, `POST /api/v1/notifications/alerts/trigger` — Scoped alert workbench and event/threshold evaluation trigger with cooldown & `idempotency_key` deduplication.
- `POST /api/v1/notifications/alerts/{alert}/acknowledge`, `POST /api/v1/notifications/alerts/{alert}/resolve`, `POST /api/v1/notifications/alerts/escalate` — Audited alert acknowledgement & resolution (enforcing `ROLE_SUPER_ADMIN` operational blackout & jurisdiction scoping) and SLA escalation sweep (`ProcessAlertEscalationsJob` / `php artisan notifications:escalate-alerts`).




