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

