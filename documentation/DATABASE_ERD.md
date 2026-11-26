# Integrated Disaster Management System (IDMS)
## Enterprise Database Entity-Relationship Diagram (ERD) Specification

| Document Information | Details |
| :--- | :--- |
| **System** | Integrated Disaster Management System (IDMS) |
| **Document Purpose** | Complete Database Relational Schema & Entity-Relationship Modeling |
| **Target Engine** | PostgreSQL 16.x + PostGIS 3.4+ |
| **Document Version** | 1.0.0-PROPOSED |
| **Status** | Prepared for Review (No Migrations Generated) |

---

## 1. Master System Entity-Relationship Diagram (Mermaid)

The master diagram depicts all core operational entities and their foreign key constraints across IDMS:

```mermaid
erDiagram
    %% CORE ADMINISTRATIVE & SECURITY HIERARCHY
    ADMINISTRATIVE_UNITS ||--o{ ADMINISTRATIVE_UNITS : "parent_of"
    ADMINISTRATIVE_UNITS ||--o{ USERS : "assigned_jurisdiction"
    ADMINISTRATIVE_UNITS ||--o{ HOUSEHOLDS : "contains_households"
    ADMINISTRATIVE_UNITS ||--o{ SHELTERS : "located_within"
    ADMINISTRATIVE_UNITS ||--o{ WAREHOUSES : "located_within"

    %% BENEFICIARY & NID VERIFICATION
    HOUSEHOLDS ||--|{ HOUSEHOLD_MEMBERS : "has_members"
    HOUSEHOLDS ||--o{ NID_VERIFICATIONS : "verified_via"
    HOUSEHOLD_MEMBERS ||--o{ NID_VERIFICATIONS : "member_verified_via"
    ADMINISTRATIVE_UNITS ||--o{ NID_VERIFICATIONS : "scoped_to"
    NID_VERIFICATIONS ||--|{ NID_VERIFICATION_ATTEMPTS : "logs_attempts"
    HOUSEHOLDS ||--o{ DISTRIBUTION_VOUCHERS : "receives_relief"

    %% DISASTER & SHELTER MANAGEMENT
    DISASTER_CATEGORIES ||--o{ DISASTER_EVENTS : "classifies"
    DISASTER_EVENTS ||--|{ DISASTER_LOCATIONS : "affects_areas"
    ADMINISTRATIVE_UNITS ||--o{ DISASTER_LOCATIONS : "mapped_to_disaster"
    DISASTER_EVENTS ||--o| DISASTER_IMPACTS : "summarizes_impact"
    DISASTER_EVENTS ||--o{ DISASTER_WARNINGS : "issues_warnings"
    DISASTER_EVENTS ||--o{ DISASTER_TIMELINES : "tracks_history"
    DISASTER_EVENTS ||--o{ SHELTER_OCCUPANCIES : "causes_evacuation_to"
    SHELTERS ||--o{ SHELTER_OCCUPANCIES : "logs_occupancy"
    DISASTER_EVENTS ||--o{ SAR_INCIDENTS : "triggers_sar"
    DISASTER_EVENTS ||--o{ DAMAGE_ASSESSMENTS : "caused_damage"
    DISASTER_EVENTS ||--o{ RELIEF_REQUISITIONS : "triggers_requisitions"
    DISASTER_EVENTS ||--o{ PROJECTS : "rehabilitates_from"
    DISASTER_EVENTS ||--o{ RESPONSE_OPERATIONS : "triggers_operations"

    %% DISASTER RESPONSE MANAGEMENT (M05)
    ADMINISTRATIVE_UNITS ||--o{ RESPONSE_CENTERS : "hosts_eoc"
    ADMINISTRATIVE_UNITS ||--o{ RESOURCE_ASSETS : "stationed_at"
    ADMINISTRATIVE_UNITS ||--o{ RESPONSE_OPERATIONS : "scoped_operation"
    RESPONSE_CENTERS ||--o{ RESPONSE_OPERATIONS : "coordinated_from"
    RESPONSE_OPERATIONS ||--o{ RESPONSE_OPERATION_AGENCIES : "coordinates_with"
    RESPONSE_OPERATIONS ||--o{ RESPONSE_TEAMS : "deploys_teams"
    RESPONSE_OPERATIONS ||--o{ RESPONSE_TASKS : "tracks_tasks"
    RESPONSE_ACTIVITY_TYPES ||--o{ RESPONSE_TASKS : "classifies"
    RESPONSE_TEAMS ||--o{ RESPONSE_TASKS : "executes"
    RESPONSE_TASKS ||--o{ RESPONSE_TASK_ATTACHMENTS : "evidenced_by"
    RESPONSE_OPERATIONS ||--o{ RESPONSE_RESOURCE_ALLOCATIONS : "requests_or_links"
    RESOURCE_ASSETS ||--o{ RESPONSE_RESOURCE_ALLOCATIONS : "deployed_to_operation"
    RESPONSE_OPERATIONS ||--o{ RESPONSE_SITUATION_REPORTS : "receives_sitreps"

    %% RESOURCE MANAGEMENT (M07)
    RESOURCE_CATEGORIES ||--o{ RESOURCE_ASSETS : "classifies_resource"
    RESOURCE_ASSETS ||--o{ RESOURCE_ATTACHMENTS : "documented_by"
    RESOURCE_ASSETS ||--o{ RESOURCE_ALLOCATIONS : "allocated_via"
    DISASTER_EVENTS ||--o{ RESOURCE_ALLOCATIONS : "allocates_resources"
    RESPONSE_OPERATIONS ||--o{ RESOURCE_ALLOCATIONS : "deploys_resources"
    ADMINISTRATIVE_UNITS ||--o{ RESOURCE_ALLOCATIONS : "allocation_location"
    RESOURCE_ASSETS ||--o{ RESOURCE_MAINTENANCES : "serviced_via"
    RESOURCE_ASSETS ||--o{ HUMAN_RESOURCE_DEPLOYMENTS : "assigned_personnel"
    RESPONSE_TEAMS ||--o{ HUMAN_RESOURCE_DEPLOYMENTS : "assigned_team"
    RESPONSE_OPERATIONS ||--o{ HUMAN_RESOURCE_DEPLOYMENTS : "personnel_deployed_to"
    DISASTER_EVENTS ||--o{ HUMAN_RESOURCE_DEPLOYMENTS : "personnel_for_disaster"

    %% DAMAGE ASSESSMENT (D-FORM)
    DAMAGE_ASSESSMENTS ||--|{ DAMAGE_SECTOR_ITEMS : "details_sector_loss"
    ADMINISTRATIVE_UNITS ||--o{ DAMAGE_ASSESSMENTS : "assessed_at"

    %% LOGISTICS, WAREHOUSING & RELIEF CHAIN
    RELIEF_CATEGORIES ||--o{ COMMODITIES : "categorizes"
    COMMODITIES ||--o{ WAREHOUSE_INVENTORIES : "stocked_as"
    WAREHOUSES ||--|{ WAREHOUSE_INVENTORIES : "stores_lots"
    WAREHOUSE_INVENTORIES ||--o{ STOCK_TRANSACTIONS : "ledger_records"

    DISASTER_EVENTS ||--o{ RELIEF_ALLOCATIONS : "funds_allocations"
    ADMINISTRATIVE_UNITS ||--o{ RELIEF_ALLOCATIONS : "allocated_to_unit"
    DISTRIBUTION_POINTS ||--o{ RELIEF_ALLOCATIONS : "staged_at_center"
    WAREHOUSES ||--o{ RELIEF_ALLOCATIONS : "sourced_from_warehouse"

    RELIEF_REQUISITIONS ||--|{ RELIEF_REQUISITION_ITEMS : "contains_requests"
    RELIEF_REQUISITIONS ||--o{ RELIEF_ALLOCATIONS : "approved_into"
    RELIEF_ALLOCATIONS ||--|{ RELIEF_ALLOCATION_ITEMS : "contains_allocations"
    RELIEF_ALLOCATIONS ||--o{ RELIEF_ALLOCATION_BENEFICIARIES : "targets_beneficiaries"
    HOUSEHOLDS ||--o{ RELIEF_ALLOCATION_BENEFICIARIES : "eligible_for_allocation"

    COMMODITIES ||--o{ RELIEF_REQUISITION_ITEMS : "requested_commodity"
    COMMODITIES ||--o{ RELIEF_ALLOCATION_ITEMS : "allocated_commodity"
    
    RELIEF_ALLOCATION_ITEMS ||--o{ DISTRIBUTION_VOUCHERS : "disbursed_through"
    DISTRIBUTION_POINTS ||--o{ DISTRIBUTION_VOUCHERS : "handed_over_at"
    ADMINISTRATIVE_UNITS ||--o{ DISTRIBUTION_POINTS : "located_in"
    STOCK_TRANSACTIONS ||--o| DISTRIBUTION_VOUCHERS : "deducted_for_voucher"

    %% PROJECT MANAGEMENT (M06)
    PROJECT_CATEGORIES ||--o{ PROJECTS : "categorizes"
    PROJECTS ||--o{ PROJECT_LOCATIONS : "covers_sites"
    PROJECTS ||--|{ PROJECT_MILESTONES : "scheduled_in"
    PROJECTS ||--o{ PROJECT_DISBURSEMENTS : "tracks_financials"
    PROJECT_MILESTONES ||--o{ PROJECT_DISBURSEMENTS : "authorizes_payment"
    PROJECTS ||--o{ PROJECT_MONITORING_REPORTS : "inspected_by"
    PROJECTS ||--o{ PROJECT_RISKS : "logs_risks"
    ADMINISTRATIVE_UNITS ||--o{ PROJECTS : "implemented_within"

    %% VOLUNTEER & FIELD OPERATIONS
    VOLUNTEERS ||--|{ VOLUNTEER_SKILL_ASSIGNMENTS : "possesses_skills"
    SKILL_CATALOG ||--o{ VOLUNTEER_SKILL_ASSIGNMENTS : "categorized_by"
    VOLUNTEERS ||--o{ VOLUNTEER_DEPLOYMENTS : "mobilized_into"
    DISASTER_EVENTS ||--o{ VOLUNTEER_DEPLOYMENTS : "mobilized_for"

    %% WORKFLOW ENGINE
    WORKFLOW_INSTANCES ||--|{ WORKFLOW_TRANSITIONS : "progresses_via"
    RELIEF_REQUISITIONS ||--o{ WORKFLOW_INSTANCES : "governed_by"
    DAMAGE_ASSESSMENTS ||--o{ WORKFLOW_INSTANCES : "governed_by"
    PROJECT_DISBURSEMENTS ||--o{ WORKFLOW_INSTANCES : "governed_by"

    %% NOTIFICATIONS, DOCUMENTS & AUDIT
    USERS ||--o{ NOTIFICATION_DISPATCHES : "receives_notice"
    NOTIFICATION_TEMPLATES ||--o{ NOTIFICATION_DISPATCHES : "instantiated_from"
    USERS ||--o{ DOCUMENTS : "uploaded_by"
    USERS ||--o{ AUDIT_LOGS : "performed_by"

    %% ENTITY ATTRIBUTE DEFINITIONS
    ADMINISTRATIVE_UNITS {
        uuid id PK
        uuid parent_id FK
        string code UK
        string name_en
        string name_bn
        string tier_level
        string ancestry_path
        geometry_multipolygon boundary_geom
    }

    USERS {
        uuid id PK
        string keycloak_sub UK
        string email UK
        string full_name_en
        string role_code
        uuid administrative_unit_id FK
        boolean is_active
    }

    HOUSEHOLDS {
        uuid id PK
        string household_code UK
        uuid administrative_unit_id FK
        string hoh_name_en
        string hoh_name_bn
        string hoh_nid_hash UK
        string hoh_nid_encrypted
        decimal vulnerability_score
        geometry_point dwelling_geom
    }

    HOUSEHOLD_MEMBERS {
        uuid id PK
        uuid household_id FK
        string full_name_en
        string full_name_bn
        string nid_hash
        string gender
        int age
        boolean has_disability
    }

    NID_VERIFICATIONS {
        uuid id PK
        uuid household_id FK
        uuid household_member_id FK
        uuid administrative_unit_id FK
        string nid_hash
        string nid_masked
        date date_of_birth
        string verification_status
        string verification_reference
        string verification_token
        string provider_code
        boolean is_mock
        int attempt_count
        boolean manual_override
        uuid requested_by_user_id FK
        uuid reviewed_by_user_id FK
        timestamptz verified_at
        timestamptz reviewed_at
    }

    NID_VERIFICATION_ATTEMPTS {
        uuid id PK
        uuid nid_verification_id FK
        int attempt_number
        string provider_code
        string status
        int http_status
        int response_time_ms
        string error_code
        timestamptz attempted_at
    }

    DISASTER_CATEGORIES {
        uuid id PK
        string code UK
        string name_en
        string name_bn
        json subtypes
        boolean is_active
    }

    DISASTER_EVENTS {
        uuid id PK
        uuid category_id FK
        string disaster_code UK
        string name_en
        string name_bn
        string disaster_type
        string subtype
        string severity_level
        string lifecycle_phase
        timestamptz declaration_date
        date start_date
        date end_date
        decimal epicenter_lat
        decimal epicenter_lng
        geometry_point location_geom
        geometry_multipolygon affected_zone_geom
        boolean is_national
        boolean is_emergency_declared
    }

    DISASTER_LOCATIONS {
        uuid id PK
        uuid disaster_event_id FK
        uuid administrative_unit_id FK
        decimal latitude
        decimal longitude
        string severity_override
    }

    DISASTER_IMPACTS {
        uuid id PK
        uuid disaster_event_id FK
        int affected_population
        int affected_households
        int deaths_count
        int injured_count
        int missing_count
        int damaged_houses_fully
        int damaged_houses_partially
        int damaged_infrastructure_count
        decimal agricultural_loss_bdt
        int livestock_loss_count
        decimal total_economic_loss_bdt
    }

    DISASTER_WARNINGS {
        uuid id PK
        uuid disaster_event_id FK
        string warning_level
        string title_en
        string title_bn
        string issuing_authority
        timestamptz issued_at
        timestamptz expires_at
        string status
    }

    DISASTER_TIMELINES {
        uuid id PK
        uuid disaster_event_id FK
        string event_type
        string from_phase
        string to_phase
        string title_en
        string attachment_path
        uuid recorded_by FK
        timestamptz occurred_at
    }

    SHELTERS {
        uuid id PK
        uuid administrative_unit_id FK
        string shelter_code UK
        string shelter_name_en
        int capacity_persons
        int capacity_livestock
        geometry_point location_geom
    }

    SHELTER_OCCUPANCIES {
        uuid id PK
        uuid shelter_id FK
        uuid disaster_event_id FK
        int current_men
        int current_women
        int current_children
        timestamptz recorded_at
    }

    RESPONSE_ACTIVITY_TYPES {
        uuid id PK
        string code UK
        string name_en
        string name_bn
        boolean is_system
        boolean is_active
    }

    RESPONSE_CENTERS {
        uuid id PK
        uuid administrative_unit_id FK
        string center_code UK
        string center_type
        string name_en
        string name_bn
        decimal latitude
        decimal longitude
        geometry_point location_geom
    }

    RESOURCE_CATEGORIES {
        uuid id PK
        string code UK
        string name_en
        string name_bn
        boolean is_human_resource
        boolean is_system
        boolean is_active
    }

    RESOURCE_ASSETS {
        uuid id PK
        uuid category_id FK
        uuid administrative_unit_id FK
        string asset_code UK
        string asset_type
        string resource_type
        string name_en
        string name_bn
        string owning_organization
        string current_location
        string responsible_officer_name
        uuid responsible_user_id FK
        date acquisition_date
        decimal acquisition_value_bdt
        date next_maintenance_due_at
        string status
        geometry_point location_geom
    }

    RESOURCE_ATTACHMENTS {
        uuid id PK
        uuid resource_asset_id FK
        string attachment_kind
        string original_name
        string storage_path
        string sha256_hash
    }

    RESOURCE_ALLOCATIONS {
        uuid id PK
        uuid resource_asset_id FK
        uuid disaster_event_id FK
        uuid response_operation_id FK
        uuid administrative_unit_id FK
        string allocation_no UK
        string allocation_location
        date assignment_date
        date expected_return_date
        date actual_return_date
        string responsible_officer_name
        string status
        geometry_point location_geom
    }

    RESOURCE_MAINTENANCES {
        uuid id PK
        uuid resource_asset_id FK
        uuid administrative_unit_id FK
        string maintenance_no UK
        string record_type
        string title
        date scheduled_date
        date performed_date
        date next_due_date
        string inspection_result
        decimal repair_cost_bdt
        string status
        string attachment_path
    }

    HUMAN_RESOURCE_DEPLOYMENTS {
        uuid id PK
        uuid resource_asset_id FK
        uuid user_id FK
        uuid response_team_id FK
        uuid response_operation_id FK
        uuid disaster_event_id FK
        uuid administrative_unit_id FK
        string deployment_no UK
        string personnel_name
        text assigned_duty
        date deployment_start_date
        date deployment_end_date
        date actual_return_date
        string availability_status
    }

    RESPONSE_OPERATIONS {
        uuid id PK
        uuid disaster_event_id FK
        uuid administrative_unit_id FK
        uuid response_center_id FK
        string operation_no UK
        string title_en
        string title_bn
        string priority
        string severity_level
        string status
        date start_date
        date expected_completion_date
    }

    RESPONSE_OPERATION_AGENCIES {
        uuid id PK
        uuid response_operation_id FK
        string agency_name
        string agency_type
        string role_summary
        string coordination_status
    }

    RESPONSE_TEAMS {
        uuid id PK
        uuid response_operation_id FK
        uuid administrative_unit_id FK
        string team_code UK
        string team_name
        string agency_name
        string team_type
        int member_count
        string status
        decimal latitude
        decimal longitude
        geometry_point location_geom
    }

    RESPONSE_TASKS {
        uuid id PK
        uuid response_operation_id FK
        uuid activity_type_id FK
        uuid response_team_id FK
        uuid assigned_user_id FK
        string task_code UK
        string title_en
        string title_bn
        string priority
        string status
        int progress_percentage
        timestamptz deadline_at
    }

    RESPONSE_TASK_ATTACHMENTS {
        uuid id PK
        uuid response_task_id FK
        string original_name
        string storage_path
        string sha256_hash
    }

    RESPONSE_RESOURCE_ALLOCATIONS {
        uuid id PK
        uuid response_operation_id FK
        uuid resource_asset_id FK
        string requirement_title
        decimal quantity_needed
        decimal quantity_assigned
        string status
    }

    RESPONSE_SITUATION_REPORTS {
        uuid id PK
        uuid response_operation_id FK
        uuid disaster_event_id FK
        uuid administrative_unit_id FK
        string report_no UK
        string report_type
        int casualties_injured
        int casualties_missing
        int casualties_fatalities
        int rescued_count
        string privacy_level
        decimal latitude
        decimal longitude
        geometry_point location_geom
        timestamptz reported_at
    }

    DAMAGE_ASSESSMENTS {
        uuid id PK
        uuid disaster_event_id FK
        uuid administrative_unit_id FK
        string d_form_code UK
        string assessment_stage
        string validation_status
        decimal total_estimated_loss_bdt
    }

    DAMAGE_SECTOR_ITEMS {
        uuid id PK
        uuid damage_assessment_id FK
        string sector_code
        int fully_damaged_count
        int partially_damaged_count
        decimal estimated_loss_bdt
    }

    RELIEF_CATEGORIES {
        uuid id PK
        string code UK
        string name_en
        string name_bn
        boolean is_system
        boolean is_active
    }

    COMMODITIES {
        uuid id PK
        uuid relief_category_id FK
        string commodity_code UK
        string name_en
        string name_bn
        string category
        string unit_of_measure
    }

    WAREHOUSES {
        uuid id PK
        uuid administrative_unit_id FK
        string warehouse_code UK
        string warehouse_name
        string tier
        decimal capacity_mt
        decimal latitude
        decimal longitude
        geometry_point location_geom
    }

    WAREHOUSE_INVENTORIES {
        uuid id PK
        uuid warehouse_id FK
        uuid commodity_id FK
        string batch_number
        decimal current_quantity
        decimal reserved_quantity
        date expiry_date
    }

    STOCK_TRANSACTIONS {
        uuid id PK
        uuid warehouse_inventory_id FK
        string transaction_type
        decimal quantity
        decimal balance_after
        string reference_voucher_no
        uuid relief_allocation_item_id FK
        uuid distribution_voucher_id FK
    }

    RELIEF_REQUISITIONS {
        uuid id PK
        uuid disaster_event_id FK
        uuid administrative_unit_id FK
        string requisition_no UK
        string requesting_organization
        int estimated_beneficiaries
        string priority
        date request_date
        string status
        string supporting_doc_path
        timestamptz submitted_at
    }

    RELIEF_REQUISITION_ITEMS {
        uuid id PK
        uuid relief_requisition_id FK
        uuid commodity_id FK
        decimal quantity_requested
        decimal quantity_approved
    }

    RELIEF_ALLOCATIONS {
        uuid id PK
        uuid relief_requisition_id FK
        uuid disaster_event_id FK
        uuid administrative_unit_id FK
        uuid distribution_point_id FK
        uuid warehouse_id FK
        string allocation_order_no UK
        string status
        timestamptz scheduled_start_at
        timestamptz scheduled_end_at
        timestamptz approved_at
    }

    RELIEF_ALLOCATION_ITEMS {
        uuid id PK
        uuid relief_allocation_id FK
        uuid commodity_id FK
        decimal quantity_allocated
        decimal quantity_distributed
        decimal quantity_cancelled
    }

    RELIEF_ALLOCATION_BENEFICIARIES {
        uuid id PK
        uuid relief_allocation_id FK
        uuid household_id FK
        string selection_status
    }

    DISTRIBUTION_POINTS {
        uuid id PK
        uuid administrative_unit_id FK
        string point_code UK
        string point_name
        string point_name_bn
        decimal latitude
        decimal longitude
        geometry_point location_geom
    }

    DISTRIBUTION_VOUCHERS {
        uuid id PK
        uuid relief_allocation_item_id FK
        uuid household_id FK
        uuid distribution_point_id FK
        string voucher_no UK
        string idempotency_key UK
        decimal quantity_disbursed
        string verification_method
        string acknowledgment_signature
        string photo_evidence_path
        decimal distribution_lat
        decimal distribution_lng
        boolean duplicate_override
        text duplicate_override_reason
        timestamptz distributed_at
        boolean is_voided
    }

    PROJECTS {
        uuid id PK
        uuid category_id FK
        uuid disaster_event_id FK
        uuid administrative_unit_id FK
        string project_code UK
        string reference_no UK
        string title_en
        string title_bn
        decimal approved_budget_bdt
        decimal revised_budget_bdt
        decimal released_funds_bdt
        decimal expenditure_bdt
        decimal physical_progress_pct
        decimal financial_progress_pct
        string status
        boolean is_delayed
    }

    PROJECT_MILESTONES {
        uuid id PK
        uuid project_id FK
        uuid parent_milestone_id FK
        string wbs_code
        string milestone_title
        string item_type
        decimal weight_percentage
        decimal target_percentage
        decimal completion_percentage
        string approval_status
    }

    PROJECT_DISBURSEMENTS {
        uuid id PK
        uuid project_id FK
        uuid project_milestone_id FK
        string record_type
        string voucher_no UK
        decimal amount_bdt
        string payment_status
        timestamptz disbursed_at
    }

    VOLUNTEERS {
        uuid id PK
        uuid administrative_unit_id FK
        string volunteer_code UK
        string nid_hash UK
        string full_name_en
        string organization
        string mobile_encrypted
        string status
    }

    SKILL_CATALOG {
        uuid id PK
        string skill_code UK
        string skill_name
        string category
    }

    VOLUNTEER_SKILL_ASSIGNMENTS {
        uuid id PK
        uuid volunteer_id FK
        uuid skill_id FK
        string certification_level
    }

    VOLUNTEER_DEPLOYMENTS {
        uuid id PK
        uuid volunteer_id FK
        uuid disaster_event_id FK
        uuid administrative_unit_id FK
        string assignment_role
        date start_date
        date end_date
    }

    WORKFLOW_INSTANCES {
        uuid id PK
        string entity_type
        uuid entity_id
        string current_state
    }

    WORKFLOW_TRANSITIONS {
        uuid id PK
        uuid workflow_instance_id FK
        string from_state
        string to_state
        uuid action_by_user_id FK
        text remarks
        timestamptz transitioned_at
    }

    NOTIFICATION_TEMPLATES {
        uuid id PK
        string template_code UK
        string channel
        text body_template_bn
        text body_template_en
    }

    NOTIFICATION_DISPATCHES {
        uuid id PK
        uuid template_id FK
        uuid recipient_user_id FK
        string destination_address
        string delivery_status
        timestamptz dispatched_at
    }

    DOCUMENTS {
        uuid id PK
        string entity_type
        uuid entity_id
        string original_filename
        string file_hash_sha256
        string mime_type
        bigint file_size_bytes
        string storage_path
        string scan_status
    }

    AUDIT_LOGS {
        uuid id PK
        uuid user_id FK
        string action_name
        string auditable_type
        uuid auditable_id
        jsonb old_values
        jsonb new_values
        string ip_address
        timestamptz created_at
    }
```

