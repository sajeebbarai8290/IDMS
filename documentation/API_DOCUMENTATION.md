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

interface NidVerificationServiceInterface
{
    /**
     * Verify a citizen's National Identity credentials against the verification provider.
     *
     * @param NidVerificationRequestDto $requestDto
     * @return NidVerificationResultDto
     * @throws \App\Modules\NidVerification\Exceptions\NidVerificationException
     */
    public function verifyNid(NidVerificationRequestDto $requestDto): NidVerificationResultDto;

    /**
     * Check health status of the verification upstream provider.
     *
     * @return bool
     */
    public function isProviderAvailable(): bool;
}
```

### 3.2 Mock Adapter Specification (`MockNidAdapter`)
When running in local, testing, or uncertified staging environments, Laravel binds `MockNidAdapter` to `NidVerificationServiceInterface` via the Service Container:

```php
namespace App\Modules\NidVerification\Adapters;

use App\Modules\NidVerification\Contracts\NidVerificationServiceInterface;
use App\Modules\NidVerification\DTOs\NidVerificationRequestDto;
use App\Modules\NidVerification\DTOs\NidVerificationResultDto;

class MockNidAdapter implements NidVerificationServiceInterface
{
    public function verifyNid(NidVerificationRequestDto $dto): NidVerificationResultDto
    {
        // 1. Simulate network latency (200ms - 500ms)
        usleep(rand(200000, 500000));

        $nid = preg_replace('/[^0-9]/', '', $dto->nidNumber);

        // 2. Format validation: Must be 10 digits (Smart Card), 13 digits, or 17 digits
        if (!in_array(strlen($nid), [10, 13, 17])) {
            return new NidVerificationResultDto(
                isValid: false,
                errorCode: 'INVALID_NID_LENGTH',
                errorMessage: 'NID must be 10, 13, or 17 digits.'
            );
        }

        // 3. Test Fixture: Special test numbers for simulated negative outcomes
        if ($nid === '0000000000') {
            return new NidVerificationResultDto(
                isValid: false,
                errorCode: 'NID_NOT_FOUND',
                errorMessage: 'NID record does not exist in national registry.'
            );
        }

        if ($nid === '9999999999') {
            return new NidVerificationResultDto(
                isValid: false,
                errorCode: 'CITIZEN_DECEASED',
                errorMessage: 'Citizen recorded as deceased in civil registry.'
            );
        }

        // 4. Default Success Simulation
        return new NidVerificationResultDto(
            isValid: true,
            nid: $nid,
            fullNameEn: $dto->nameEn ?? 'MOHAMMAD RAHMAN',
            fullNameBn: 'মোহাম্মদ রহমান',
            dob: $dto->dob ?? '1985-05-15',
            fatherName: 'আজিজুর রহমান',
            motherName: 'ফাতেমা বেগম',
            photoUrl: 'https://mock.nid.gov.bd/photos/placeholder.jpg',
            verificationToken: 'MOCK-VERIFIED-' . hash('sha256', $nid . time())
        );
    }

    public function isProviderAvailable(): bool
    {
        return true;
    }
}
```

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

