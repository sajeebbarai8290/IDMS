
# AGENTS.md
# Integrated Disaster Management System (IDMS)

## 1. Project Overview

Develop a comprehensive Web-Based Integrated Disaster Management System (IDMS) for the Ministry of Disaster Management and Relief (MoDMR), Bangladesh.

The system will centralize disaster information, beneficiary management, NID verification, relief distribution, disaster response, project monitoring, GIS visualization, resource management, and AI-powered analytics.

The platform must support national, divisional, district, upazila, and field-level operations.

## 2. Technology Stack

### Frontend
- React.js
- JavaScript
- HTML5
- CSS3
- Bootstrap 5
- React Router
- Axios
- React Query / TanStack Query
- OpenLayers or Leaflet

### Backend
- Laravel
- PHP
- RESTful API
- Laravel API Resources
- Form Request Validation
- Laravel Queues and Jobs

### Database
- PostgreSQL
- PostGIS Extension
- Redis

### Authentication and Authorization
- Keycloak
- OpenID Connect (OIDC)
- JWT
- Multi-Factor Authentication (MFA)
- Role-Based Access Control (RBAC)

### GIS
- GeoServer
- PostGIS
- OpenLayers / Leaflet
- WMS / WFS

### Future Mobile Application
- Flutter
- Android
- iOS
- Offline data collection
- API synchronization

## 3. Core Functional Modules

Develop the following modules:

1. Beneficiary Management
2. National ID (NID) Verification
3. Disaster Information Management
4. Relief Distribution Management
5. Disaster Response Management
6. Project Management
7. Resource Management
8. Warehouse and Inventory Management
9. Damage Assessment
10. Volunteer Management
11. Workflow Management
12. User Management
13. Notification and Alert Management
14. Document Management
15. Reporting and Analytics
16. Audit Trail
17. Artificial Intelligence Features

## 4. Organizational Structure

Design the system to support the following administrative hierarchy:

- Ministry Level
- Directorate / Department Level
- Division Level
- District Level
- Upazila Level
- Union Level
- Field Officer Level

Implement hierarchical data access.

Users should only access information permitted by their assigned roles, permissions, and organizational scope.

Do not implement SaaS-style tenant isolation by default. Use an organizational hierarchy and data-scoping architecture. If organizational or legal requirements demand separate tenants, document the design before implementing it.

## 5. Architecture Requirements

Use a modular monolithic Laravel architecture initially.

Requirements:
- Clean architecture principles
- Separation of concerns
- Modular domain organization
- REST API versioning (/api/v1)
- Reusable services
- Repository pattern only where justified
- API Resources
- Form Request validation
- Database transactions
- Queue-based background processing
- Centralized exception handling
- Structured logging

Keep the frontend and backend separate.

## 6. Authentication and Security

Use Keycloak as the centralized Identity Provider.

Requirements:
- OpenID Connect
- JWT access token validation
- Refresh token handling
- MFA
- RBAC
- Secure logout
- Session security
- API rate limiting
- CORS configuration
- Input validation
- SQL injection prevention
- XSS protection
- CSRF protection where applicable
- Secure file uploads
- Audit logging
- Encryption at rest and in transit

Never store plain-text passwords.

Never expose API secrets, private keys, database passwords, or NID credentials in frontend code or Git.

Do not invent NID API endpoints or assume access to government services. Use documented integration contracts and mock adapters until official access is provided.

## 7. Database Standards

Use PostgreSQL with PostGIS.

Requirements:
- Proper foreign keys
- Appropriate indexes
- Unique constraints
- Database transactions
- Soft deletes where justified
- Created_by and updated_by where applicable
- Created_at and updated_at
- Normalized database design
- Spatial data support
- Migration and seeder standards

Use UUID or another documented identifier strategy consistently.

Do not create duplicate tables or columns.

Document every important database relationship.

## 8. Coding Standards

### Laravel
- Follow PSR-12.
- Use meaningful class and method names.
- Use Form Requests for validation.
- Use API Resources for responses.
- Keep controllers thin.
- Use services for complex business logic.
- Use policies and middleware for authorization.
- Write automated tests.

### React
- Use reusable components.
- Use functional components and hooks.
- Separate API services from UI components.
- Use centralized authentication handling.
- Use reusable layouts.
- Implement loading, empty, and error states.
- Use responsive design.
- Avoid unnecessary duplication.

## 9. UI/UX Requirements

Build a modern government enterprise dashboard.

Design characteristics:
- Professional government interface
- Responsive design
- Desktop, tablet, and mobile support
- Sidebar navigation
- Top navigation
- Role-based menus
- Dashboard cards
- Interactive tables
- Search and filtering
- Pagination
- Charts and GIS maps
- Form validation
- Confirmation dialogs
- Consistent typography
- Accessible color contrast

Support Bangla and English interfaces.

Use Bangla as the default display language unless the user selects English.

## 10. AI Requirements

Implement AI capabilities in a controlled and auditable manner.

Features:
- AI-assisted report generation
- Intelligent data analysis
- Predictive analytics
- Natural language search
- Intelligent query processing
- Decision support dashboards

AI must not autonomously approve beneficiaries, distribute relief, or make legally binding decisions.

AI-generated insights must identify their data sources and limitations. Human authorization is required for consequential decisions.

## 11. Development Rules

IMPORTANT:

1. Never generate the entire application in one step.
2. First analyze requirements and prepare architecture.
3. Prepare ERD before database migrations.
4. Get architecture approval before implementation.
5. Implement one module at a time.
6. Before coding, inspect the existing project.
7. Never overwrite existing working code without reviewing it.
8. Do not create unnecessary files.
9. Follow the established naming conventions.
10. Write tests for every important feature.
11. Update documentation after each phase.
12. Do not use fake production data or hardcoded credentials.
13. Never claim an integration works without testing it.
14. Run migrations and tests in a controlled development environment.
15. Ask for approval before destructive operations.

## 12. Required Documentation

Maintain these documents:

- documentation/PROJECT_OVERVIEW.md
- documentation/SRS.md
- documentation/SYSTEM_ARCHITECTURE.md
- documentation/DATABASE_ERD.md
- documentation/DATABASE_DICTIONARY.md
- documentation/API_DOCUMENTATION.md
- documentation/SECURITY_DESIGN.md
- documentation/GIS_ARCHITECTURE.md
- documentation/AI_ARCHITECTURE.md
- documentation/DEPLOYMENT_GUIDE.md
- documentation/DEVELOPMENT_ROADMAP.md
- documentation/TESTING_STRATEGY.md

## 13. Completion Criteria

A module is complete only when:

- Database migrations are implemented.
- Backend business logic works.
- API endpoints are implemented.
- Authorization is enforced.
- Frontend UI is connected to real APIs.
- Validation and error handling work.
- Automated tests pass.
- Documentation is updated.

Always prioritize security, maintainability, scalability, data integrity, and usability.