---

## 2. Relational Cardinality & Cascade Integrity Rules

The table below defines relational integrity constraints, foreign key cascades, and operational behaviors:

| Parent Table | Child Table | Foreign Key Column | Delete Cascade Policy | Business Justification |
| :--- | :--- | :--- | :--- | :--- |
| `core.administrative_units` | `core.administrative_units` | `parent_id` | `RESTRICT` | Prevent orphaned administrative regions |
| `core.administrative_units` | `core.users` | `administrative_unit_id` | `RESTRICT` | Prohibit deleting jurisdictions with active officers |
| `core.administrative_units` | `beneficiary.households` | `administrative_unit_id` | `RESTRICT` | Citizen registry records must preserve historical jurisdiction |
| `beneficiary.households` | `beneficiary.household_members` | `household_id` | `CASCADE` | Deleting a household deletes its dependent members |
| `core.administrative_units` | `beneficiary.nid_verifications` | `administrative_unit_id` | `SET NULL` | Retains verification audit record while enforcing jurisdictional scoping |
| `beneficiary.nid_verifications` | `beneficiary.nid_verification_attempts` | `nid_verification_id` | `CASCADE` | Provider attempt logs belong to parent verification transaction |
| `disaster.disaster_events` | `disaster.shelter_occupancies` | `disaster_event_id` | `RESTRICT` | Historical disaster shelter usage cannot be dropped |
| `disaster.shelters` | `disaster.shelter_occupancies` | `shelter_id` | `RESTRICT` | Preserves shelter occupancy audit history |
| `disaster.disaster_events` | `damage.damage_assessments` | `disaster_event_id` | `RESTRICT` | D-Form damage assessments are legally binding government records |
| `damage.damage_assessments`| `damage.damage_sector_items` | `damage_assessment_id` | `CASCADE` | Sector breakdown items belong entirely to parent assessment |
| `logistics.warehouses` | `logistics.warehouse_inventories`| `warehouse_id` | `RESTRICT` | Warehouse cannot be deleted while inventory balances exist |
| `logistics.commodities` | `logistics.warehouse_inventories`| `commodity_id` | `RESTRICT` | Commodity definition locked if referenced in stock |
| `logistics.warehouse_inventories`| `logistics.stock_transactions`| `warehouse_inventory_id`| `RESTRICT` | Financial/commodity stock ledgers must be immutable |
| `logistics.relief_requisitions`| `logistics.relief_requisition_items`| `relief_requisition_id`| `CASCADE` | Deleting draft requisition removes line items |
| `logistics.relief_requisitions`| `logistics.relief_allocations` | `relief_requisition_id` | `RESTRICT` | Approved allocations cannot lose parent requisition reference |
| `logistics.relief_allocations` | `logistics.relief_allocation_items` | `relief_allocation_id` | `CASCADE` | Allocation line items belong to parent sanction order |
| `logistics.relief_allocation_items`| `distribution.distribution_vouchers`| `relief_allocation_item_id`| `RESTRICT` | Distribution vouchers are official receipts; must preserve link |
| `beneficiary.households` | `distribution.distribution_vouchers`| `household_id` | `RESTRICT` | Retains lifelong aid delivery records for citizen |
| `project.projects` | `project.project_milestones` | `project_id` | `CASCADE` | Milestones cascade with project definition |
| `project.project_milestones` | `project.project_disbursements` | `project_milestone_id`| `RESTRICT` | Prohibits deleting milestone once money has been disbursed |
| `volunteer.volunteers` | `volunteer.volunteer_skill_assignments`| `volunteer_id` | `CASCADE` | Volunteer skill associations cascade with volunteer |
| `workflow.workflow_instances`| `workflow.workflow_transitions`| `workflow_instance_id`| `RESTRICT` | Workflow historical transitions are permanent audit records |
| `core.users` | `audit.audit_logs` | `user_id` | `SET NULL` | Preserves audit record if user account is deactivated/purged |

