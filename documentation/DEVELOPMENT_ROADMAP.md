# Integrated Disaster Management System (IDMS)
## Enterprise Development Roadmap, Dependency Matrix & Sign-Off Checklist

| Document Information | Details |
| :--- | :--- |
| **Document Version** | 1.0.0-PROPOSED |
| **Methodology** | Agile / Phased Milestone Delivery with Strict Gate Sign-Offs |
| **Governance Baseline**| Rule 11 of `AGENTS.md` (Module-by-Module Delivery & Test-Driven Verification) |

---

## 1. Module Dependency Matrix

Understanding inter-module dependencies ensures that database tables, domain service contracts, and frontend components are sequenced without circular blockers:

```mermaid
flowchart TD
    M12[12. User & RBAC] --> M16[16. Audit Trail]
    M12 --> M01[01. Beneficiary Management]
    M02[02. NID Verification] --> M01
    M12 --> M03[03. Disaster Information]
    M03 --> M05[05. Response & Shelters]
    M03 --> M09[09. Damage Assessment D-Form]
    M12 --> M08[08. Warehouse & Inventory]
    M08 --> M04[04. Relief Distribution]
    M01 --> M04
    M03 --> M04
    M07[07. Resource Management] --> M05
    M10[10. Volunteer Management] --> M05
    M05 --> M06[06. Project Management]
    M11[11. Workflow Engine] --> M04
    M11 --> M09
    M11 --> M06
    M13[13. Notification Engine] --> M04
    M13 --> M05
    M14[14. Document Management] --> M06
    M14 --> M09
    M04 --> M15[15. Reporting & Analytics]
    M09 --> M15
    M15 --> M17[17. AI Features]
```

---

## 2. Phased Implementation Roadmap

In accordance with Section 11 of `AGENTS.md` (*"Never generate the entire application in one step. Implement one module at a time"*), development proceeds in 8 structured phases:

### Phase 0: Architecture, Foundation & Scaffolding (COMPLETED)
- [x] Architecture approval and sign-off (System Architecture, Security Design, Database ERD, RBAC Matrix, Organizational Access Control).
- [x] Provision local and staging development configurations (Docker Compose for PostgreSQL 16 + PostGIS, Redis 7, Keycloak 24+).
- [x] Initialize Laravel 11 modular monolith scaffold under `backend/` with PostgreSQL/PostGIS, Redis (Predis), `/api/v1` versioning, centralized JSON error handling, and Keycloak JWT guard.
- [x] Initialize React 19 SPA under `frontend/` with Vite, React Router, TanStack Query, Axios, Bootstrap 5, and sovereign BNDA theme.
- [x] Establish base database migrations for extensions (`postgis`, `uuid-ossp`) and 12 sovereign schemas (`core`, `beneficiary`, `disaster`, `damage`, `logistics`, `distribution`, `project`, `volunteer`, `workflow`, `notification`, `document`, `audit`).
- [x] Establish passwordless sovereign user model (`App\Core\Models\User`) delegating all authentication to Keycloak IAM.
- [x] Verify automated unit/feature tests (6 passed) and frontend Vite build.

### Phase 1: Core System & Security Infrastructure (AUTHENTICATION & RBAC COMPLETED)
- [x] **Keycloak OIDC Centralized Authentication:**
  - Provisioned Keycloak 24.0.5 running on `http://localhost:8080` in Docker.
  - Configured realm `idms-gov-realm` with clients: `idms-spa-frontend` (Public, PKCE S256) and `idms-backend-api` (Bearer-Only).
  - Protocol mappers configured for `ancestry_path`, `tier_level`, `scope_id`, `roles`, and `designation`.
  - Configured MFA policy (TOTP) in Keycloak.
- [x] **React Frontend Authentication (RFC 7636 PKCE Flow):**
  - Implemented Authorization Code Flow with PKCE (`code_verifier`, SHA-256 `code_challenge`, cryptographic `state`).
  - Implemented `/auth/callback` handler (`CallbackPage.jsx`) exchanging code for access, refresh, and ID tokens.
  - Implemented transparent silent refresh in Axios interceptor with retry queuing.
  - Implemented OIDC end-session logout (`post_logout_redirect_uri`).
  - Zero client secrets exposed in frontend.
- [x] **Laravel Backend Authentication & Verification Guard:**
  - Implemented `KeycloakJwtService.php`: strictly validates RS256 algorithm (rejects `none` and symmetric algs), checks expiration (`exp`), not-before (`nbf`), issuer (`iss`), and audience (`aud`), verifies cryptographic RS256 signature using Keycloak JWKS public certs with rotation cache.
  - Implemented `UserSynchronizationService.php`: maps validated token claims into PostgreSQL `core.users` without storing passwords in Laravel.
  - Implemented `AdministrativeScopeService.php`: strictly enforces 7-tier hierarchical scoping (downward subtree access, lateral boundary isolation, upward access denial, national executive bypass).
  - Implemented `KeycloakAuthenticate` & `RequireRole` middleware on protected `/api/v1` routes.
