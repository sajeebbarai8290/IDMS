<?php

namespace Database\Seeders;

use App\Core\Models\AdministrativeUnit;
use App\Modules\UserManagement\Models\Organization;
use App\Modules\UserManagement\Models\Permission;
use App\Modules\UserManagement\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserManagementSeeder extends Seeder
{
    public function run(): void
    {
        $rolesData = [
            ['code' => 'ROLE_SUPER_ADMIN', 'name_en' => 'Super Administrator', 'name_bn' => 'সুপার অ্যাডমিনিস্ট্রেটর', 'tier_level' => 'NATIONAL', 'hierarchy_rank' => 1, 'mfa_required' => true, 'description' => 'MoDMR Lead ICT & Security Administrator (operational blackout enforced).'],
            ['code' => 'ROLE_MINISTRY_EXECUTIVE', 'name_en' => 'Ministry Administrator / Executive', 'name_bn' => 'মন্ত্রণালয় প্রশাসক / নির্বাহী', 'tier_level' => 'NATIONAL', 'hierarchy_rank' => 1, 'mfa_required' => true, 'description' => 'Minister, Senior Secretary, Additional Secretary & Ministry Admin.'],
            ['code' => 'ROLE_NEOC_DIRECTOR', 'name_en' => 'NEOC Director', 'name_bn' => 'এনইওসি পরিচালক', 'tier_level' => 'NATIONAL', 'hierarchy_rank' => 1, 'mfa_required' => true, 'description' => 'Director, National Emergency Operations Centre.'],
            ['code' => 'ROLE_NATIONAL_AUDITOR', 'name_en' => 'National Auditor', 'name_bn' => 'জাতীয় নিরীক্ষক', 'tier_level' => 'NATIONAL', 'hierarchy_rank' => 1, 'mfa_required' => true, 'description' => 'Comptroller & Auditor General / MoDMR Audit Cell (read-only national visibility).'],
            ['code' => 'ROLE_DDM_DIRECTOR_GENERAL', 'name_en' => 'Directorate Administrator (DDM DG)', 'name_bn' => 'মহাপরিচালক / অধিদপ্তর প্রশাসক (ডিডিএম)', 'tier_level' => 'DIRECTORATE', 'hierarchy_rank' => 2, 'mfa_required' => true, 'description' => 'Director General, Department of Disaster Management.'],
            ['code' => 'ROLE_DDM_RELIEF_DIRECTOR', 'name_en' => 'Director (Relief & Logistics, DDM)', 'name_bn' => 'পরিচালক (ত্রাণ ও লজিস্টিকস, ডিডিএম)', 'tier_level' => 'DIRECTORATE', 'hierarchy_rank' => 2, 'mfa_required' => true, 'description' => 'Directs Central Supply Depots and national relief reserves.'],
            ['code' => 'ROLE_DDM_PLANNING_DIRECTOR', 'name_en' => 'Director (Planning & M&E, DDM)', 'name_bn' => 'পরিচালক (পরিকল্পনা ও পরিবীক্ষণ, ডিডিএম)', 'tier_level' => 'DIRECTORATE', 'hierarchy_rank' => 2, 'mfa_required' => true, 'description' => 'Oversees rehabilitation schemes, TR/KABITA, and damage consolidation.'],
            ['code' => 'ROLE_DDM_PLANNING_OFFICER', 'name_en' => 'Planning Officer (DDM)', 'name_bn' => 'পরিকল্পনা কর্মকর্তা (ডিডিএম)', 'tier_level' => 'DIRECTORATE', 'hierarchy_rank' => 2, 'mfa_required' => true, 'description' => 'Reviews planning proposals and project submissions at DDM.'],
            ['code' => 'ROLE_DIVISIONAL_COMMISSIONER', 'name_en' => 'Division Administrator (Divisional Commissioner)', 'name_bn' => 'বিভাগীয় প্রশাসক (বিভাগীয় কমিশনার)', 'tier_level' => 'DIVISION', 'hierarchy_rank' => 3, 'mfa_required' => true, 'description' => 'Regional oversight over constituent districts in a division.'],
            ['code' => 'ROLE_DEPUTY_COMMISSIONER', 'name_en' => 'District Administrator (Deputy Commissioner)', 'name_bn' => 'জেলা প্রশাসক (ডিসি)', 'tier_level' => 'DISTRICT', 'hierarchy_rank' => 4, 'mfa_required' => true, 'description' => 'Chair of DDMC; sanctions district emergency buffers and D-Forms.'],
            ['code' => 'ROLE_DRRO', 'name_en' => 'District Relief & Rehabilitation Officer (DRRO)', 'name_bn' => 'জেলা ত্রাণ ও পুনর্বাসন কর্মকর্তা (ডিআরআরও)', 'tier_level' => 'DISTRICT', 'hierarchy_rank' => 4, 'mfa_required' => true, 'description' => 'Operational custodian of district disaster response and local godowns.'],
            ['code' => 'ROLE_UNO', 'name_en' => 'Upazila Administrator (UNO)', 'name_bn' => 'উপজেলা প্রশাসক (ইউএনও)', 'tier_level' => 'UPAZILA', 'hierarchy_rank' => 5, 'mfa_required' => true, 'description' => 'Upazila Nirbahi Officer; chairs UzDMC and approves upazila allocations.'],
            ['code' => 'ROLE_PIO', 'name_en' => 'Project Implementation Officer (PIO)', 'name_bn' => 'প্রকল্প বাস্তবায়ন কর্মকর্তা (পিআইও)', 'tier_level' => 'UPAZILA', 'hierarchy_rank' => 5, 'mfa_required' => true, 'description' => 'Primary operational MoDMR officer at Upazila level.'],
            ['code' => 'ROLE_UP_CHAIRMAN', 'name_en' => 'Union-level Officer (UP Chairman / Mayor)', 'name_bn' => 'ইউনিয়ন পর্যায়ের কর্মকর্তা (চেয়ারম্যান / মেয়র)', 'tier_level' => 'UNION', 'hierarchy_rank' => 6, 'mfa_required' => false, 'description' => 'Chair of UDMC; submits grassroots requisitions and endorses lists.'],
            ['code' => 'ROLE_UP_SECRETARY', 'name_en' => 'Union Parishad Secretary', 'name_bn' => 'ইউনিয়ন পরিষদ সচিব', 'tier_level' => 'UNION', 'hierarchy_rank' => 6, 'mfa_required' => false, 'description' => 'Administrative officer of Union Parishad for enrollments and muster rolls.'],
            ['code' => 'ROLE_FIELD_ENUMERATOR', 'name_en' => 'Field Officer / Enumerator', 'name_bn' => 'মাঠ পর্যায়ের কর্মকর্তা / গণনাকারী', 'tier_level' => 'FIELD', 'hierarchy_rank' => 7, 'mfa_required' => false, 'description' => 'On-ground D-Form surveys, household registration, and GPS tagging.'],
            ['code' => 'ROLE_DATA_ENTRY_OPERATOR', 'name_en' => 'Data Entry Operator', 'name_bn' => 'ডাটা এন্ট্রি অপারেটর', 'tier_level' => 'FIELD', 'hierarchy_rank' => 7, 'mfa_required' => false, 'description' => 'Structured data entry across beneficiary, damage, and relief drafts.'],
            ['code' => 'ROLE_TAG_OFFICER', 'name_en' => 'Tag / Verification Officer', 'name_bn' => 'ট্যাগ ও যাচাই কর্মকর্তা', 'tier_level' => 'FIELD', 'hierarchy_rank' => 7, 'mfa_required' => false, 'description' => 'Independent government witness verifying relief handovers and milestones.'],
            ['code' => 'ROLE_WAREHOUSE_OFFICER', 'name_en' => 'Warehouse / Godown Officer', 'name_bn' => 'গুদাম কর্মকর্তা', 'tier_level' => 'FIELD', 'hierarchy_rank' => 7, 'mfa_required' => false, 'description' => 'Godown stock receipt, batch accounting, and dispatch against issue orders.'],
            ['code' => 'ROLE_SHELTER_MANAGER', 'name_en' => 'Shelter Manager', 'name_bn' => 'আশ্রয়কেন্দ্র ব্যবস্থাপক', 'tier_level' => 'FIELD', 'hierarchy_rank' => 7, 'mfa_required' => false, 'description' => 'Cyclone/Flood shelter occupancy tracking and emergency relief inside shelter.'],
            ['code' => 'ROLE_CPP_LEADER', 'name_en' => 'CPP Unit Leader', 'name_bn' => 'সিপিপি ইউনিট লিডার', 'tier_level' => 'FIELD', 'hierarchy_rank' => 7, 'mfa_required' => false, 'description' => 'Cyclone Preparedness Programme volunteer coordination and early warning.'],
            ['code' => 'ROLE_VOLUNTEER_RESPONDER', 'name_en' => 'Volunteer Responder', 'name_bn' => 'স্বেচ্ছাসেবক সাড়াদানকারী', 'tier_level' => 'FIELD', 'hierarchy_rank' => 7, 'mfa_required' => false, 'description' => 'Registered Red Crescent / community first responder.'],
            ['code' => 'ROLE_EXTERNAL_PARTNER', 'name_en' => 'External Humanitarian Partner', 'name_bn' => 'বহিঃস্থ মানবিক অংশীদার', 'tier_level' => 'NATIONAL', 'hierarchy_rank' => 7, 'mfa_required' => false, 'description' => 'UN / INGO observer restricted strictly to anonymized/aggregated data.'],
        ];

        $rolesByCode = [];
        foreach ($rolesData as $r) {
            $role = Role::withTrashed()->updateOrCreate(
                ['code' => $r['code']],
                [
                    'name_en' => $r['name_en'],
                    'name_bn' => $r['name_bn'],
                    'tier_level' => $r['tier_level'],
                    'hierarchy_rank' => $r['hierarchy_rank'],
                    'description' => $r['description'],
                    'is_system' => true,
                    'is_active' => true,
                    'mfa_required' => $r['mfa_required'],
                ]
            );
            if ($role->trashed()) {
                $role->restore();
            }
            $rolesByCode[$role->code] = $role;
        }

        $permissionsCatalog = $this->permissionCatalog();
        $permissionsByCode = [];
        foreach ($permissionsCatalog as $p) {
            $perm = Permission::updateOrCreate(
                ['code' => $p['code']],
                [
                    'module_code' => $p['module_code'],
                    'name_en' => $p['name_en'],
                    'name_bn' => $p['name_bn'],
                    'action_type' => $p['action_type'],
                    'is_sensitive' => $p['is_sensitive'] ?? false,
                    'description' => $p['description'] ?? null,
                    'created_at' => now(),
                ]
            );
            $permissionsByCode[$perm->code] = $perm;
        }

        $this->seedRolePermissionMappings($rolesByCode, $permissionsByCode);
        $this->seedOrganizations();
    }

    protected function permissionCatalog(): array
    {
        return [
            // M01 Beneficiary
            ['code' => 'beneficiary.household.view', 'module_code' => 'beneficiary', 'name_en' => 'View Beneficiary Households', 'name_bn' => 'উপকারভোগী পরিবার দেখুন', 'action_type' => 'VIEW'],
            ['code' => 'beneficiary.household.create', 'module_code' => 'beneficiary', 'name_en' => 'Create Beneficiary Households', 'name_bn' => 'উপকারভোগী পরিবার নিবন্ধন', 'action_type' => 'CREATE'],
            ['code' => 'beneficiary.household.update', 'module_code' => 'beneficiary', 'name_en' => 'Update Beneficiary Households', 'name_bn' => 'উপকারভোগী তথ্য হালনাগাদ', 'action_type' => 'UPDATE'],
            ['code' => 'beneficiary.household.delete', 'module_code' => 'beneficiary', 'name_en' => 'Archive Beneficiary Households', 'name_bn' => 'উপকারভোগী আর্কাইভ', 'action_type' => 'DELETE'],
            ['code' => 'beneficiary.household.export', 'module_code' => 'beneficiary', 'name_en' => 'Export Beneficiary Registry', 'name_bn' => 'উপকারভোগী তালিকা এক্সপোর্ট', 'action_type' => 'EXPORT'],
            ['code' => 'beneficiary.pii.decrypt', 'module_code' => 'beneficiary', 'name_en' => 'Decrypt Beneficiary PII', 'name_bn' => 'উপকারভোগীর ব্যক্তিগত তথ্য দেখুন', 'action_type' => 'DECRYPT_PII', 'is_sensitive' => true],
            ['code' => 'beneficiary.dedup.override', 'module_code' => 'beneficiary', 'name_en' => 'Override Beneficiary Duplicate Flag', 'name_bn' => 'ডুপ্লিকেট সতর্কতা ওভাররাইড', 'action_type' => 'VERIFY'],

            // M02 NID
            ['code' => 'nid.verification.initiate', 'module_code' => 'nid', 'name_en' => 'Initiate NID Verification', 'name_bn' => 'এনআইডি যাচাই শুরু করুন', 'action_type' => 'CREATE'],
            ['code' => 'nid.verification.view_status', 'module_code' => 'nid', 'name_en' => 'View NID Verification Status', 'name_bn' => 'এনআইডি যাচাই অবস্থা দেখুন', 'action_type' => 'VIEW'],
            ['code' => 'nid.verification.manual_review', 'module_code' => 'nid', 'name_en' => 'Manual Review NID Verification', 'name_bn' => 'এনআইডি ম্যানুয়াল পর্যালোচনা', 'action_type' => 'VERIFY', 'is_sensitive' => true],
            ['code' => 'nid.adapter.configure', 'module_code' => 'nid', 'name_en' => 'Configure NID Adapter', 'name_bn' => 'এনআইডি অ্যাডাপ্টার কনফিগার', 'action_type' => 'MANAGE'],

            // M03 Disaster
            ['code' => 'disaster.event.view', 'module_code' => 'disaster', 'name_en' => 'View Disaster Events', 'name_bn' => 'দুর্যোগ তথ্য দেখুন', 'action_type' => 'VIEW'],
            ['code' => 'disaster.event.create', 'module_code' => 'disaster', 'name_en' => 'Create Disaster Events', 'name_bn' => 'দুর্যোগ নিবন্ধন', 'action_type' => 'CREATE'],
            ['code' => 'disaster.event.update', 'module_code' => 'disaster', 'name_en' => 'Update Disaster Events', 'name_bn' => 'দুর্যোগ তথ্য হালনাগাদ', 'action_type' => 'UPDATE'],
            ['code' => 'disaster.event.verify', 'module_code' => 'disaster', 'name_en' => 'Verify & Transition Disaster Lifecycle', 'name_bn' => 'দুর্যোগ যাচাই ও ধাপ পরিবর্তন', 'action_type' => 'VERIFY'],
            ['code' => 'disaster.category.manage', 'module_code' => 'disaster', 'name_en' => 'Manage Disaster Categories', 'name_bn' => 'দুর্যোগ ক্যাটাগরি ব্যবস্থাপনা', 'action_type' => 'MANAGE'],
            ['code' => 'disaster.warning.broadcast', 'module_code' => 'disaster', 'name_en' => 'Broadcast Early Warnings', 'name_bn' => 'আগাম সতর্কবার্তা প্রচার', 'action_type' => 'CREATE'],
            ['code' => 'disaster.hazard_map.manage', 'module_code' => 'disaster', 'name_en' => 'Manage GIS Hazard Maps', 'name_bn' => 'জিআইএস ঝুঁকি মানচিত্র ব্যবস্থাপনা', 'action_type' => 'GIS'],
            ['code' => 'disaster.emergency.declare', 'module_code' => 'disaster', 'name_en' => 'Declare Disaster Emergency', 'name_bn' => 'জরুরি দুর্যোগ অবস্থা ঘোষণা', 'action_type' => 'APPROVE', 'is_sensitive' => true],

            // M04 Relief
            ['code' => 'relief.category.manage', 'module_code' => 'relief', 'name_en' => 'Manage Relief Categories', 'name_bn' => 'ত্রাণ ক্যাটাগরি ব্যবস্থাপনা', 'action_type' => 'MANAGE'],
            ['code' => 'relief.requisition.view', 'module_code' => 'relief', 'name_en' => 'View Relief Requisitions', 'name_bn' => 'ত্রাণ চাহিদাপত্র দেখুন', 'action_type' => 'VIEW'],
            ['code' => 'relief.requisition.create', 'module_code' => 'relief', 'name_en' => 'Create Relief Requisitions', 'name_bn' => 'ত্রাণ চাহিদাপত্র তৈরি', 'action_type' => 'CREATE'],
            ['code' => 'relief.requisition.update', 'module_code' => 'relief', 'name_en' => 'Update Relief Requisitions', 'name_bn' => 'ত্রাণ চাহিদাপত্র হালনাগাদ', 'action_type' => 'UPDATE'],
            ['code' => 'relief.requisition.delete', 'module_code' => 'relief', 'name_en' => 'Delete Relief Requisitions', 'name_bn' => 'ত্রাণ চাহিদাপত্র মুছুন', 'action_type' => 'DELETE'],
            ['code' => 'relief.requisition.approve', 'module_code' => 'relief', 'name_en' => 'Approve or Reject Relief Requisitions', 'name_bn' => 'ত্রাণ চাহিদাপত্র অনুমোদন/প্রত্যাখ্যান', 'action_type' => 'APPROVE'],
            ['code' => 'relief.allocation.view', 'module_code' => 'relief', 'name_en' => 'View Relief Allocations', 'name_bn' => 'ত্রাণ বরাদ্দ দেখুন', 'action_type' => 'VIEW'],
            ['code' => 'relief.allocation.create', 'module_code' => 'relief', 'name_en' => 'Create Relief Allocations', 'name_bn' => 'ত্রাণ বরাদ্দ তৈরি', 'action_type' => 'CREATE'],
            ['code' => 'relief.allocation.approve', 'module_code' => 'relief', 'name_en' => 'Approve Relief Allocations', 'name_bn' => 'ত্রাণ বরাদ্দ অনুমোদন', 'action_type' => 'APPROVE'],
            ['code' => 'relief.allocation.export', 'module_code' => 'relief', 'name_en' => 'Export Relief Allocations', 'name_bn' => 'ত্রাণ বরাদ্দ এক্সপোর্ট', 'action_type' => 'EXPORT'],
            ['code' => 'relief.distribution.record', 'module_code' => 'relief', 'name_en' => 'Record Relief Distribution', 'name_bn' => 'ত্রাণ বিতরণ নথিভুক্তি', 'action_type' => 'CREATE'],
            ['code' => 'relief.distribution.override_duplicate', 'module_code' => 'relief', 'name_en' => 'Override Distribution Duplicate Rule', 'name_bn' => 'বিতরণ ডুপ্লিকেট নিয়ম ওভাররাইড', 'action_type' => 'VERIFY'],
            ['code' => 'relief.distribution.verify', 'module_code' => 'relief', 'name_en' => 'Verify Relief Distribution', 'name_bn' => 'ত্রাণ বিতরণ যাচাই', 'action_type' => 'VERIFY'],
            ['code' => 'relief.distribution.export_muster', 'module_code' => 'relief', 'name_en' => 'Export Distribution Muster Roll', 'name_bn' => 'মাস্টার রোল এক্সপোর্ট', 'action_type' => 'EXPORT'],

            // M05 Response
            ['code' => 'response.activity.manage', 'module_code' => 'response', 'name_en' => 'Manage Response Activity Types', 'name_bn' => 'সাড়াদান কার্যক্রম ধরন ব্যবস্থাপনা', 'action_type' => 'MANAGE'],
            ['code' => 'response.operation.view', 'module_code' => 'response', 'name_en' => 'View Response Operations', 'name_bn' => 'সাড়াদান অভিযান দেখুন', 'action_type' => 'VIEW'],
            ['code' => 'response.operation.create', 'module_code' => 'response', 'name_en' => 'Create Response Operations', 'name_bn' => 'সাড়াদান অভিযান তৈরি', 'action_type' => 'CREATE'],
            ['code' => 'response.operation.update', 'module_code' => 'response', 'name_en' => 'Update Response Operations', 'name_bn' => 'সাড়াদান অভিযান হালনাগাদ', 'action_type' => 'UPDATE'],
            ['code' => 'response.operation.manage_lifecycle', 'module_code' => 'response', 'name_en' => 'Manage Operation Lifecycle', 'name_bn' => 'অভিযান জীবনচক্র পরিচালনা', 'action_type' => 'APPROVE'],
            ['code' => 'response.task.manage', 'module_code' => 'response', 'name_en' => 'Manage Response Tasks', 'name_bn' => 'সাড়াদান কাজ পরিচালনা', 'action_type' => 'UPDATE'],
            ['code' => 'response.coordination.manage', 'module_code' => 'response', 'name_en' => 'Manage Inter-Agency Coordination', 'name_bn' => 'আন্তঃসংস্থা সমন্বয়', 'action_type' => 'MANAGE'],
            ['code' => 'response.report.create', 'module_code' => 'response', 'name_en' => 'Submit Response SitReps', 'name_bn' => 'সাড়াদান প্রতিবেদন দাখিল', 'action_type' => 'REPORT'],
            ['code' => 'response.gis.view_exact', 'module_code' => 'response', 'name_en' => 'View Exact Response GIS Coordinates', 'name_bn' => 'সঠিক সাড়াদান জিআইএস অবস্থান দেখুন', 'action_type' => 'GIS', 'is_sensitive' => true],

            // M06 Project
            ['code' => 'project.scheme.view', 'module_code' => 'project', 'name_en' => 'View Projects & Schemes', 'name_bn' => 'প্রকল্প ও স্কিম দেখুন', 'action_type' => 'VIEW'],
            ['code' => 'project.scheme.create', 'module_code' => 'project', 'name_en' => 'Create Projects & Schemes', 'name_bn' => 'নতুন প্রকল্প প্রস্তাবনা', 'action_type' => 'CREATE'],
            ['code' => 'project.scheme.update', 'module_code' => 'project', 'name_en' => 'Update Projects & Schemes', 'name_bn' => 'প্রকল্প তথ্য হালনাগাদ', 'action_type' => 'UPDATE'],
            ['code' => 'project.scheme.approve', 'module_code' => 'project', 'name_en' => 'Approve Projects & Schemes', 'name_bn' => 'প্রকল্প অনুমোদন', 'action_type' => 'APPROVE'],
            ['code' => 'project.milestone.update', 'module_code' => 'project', 'name_en' => 'Update Project Milestones', 'name_bn' => 'প্রকল্প মাইলস্টোন হালনাগাদ', 'action_type' => 'UPDATE'],
            ['code' => 'project.milestone.certify', 'module_code' => 'project', 'name_en' => 'Certify Project Milestones', 'name_bn' => 'প্রকল্প মাইলস্টোন প্রত্যয়ন', 'action_type' => 'VERIFY'],
            ['code' => 'project.disbursement.create', 'module_code' => 'project', 'name_en' => 'Create Project Disbursement', 'name_bn' => 'অর্থ ছাড় ভাউচার তৈরি', 'action_type' => 'CREATE'],
            ['code' => 'project.disbursement.approve', 'module_code' => 'project', 'name_en' => 'Approve Project Disbursement', 'name_bn' => 'অর্থ ছাড় অনুমোদন', 'action_type' => 'APPROVE'],
            ['code' => 'project.monitoring.create', 'module_code' => 'project', 'name_en' => 'Create Project Monitoring Reports', 'name_bn' => 'প্রকল্প পরিবীক্ষণ প্রতিবেদন', 'action_type' => 'REPORT'],
            ['code' => 'project.category.manage', 'module_code' => 'project', 'name_en' => 'Manage Project Categories', 'name_bn' => 'প্রকল্প ক্যাটাগরি ব্যবস্থাপনা', 'action_type' => 'MANAGE'],
            ['code' => 'project.scheme.export', 'module_code' => 'project', 'name_en' => 'Export Project Reports', 'name_bn' => 'প্রকল্প প্রতিবেদন এক্সপোর্ট', 'action_type' => 'EXPORT'],

            // M07 Resource
            ['code' => 'resource.category.manage', 'module_code' => 'resource', 'name_en' => 'Manage Resource Categories', 'name_bn' => 'সম্পদ ক্যাটাগরি ব্যবস্থাপনা', 'action_type' => 'MANAGE'],
            ['code' => 'resource.asset.view', 'module_code' => 'resource', 'name_en' => 'View Resource Assets', 'name_bn' => 'সম্পদ তালিকা দেখুন', 'action_type' => 'VIEW'],
            ['code' => 'resource.asset.create', 'module_code' => 'resource', 'name_en' => 'Register Resource Assets', 'name_bn' => 'নতুন সম্পদ নিবন্ধন', 'action_type' => 'CREATE'],
            ['code' => 'resource.asset.update', 'module_code' => 'resource', 'name_en' => 'Update Resource Assets', 'name_bn' => 'সম্পদ তথ্য হালনাগাদ', 'action_type' => 'UPDATE'],
            ['code' => 'resource.asset.deploy', 'module_code' => 'resource', 'name_en' => 'Deploy Resource Assets', 'name_bn' => 'সম্পদ মোতায়েন', 'action_type' => 'APPROVE'],
            ['code' => 'resource.asset.export', 'module_code' => 'resource', 'name_en' => 'Export Resource Register', 'name_bn' => 'সম্পদ তালিকা এক্সপোর্ট', 'action_type' => 'EXPORT'],

            // M08 Warehouse
            ['code' => 'warehouse.facility.view', 'module_code' => 'warehouse', 'name_en' => 'View Warehouse Facilities', 'name_bn' => 'গুদাম স্থাপনা দেখুন', 'action_type' => 'VIEW'],
            ['code' => 'warehouse.facility.manage', 'module_code' => 'warehouse', 'name_en' => 'Manage Warehouse Facilities', 'name_bn' => 'গুদাম স্থাপনা ব্যবস্থাপনা', 'action_type' => 'MANAGE'],
            ['code' => 'warehouse.stock.view', 'module_code' => 'warehouse', 'name_en' => 'View Warehouse Stock', 'name_bn' => 'গুদাম মজুদ দেখুন', 'action_type' => 'VIEW'],
            ['code' => 'warehouse.stock.receive', 'module_code' => 'warehouse', 'name_en' => 'Receive Warehouse Stock (GRN)', 'name_bn' => 'মালামাল গ্রহণ (GRN)', 'action_type' => 'CREATE'],
            ['code' => 'warehouse.stock.transfer', 'module_code' => 'warehouse', 'name_en' => 'Transfer Warehouse Stock', 'name_bn' => 'আন্তঃগুদাম স্থানান্তর', 'action_type' => 'UPDATE'],
            ['code' => 'warehouse.stock.issue', 'module_code' => 'warehouse', 'name_en' => 'Issue Warehouse Stock', 'name_bn' => 'ত্রাণ বরাদ্দ স্টক ইস্যু', 'action_type' => 'APPROVE'],
            ['code' => 'warehouse.stock.writeoff', 'module_code' => 'warehouse', 'name_en' => 'Write-off Damaged Stock', 'name_bn' => 'নষ্ট মালামাল অবলোপন', 'action_type' => 'APPROVE'],
            ['code' => 'warehouse.stock.export_ledger', 'module_code' => 'warehouse', 'name_en' => 'Export Stock Ledger', 'name_bn' => 'মজুদ লেজার এক্সপোর্ট', 'action_type' => 'EXPORT'],

            // M09 Damage
            ['code' => 'damage.assessment.view', 'module_code' => 'damage', 'name_en' => 'View Damage Assessments', 'name_bn' => 'ক্ষয়ক্ষতি নিরূপণ দেখুন', 'action_type' => 'VIEW'],
            ['code' => 'damage.assessment.create', 'module_code' => 'damage', 'name_en' => 'Create Damage Assessments', 'name_bn' => 'ডি-ফর্ম তৈরি', 'action_type' => 'CREATE'],
            ['code' => 'damage.assessment.update', 'module_code' => 'damage', 'name_en' => 'Update Damage Assessments', 'name_bn' => 'ডি-ফর্ম হালনাগাদ', 'action_type' => 'UPDATE'],
            ['code' => 'damage.assessment.delete', 'module_code' => 'damage', 'name_en' => 'Delete Damage Assessments', 'name_bn' => 'ডি-ফর্ম মুছুন', 'action_type' => 'DELETE'],
            ['code' => 'damage.assessment.submit', 'module_code' => 'damage', 'name_en' => 'Submit Damage Assessments', 'name_bn' => 'ডি-ফর্ম দাখিল', 'action_type' => 'UPDATE'],
            ['code' => 'damage.assessment.verify', 'module_code' => 'damage', 'name_en' => 'Verify Damage Assessments', 'name_bn' => 'ডি-ফর্ম যাচাই', 'action_type' => 'VERIFY'],
            ['code' => 'damage.assessment.approve', 'module_code' => 'damage', 'name_en' => 'Approve Damage Assessments', 'name_bn' => 'ডি-ফর্ম অনুমোদন', 'action_type' => 'APPROVE'],
            ['code' => 'damage.assessment.export', 'module_code' => 'damage', 'name_en' => 'Export Damage Assessments', 'name_bn' => 'ডি-ফর্ম এক্সপোর্ট', 'action_type' => 'EXPORT'],

            // M10 Volunteer
            ['code' => 'volunteer.roster.view', 'module_code' => 'volunteer', 'name_en' => 'View Volunteer Roster', 'name_bn' => 'স্বেচ্ছাসেবক রোস্টার দেখুন', 'action_type' => 'VIEW'],
            ['code' => 'volunteer.roster.register', 'module_code' => 'volunteer', 'name_en' => 'Register Volunteers', 'name_bn' => 'স্বেচ্ছাসেবক নিবন্ধন', 'action_type' => 'CREATE'],
            ['code' => 'volunteer.roster.update', 'module_code' => 'volunteer', 'name_en' => 'Update Volunteers', 'name_bn' => 'স্বেচ্ছাসেবক তথ্য হালনাগাদ', 'action_type' => 'UPDATE'],
            ['code' => 'volunteer.roster.verify', 'module_code' => 'volunteer', 'name_en' => 'Verify Volunteers', 'name_bn' => 'স্বেচ্ছাসেবক যাচাই', 'action_type' => 'VERIFY'],
            ['code' => 'volunteer.pii.decrypt', 'module_code' => 'volunteer', 'name_en' => 'Decrypt Volunteer PII', 'name_bn' => 'স্বেচ্ছাসেবকের ব্যক্তিগত তথ্য দেখুন', 'action_type' => 'DECRYPT_PII', 'is_sensitive' => true],
            ['code' => 'volunteer.category.manage', 'module_code' => 'volunteer', 'name_en' => 'Manage Volunteer Categories', 'name_bn' => 'স্বেচ্ছাসেবক ক্যাটাগরি ব্যবস্থাপনা', 'action_type' => 'MANAGE'],
            ['code' => 'volunteer.skill.manage', 'module_code' => 'volunteer', 'name_en' => 'Manage Volunteer Skills', 'name_bn' => 'দক্ষতা ক্যাটালগ ব্যবস্থাপনা', 'action_type' => 'MANAGE'],
            ['code' => 'volunteer.organization.manage', 'module_code' => 'volunteer', 'name_en' => 'Manage Volunteer Organizations', 'name_bn' => 'স্বেচ্ছাসেবক সংগঠন ব্যবস্থাপনা', 'action_type' => 'MANAGE'],
            ['code' => 'volunteer.organization.verify', 'module_code' => 'volunteer', 'name_en' => 'Verify Volunteer Organizations', 'name_bn' => 'স্বেচ্ছাসেবক সংগঠন যাচাই', 'action_type' => 'VERIFY'],
            ['code' => 'volunteer.deployment.dispatch', 'module_code' => 'volunteer', 'name_en' => 'Dispatch Volunteer Deployments', 'name_bn' => 'স্বেচ্ছাসেবক মোতায়েন', 'action_type' => 'APPROVE'],
            ['code' => 'volunteer.attendance.record', 'module_code' => 'volunteer', 'name_en' => 'Record Volunteer Attendance', 'name_bn' => 'স্বেচ্ছাসেবক উপস্থিতি নথিভুক্তি', 'action_type' => 'CREATE'],
            ['code' => 'volunteer.attendance.confirm', 'module_code' => 'volunteer', 'name_en' => 'Confirm Volunteer Attendance', 'name_bn' => 'স্বেচ্ছাসেবক উপস্থিতি নিশ্চিতকরণ', 'action_type' => 'VERIFY'],
            ['code' => 'volunteer.training.manage', 'module_code' => 'volunteer', 'name_en' => 'Manage Volunteer Training', 'name_bn' => 'স্বেচ্ছাসেবক প্রশিক্ষণ ব্যবস্থাপনা', 'action_type' => 'MANAGE'],
            ['code' => 'volunteer.location.view_exact', 'module_code' => 'volunteer', 'name_en' => 'View Exact Volunteer Coordinates', 'name_bn' => 'স্বেচ্ছাসেবকের সঠিক অবস্থান দেখুন', 'action_type' => 'GIS', 'is_sensitive' => true],
            ['code' => 'volunteer.roster.export', 'module_code' => 'volunteer', 'name_en' => 'Export Volunteer Roster', 'name_bn' => 'স্বেচ্ছাসেবক রোস্টার এক্সপোর্ট', 'action_type' => 'EXPORT'],

            // M11 Workflow
            ['code' => 'workflow.instance.view', 'module_code' => 'workflow', 'name_en' => 'View Workflow Instances', 'name_bn' => 'কর্মপ্রবাহ দেখুন', 'action_type' => 'VIEW'],
            ['code' => 'workflow.transition.execute', 'module_code' => 'workflow', 'name_en' => 'Execute Workflow Transitions', 'name_bn' => 'কর্মপ্রবাহ অনুমোদন/সম্পাদন', 'action_type' => 'APPROVE'],
            ['code' => 'workflow.configuration.manage', 'module_code' => 'workflow', 'name_en' => 'Manage Workflow Configuration', 'name_bn' => 'কর্মপ্রবাহ কনফিগারেশন', 'action_type' => 'MANAGE'],

            // M12 User & Access Management
            ['code' => 'user.account.view', 'module_code' => 'user_management', 'name_en' => 'View User Accounts', 'name_bn' => 'ব্যবহারকারী তালিকা ও বিবরণ দেখুন', 'action_type' => 'VIEW'],
            ['code' => 'user.account.create', 'module_code' => 'user_management', 'name_en' => 'Create & Provision User Accounts', 'name_bn' => 'নতুন ব্যবহারকারী তৈরি ও প্রভিশন', 'action_type' => 'CREATE'],
            ['code' => 'user.account.update', 'module_code' => 'user_management', 'name_en' => 'Update User Accounts', 'name_bn' => 'ব্যবহারকারীর তথ্য হালনাগাদ', 'action_type' => 'UPDATE'],
            ['code' => 'user.account.delete', 'module_code' => 'user_management', 'name_en' => 'Archive User Accounts', 'name_bn' => 'ব্যবহারকারী আর্কাইভ', 'action_type' => 'DELETE'],
            ['code' => 'user.account.deactivate', 'module_code' => 'user_management', 'name_en' => 'Suspend / Deactivate / Lock Accounts', 'name_bn' => 'অ্যাকাউন্ট স্থগিত / নিষ্ক্রিয় / লক', 'action_type' => 'APPROVE', 'is_sensitive' => true],
            ['code' => 'user.scope.assign', 'module_code' => 'user_management', 'name_en' => 'Assign Organizational & Geographic Scopes', 'name_bn' => 'সাংগঠনিক ও ভৌগোলিক অধিক্ষেত্র নির্ধারণ', 'action_type' => 'MANAGE'],
            ['code' => 'user.keycloak.sync', 'module_code' => 'user_management', 'name_en' => 'Synchronize Keycloak Identity Provider', 'name_bn' => 'কীক্লক আইডেন্টিটি সিঙ্ক', 'action_type' => 'VERIFY'],
            ['code' => 'user.activity.view', 'module_code' => 'user_management', 'name_en' => 'View User Activity & RBAC Logs', 'name_bn' => 'ব্যবহারকারীর কার্যক্রম লগ দেখুন', 'action_type' => 'VIEW', 'is_sensitive' => true],
            ['code' => 'role.assignment.manage', 'module_code' => 'user_management', 'name_en' => 'Assign Roles to Users', 'name_bn' => 'ব্যবহারকারীকে ভূমিকা অর্পণ', 'action_type' => 'MANAGE', 'is_sensitive' => true],
            ['code' => 'role.configuration.manage', 'module_code' => 'user_management', 'name_en' => 'Manage Roles & Permissions Matrix', 'name_bn' => 'ভূমিকা ও অনুমতি ম্যাট্রিক্স ব্যবস্থাপনা', 'action_type' => 'MANAGE', 'is_sensitive' => true],
            ['code' => 'role.permission.view', 'module_code' => 'user_management', 'name_en' => 'View Roles & Permissions', 'name_bn' => 'ভূমিকা ও অনুমতিসমূহ দেখুন', 'action_type' => 'VIEW'],
            ['code' => 'organization.view', 'module_code' => 'user_management', 'name_en' => 'View Organizations', 'name_bn' => 'সংগঠন তালিকা দেখুন', 'action_type' => 'VIEW'],
            ['code' => 'organization.manage', 'module_code' => 'user_management', 'name_en' => 'Manage Organizations', 'name_bn' => 'সংগঠন ব্যবস্থাপনা', 'action_type' => 'MANAGE'],

            // M13 Notifications
            ['code' => 'notification.template.manage', 'module_code' => 'notification', 'name_en' => 'Manage Notification Templates', 'name_bn' => 'বিজ্ঞপ্তি টেমপ্লেট ব্যবস্থাপনা', 'action_type' => 'MANAGE'],
            ['code' => 'notification.broadcast.send', 'module_code' => 'notification', 'name_en' => 'Send Emergency Broadcasts', 'name_bn' => 'জরুরি বিজ্ঞপ্তি প্রেরণ', 'action_type' => 'CREATE'],
            ['code' => 'notification.log.view', 'module_code' => 'notification', 'name_en' => 'View Notification Logs', 'name_bn' => 'বিজ্ঞপ্তি লগ দেখুন', 'action_type' => 'VIEW'],
            ['code' => 'notification.alert.manage', 'module_code' => 'notification', 'name_en' => 'Manage Alert Rules & Escalations', 'name_bn' => 'সতর্কতা নিয়ম ও এসকেলেশন ব্যবস্থাপনা', 'action_type' => 'MANAGE'],
            ['code' => 'notification.alert.acknowledge', 'module_code' => 'notification', 'name_en' => 'Acknowledge & Resolve Alerts', 'name_bn' => 'সতর্কতা গ্রহণ ও সমাধান', 'action_type' => 'VERIFY'],
            ['code' => 'notification.preference.manage', 'module_code' => 'notification', 'name_en' => 'Manage Policy-Locked Notification Preferences', 'name_bn' => 'নীতি-লককৃত বিজ্ঞপ্তি পছন্দ ব্যবস্থাপনা', 'action_type' => 'MANAGE'],

            // M14 Documents
            ['code' => 'document.file.view', 'module_code' => 'document', 'name_en' => 'View Documents', 'name_bn' => 'নথিপত্র দেখুন', 'action_type' => 'VIEW'],
            ['code' => 'document.file.upload', 'module_code' => 'document', 'name_en' => 'Upload Documents', 'name_bn' => 'নথিপত্র আপলোড', 'action_type' => 'CREATE'],
            ['code' => 'document.file.approve', 'module_code' => 'document', 'name_en' => 'Approve or Reject Documents', 'name_bn' => 'নথিপত্র অনুমোদন/প্রত্যাখ্যান', 'action_type' => 'APPROVE'],
            ['code' => 'document.file.delete', 'module_code' => 'document', 'name_en' => 'Delete Documents', 'name_bn' => 'নথিপত্র মুছুন', 'action_type' => 'DELETE'],
            ['code' => 'document.file.verify_integrity', 'module_code' => 'document', 'name_en' => 'Verify Document Integrity', 'name_bn' => 'নথির অখণ্ডতা যাচাই', 'action_type' => 'VERIFY'],
            ['code' => 'document.category.manage', 'module_code' => 'document', 'name_en' => 'Manage Document Categories', 'name_bn' => 'নথির ক্যাটাগরি ব্যবস্থাপনা', 'action_type' => 'MANAGE'],

            // M15 Reporting
            ['code' => 'report.sitrep.view', 'module_code' => 'report', 'name_en' => 'View Situation Reports', 'name_bn' => 'পরিস্থিতি প্রতিবেদন দেখুন', 'action_type' => 'REPORT'],
            ['code' => 'report.sitrep.generate', 'module_code' => 'report', 'name_en' => 'Generate Situation Reports', 'name_bn' => 'পরিস্থিতি প্রতিবেদন তৈরি', 'action_type' => 'REPORT'],
            ['code' => 'report.analytics.view', 'module_code' => 'report', 'name_en' => 'View Analytics Dashboards', 'name_bn' => 'বিশ্লেষণ ড্যাশবোর্ড দেখুন', 'action_type' => 'REPORT'],
            ['code' => 'report.analytics.export', 'module_code' => 'report', 'name_en' => 'Export Analytics Datasets', 'name_bn' => 'বিশ্লেষণ ডাটা এক্সপোর্ট', 'action_type' => 'EXPORT'],

            // M16 Audit
            ['code' => 'audit.log.view', 'module_code' => 'audit', 'name_en' => 'View Audit Trail Logs', 'name_bn' => 'অডিট ট্রেইল লগ দেখুন', 'action_type' => 'VIEW', 'is_sensitive' => true],
            ['code' => 'audit.pii_access.view', 'module_code' => 'audit', 'name_en' => 'View PII Decryption Audit Logs', 'name_bn' => 'ব্যক্তিগত তথ্য প্রবেশ লগ দেখুন', 'action_type' => 'VIEW', 'is_sensitive' => true],
            ['code' => 'audit.log.export', 'module_code' => 'audit', 'name_en' => 'Export Audit Trail Logs', 'name_bn' => 'অডিট লগ এক্সপোর্ট', 'action_type' => 'EXPORT', 'is_sensitive' => true],

            // M17 AI
            ['code' => 'ai.insight.view', 'module_code' => 'ai', 'name_en' => 'View AI Decision Insights', 'name_bn' => 'এআই সিদ্ধান্ত সহায়তা দেখুন', 'action_type' => 'VIEW'],
            ['code' => 'ai.report.generate', 'module_code' => 'ai', 'name_en' => 'Generate AI Draft Reports', 'name_bn' => 'এআই খসড়া প্রতিবেদন তৈরি', 'action_type' => 'REPORT'],
            ['code' => 'ai.model.configure', 'module_code' => 'ai', 'name_en' => 'Configure AI Parameters', 'name_bn' => 'এআই মডেল কনফিগারেশন', 'action_type' => 'MANAGE'],
        ];
    }

    protected function seedRolePermissionMappings(array $rolesByCode, array $permissionsByCode): void
    {
        $allCodes = array_keys($permissionsByCode);
        $viewCodes = array_values(array_filter($allCodes, fn (string $c) => str_ends_with($c, '.view') || str_ends_with($c, '.view_status')));

        // Operational blackout exclusions for ROLE_SUPER_ADMIN
        $superAdminExcluded = [
            'beneficiary.household.create', 'beneficiary.household.update', 'beneficiary.household.delete', 'beneficiary.pii.decrypt', 'beneficiary.dedup.override',
            'nid.verification.initiate',
            'disaster.emergency.declare',
            'relief.requisition.create', 'relief.requisition.update', 'relief.requisition.delete', 'relief.requisition.approve',
            'relief.allocation.create', 'relief.allocation.approve', 'relief.distribution.record', 'relief.distribution.override_duplicate', 'relief.distribution.verify',
            'response.operation.create', 'response.operation.update', 'response.operation.manage_lifecycle', 'response.task.manage', 'response.coordination.manage', 'response.report.create',
            'project.scheme.create', 'project.scheme.update', 'project.scheme.approve', 'project.milestone.update', 'project.milestone.certify', 'project.disbursement.create', 'project.disbursement.approve', 'project.monitoring.create',
            'resource.asset.deploy',
            'warehouse.stock.receive', 'warehouse.stock.transfer', 'warehouse.stock.issue', 'warehouse.stock.writeoff',
            'damage.assessment.create', 'damage.assessment.update', 'damage.assessment.delete', 'damage.assessment.submit', 'damage.assessment.verify', 'damage.assessment.approve',
            'volunteer.roster.register', 'volunteer.roster.update', 'volunteer.roster.verify', 'volunteer.pii.decrypt', 'volunteer.organization.verify', 'volunteer.deployment.dispatch', 'volunteer.attendance.record', 'volunteer.attendance.confirm',
            'workflow.transition.execute',
            'notification.broadcast.send',
            'notification.alert.acknowledge',
            'document.file.approve',
        ];

        $rolePermissions = [
            'ROLE_SUPER_ADMIN' => array_values(array_diff($allCodes, $superAdminExcluded)),
            'ROLE_MINISTRY_EXECUTIVE' => array_values(array_unique(array_merge(
                $viewCodes,
                [
                    'beneficiary.household.export',
                    'disaster.event.create', 'disaster.event.update', 'disaster.event.verify', 'disaster.category.manage', 'disaster.warning.broadcast', 'disaster.hazard_map.manage', 'disaster.emergency.declare',
                    'relief.category.manage', 'relief.requisition.approve', 'relief.allocation.create', 'relief.allocation.approve', 'relief.allocation.export', 'relief.distribution.export_muster',
                    'response.activity.manage', 'response.operation.create', 'response.operation.update', 'response.operation.manage_lifecycle', 'response.task.manage', 'response.coordination.manage', 'response.report.create', 'response.gis.view_exact',
                    'project.scheme.approve', 'project.disbursement.approve', 'project.scheme.export',
                    'resource.category.manage', 'resource.asset.deploy', 'resource.asset.export',
                    'warehouse.stock.export_ledger',
                    'damage.assessment.approve', 'damage.assessment.export',
                    'volunteer.roster.register', 'volunteer.roster.update', 'volunteer.roster.verify', 'volunteer.pii.decrypt', 'volunteer.category.manage', 'volunteer.skill.manage', 'volunteer.organization.manage', 'volunteer.organization.verify', 'volunteer.deployment.dispatch', 'volunteer.attendance.record', 'volunteer.attendance.confirm', 'volunteer.training.manage', 'volunteer.location.view_exact', 'volunteer.roster.export',
                    'workflow.transition.execute',
                    'user.account.view', 'user.activity.view', 'role.permission.view', 'organization.view',
                    'notification.broadcast.send', 'notification.log.view', 'notification.alert.manage', 'notification.alert.acknowledge',
                    'document.file.upload', 'document.file.approve', 'document.file.verify_integrity', 'document.category.manage',
                    'report.sitrep.view', 'report.sitrep.generate', 'report.analytics.view', 'report.analytics.export',
                    'audit.log.view', 'audit.pii_access.view', 'audit.log.export',
                    'ai.insight.view', 'ai.report.generate',
                ]
            ))),
            'ROLE_NATIONAL_AUDITOR' => array_values(array_unique(array_merge(
                $viewCodes,
                [
                    'beneficiary.household.export', 'beneficiary.pii.decrypt',
                    'relief.allocation.export', 'relief.distribution.export_muster',
                    'project.scheme.export', 'resource.asset.export', 'warehouse.stock.export_ledger',
                    'damage.assessment.export', 'volunteer.pii.decrypt', 'volunteer.location.view_exact', 'volunteer.roster.export',
                    'user.account.view', 'user.activity.view', 'role.permission.view', 'organization.view',
                    'document.file.verify_integrity',
                    'report.sitrep.view', 'report.analytics.view', 'report.analytics.export',
                    'audit.log.view', 'audit.pii_access.view', 'audit.log.export',
                    'ai.insight.view',
                ]
            ))),
            'ROLE_NEOC_DIRECTOR' => [
                'beneficiary.household.view', 'beneficiary.household.export', 'nid.verification.view_status',
                'disaster.event.view', 'disaster.event.create', 'disaster.event.update', 'disaster.event.verify', 'disaster.category.manage', 'disaster.warning.broadcast', 'disaster.hazard_map.manage',
                'relief.requisition.view', 'relief.requisition.approve', 'relief.allocation.view', 'relief.allocation.export', 'relief.distribution.export_muster',
                'response.activity.manage', 'response.operation.view', 'response.operation.create', 'response.operation.update', 'response.operation.manage_lifecycle', 'response.task.manage', 'response.coordination.manage', 'response.report.create', 'response.gis.view_exact',
                'project.scheme.view', 'project.scheme.export',
                'resource.category.manage', 'resource.asset.view', 'resource.asset.create', 'resource.asset.update', 'resource.asset.deploy', 'resource.asset.export',
                'warehouse.facility.view', 'warehouse.stock.view', 'warehouse.stock.export_ledger',
                'damage.assessment.view', 'damage.assessment.approve', 'damage.assessment.export',
                'volunteer.roster.view', 'volunteer.roster.register', 'volunteer.roster.update', 'volunteer.roster.verify', 'volunteer.pii.decrypt', 'volunteer.deployment.dispatch', 'volunteer.location.view_exact', 'volunteer.roster.export',
                'workflow.instance.view', 'workflow.transition.execute',
                'user.account.view', 'role.permission.view', 'organization.view',
                'notification.template.manage', 'notification.broadcast.send', 'notification.log.view', 'notification.alert.manage', 'notification.alert.acknowledge', 'notification.preference.manage',
                'document.file.view', 'document.file.upload', 'document.file.approve', 'document.file.verify_integrity', 'document.category.manage',
                'report.sitrep.view', 'report.sitrep.generate', 'report.analytics.view', 'report.analytics.export',
                'ai.insight.view', 'ai.report.generate',
            ],
            'ROLE_DDM_DIRECTOR_GENERAL' => [
                'beneficiary.household.view', 'beneficiary.household.export', 'nid.verification.view_status',
                'disaster.event.view', 'disaster.event.create', 'disaster.event.update', 'disaster.event.verify', 'disaster.category.manage', 'disaster.warning.broadcast', 'disaster.hazard_map.manage',
                'relief.category.manage', 'relief.requisition.view', 'relief.requisition.approve', 'relief.allocation.view', 'relief.allocation.create', 'relief.allocation.approve', 'relief.allocation.export', 'relief.distribution.export_muster',
                'response.activity.manage', 'response.operation.view', 'response.operation.create', 'response.operation.update', 'response.operation.manage_lifecycle', 'response.task.manage', 'response.coordination.manage', 'response.report.create', 'response.gis.view_exact',
                'project.scheme.view', 'project.scheme.approve', 'project.disbursement.approve', 'project.scheme.export',
                'resource.category.manage', 'resource.asset.view', 'resource.asset.create', 'resource.asset.update', 'resource.asset.deploy', 'resource.asset.export',
                'warehouse.facility.view', 'warehouse.facility.manage', 'warehouse.stock.view', 'warehouse.stock.transfer', 'warehouse.stock.writeoff', 'warehouse.stock.export_ledger',
                'damage.assessment.view', 'damage.assessment.approve', 'damage.assessment.export',
                'volunteer.roster.view', 'volunteer.roster.register', 'volunteer.roster.update', 'volunteer.roster.verify', 'volunteer.pii.decrypt', 'volunteer.deployment.dispatch', 'volunteer.roster.export',
                'workflow.instance.view', 'workflow.transition.execute',
                'user.account.view', 'user.activity.view', 'role.permission.view', 'organization.view',
                'notification.template.manage', 'notification.broadcast.send', 'notification.log.view', 'notification.alert.manage', 'notification.alert.acknowledge', 'notification.preference.manage',
                'document.file.view', 'document.file.upload', 'document.file.approve', 'document.file.verify_integrity', 'document.category.manage',
                'report.sitrep.view', 'report.sitrep.generate', 'report.analytics.view', 'report.analytics.export',
                'audit.log.view', 'audit.pii_access.view',
                'ai.insight.view', 'ai.report.generate',
            ],
            'ROLE_DDM_RELIEF_DIRECTOR' => [
                'beneficiary.household.view', 'beneficiary.household.export', 'nid.verification.view_status',
                'disaster.event.view', 'disaster.event.update',
                'relief.category.manage', 'relief.requisition.view', 'relief.requisition.approve', 'relief.allocation.view', 'relief.allocation.create', 'relief.allocation.approve', 'relief.allocation.export', 'relief.distribution.export_muster',
                'response.activity.manage', 'response.operation.view', 'response.operation.create', 'response.operation.update', 'response.operation.manage_lifecycle', 'response.task.manage', 'response.coordination.manage', 'response.report.create', 'response.gis.view_exact',
                'project.scheme.view', 'project.scheme.export',
                'resource.category.manage', 'resource.asset.view', 'resource.asset.create', 'resource.asset.update', 'resource.asset.deploy', 'resource.asset.export',
                'warehouse.facility.view', 'warehouse.facility.manage', 'warehouse.stock.view', 'warehouse.stock.transfer', 'warehouse.stock.writeoff', 'warehouse.stock.export_ledger',
                'damage.assessment.view', 'damage.assessment.export',
                'volunteer.roster.view', 'volunteer.roster.export',
                'workflow.instance.view', 'workflow.transition.execute',
                'user.account.view', 'role.permission.view', 'organization.view',
                'notification.log.view', 'notification.alert.acknowledge',
                'document.file.view', 'document.file.upload', 'document.file.approve', 'document.file.verify_integrity',
                'report.sitrep.view', 'report.sitrep.generate', 'report.analytics.view', 'report.analytics.export',
                'ai.insight.view', 'ai.report.generate',
            ],
            'ROLE_DDM_PLANNING_DIRECTOR' => [
                'beneficiary.household.view', 'beneficiary.household.export', 'nid.verification.view_status',
                'disaster.event.view', 'relief.requisition.view', 'relief.allocation.view', 'relief.allocation.export', 'relief.distribution.export_muster',
                'response.operation.view', 'response.operation.create',
                'project.scheme.view', 'project.scheme.create', 'project.scheme.update', 'project.scheme.approve', 'project.disbursement.approve', 'project.monitoring.create', 'project.category.manage', 'project.scheme.export',
                'resource.asset.view', 'resource.asset.export', 'warehouse.facility.view', 'warehouse.stock.view', 'warehouse.stock.export_ledger',
                'damage.assessment.view', 'damage.assessment.approve', 'damage.assessment.export',
                'volunteer.roster.view', 'volunteer.roster.export',
                'workflow.instance.view', 'workflow.transition.execute',
                'user.account.view', 'role.permission.view', 'organization.view',
                'notification.log.view', 'notification.alert.acknowledge', 'document.file.view', 'document.file.upload', 'document.file.approve', 'document.file.verify_integrity',
                'report.sitrep.view', 'report.sitrep.generate', 'report.analytics.view', 'report.analytics.export',
                'ai.insight.view', 'ai.report.generate',
            ],
            'ROLE_DIVISIONAL_COMMISSIONER' => [
                'beneficiary.household.view', 'beneficiary.household.export', 'nid.verification.view_status',
                'disaster.event.view', 'disaster.event.verify', 'disaster.warning.broadcast',
                'relief.requisition.view', 'relief.requisition.approve', 'relief.allocation.view', 'relief.allocation.export', 'relief.distribution.export_muster',
                'response.operation.view', 'response.operation.create', 'response.operation.update', 'response.operation.manage_lifecycle', 'response.task.manage', 'response.coordination.manage', 'response.report.create', 'response.gis.view_exact',
                'project.scheme.view', 'project.scheme.approve', 'project.scheme.export',
                'resource.asset.view', 'resource.asset.deploy', 'resource.asset.export',
                'warehouse.facility.view', 'warehouse.stock.view', 'warehouse.stock.transfer', 'warehouse.stock.export_ledger',
                'damage.assessment.view', 'damage.assessment.verify', 'damage.assessment.approve', 'damage.assessment.export',
                'volunteer.roster.view', 'volunteer.roster.verify', 'volunteer.deployment.dispatch', 'volunteer.attendance.confirm', 'volunteer.location.view_exact', 'volunteer.roster.export',
                'workflow.instance.view', 'workflow.transition.execute',
                'user.account.view', 'role.permission.view', 'organization.view',
                'notification.broadcast.send', 'notification.log.view', 'notification.alert.manage', 'notification.alert.acknowledge',
                'document.file.view', 'document.file.upload', 'document.file.approve', 'document.file.verify_integrity',
                'report.sitrep.view', 'report.sitrep.generate', 'report.analytics.view', 'report.analytics.export',
                'ai.insight.view', 'ai.report.generate',
            ],
            'ROLE_DEPUTY_COMMISSIONER' => [
                'beneficiary.household.view', 'beneficiary.household.export', 'beneficiary.pii.decrypt',
                'nid.verification.view_status', 'nid.verification.manual_review',
                'disaster.event.view', 'disaster.event.create', 'disaster.event.update', 'disaster.event.verify', 'disaster.warning.broadcast', 'disaster.hazard_map.manage', 'disaster.emergency.declare',
                'relief.requisition.view', 'relief.requisition.approve', 'relief.allocation.view', 'relief.allocation.create', 'relief.allocation.approve', 'relief.allocation.export', 'relief.distribution.override_duplicate', 'relief.distribution.export_muster',
                'response.operation.view', 'response.operation.create', 'response.operation.update', 'response.operation.manage_lifecycle', 'response.task.manage', 'response.coordination.manage', 'response.report.create', 'response.gis.view_exact',
                'project.scheme.view', 'project.scheme.approve', 'project.disbursement.approve', 'project.scheme.export',
                'resource.asset.view', 'resource.asset.create', 'resource.asset.update', 'resource.asset.deploy', 'resource.asset.export',
                'warehouse.facility.view', 'warehouse.facility.manage', 'warehouse.stock.view', 'warehouse.stock.transfer', 'warehouse.stock.writeoff', 'warehouse.stock.export_ledger',
                'damage.assessment.view', 'damage.assessment.verify', 'damage.assessment.approve', 'damage.assessment.export',
                'volunteer.roster.view', 'volunteer.roster.verify', 'volunteer.pii.decrypt', 'volunteer.organization.manage', 'volunteer.organization.verify', 'volunteer.deployment.dispatch', 'volunteer.attendance.record', 'volunteer.attendance.confirm', 'volunteer.training.manage', 'volunteer.location.view_exact', 'volunteer.roster.export',
                'workflow.instance.view', 'workflow.transition.execute',
                'user.account.view', 'user.activity.view', 'role.permission.view', 'organization.view',
                'notification.broadcast.send', 'notification.log.view', 'notification.alert.manage', 'notification.alert.acknowledge',
                'document.file.view', 'document.file.upload', 'document.file.approve', 'document.file.verify_integrity',
                'report.sitrep.view', 'report.sitrep.generate', 'report.analytics.view', 'report.analytics.export',
                'audit.log.view', 'audit.pii_access.view', 'audit.log.export',
                'ai.insight.view', 'ai.report.generate',
            ],
            'ROLE_DRRO' => [
                'beneficiary.household.view', 'beneficiary.household.delete', 'beneficiary.household.export', 'beneficiary.pii.decrypt', 'beneficiary.dedup.override',
                'nid.verification.initiate', 'nid.verification.view_status', 'nid.verification.manual_review',
                'disaster.event.view', 'disaster.event.create', 'disaster.event.update', 'disaster.event.verify', 'disaster.warning.broadcast', 'disaster.hazard_map.manage',
                'relief.requisition.view', 'relief.requisition.create', 'relief.requisition.update', 'relief.requisition.approve', 'relief.allocation.view', 'relief.allocation.create', 'relief.allocation.approve', 'relief.allocation.export', 'relief.distribution.record', 'relief.distribution.override_duplicate', 'relief.distribution.verify', 'relief.distribution.export_muster',
                'response.operation.view', 'response.operation.create', 'response.operation.update', 'response.operation.manage_lifecycle', 'response.task.manage', 'response.coordination.manage', 'response.report.create', 'response.gis.view_exact',
                'project.scheme.view', 'project.scheme.create', 'project.scheme.update', 'project.milestone.certify', 'project.disbursement.create', 'project.disbursement.approve', 'project.monitoring.create', 'project.scheme.export',
                'resource.asset.view', 'resource.asset.create', 'resource.asset.update', 'resource.asset.deploy', 'resource.asset.export',
                'warehouse.facility.view', 'warehouse.facility.manage', 'warehouse.stock.view', 'warehouse.stock.receive', 'warehouse.stock.transfer', 'warehouse.stock.issue', 'warehouse.stock.writeoff', 'warehouse.stock.export_ledger',
                'damage.assessment.view', 'damage.assessment.submit', 'damage.assessment.verify', 'damage.assessment.export',
                'volunteer.roster.view', 'volunteer.roster.register', 'volunteer.roster.update', 'volunteer.roster.verify', 'volunteer.pii.decrypt', 'volunteer.skill.manage', 'volunteer.organization.manage', 'volunteer.organization.verify', 'volunteer.deployment.dispatch', 'volunteer.attendance.record', 'volunteer.attendance.confirm', 'volunteer.training.manage', 'volunteer.location.view_exact', 'volunteer.roster.export',
                'workflow.instance.view', 'workflow.transition.execute',
                'user.account.view', 'user.activity.view', 'role.permission.view', 'organization.view',
                'notification.log.view', 'notification.alert.acknowledge',
                'document.file.view', 'document.file.upload', 'document.file.approve', 'document.file.delete', 'document.file.verify_integrity',
                'report.sitrep.view', 'report.sitrep.generate', 'report.analytics.view', 'report.analytics.export',
                'audit.log.view', 'audit.pii_access.view', 'audit.log.export',
                'ai.insight.view', 'ai.report.generate',
            ],
            'ROLE_UNO' => [
                'beneficiary.household.view', 'beneficiary.household.export', 'beneficiary.pii.decrypt', 'beneficiary.dedup.override',
                'nid.verification.view_status', 'nid.verification.manual_review',
                'disaster.event.view', 'disaster.event.create', 'disaster.event.update', 'disaster.hazard_map.manage',
                'relief.requisition.view', 'relief.requisition.approve', 'relief.allocation.view', 'relief.allocation.create', 'relief.allocation.approve', 'relief.allocation.export', 'relief.distribution.override_duplicate', 'relief.distribution.verify', 'relief.distribution.export_muster',
                'response.operation.view', 'response.operation.create', 'response.operation.update', 'response.operation.manage_lifecycle', 'response.task.manage', 'response.coordination.manage', 'response.report.create', 'response.gis.view_exact',
                'project.scheme.view', 'project.scheme.approve', 'project.milestone.certify', 'project.disbursement.approve', 'project.scheme.export',
                'resource.asset.view', 'resource.asset.create', 'resource.asset.update', 'resource.asset.deploy', 'resource.asset.export',
                'warehouse.facility.view', 'warehouse.facility.manage', 'warehouse.stock.view', 'warehouse.stock.transfer', 'warehouse.stock.export_ledger',
                'damage.assessment.view', 'damage.assessment.submit', 'damage.assessment.verify', 'damage.assessment.export',
                'volunteer.roster.view', 'volunteer.roster.register', 'volunteer.roster.update', 'volunteer.roster.verify', 'volunteer.pii.decrypt', 'volunteer.organization.manage', 'volunteer.organization.verify', 'volunteer.deployment.dispatch', 'volunteer.attendance.record', 'volunteer.attendance.confirm', 'volunteer.training.manage', 'volunteer.location.view_exact', 'volunteer.roster.export',
                'workflow.instance.view', 'workflow.transition.execute',
                'user.account.view', 'user.activity.view', 'role.permission.view', 'organization.view',
                'notification.broadcast.send', 'notification.log.view', 'notification.alert.acknowledge',
                'document.file.view', 'document.file.upload', 'document.file.approve', 'document.file.verify_integrity',
                'report.sitrep.view', 'report.sitrep.generate', 'report.analytics.view', 'report.analytics.export',
                'audit.log.view', 'audit.pii_access.view',
                'ai.insight.view', 'ai.report.generate',
            ],
            'ROLE_PIO' => [
                'beneficiary.household.view', 'beneficiary.household.create', 'beneficiary.household.update', 'beneficiary.household.delete', 'beneficiary.household.export', 'beneficiary.pii.decrypt', 'beneficiary.dedup.override',
                'nid.verification.initiate', 'nid.verification.view_status', 'nid.verification.manual_review',
                'disaster.event.view', 'disaster.event.create', 'disaster.event.update', 'disaster.hazard_map.manage',
                'relief.requisition.view', 'relief.requisition.create', 'relief.requisition.update', 'relief.requisition.delete', 'relief.requisition.approve', 'relief.allocation.view', 'relief.allocation.create', 'relief.allocation.export', 'relief.distribution.record', 'relief.distribution.override_duplicate', 'relief.distribution.verify', 'relief.distribution.export_muster',
                'response.operation.view', 'response.operation.create', 'response.operation.update', 'response.task.manage', 'response.coordination.manage', 'response.report.create', 'response.gis.view_exact',
                'project.scheme.view', 'project.scheme.create', 'project.scheme.update', 'project.milestone.update', 'project.milestone.certify', 'project.disbursement.create', 'project.monitoring.create', 'project.scheme.export',
                'resource.asset.view', 'resource.asset.create', 'resource.asset.update', 'resource.asset.deploy', 'resource.asset.export',
                'warehouse.facility.view', 'warehouse.facility.manage', 'warehouse.stock.view', 'warehouse.stock.receive', 'warehouse.stock.transfer', 'warehouse.stock.issue', 'warehouse.stock.export_ledger',
                'damage.assessment.view', 'damage.assessment.create', 'damage.assessment.update', 'damage.assessment.delete', 'damage.assessment.submit', 'damage.assessment.verify', 'damage.assessment.export',
                'volunteer.roster.view', 'volunteer.roster.register', 'volunteer.roster.update', 'volunteer.roster.verify', 'volunteer.pii.decrypt', 'volunteer.skill.manage', 'volunteer.organization.manage', 'volunteer.organization.verify', 'volunteer.deployment.dispatch', 'volunteer.attendance.record', 'volunteer.attendance.confirm', 'volunteer.training.manage', 'volunteer.location.view_exact', 'volunteer.roster.export',
                'workflow.instance.view', 'workflow.transition.execute',
                'user.account.view', 'user.activity.view', 'role.permission.view', 'organization.view',
                'notification.log.view', 'notification.alert.acknowledge',
                'document.file.view', 'document.file.upload', 'document.file.approve', 'document.file.delete', 'document.file.verify_integrity',
                'report.sitrep.view', 'report.sitrep.generate', 'report.analytics.view', 'report.analytics.export',
                'audit.log.view', 'audit.pii_access.view',
                'ai.insight.view', 'ai.report.generate',
            ],
            'ROLE_UP_CHAIRMAN' => [
                'beneficiary.household.view', 'beneficiary.household.export', 'nid.verification.view_status',
                'disaster.event.view', 'relief.requisition.view', 'relief.requisition.create', 'relief.requisition.update', 'relief.requisition.delete', 'relief.allocation.view', 'relief.allocation.export', 'relief.distribution.record', 'relief.distribution.export_muster',
                'response.operation.view', 'response.task.manage', 'response.coordination.manage', 'response.report.create', 'response.gis.view_exact',
                'project.scheme.view', 'project.scheme.create', 'project.scheme.update', 'project.scheme.export',
                'resource.asset.view', 'resource.asset.export', 'warehouse.facility.view', 'warehouse.stock.view', 'warehouse.stock.export_ledger',
                'damage.assessment.view', 'damage.assessment.create', 'damage.assessment.update', 'damage.assessment.delete', 'damage.assessment.submit', 'damage.assessment.export',
                'volunteer.roster.view', 'volunteer.roster.register', 'volunteer.roster.update', 'volunteer.deployment.dispatch', 'volunteer.attendance.record', 'volunteer.attendance.confirm', 'volunteer.roster.export',
                'workflow.instance.view', 'workflow.transition.execute', 'role.permission.view',
                'notification.log.view', 'notification.alert.acknowledge', 'document.file.view', 'document.file.upload', 'document.file.verify_integrity',
                'report.sitrep.view', 'report.analytics.view', 'report.analytics.export', 'ai.insight.view',
            ],
            'ROLE_UP_SECRETARY' => [
                'beneficiary.household.view', 'beneficiary.household.create', 'beneficiary.household.update', 'beneficiary.household.export', 'beneficiary.pii.decrypt',
                'nid.verification.initiate', 'nid.verification.view_status', 'disaster.event.view',
                'relief.requisition.view', 'relief.requisition.create', 'relief.requisition.update', 'relief.allocation.view', 'relief.allocation.export', 'relief.distribution.record', 'relief.distribution.export_muster',
                'response.operation.view', 'response.task.manage', 'response.report.create', 'response.gis.view_exact',
                'project.scheme.view', 'project.scheme.create', 'project.scheme.update', 'project.scheme.export',
                'resource.asset.view', 'resource.asset.export', 'warehouse.facility.view', 'warehouse.stock.view', 'warehouse.stock.export_ledger',
                'damage.assessment.view', 'damage.assessment.create', 'damage.assessment.update', 'damage.assessment.submit', 'damage.assessment.export',
                'volunteer.roster.view', 'volunteer.roster.register', 'volunteer.roster.update', 'volunteer.attendance.record', 'volunteer.roster.export',
                'workflow.instance.view', 'workflow.transition.execute', 'role.permission.view',
                'notification.log.view', 'notification.alert.acknowledge', 'document.file.view', 'document.file.upload', 'document.file.verify_integrity',
                'report.sitrep.view', 'report.analytics.view', 'report.analytics.export', 'ai.insight.view',
            ],
            'ROLE_FIELD_ENUMERATOR' => [
                'beneficiary.household.view', 'beneficiary.household.create', 'beneficiary.household.update',
                'nid.verification.initiate', 'nid.verification.view_status', 'disaster.event.view',
                'relief.requisition.view', 'relief.allocation.view', 'relief.distribution.record',
                'response.operation.view', 'response.task.manage', 'response.report.create', 'response.gis.view_exact',
                'project.scheme.view', 'project.milestone.update', 'resource.asset.view',
                'damage.assessment.view', 'damage.assessment.create', 'damage.assessment.update', 'damage.assessment.submit', 'damage.assessment.export',
                'volunteer.roster.view', 'workflow.instance.view', 'workflow.transition.execute', 'role.permission.view',
                'document.file.view', 'document.file.upload', 'report.sitrep.view', 'report.analytics.view', 'ai.insight.view',
            ],
            'ROLE_DATA_ENTRY_OPERATOR' => [
                'beneficiary.household.view', 'beneficiary.household.create', 'beneficiary.household.update',
                'nid.verification.initiate', 'nid.verification.view_status', 'disaster.event.view',
                'relief.requisition.view', 'relief.requisition.create', 'relief.requisition.update',
                'damage.assessment.view', 'damage.assessment.create', 'damage.assessment.update',
                'volunteer.roster.view', 'volunteer.roster.register', 'volunteer.roster.update',
                'role.permission.view', 'document.file.view', 'document.file.upload', 'report.sitrep.view',
            ],
            'ROLE_TAG_OFFICER' => [
                'beneficiary.household.view', 'beneficiary.pii.decrypt', 'nid.verification.view_status', 'disaster.event.view',
                'relief.requisition.view', 'relief.allocation.view', 'relief.allocation.export', 'relief.distribution.verify', 'relief.distribution.export_muster',
                'response.operation.view', 'response.report.create', 'response.gis.view_exact',
                'project.scheme.view', 'project.milestone.update', 'project.milestone.certify', 'project.scheme.export',
                'resource.asset.view', 'warehouse.facility.view', 'warehouse.stock.view', 'warehouse.stock.export_ledger',
                'damage.assessment.view', 'damage.assessment.verify', 'damage.assessment.export',
                'volunteer.roster.view', 'volunteer.roster.verify', 'workflow.instance.view', 'workflow.transition.execute', 'role.permission.view',
                'document.file.view', 'document.file.upload', 'document.file.verify_integrity',
                'report.sitrep.view', 'report.analytics.view', 'report.analytics.export', 'ai.insight.view',
            ],
            'ROLE_WAREHOUSE_OFFICER' => [
                'beneficiary.household.view', 'disaster.event.view',
                'relief.requisition.view', 'relief.allocation.view', 'relief.allocation.export', 'relief.distribution.export_muster',
                'response.operation.view', 'response.coordination.manage',
                'resource.asset.view', 'resource.asset.create', 'resource.asset.update', 'resource.asset.export',
                'warehouse.facility.view', 'warehouse.stock.view', 'warehouse.stock.receive', 'warehouse.stock.transfer', 'warehouse.stock.issue', 'warehouse.stock.export_ledger',
                'workflow.instance.view', 'workflow.transition.execute', 'role.permission.view',
                'notification.alert.acknowledge', 'document.file.view', 'document.file.upload', 'document.file.verify_integrity',
                'report.sitrep.view', 'report.analytics.view', 'report.analytics.export', 'ai.insight.view',
            ],
            'ROLE_SHELTER_MANAGER' => [
                'beneficiary.household.view', 'beneficiary.household.create', 'beneficiary.household.update', 'disaster.event.view',
                'relief.requisition.view', 'relief.requisition.create', 'relief.requisition.update', 'relief.allocation.view', 'relief.distribution.record', 'relief.distribution.export_muster',
                'response.operation.view', 'response.task.manage', 'response.report.create', 'response.gis.view_exact',
                'resource.asset.view', 'warehouse.stock.view',
                'damage.assessment.view', 'damage.assessment.create', 'damage.assessment.update', 'damage.assessment.submit',
                'volunteer.roster.view', 'volunteer.deployment.dispatch', 'volunteer.attendance.record', 'volunteer.attendance.confirm', 'volunteer.location.view_exact',
                'workflow.instance.view', 'workflow.transition.execute', 'role.permission.view',
                'notification.alert.acknowledge', 'document.file.view', 'document.file.upload', 'report.sitrep.view', 'report.analytics.view', 'ai.insight.view',
            ],
            'ROLE_CPP_LEADER' => [
                'beneficiary.household.view', 'disaster.event.view', 'relief.requisition.view', 'relief.allocation.view',
                'response.operation.view', 'response.task.manage', 'response.coordination.manage', 'response.report.create', 'response.gis.view_exact',
                'resource.asset.view', 'damage.assessment.view',
                'volunteer.roster.view', 'volunteer.roster.register', 'volunteer.roster.update', 'volunteer.skill.manage', 'volunteer.deployment.dispatch', 'volunteer.attendance.record', 'volunteer.attendance.confirm', 'volunteer.training.manage', 'volunteer.location.view_exact', 'volunteer.roster.export',
                'role.permission.view', 'notification.alert.acknowledge', 'document.file.view', 'document.file.upload', 'report.sitrep.view', 'report.analytics.view', 'ai.insight.view',
            ],
            'ROLE_VOLUNTEER_RESPONDER' => [
                'disaster.event.view', 'relief.requisition.view', 'relief.allocation.view', 'relief.distribution.record',
                'response.operation.view', 'response.task.manage', 'response.report.create', 'response.gis.view_exact',
                'resource.asset.view', 'volunteer.roster.view', 'volunteer.roster.update', 'volunteer.attendance.record',
                'role.permission.view', 'document.file.upload', 'report.sitrep.view', 'report.analytics.view', 'ai.insight.view',
            ],
            'ROLE_EXTERNAL_PARTNER' => [
                'beneficiary.household.view', 'beneficiary.household.export', 'disaster.event.view',
                'relief.requisition.view', 'relief.allocation.view', 'relief.allocation.export',
                'response.operation.view', 'project.scheme.view', 'project.scheme.export',
                'resource.asset.view', 'resource.asset.export', 'warehouse.facility.view', 'warehouse.stock.view',
                'damage.assessment.view', 'damage.assessment.export', 'volunteer.roster.view', 'volunteer.roster.export',
                'role.permission.view', 'document.file.view', 'report.sitrep.view', 'report.analytics.view', 'report.analytics.export', 'ai.insight.view',
            ],
            'ROLE_DDM_PLANNING_OFFICER' => [
                'disaster.event.view', 'project.scheme.view', 'project.scheme.create', 'project.scheme.update', 'project.monitoring.create',
                'damage.assessment.view', 'workflow.instance.view', 'workflow.transition.execute',
                'role.permission.view', 'report.sitrep.view', 'report.analytics.view',
            ],
        ];

        $pivotTable = DB::getDriverName() === 'pgsql' ? 'core.role_permissions' : 'role_permissions';

        foreach ($rolePermissions as $roleCode => $permCodes) {
            if (!isset($rolesByCode[$roleCode])) {
                continue;
            }
            $role = $rolesByCode[$roleCode];
            foreach ($permCodes as $pCode) {
                if (!isset($permissionsByCode[$pCode])) {
                    continue;
                }
                DB::table($pivotTable)->insertOrIgnore([
                    'role_id' => $role->id,
                    'permission_id' => $permissionsByCode[$pCode]->id,
                    'created_at' => now(),
                ]);
            }
        }
    }

    protected function seedOrganizations(): void
    {
        $national = AdministrativeUnit::where('tier_level', 'NATIONAL')->first();
        $khulnaDiv = AdministrativeUnit::where('code', '40')->first();
        $khulnaDist = AdministrativeUnit::where('code', '47')->first();
        $dacopeUpz = AdministrativeUnit::where('code', '12')->first();
        $chalnaUni = AdministrativeUnit::where('code', '35')->first();

        $orgs = [
            [
                'org_code' => 'MODMR-HQ',
                'name_en' => 'Ministry of Disaster Management and Relief (MoDMR)',
                'name_bn' => 'দুর্যোগ ব্যবস্থাপনা ও ত্রাণ মন্ত্রণালয়',
                'org_type' => 'MINISTRY',
                'tier_level' => 'NATIONAL',
                'administrative_unit_id' => $national?->id,
                'email' => 'info@modmr.gov.bd',
                'phone' => '+88029540542',
                'address' => 'Bangladesh Secretariat, Dhaka-1000',
            ],
            [
                'org_code' => 'DDM-HQ',
                'name_en' => 'Department of Disaster Management (DDM)',
                'name_bn' => 'দুর্যোগ ব্যবস্থাপনা অধিদপ্তর (ডিডিএম)',
                'org_type' => 'DIRECTORATE',
                'tier_level' => 'DIRECTORATE',
                'administrative_unit_id' => $national?->id,
                'parent_code' => 'MODMR-HQ',
                'email' => 'dg@ddm.gov.bd',
                'phone' => '+88029861370',
                'address' => '92-93 Mohakhali C/A, Dhaka-1212',
            ],
            [
                'org_code' => 'NEOC-HQ',
                'name_en' => 'National Emergency Operations Centre (NEOC)',
                'name_bn' => 'জাতীয় জরুরি পরিচালনা কেন্দ্র (এনইওসি)',
                'org_type' => 'MINISTRY',
                'tier_level' => 'NATIONAL',
                'administrative_unit_id' => $national?->id,
                'parent_code' => 'MODMR-HQ',
                'email' => 'neoc@modmr.gov.bd',
                'address' => 'Secretariat / DDM Control Room, Dhaka',
            ],
            [
                'org_code' => 'DIV-KHL',
                'name_en' => 'Office of the Divisional Commissioner, Khulna',
                'name_bn' => 'বিভাগীয় কমিশনারের কার্যালয়, খুলনা',
                'org_type' => 'DIVISION_ADMIN',
                'tier_level' => 'DIVISION',
                'administrative_unit_id' => $khulnaDiv?->id,
                'parent_code' => 'MODMR-HQ',
                'email' => 'divcom.khulna@mopa.gov.bd',
            ],
            [
                'org_code' => 'DIST-KHL',
                'name_en' => 'Office of the Deputy Commissioner (DDMC), Khulna',
                'name_bn' => 'জেলা প্রশাসকের কার্যালয় (ডিডিএমসি), খুলনা',
                'org_type' => 'DISTRICT_ADMIN',
                'tier_level' => 'DISTRICT',
                'administrative_unit_id' => $khulnaDist?->id,
                'parent_code' => 'DIV-KHL',
                'email' => 'dc.khulna@mopa.gov.bd',
            ],
            [
                'org_code' => 'UPZ-DACOPE',
                'name_en' => 'Upazila Disaster Management Office (UNO/PIO), Dacope',
                'name_bn' => 'উপজেলা দুর্যোগ ব্যবস্থাপনা কার্যালয়, দাকোপ',
                'org_type' => 'UPAZILA_ADMIN',
                'tier_level' => 'UPAZILA',
                'administrative_unit_id' => $dacopeUpz?->id,
                'parent_code' => 'DIST-KHL',
                'email' => 'uno.dacope@modmr.gov.bd',
            ],
            [
                'org_code' => 'UNI-CHALNA',
                'name_en' => 'Chalna Union Parishad Disaster Committee (UDMC)',
                'name_bn' => 'চালনা ইউনিয়ন পরিষদ দুর্যোগ কমিটি',
                'org_type' => 'UNION_PARISHAD',
                'tier_level' => 'UNION',
                'administrative_unit_id' => $chalnaUni?->id,
                'parent_code' => 'UPZ-DACOPE',
                'email' => 'udmc.chalna@modmr.gov.bd',
            ],
            [
                'org_code' => 'CPP-HQ',
                'name_en' => 'Cyclone Preparedness Programme (CPP)',
                'name_bn' => 'ঘূর্ণিঝড় প্রস্তুতি কর্মসূচি (সিপিপি)',
                'org_type' => 'VOLUNTEER_AGENCY',
                'tier_level' => 'NATIONAL',
                'administrative_unit_id' => $national?->id,
                'parent_code' => 'MODMR-HQ',
            ],
            [
                'org_code' => 'FSCD-HQ',
                'name_en' => 'Bangladesh Fire Service and Civil Defence (FSCD)',
                'name_bn' => 'বাংলাদেশ ফায়ার সার্ভিস ও সিভিল ডিফেন্স',
                'org_type' => 'DEFENSE_RESCUE',
                'tier_level' => 'NATIONAL',
                'administrative_unit_id' => $national?->id,
            ],
            [
                'org_code' => 'BDRCS-HQ',
                'name_en' => 'Bangladesh Red Crescent Society (BDRCS)',
                'name_bn' => 'বাংলাদেশ রেড ক্রিসেন্ট সোসাইটি',
                'org_type' => 'VOLUNTEER_AGENCY',
                'tier_level' => 'NATIONAL',
                'administrative_unit_id' => $national?->id,
            ],
            [
                'org_code' => 'WFP-BD',
                'name_en' => 'World Food Programme (WFP) Bangladesh',
                'name_bn' => 'বিশ্ব খাদ্য কর্মসূচি (ডব্লিউএফপি) বাংলাদেশ',
                'org_type' => 'HUMANITARIAN_PARTNER',
                'tier_level' => 'NATIONAL',
                'administrative_unit_id' => $national?->id,
            ],
        ];

        $seededByCode = [];
        foreach ($orgs as $item) {
            $parentId = isset($item['parent_code']) && isset($seededByCode[$item['parent_code']])
                ? $seededByCode[$item['parent_code']]->id
                : null;

            $org = Organization::withTrashed()->updateOrCreate(
                ['org_code' => $item['org_code']],
                [
                    'name_en' => $item['name_en'],
                    'name_bn' => $item['name_bn'],
                    'org_type' => $item['org_type'],
                    'tier_level' => $item['tier_level'],
                    'administrative_unit_id' => $item['administrative_unit_id'] ?? null,
                    'parent_organization_id' => $parentId,
                    'email' => $item['email'] ?? null,
                    'phone' => $item['phone'] ?? null,
                    'address' => $item['address'] ?? null,
                    'is_active' => true,
                ]
            );
            if ($org->trashed()) {
                $org->restore();
            }
            $seededByCode[$org->org_code] = $org;
        }
    }
}
