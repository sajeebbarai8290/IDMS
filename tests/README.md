# IDMS Automated Testing Strategy

## Overview
Automated testing is mandatory for all IDMS features under the Ministry of Disaster Management and Relief (MoDMR) quality assurance charter.

---

## 1. Testing Pyramid

```
       /\
      /  \      E2E Tests (Playwright / Cypress in tests/e2e)
     /----\     
    /      \    Feature / API Integration Tests (backend/tests/Feature)
   /--------\   
  /          \  Unit Tests (backend/tests/Unit & frontend component tests)
 /------------\
```

---

## 2. Test Execution Commands

### Backend Automated Tests (PHPUnit / Pest):
```bash
cd backend
php artisan test
```

### Frontend Build & Lint Checks:
```bash
cd frontend
npm run build
```

---

## 3. Database Scoping Test Principles
Every feature test interacting with administrative data must test:
1. **Vertical Inheritance:** National / District officers can retrieve descendant records.
2. **Lateral Isolation:** Peer upazilas cannot access each other's records (assert HTTP 403 / empty dataset).
3. **Citizen PII Masking:** Beneficiary queries return masked NIDs unless specifically authorized.