---

## 3. Sub-Domain Architectural ERD Views

### 3.1 Citizen Identity & Beneficiary Verification Sub-Domain
```mermaid
erDiagram
    HOUSEHOLDS ||--|{ HOUSEHOLD_MEMBERS : "contains"
    HOUSEHOLDS ||--o{ NID_VERIFICATIONS : "authenticates"
    HOUSEHOLD_MEMBERS ||--o{ NID_VERIFICATIONS : "authenticates"
    NID_VERIFICATIONS ||--|{ NID_VERIFICATION_ATTEMPTS : "records_attempts"
    HOUSEHOLDS ||--o{ DISTRIBUTION_VOUCHERS : "receives"

    HOUSEHOLDS {
        uuid id PK
        string household_code UK
        string hoh_name_en
        string hoh_nid_hash UK
        string hoh_nid_encrypted
        decimal vulnerability_score
        geometry_point dwelling_geom
    }

    HOUSEHOLD_MEMBERS {
        uuid id PK
        uuid household_id FK
        string full_name_en
        string nid_hash
        string gender
        int age
        boolean has_disability
    }

    NID_VERIFICATIONS {
        uuid id PK
        uuid administrative_unit_id FK
        string nid_hash
        string nid_masked
        string verification_status
        string verification_reference
        string verification_token
        string provider_code
        boolean is_mock
        int attempt_count
        boolean manual_override
        timestamptz verified_at
    }

    NID_VERIFICATION_ATTEMPTS {
        uuid id PK
        uuid nid_verification_id FK
        int attempt_number
        string provider_code
        string status
        int http_status
        int response_time_ms
        string error_code
        timestamptz attempted_at
    }

    DISTRIBUTION_VOUCHERS {
        uuid id PK
        uuid household_id FK
        decimal quantity_disbursed
        string verification_method
    }
```

