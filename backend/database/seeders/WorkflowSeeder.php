<?php

namespace Database\Seeders;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\User;
use App\Modules\Workflow\Models\WorkflowDefinition;
use App\Modules\Workflow\Models\WorkflowStage;
use App\Modules\Workflow\Models\WorkflowStageAssignment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class WorkflowSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = [
            [
                'code' => 'WF_RELIEF_REQUISITION',
                'name_en' => 'Relief Requisition Multi-Tier Approval',
                'name_bn' => 'ত্রাণ চাহিদাপত্র বহু-স্তরীয় অনুমোদন',
                'module_code' => 'RELIEF',
                'entity_type' => 'RELIEF_REQUISITION',
                'description' => 'Upazila, District dual-authorization, Directorate, and Ministry approval chain for relief requisitions.',
                'is_active' => true,
                'is_default' => true,
                'is_system' => true,
                'allow_self_approval' => false,
                'allow_delegation' => true,
                'sync_entity_status' => false,
                'sla_hours' => 72,
                'escalation_enabled' => true,
                'stages' => [
                    [
                        'stage_order' => 1,
                        'stage_code' => 'INITIAL_REVIEW',
                        'name_en' => 'PIO Initial Verification',
                        'name_bn' => 'পিআইও প্রাথমিক যাচাই',
                        'approval_level' => WorkflowStage::LEVEL_INITIAL_REVIEW,
                        'execution_mode' => WorkflowStage::MODE_SEQUENTIAL,
                        'required_approvals' => 1,
                        'min_tier_level' => 'UPAZILA',
                        'sla_hours' => 12,
                        'escalation_role_code' => 'ROLE_UNO',
                        'assignments' => [
                            ['assignee_type' => 'ROLE', 'role_code' => 'ROLE_PIO', 'tier_level' => 'UPAZILA', 'authority_type' => 'REVIEWER'],
                        ],
                    ],
                    [
                        'stage_order' => 2,
                        'stage_code' => 'SUPERVISOR_APPROVAL',
                        'name_en' => 'Upazila UNO Endorsement',
                        'name_bn' => 'উপজেলা নির্বাহী অফিসার অনুমোদন',
                        'approval_level' => WorkflowStage::LEVEL_SUPERVISOR_APPROVAL,
                        'execution_mode' => WorkflowStage::MODE_SEQUENTIAL,
                        'required_approvals' => 1,
                        'min_tier_level' => 'UPAZILA',
                        'sla_hours' => 12,
                        'escalation_role_code' => 'ROLE_DRRO',
                        'assignments' => [
                            ['assignee_type' => 'ROLE', 'role_code' => 'ROLE_UNO', 'tier_level' => 'UPAZILA', 'authority_type' => 'APPROVER'],
                        ],
                    ],
                    [
                        'stage_order' => 3,
                        'stage_code' => 'DISTRICT_APPROVAL',
                        'name_en' => 'District Dual-Authorization (DRRO + DC)',
                        'name_bn' => 'জেলা দ্বৈত অনুমোদন (ডিআরআরও ও জেলা প্রশাসক)',
                        'approval_level' => WorkflowStage::LEVEL_DISTRICT_APPROVAL,
                        'execution_mode' => WorkflowStage::MODE_PARALLEL_ALL,
                        'required_approvals' => 2,
                        'min_tier_level' => 'DISTRICT',
                        'sla_hours' => 24,
                        'escalation_role_code' => 'ROLE_DDM_RELIEF_DIRECTOR',
                        'assignments' => [
                            ['assignee_type' => 'ROLE', 'role_code' => 'ROLE_DRRO', 'tier_level' => 'DISTRICT', 'authority_type' => 'ENDORSE', 'sort_order' => 1],
                            ['assignee_type' => 'ROLE', 'role_code' => 'ROLE_DEPUTY_COMMISSIONER', 'tier_level' => 'DISTRICT', 'authority_type' => 'APPROVER', 'sort_order' => 2],
                        ],
                    ],
                    [
                        'stage_order' => 4,
                        'stage_code' => 'DIRECTORATE_APPROVAL',
                        'name_en' => 'DDM Directorate Sanction',
                        'name_bn' => 'ডিডিএম অধিদপ্তর অনুমোদন',
                        'approval_level' => WorkflowStage::LEVEL_DIRECTORATE_APPROVAL,
                        'execution_mode' => WorkflowStage::MODE_SEQUENTIAL,
                        'required_approvals' => 1,
                        'min_tier_level' => 'DIRECTORATE',
                        'sla_hours' => 24,
                        'escalation_role_code' => 'ROLE_DDM_DIRECTOR_GENERAL',
                        'assignments' => [
                            ['assignee_type' => 'ROLE', 'role_code' => 'ROLE_DDM_RELIEF_DIRECTOR', 'tier_level' => 'DIRECTORATE', 'authority_type' => 'APPROVER'],
                        ],
                    ],
                    [
                        'stage_order' => 5,
                        'stage_code' => 'FINAL_APPROVAL',
                        'name_en' => 'Ministry Final Sanction',
                        'name_bn' => 'মন্ত্রণালয় চূড়ান্ত অনুমোদন',
                        'approval_level' => WorkflowStage::LEVEL_FINAL_APPROVAL,
                        'execution_mode' => WorkflowStage::MODE_SEQUENTIAL,
                        'required_approvals' => 1,
                        'min_tier_level' => 'NATIONAL',
                        'sla_hours' => 24,
                        'is_terminal' => true,
                        'mapped_entity_status' => 'APPROVED',
                        'assignments' => [
                            ['assignee_type' => 'ROLE', 'role_code' => 'ROLE_MINISTRY_EXECUTIVE', 'tier_level' => 'NATIONAL', 'authority_type' => 'APPROVER'],
                        ],
                    ],
                ],
            ],
            [
                'code' => 'WF_RELIEF_ALLOCATION',
                'name_en' => 'Relief Allocation Order Sanction',
                'name_bn' => 'ত্রাণ বরাদ্দ আদেশ অনুমোদন',
                'module_code' => 'RELIEF',
                'entity_type' => 'RELIEF_ALLOCATION',
                'description' => 'District and Directorate authorization workflow for releasing warehouse relief stock.',
                'is_active' => true,
                'is_default' => true,
                'is_system' => true,
                'allow_self_approval' => false,
                'allow_delegation' => true,
                'sla_hours' => 36,
                'escalation_enabled' => true,
                'stages' => [
                    [
                        'stage_order' => 1,
                        'stage_code' => 'INITIAL_REVIEW',
                        'name_en' => 'DRRO Allocation Review',
                        'name_bn' => 'ডিআরআরও বরাদ্দ পর্যালোচনা',
                        'approval_level' => WorkflowStage::LEVEL_INITIAL_REVIEW,
                        'execution_mode' => WorkflowStage::MODE_SEQUENTIAL,
                        'required_approvals' => 1,
                        'min_tier_level' => 'DISTRICT',
                        'sla_hours' => 12,
                        'escalation_role_code' => 'ROLE_DEPUTY_COMMISSIONER',
                        'assignments' => [
                            ['assignee_type' => 'ROLE', 'role_code' => 'ROLE_DRRO', 'tier_level' => 'DISTRICT', 'authority_type' => 'REVIEWER'],
                        ],
                    ],
                    [
                        'stage_order' => 2,
                        'stage_code' => 'FINAL_APPROVAL',
                        'name_en' => 'Deputy Commissioner / DDM Sanction',
                        'name_bn' => 'জেলা প্রশাসক / ডিডিএম চূড়ান্ত অনুমোদন',
                        'approval_level' => WorkflowStage::LEVEL_FINAL_APPROVAL,
                        'execution_mode' => WorkflowStage::MODE_PARALLEL_ANY,
                        'required_approvals' => 1,
                        'min_tier_level' => 'DISTRICT',
                        'sla_hours' => 24,
                        'is_terminal' => true,
                        'mapped_entity_status' => 'SANCTIONED',
                        'assignments' => [
                            ['assignee_type' => 'ROLE', 'role_code' => 'ROLE_DEPUTY_COMMISSIONER', 'tier_level' => 'DISTRICT', 'authority_type' => 'APPROVER', 'sort_order' => 1],
                            ['assignee_type' => 'ROLE', 'role_code' => 'ROLE_DDM_RELIEF_DIRECTOR', 'tier_level' => 'DIRECTORATE', 'authority_type' => 'APPROVER', 'sort_order' => 2],
                        ],
                    ],
                ],
            ],
            [
                'code' => 'WF_DAMAGE_DFORM',
                'name_en' => 'Damage Assessment (D-Form) Validation Chain',
                'name_bn' => 'ক্ষয়ক্ষতি নিরূপণ (ডি-ফর্ম) যাচাই প্রক্রিয়া',
                'module_code' => 'DAMAGE',
                'entity_type' => 'DAMAGE_ASSESSMENT',
                'description' => 'Multi-echelon D-Form verification from Upazila technical inspection to District, Directorate, and Ministry consolidation.',
                'is_active' => true,
                'is_default' => true,
                'is_system' => true,
                'allow_self_approval' => false,
                'allow_delegation' => true,
                'sla_hours' => 96,
                'escalation_enabled' => true,
                'stages' => [
                    [
                        'stage_order' => 1,
                        'stage_code' => 'INITIAL_REVIEW',
                        'name_en' => 'Upazila Technical Verification',
                        'name_bn' => 'উপজেলা কারিগরি যাচাই',
                        'approval_level' => WorkflowStage::LEVEL_INITIAL_REVIEW,
                        'execution_mode' => WorkflowStage::MODE_PARALLEL_ANY,
                        'required_approvals' => 1,
                        'min_tier_level' => 'UPAZILA',
                        'sla_hours' => 24,
                        'escalation_role_code' => 'ROLE_UNO',
                        'assignments' => [
                            ['assignee_type' => 'ROLE', 'role_code' => 'ROLE_PIO', 'tier_level' => 'UPAZILA', 'authority_type' => 'REVIEWER', 'sort_order' => 1],
                            ['assignee_type' => 'ROLE', 'role_code' => 'ROLE_TAG_OFFICER', 'tier_level' => 'FIELD', 'authority_type' => 'REVIEWER', 'sort_order' => 2],
                        ],
                    ],
                    [
                        'stage_order' => 2,
                        'stage_code' => 'SUPERVISOR_APPROVAL',
                        'name_en' => 'UNO Verification Endorsement',
                        'name_bn' => 'ইউএনও যাচাই অনুমোদন',
                        'approval_level' => WorkflowStage::LEVEL_SUPERVISOR_APPROVAL,
                        'execution_mode' => WorkflowStage::MODE_SEQUENTIAL,
                        'required_approvals' => 1,
                        'min_tier_level' => 'UPAZILA',
                        'sla_hours' => 24,
                        'escalation_role_code' => 'ROLE_DRRO',
                        'assignments' => [
                            ['assignee_type' => 'ROLE', 'role_code' => 'ROLE_UNO', 'tier_level' => 'UPAZILA', 'authority_type' => 'ENDORSE'],
                        ],
                    ],
                    [
                        'stage_order' => 3,
                        'stage_code' => 'DISTRICT_APPROVAL',
                        'name_en' => 'District DRRO & DC Consolidation',
                        'name_bn' => 'জেলা ডিআরআরও ও জেলা প্রশাসক সমন্বয়',
                        'approval_level' => WorkflowStage::LEVEL_DISTRICT_APPROVAL,
                        'execution_mode' => WorkflowStage::MODE_PARALLEL_ALL,
                        'required_approvals' => 2,
                        'min_tier_level' => 'DISTRICT',
                        'sla_hours' => 24,
                        'escalation_role_code' => 'ROLE_DDM_PLANNING_DIRECTOR',
                        'assignments' => [
                            ['assignee_type' => 'ROLE', 'role_code' => 'ROLE_DRRO', 'tier_level' => 'DISTRICT', 'authority_type' => 'ENDORSE', 'sort_order' => 1],
                            ['assignee_type' => 'ROLE', 'role_code' => 'ROLE_DEPUTY_COMMISSIONER', 'tier_level' => 'DISTRICT', 'authority_type' => 'APPROVER', 'sort_order' => 2],
                        ],
                    ],
                    [
                        'stage_order' => 4,
                        'stage_code' => 'FINAL_APPROVAL',
                        'name_en' => 'Directorate / Ministry National Sign-Off',
                        'name_bn' => 'অধিদপ্তর / মন্ত্রণালয় জাতীয় চূড়ান্ত অনুমোদন',
                        'approval_level' => WorkflowStage::LEVEL_FINAL_APPROVAL,
                        'execution_mode' => WorkflowStage::MODE_PARALLEL_ANY,
                        'required_approvals' => 1,
                        'min_tier_level' => 'DIRECTORATE',
                        'sla_hours' => 24,
                        'is_terminal' => true,
                        'mapped_entity_status' => 'APPROVED',
                        'assignments' => [
                            ['assignee_type' => 'ROLE', 'role_code' => 'ROLE_DDM_PLANNING_DIRECTOR', 'tier_level' => 'DIRECTORATE', 'authority_type' => 'APPROVER', 'sort_order' => 1],
                            ['assignee_type' => 'ROLE', 'role_code' => 'ROLE_MINISTRY_EXECUTIVE', 'tier_level' => 'NATIONAL', 'authority_type' => 'APPROVER', 'sort_order' => 2],
                        ],
                    ],
                ],
            ],
            [
                'code' => 'WF_PROJECT_APPROVAL',
                'name_en' => 'Rehabilitation Project & Scheme Approval',
                'name_bn' => 'পুনর্বাসন প্রকল্প ও স্কিম অনুমোদন',
                'module_code' => 'PROJECT',
                'entity_type' => 'PROJECT',
                'description' => 'TR/KABITA and disaster recovery scheme approval workflow from Upazila/District through DDM and MoDMR.',
                'is_active' => true,
                'is_default' => true,
                'is_system' => true,
                'allow_self_approval' => false,
                'allow_delegation' => true,
                'sla_hours' => 120,
                'escalation_enabled' => true,
                'stages' => [
                    [
                        'stage_order' => 1,
                        'stage_code' => 'SUPERVISOR_APPROVAL',
                        'name_en' => 'UNO Technical & Field Scrutiny',
                        'name_bn' => 'ইউএনও কারিগরি যাচাই',
                        'approval_level' => WorkflowStage::LEVEL_SUPERVISOR_APPROVAL,
                        'execution_mode' => WorkflowStage::MODE_SEQUENTIAL,
                        'required_approvals' => 1,
                        'min_tier_level' => 'UPAZILA',
                        'sla_hours' => 24,
                        'escalation_role_code' => 'ROLE_DEPUTY_COMMISSIONER',
                        'assignments' => [
                            ['assignee_type' => 'ROLE', 'role_code' => 'ROLE_UNO', 'tier_level' => 'UPAZILA', 'authority_type' => 'REVIEWER'],
                        ],
                    ],
                    [
                        'stage_order' => 2,
                        'stage_code' => 'DISTRICT_APPROVAL',
                        'name_en' => 'District Committee Approval',
                        'name_bn' => 'জেলা কমিটি অনুমোদন',
                        'approval_level' => WorkflowStage::LEVEL_DISTRICT_APPROVAL,
                        'execution_mode' => WorkflowStage::MODE_SEQUENTIAL,
                        'required_approvals' => 1,
                        'min_tier_level' => 'DISTRICT',
                        'sla_hours' => 48,
                        'escalation_role_code' => 'ROLE_DDM_PLANNING_DIRECTOR',
                        'assignments' => [
                            ['assignee_type' => 'ROLE', 'role_code' => 'ROLE_DEPUTY_COMMISSIONER', 'tier_level' => 'DISTRICT', 'authority_type' => 'APPROVER'],
                        ],
                    ],
                    [
                        'stage_order' => 3,
                        'stage_code' => 'DIRECTORATE_APPROVAL',
                        'name_en' => 'DDM Planning & DG Approval',
                        'name_bn' => 'ডিডিএম পরিকল্পনা ও মহাপরিচালক অনুমোদন',
                        'approval_level' => WorkflowStage::LEVEL_DIRECTORATE_APPROVAL,
                        'execution_mode' => WorkflowStage::MODE_PARALLEL_ANY,
                        'required_approvals' => 1,
                        'min_tier_level' => 'DIRECTORATE',
                        'sla_hours' => 48,
                        'escalation_role_code' => 'ROLE_MINISTRY_EXECUTIVE',
                        'assignments' => [
                            ['assignee_type' => 'ROLE', 'role_code' => 'ROLE_DDM_PLANNING_DIRECTOR', 'tier_level' => 'DIRECTORATE', 'authority_type' => 'APPROVER', 'sort_order' => 1],
                            ['assignee_type' => 'ROLE', 'role_code' => 'ROLE_DDM_DIRECTOR_GENERAL', 'tier_level' => 'DIRECTORATE', 'authority_type' => 'APPROVER', 'sort_order' => 2],
                        ],
                    ],
                    [
                        'stage_order' => 4,
                        'stage_code' => 'MINISTRY_APPROVAL',
                        'name_en' => 'Ministry Executive Sanction',
                        'name_bn' => 'মন্ত্রণালয় নির্বাহী অনুমোদন',
                        'approval_level' => WorkflowStage::LEVEL_MINISTRY_APPROVAL,
                        'execution_mode' => WorkflowStage::MODE_SEQUENTIAL,
                        'required_approvals' => 1,
                        'min_tier_level' => 'NATIONAL',
                        'sla_hours' => 48,
                        'is_terminal' => true,
                        'mapped_entity_status' => 'APPROVED',
                        'assignments' => [
                            ['assignee_type' => 'ROLE', 'role_code' => 'ROLE_MINISTRY_EXECUTIVE', 'tier_level' => 'NATIONAL', 'authority_type' => 'APPROVER'],
                        ],
                    ],
                ],
            ],
            [
                'code' => 'WF_BENEFICIARY_VERIFY',
                'name_en' => 'Beneficiary Household Entitlement Approval',
                'name_bn' => 'উপকারভোগী পরিবার যোগ্যতা অনুমোদন',
                'module_code' => 'BENEFICIARY',
                'entity_type' => 'HOUSEHOLD',
                'description' => 'Union Parishad vetting followed by Upazila PIO and District DRRO verification.',
                'is_active' => true,
                'is_default' => true,
                'is_system' => true,
                'allow_self_approval' => false,
                'allow_delegation' => true,
                'sla_hours' => 48,
                'escalation_enabled' => true,
                'stages' => [
                    [
                        'stage_order' => 1,
                        'stage_code' => 'INITIAL_REVIEW',
                        'name_en' => 'Union Parishad Vetting',
                        'name_bn' => 'ইউনিয়ন পরিষদ যাচাই',
                        'approval_level' => WorkflowStage::LEVEL_INITIAL_REVIEW,
                        'execution_mode' => WorkflowStage::MODE_SEQUENTIAL,
                        'required_approvals' => 1,
                        'min_tier_level' => 'UNION',
                        'sla_hours' => 24,
                        'escalation_role_code' => 'ROLE_PIO',
                        'assignments' => [
                            ['assignee_type' => 'ROLE', 'role_code' => 'ROLE_UP_CHAIRMAN', 'tier_level' => 'UNION', 'authority_type' => 'ENDORSE'],
                        ],
                    ],
                    [
                        'stage_order' => 2,
                        'stage_code' => 'FINAL_APPROVAL',
                        'name_en' => 'Upazila PIO / District DRRO Final Approval',
                        'name_bn' => 'উপজেলা পিআইও / জেলা ডিআরআরও চূড়ান্ত অনুমোদন',
                        'approval_level' => WorkflowStage::LEVEL_FINAL_APPROVAL,
                        'execution_mode' => WorkflowStage::MODE_PARALLEL_ANY,
                        'required_approvals' => 1,
                        'min_tier_level' => 'UPAZILA',
                        'sla_hours' => 24,
                        'is_terminal' => true,
                        'mapped_entity_status' => 'APPROVED',
                        'assignments' => [
                            ['assignee_type' => 'ROLE', 'role_code' => 'ROLE_PIO', 'tier_level' => 'UPAZILA', 'authority_type' => 'APPROVER', 'sort_order' => 1],
                            ['assignee_type' => 'ROLE', 'role_code' => 'ROLE_DRRO', 'tier_level' => 'DISTRICT', 'authority_type' => 'APPROVER', 'sort_order' => 2],
                        ],
                    ],
                ],
            ],
            [
                'code' => 'WF_RESOURCE_ALLOCATION',
                'name_en' => 'Emergency Resource Allocation Authorization',
                'name_bn' => 'জরুরি সম্পদ বরাদ্দ অনুমোদন',
                'module_code' => 'RESOURCE',
                'entity_type' => 'RESOURCE_ALLOCATION',
                'description' => 'Rapid response authorization for deploying rescue boats, heavy machinery, and emergency fleet assets.',
                'is_active' => true,
                'is_default' => true,
                'is_system' => true,
                'allow_self_approval' => false,
                'allow_delegation' => true,
                'sla_hours' => 18,
                'escalation_enabled' => true,
                'stages' => [
                    [
                        'stage_order' => 1,
                        'stage_code' => 'SUPERVISOR_APPROVAL',
                        'name_en' => 'Upazila / District Operational Clearance',
                        'name_bn' => 'উপজেলা / জেলা অপারেশনাল অনুমোদন',
                        'approval_level' => WorkflowStage::LEVEL_SUPERVISOR_APPROVAL,
                        'execution_mode' => WorkflowStage::MODE_PARALLEL_ANY,
                        'required_approvals' => 1,
                        'min_tier_level' => 'UPAZILA',
                        'sla_hours' => 6,
                        'escalation_role_code' => 'ROLE_DEPUTY_COMMISSIONER',
                        'assignments' => [
                            ['assignee_type' => 'ROLE', 'role_code' => 'ROLE_UNO', 'tier_level' => 'UPAZILA', 'authority_type' => 'REVIEWER', 'sort_order' => 1],
                            ['assignee_type' => 'ROLE', 'role_code' => 'ROLE_DRRO', 'tier_level' => 'DISTRICT', 'authority_type' => 'REVIEWER', 'sort_order' => 2],
                        ],
                    ],
                    [
                        'stage_order' => 2,
                        'stage_code' => 'FINAL_APPROVAL',
                        'name_en' => 'DC / NEOC Final Dispatch Authorization',
                        'name_bn' => 'জেলা প্রশাসক / এনইওসি চূড়ান্ত ছাড়পত্র',
                        'approval_level' => WorkflowStage::LEVEL_FINAL_APPROVAL,
                        'execution_mode' => WorkflowStage::MODE_PARALLEL_ANY,
                        'required_approvals' => 1,
                        'min_tier_level' => 'DISTRICT',
                        'sla_hours' => 12,
                        'is_terminal' => true,
                        'mapped_entity_status' => 'ASSIGNED',
                        'assignments' => [
                            ['assignee_type' => 'ROLE', 'role_code' => 'ROLE_DEPUTY_COMMISSIONER', 'tier_level' => 'DISTRICT', 'authority_type' => 'APPROVER', 'sort_order' => 1],
                            ['assignee_type' => 'ROLE', 'role_code' => 'ROLE_NEOC_DIRECTOR', 'tier_level' => 'NATIONAL', 'authority_type' => 'APPROVER', 'sort_order' => 2],
                        ],
                    ],
                ],
            ],
        ];

        $actorId = User::value('id');

        foreach ($definitions as $defData) {
            $stages = $defData['stages'];
            unset($defData['stages']);

            $definition = WorkflowDefinition::where('code', $defData['code'])->first();
            if (!$definition) {
                $definition = WorkflowDefinition::create(array_merge($defData, [
                    'id' => (string) Str::uuid(),
                    'created_by' => $actorId,
                    'updated_by' => $actorId,
                ]));
            }

            if ($definition->stages()->count() === 0) {
                foreach ($stages as $stageData) {
                    $assignments = $stageData['assignments'] ?? [];
                    unset($stageData['assignments']);

                    $stage = WorkflowStage::create(array_merge($stageData, [
                        'id' => (string) Str::uuid(),
                        'workflow_definition_id' => $definition->id,
                    ]));

                    foreach ($assignments as $index => $assignData) {
                        WorkflowStageAssignment::create(array_merge([
                            'id' => (string) Str::uuid(),
                            'workflow_stage_id' => $stage->id,
                            'sort_order' => $assignData['sort_order'] ?? ($index + 1),
                            'can_delegate' => true,
                            'is_mandatory' => true,
                        ], $assignData));
                    }
                }
            }
        }
    }
}