- [x] **Automated & Live Verification:**
  - 18 automated tests passing in PHPUnit (`KeycloakAuthenticationTest`, `HealthCheckTest`), asserting 401 on missing/malformed/insecure/expired tokens, tampered signatures, 403 on role/scope mismatches, and 200 on authorized access.
  - Live end-to-end integration verified against live Docker Keycloak, PostgreSQL PostGIS (`core.users`), and Redis.
- [ ] **Module 16 (Audit Trail):** Global audit observers capturing JSON mutations, IP, and actor credentials into partitioned audit tables.
- [ ] **Module 14 (Document Management):** Secure file upload pipeline with MIME sniffing, ClamAV hook, UUID obfuscation, and pre-signed URL streamer.
- [ ] **Base Seeder:** Bangladesh Administrative Hierarchy dataset (8 Divisions, 64 Districts, 495 Upazilas, 4,500+ Unions).

### Phase 2: Citizen Identity & Beneficiary Registry (MODULE 01 & 02 COMPLETED)
- [x] **Module 02 (NID Verification Engine):** Dedicated `App\Modules\NidVerification` module with `NidVerificationServiceInterface`, `MockNidAdapter` (dev/test only with explicit `is_mock` isolation), fail-closed `AuthorizedGovernmentNidAdapter` awaiting official government credentials, bounded retries/timeouts, Redis rate limiting, optional async queue job (`ProcessNidVerificationJob`), `beneficiary.nid_verification_attempts` history, 4-eye SoD manual review overrides, React dashboard/workbench/history/detail UI, and 14 dedicated unit/feature tests.
- [x] **Module 01 (Beneficiary Management):** Master household enrollment, member roster, deterministic vulnerability scoring formula (0-100 scale), multi-factor duplicate suspect detection engine, 7-tier scoping, and PostGIS dwelling coordinates.
- [x] **Four-Eye Verification State Machine:** Draft &rarr; Field Survey &rarr; UDMC Vetted &rarr; PIO/DRRO Statutory Approval / Rejection with separation of duties enforced.
- [x] **Sensitive Personal Data (PII) Protection:** AES-256-GCM authenticated encryption for NID/Phone, keyed HMAC-SHA256 deduplication hashing, masked UI rendering, and audited gated decryption (`beneficiary.pii.decrypt`).
- [x] **Disaster Linkages & Assistance History:** Pivot relationships linking households to disaster events (Cyclone Remal, Floods) with assistance disbursement counters preventing unauthorized double dipping.
- [x] **Batch Processing:** CSV/Excel import with line-by-line validation error reporting, sample template generator, and muster roll CSV export.
- [x] **React Frontend:** Enterprise dashboard with KPI metrics, high-performance searchable/filterable master registry, 5-step registration wizard with GPS capture, profile details, family member roster, and side-by-side duplicate comparison dialog.
- [x] **Automated Testing:** 45 backend tests passing in PHPUnit (14 new Module 02 unit/feature tests + 31 existing tests, 240+ assertions) covering statutory rules, 4-eye SoD, scoping, rate limiting, retries, async queue safety, and failure scenarios.

### Phase 3: Disaster Lifecycle & GIS Spatial Operations (MODULE 03 COMPLETED)
- [x] **Module 03 (Disaster Information Management):** Configurable disaster categories/subtypes, multi-location 7-tier scoping, PostGIS epicenter & affected-area MultiPolygon/GeoJSON support, humanitarian & economic impact tracking, 7-stage lifecycle state machine (`REPORTED` &rarr; `CLOSED`), early warning bulletins with queued notification adapter (`DisasterNotificationInterface`), chronological timeline with SHA-256 hashed private attachments, Leaflet interactive map with Shapefile (`.zip`) / GeoJSON upload & polygon/point picker, and 5 feature tests (52 total tests passing, 300+ assertions).
- **GIS Core Engine:** Leaflet React integration (`leaflet` + `shpjs`) implemented with spatial filters (`bbox`, radius, administrative unit, category, severity, lifecycle, date range), Shapefile upload, optional GeoServer WMS toggle, and PostGIS spatial queries (`ST_Intersects`, `ST_DWithin`). GeoServer container publishing remains ready for deployment configuration.
- **Module 05 (Disaster Response & Shelter Management):** Cyclone/flood shelter asset catalog, capacity and real-time occupancy logging, emergency SOS incident intake.