### 3.2 Logistics, Warehouse & Relief Chain Sub-Domain
```mermaid
erDiagram
    COMMODITIES ||--o{ WAREHOUSE_INVENTORIES : "categorizes"
    WAREHOUSES ||--|{ WAREHOUSE_INVENTORIES : "holds"
    WAREHOUSE_INVENTORIES ||--|{ STOCK_TRANSACTIONS : "logs"

    RELIEF_REQUISITIONS ||--|{ RELIEF_REQUISITION_ITEMS : "requests"
    RELIEF_REQUISITIONS ||--o{ RELIEF_ALLOCATIONS : "approved_as"
    RELIEF_ALLOCATIONS ||--|{ RELIEF_ALLOCATION_ITEMS : "allocates"
    RELIEF_ALLOCATION_ITEMS ||--o{ DISTRIBUTION_VOUCHERS : "distributes"

    COMMODITIES {
        uuid id PK
        string commodity_code UK
        string name_en
        string unit_of_measure
    }

    WAREHOUSES {
        uuid id PK
        string warehouse_code UK
        string warehouse_name
        decimal capacity_mt
    }

    WAREHOUSE_INVENTORIES {
        uuid id PK
        uuid warehouse_id FK
        uuid commodity_id FK
        string batch_number
        decimal current_quantity
        date expiry_date
    }

    STOCK_TRANSACTIONS {
        uuid id PK
        uuid warehouse_inventory_id FK
        string transaction_type
        decimal quantity
        decimal balance_after
    }

    RELIEF_ALLOCATION_ITEMS {
        uuid id PK
        uuid relief_allocation_id FK
        uuid commodity_id FK
        decimal quantity_allocated
    }

    DISTRIBUTION_VOUCHERS {
        uuid id PK
        uuid relief_allocation_item_id FK
        uuid household_id FK
        decimal quantity_disbursed
    }
```
