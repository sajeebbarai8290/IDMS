# Integrated Disaster Management System (IDMS)
### সমন্বিত দুর্যোগ ব্যবস্থাপনা সিস্টেম
**Ministry of Disaster Management and Relief (MoDMR), Government of Bangladesh**

---

## 1. Project Overview
The Integrated Disaster Management System (IDMS) is the sovereign national web-based digital platform for disaster risk reduction, early warning dissemination, automated emergency response, beneficiary enrollment, NID verification, relief requisition and distribution, damage assessment (D-Form), shelter management, volunteer mobilization, and post-disaster reconstruction monitoring across the People's Republic of Bangladesh.

The platform is designed to operate seamlessly across **seven sovereign administrative governance tiers**:
1. **Tier 1:** National / Ministry (MoDMR, NDMC, NEOC)
2. **Tier 2:** Directorate (Department of Disaster Management - DDM)
3. **Tier 3:** Division (8 Divisions)
4. **Tier 4:** District (64 Districts - DC / DRRO)
5. **Tier 5:** Upazila (495 Upazilas - UNO / PIO)
6. **Tier 6:** Union / Pouroshava (~4,571 Unions - UP Chairman / Secretary)
7. **Tier 7:** Field Operations (Wards, Relief Points, Cyclone Shelters, Survey Zones)

---

## 2. Technology Stack

### Backend
- **Framework:** Laravel 11.x (PHP 8.3 / 8.4) Modular Monolith
- **API Standard:** RESTful API with versioning (`/api/v1`), Form Request validation, API Resources
- **Database:** PostgreSQL 16.x with PostGIS 3.4+ spatial extension
- **Caching & Queues:** Redis 7 (Predis client)
- **Identity & Access Management (IAM):** Keycloak 24+ (OpenID Connect / OAuth 2.0 / RS256 JWT / MFA)

### Frontend
- **Framework:** React 19 (JavaScript) via Vite
- **UI & Design:** Bootstrap 5.3 + Bootstrap Icons + Custom Government Enterprise Theme
- **Routing:** React Router v6 (Hierarchical Protected Routes)
- **Data Fetching & Cache:** TanStack Query v5 (@tanstack/react-query)
- **HTTP Client:** Axios (Centralized token injection & error normalization)
- **GIS Mapping:** OpenLayers (Planned integration)

---

## 3. Directory Structure

```
IDMS/
├── backend/                  # Laravel 11.x Modular Monolith API
│   ├── app/
│   │   ├── Core/             # Cross-cutting security, Keycloak JWT guard, models
│   │   └── Modules/          # 17 Encapsulated functional domains (Planned)
│   ├── config/               # Database, Redis, CORS, Services configuration
│   ├── database/migrations/  # PostGIS & UUID extensions, sovereign schemas, users
│   ├── routes/               # API routes (/api/v1) and web routes
│   └── tests/                # PHPUnit automated test suites
├── frontend/                 # React 19 SPA (Vite)
│   ├── src/
│   │   ├── api/              # Axios client, endpoints, Keycloak OIDC service
│   │   ├── components/       # Reusable UI widgets (Loader, Badge, Button, Dialog)
│   │   ├── contexts/         # AuthContext & ScopeContext
│   │   ├── modules/          # Dashboard, Health, Auth, Error views
│   │   ├── routes/           # ProtectedRoute & AppRoutes
│   │   └── styles/           # Government enterprise SCSS/CSS theme
├── database/                 # Standalone SQL scripts, schema definitions, backups
│   └── sql/                  # 01_init_postgis_and_schemas.sql
├── deployment/               # DevOps orchestration, Docker Compose, Nginx, Keycloak
│   ├── docker/               # docker-compose.yml (Postgres+PostGIS, Redis, Keycloak)
│   ├── keycloak/             # idms-realm-template.json (Realm roles & clients)
│   └── nginx/                # Reverse proxy & WAF configuration
├── documentation/            # Approved enterprise architecture specifications
│   ├── SYSTEM_ARCHITECTURE.md
│   ├── SECURITY_DESIGN.md
│   ├── DATABASE_ERD.md
│   ├── ROLE_PERMISSION_MATRIX.md
│   └── ORGANIZATIONAL_ACCESS_CONTROL.md
└── tests/                    # E2E test suite configurations
```

---

## 4. Quickstart & Installation Guide

### Prerequisites:
- PHP 8.2+ with `pdo_pgsql`, `pgsql`, `openssl`, `mbstring`, `curl`
- Composer 2.x
- Node.js 18+ and npm
- PostgreSQL 16 with PostGIS & Redis 7 (or Docker)

### Step 1: Start Infrastructure (PostgreSQL, Redis, Keycloak, pgAdmin)
Using Docker Compose:
```bash
cd deployment/docker
docker compose up -d
```
*Port Bindings:*
- **PostgreSQL 16 + PostGIS:** `localhost:5433` (Docker host port mapped to avoid native 5432 conflict)
- **pgAdmin 4 Web GUI:** `http://localhost:5050`
- **Redis 7:** `localhost:6379`
- **Keycloak 24+ IAM:** `http://localhost:8080`

### Step 2: Backend Setup (Laravel)
```bash
cd backend

# Copy environment configuration
cp .env.example .env

# Generate application encryption key
php artisan key:generate

# Run automated health tests
php artisan test
```

### Step 3: Frontend Setup (React SPA)
```bash
cd frontend

# Install npm dependencies
npm install

# Run production build check
npm run build
```

---

## 5. Starting the Development Servers

### Terminal 1: Backend API Server
```bash
cd backend
php artisan serve --port=8000
```
Backend API will be accessible at: `http://localhost:8000/api/v1/health`

### Terminal 2: Frontend Client (Vite)
```bash
cd frontend
npm run dev
```
Frontend Web Portal will be accessible at: `http://localhost:5173`

---

## 6. Authentication Architecture & Security Rules

- **Zero Password Storage:** In compliance with MoDMR cyber guidelines, user passwords are **NEVER** stored or validated inside Laravel.
- **Keycloak Centralized IAM:** Authentication is fully decoupled to Keycloak 24+ enforcing OpenID Connect (OIDC), multi-factor authentication (MFA), and RS256-signed JWTs.
- **Application Authorization:** Laravel's `KeycloakAuthenticate` middleware validates token signatures using cached Keycloak JWKS public certificates and evaluates business permissions and administrative scope boundaries on every request.
- **Citizen PII Privacy:** Citizen NID numbers, phone numbers, and financial details are AES-256-GCM encrypted and masked by default (`XXXXXXXXXXXX1234`). Unmasking requires explicit `beneficiary.pii.decrypt` authority and writes an immutable audit record to `audit.audit_logs`.

---

## 7. Automated Health & Test Verification

Run all backend unit and feature tests:
```bash
cd backend
php artisan test
```

Inspect live API health:
```bash
curl http://localhost:8000/api/v1/health
curl http://localhost:8000/api/v1/system/info
```