### Phase 4: Logistics, Warehousing & Relief Chain
- **Module 08 (Warehouse & Inventory Management):** Multi-echelon godown registry (CSD, LSD, District, Upazila), batch/expiry tracking, FIFO stock ledger transactions.
- **Module 07 (Resource & Fleet Management):** Rescue boats, 4WD vehicles, water purification units, and equipment dispatch.
- **Module 04 (Relief Distribution Management):** Relief requisition generation, allocation matrices, and distribution verification (QR code / SMS OTP hand-off proof).

### Phase 5: Field Damage Assessment & Mobilization
- **Module 09 (Damage Assessment - D-Form):** Digital official Bangladesh D-Form workflow (Housing, Crops, Livestock, Fisheries, Infrastructure, Human Casualties) with photo geo-tagging.
- **Module 10 (Volunteer Management):** Cyclone Preparedness Programme (CPP) and Red Crescent volunteer rosters, skill matrices, and callout alerting.

### Phase 6: Governance, Workflows & Communications
- **Module 11 (Workflow Engine):** State machine engine governing multi-tier approvals (Union &rarr; Upazila &rarr; District &rarr; Directorate &rarr; Ministry) with escalation timeouts.
- **Module 06 (Project Management):** Post-disaster rehabilitation schemes, milestone inspections with timestamped photographs, and disbursement authorizations.
- **Module 13 (Notification & Alert Management):** Teletalk SMS gateway adapter and priority Redis queue dispatcher.

### Phase 7: Analytics, Assistive AI & System Hardening
- **Module 15 (Reporting & Analytics):** Automated National Situation Report (SitRep) generator, export engines (PDF with Bangla UTF-8, Excel, GeoJSON).
- **Module 17 (Artificial Intelligence Features):** Predictive logistics stockout forecaster, automated SitRep drafting copilot, and natural language query parser with strict human-in-the-loop authorization gates.
- **End-to-End Hardening:** k6 stress testing (5,000 concurrent VUs), security penetration testing, accessibility review, and final acceptance testing.

---

## 3. Architecture Review & Sign-Off Checklist

Before coding begins, the following architectural decisions and baseline requirements must be formally approved:

| Item # | Architectural Domain | Checkpoint Description | Status |
| :---: | :--- | :--- | :---: |
| **01** | **System Architecture** | Modular Monolithic Laravel backend + Decoupled React SPA (Vite) selected over distributed microservices. | **PROPOSED** |
| **02** | **Administrative Scope** | 7-tier Hierarchical Scope-Based Access Control (HSBAC) approved; no SaaS tenant isolation; downward data inheritance enforced. | **PROPOSED** |
| **03** | **Identity & IAM** | Keycloak approved as sole sovereign IdP; OIDC / PKCE auth flow; JWT token with embedded administrative claims; MFA mandatory for Upazila level and above. | **PROPOSED** |
| **04** | **Database & Keys** | PostgreSQL 16+ with PostGIS approved; dedicated schemas (`core`, `beneficiary`, `disaster`, `logistics`, `volunteer`, `analytics`); UUIDv7 for all primary keys. | **PROPOSED** |
| **05** | **GIS Integration** | GeoServer OGC services (WMS/WFS/MVT) + OpenLayers 9.x in React approved; dual projection support (EPSG:4326 storage, EPSG:3857 display, EPSG:3106 BTM area calculations). | **PROPOSED** |
| **06** | **NID Verification** | Decoupled `NidVerificationServiceInterface` with enterprise `MockNidAdapter` approved until certified Election Commission / Porichoy API credentials are provided. | **PROPOSED** |
| **07** | **Logistics & Inventory** | Strict FIFO/FEFO accounting, batch tracking, expiry monitoring, and dual-authorization stock write-offs approved. | **PROPOSED** |
| **08** | **AI Governance** | Assistive-only AI co-pilot approved; strictly zero autonomous binding approvals; mandatory human officer digital signature; clear data provenance citing. | **PROPOSED** |
| **09** | **Localization (i18n)** | Bilingual interface approved with Bangla as default and English toggle; full Unicode UTF-8 Bangla rendering across all PDFs, reports, and SMS. | **PROPOSED** |
| **10** | **Execution Discipline**| Phased module-by-module delivery sequence (Phases 0 through 7) adhering strictly to `AGENTS.md` rules. | **PROPOSED** |

---

> [!IMPORTANT]
> **Action Required:** Please review the architecture artifacts saved in the `documentation/` directory and confirm your approval of the Architecture Review Checklist above before we proceed to Phase 0 environment setup and base database schema migrations.
