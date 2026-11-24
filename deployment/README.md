# IDMS Deployment & Infrastructure Architecture

## Overview
This directory contains orchestration, reverse proxy configurations, and identity templates for running the Integrated Disaster Management System (IDMS) across local development, staging, and sovereign production environments (National Data Center - BCC, Dhaka).

---

## 1. Local Development Stack (Docker Compose)
To start PostgreSQL 16 + PostGIS 3.4, Redis 7, and Keycloak 24+:

```bash
cd deployment/docker
docker compose up -d
```

### Stack Port Bindings:
- **PostgreSQL 16 + PostGIS:** `localhost:5432` (`idms_user` / `idms_password` / `idms_db`)
- **Redis 7:** `localhost:6379`
- **Keycloak 24+ IAM:** `http://localhost:8080` (Realm: `idms-gov-realm`)

---

## 2. Keycloak Realm Configuration
The realm configuration in `deployment/keycloak/idms-realm-template.json` pre-configures:
- Realm: `idms-gov-realm`
- Client 1: `idms-spa-frontend` (Public OIDC client with PKCE)
- Client 2: `idms-backend-api` (Bearer-only Resource Server)
- All 21 system roles across the 7 administrative governance tiers.

---

## 3. Production Deployment Notes
In production environments:
1. LUKS volume encryption is mandated for PostgreSQL storage volumes.
2. TLS 1.3 is enforced with HSTS (2 years max-age).
3. The application runs behind a Government Web Application Firewall (WAF) and reverse proxy (`deployment/nginx/idms.conf`).
