<?php

namespace Database\Seeders;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\User;
use App\Modules\Notification\Models\Alert;
use App\Modules\Notification\Models\AlertRule;
use App\Modules\Notification\Models\Notification;
use App\Modules\Notification\Models\NotificationDispatch;
use App\Modules\Notification\Models\NotificationPreference;
use App\Modules\Notification\Models\NotificationTemplate;
use Illuminate\Database\Seeder;

class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        $national = AdministrativeUnit::where('tier_level', 'NATIONAL')->first();
        $khulnaDistrict = AdministrativeUnit::where('code', '47')->first()
            ?? AdministrativeUnit::where('tier_level', 'DISTRICT')->first();
        $dacopeUpazila = AdministrativeUnit::where('code', '12')->first()
            ?? AdministrativeUnit::where('tier_level', 'UPAZILA')->first();

        $creator = User::where('role_code', 'ROLE_NEOC_DIRECTOR')->first()
            ?? User::where('role_code', 'ROLE_DDM_DIRECTOR_GENERAL')->first()
            ?? User::first();

        // 1. Bilingual Notification Templates covering all 8 IDMS categories
        $templates = [
            [
                'template_code' => 'DISASTER_EARLY_WARNING',
                'category' => NotificationTemplate::CATEGORY_DISASTER_WARNING,
                'channel' => 'MULTI',
                'default_severity' => 'CRITICAL',
                'title_bn' => 'ঘূর্ণিঝড় / দুর্যোগের আগাম সতর্কবার্তা ({{area_bn}})',
                'title_en' => 'Disaster Early Warning Bulletin ({{area_en}})',
                'subject_bn' => 'জরুরি দুর্যোগ সতর্কবার্তা: {{disaster_name}}',
                'subject_en' => 'URGENT Disaster Warning: {{disaster_name}}',
                'body_bn' => '{{area_bn}} এলাকায় {{disaster_name}} সংক্রান্ত {{signal_level}} সতর্কতা জারি হয়েছে। অনুগ্রহ করে আশ্রয়কেন্দ্র ও জরুরি সাড়াদান দল প্রস্তুত রাখুন।',
                'body_en' => '{{signal_level}} warning issued for {{disaster_name}} in {{area_en}}. Please mobilize cyclone shelters and emergency response teams immediately.',
                'variables' => ['area_bn', 'area_en', 'disaster_name', 'signal_level'],
                'is_system' => true,
            ],
            [
                'template_code' => 'EMERGENCY_EVACUATION_ALERT',
                'category' => NotificationTemplate::CATEGORY_EMERGENCY_ALERT,
                'channel' => 'SMS',
                'default_severity' => 'CRITICAL',
                'title_bn' => 'জরুরি অপসারণ নির্দেশনা ({{area_bn}})',
                'title_en' => 'Emergency Evacuation Directive ({{area_en}})',
                'subject_bn' => 'জরুরি অপসারণ সতর্কতা',
                'subject_en' => 'Emergency Evacuation Alert',
                'body_bn' => 'অতি জরুরি: {{area_bn}} এলাকার ঝুঁকিপূর্ণ জনগণকে নিকটস্থ আশ্রয়কেন্দ্রে সরিয়ে নেওয়ার নির্দেশ দেওয়া হলো।',
                'body_en' => 'CRITICAL: Immediate evacuation to designated shelters ordered for vulnerable zones in {{area_en}}.',
                'variables' => ['area_bn', 'area_en'],
                'is_system' => true,
            ],
            [
                'template_code' => 'RELIEF_APPROVAL_UPDATE',
                'category' => NotificationTemplate::CATEGORY_RELIEF_APPROVAL,
                'channel' => 'MULTI',
                'default_severity' => 'INFO',
                'title_bn' => 'ত্রাণ বরাদ্দ / চাহিদাপত্র হালনাগাদ ({{reference_no}})',
                'title_en' => 'Relief Requisition / Allocation Update ({{reference_no}})',
                'subject_bn' => 'ত্রাণ অনুমোদন হালনাগাদ: {{reference_no}}',
                'subject_en' => 'Relief Approval Update: {{reference_no}}',
                'body_bn' => 'ত্রাণ নথি {{reference_no}} এর বর্তমান অবস্থা: {{status}}। অনুগ্রহ করে আইডিএমএস ড্যাশবোর্ডে বিস্তারিত দেখুন।',
                'body_en' => 'Relief record {{reference_no}} is now {{status}}. Please review details in the IDMS Relief module.',
                'variables' => ['reference_no', 'status'],
                'is_system' => true,
            ],
            [
                'template_code' => 'BENEFICIARY_NID_VERIFICATION',
                'category' => NotificationTemplate::CATEGORY_BENEFICIARY_VERIFICATION,
                'channel' => 'IN_APP',
                'default_severity' => 'INFO',
                'title_bn' => 'উপকারভোগী এনআইডি যাচাই সম্পন্ন',
                'title_en' => 'Beneficiary NID Verification Update',
                'subject_bn' => 'এনআইডি যাচাই হালনাগাদ',
                'subject_en' => 'NID Verification Status Update',
                'body_bn' => 'পরিবার/উপকারভোগী কোড {{household_code}} এর এনআইডি যাচাই অবস্থা: {{status}}।',
                'body_en' => 'NID verification status for household {{household_code}} updated to {{status}}.',
                'variables' => ['household_code', 'status'],
                'is_system' => true,
            ],
            [
                'template_code' => 'WORKFLOW_APPROVAL_REQUEST',
                'category' => NotificationTemplate::CATEGORY_WORKFLOW_APPROVAL,
                'channel' => 'MULTI',
                'default_severity' => 'HIGH',
                'title_bn' => 'অনুমোদন অপেক্ষমাণ: {{instance_no}}',
                'title_en' => 'Workflow Approval Pending: {{instance_no}}',
                'subject_bn' => 'অনুমোদন অনুরোধ: {{instance_no}}',
                'subject_en' => 'Approval Action Required: {{instance_no}}',
                'body_bn' => 'কর্মপ্রবাহ {{instance_no}} ({{stage_name}}) আপনার পর্যালোচনা ও অনুমোদনের জন্য অপেক্ষমাণ রয়েছে।',
                'body_en' => 'Workflow instance {{instance_no}} ({{stage_name}}) is awaiting your review and decision.',
                'variables' => ['instance_no', 'stage_name'],
                'is_system' => true,
            ],
            [
                'template_code' => 'WAREHOUSE_LOW_STOCK_ALERT',
                'category' => NotificationTemplate::CATEGORY_INVENTORY_ALERT,
                'channel' => 'MULTI',
                'default_severity' => 'HIGH',
                'title_bn' => 'গুদাম মজুদ স্বল্পতার সতর্কতা ({{warehouse_name}})',
                'title_en' => 'Warehouse Low Stock Alert ({{warehouse_name}})',
                'subject_bn' => 'জরুরি মজুদ সতর্কতা: {{commodity_name}}',
                'subject_en' => 'Inventory Threshold Alert: {{commodity_name}}',
                'body_bn' => '{{warehouse_name}} গুদামে {{commodity_name}} এর বর্তমান মজুদ ({{current_qty}}) ন্যূনতম সীমার নিচে নেমে এসেছে।',
                'body_en' => '{{commodity_name}} stock at {{warehouse_name}} has dropped to {{current_qty}}, below the minimum threshold.',
                'variables' => ['warehouse_name', 'commodity_name', 'current_qty'],
                'is_system' => true,
            ],
            [
                'template_code' => 'PROJECT_MONITORING_REMINDER',
                'category' => NotificationTemplate::CATEGORY_PROJECT_MONITORING,
                'channel' => 'EMAIL',
                'default_severity' => 'WARNING',
                'title_bn' => 'প্রকল্প পরিবীক্ষণ ও মাইলস্টোন স্মারক ({{project_code}})',
                'title_en' => 'Project Monitoring & Milestone Reminder ({{project_code}})',
                'subject_bn' => 'প্রকল্প পরিবীক্ষণ স্মারক: {{project_code}}',
                'subject_en' => 'Project Monitoring Reminder: {{project_code}}',
                'body_bn' => 'পুনর্বাসন প্রকল্প {{project_code}} এর মাইলস্টোন পরিদর্শন প্রতিবেদন নির্ধারিত সময়ের মধ্যে দাখিল করুন।',
                'body_en' => 'Please submit the scheduled field inspection report for rehabilitation project {{project_code}}.',
                'variables' => ['project_code'],
                'is_system' => true,
            ],
            [
                'template_code' => 'SYSTEM_MAINTENANCE_NOTICE',
                'category' => NotificationTemplate::CATEGORY_SYSTEM_ANNOUNCEMENT,
                'channel' => 'IN_APP',
                'default_severity' => 'INFO',
                'title_bn' => 'সিস্টেম সংক্রান্ত ঘোষণা ({{title_tag}})',
                'title_en' => 'IDMS System Announcement ({{title_tag}})',
                'subject_bn' => 'আইডিএমএস সিস্টেম ঘোষণা',
                'subject_en' => 'IDMS System Announcement',
                'body_bn' => 'আইডিএমএস প্রশাসনিক ঘোষণা: {{message_bn}}',
                'body_en' => 'IDMS Administrative Announcement: {{message_en}}',
                'variables' => ['title_tag', 'message_bn', 'message_en'],
                'is_system' => true,
            ],
        ];

        $templateMap = [];
        foreach ($templates as $tpl) {
            $record = NotificationTemplate::withTrashed()->updateOrCreate(
                ['template_code' => $tpl['template_code']],
                array_merge($tpl, [
                    'is_active' => true,
                    'created_by' => $creator?->id,
                    'updated_by' => $creator?->id,
                ])
            );
            if ($record->trashed()) {
                $record->restore();
            }
            $templateMap[$record->template_code] = $record;
        }

        // 2. Configurable Alert Rules & Escalation Policies
        $rules = [
            [
                'rule_code' => 'RULE-CYCLONE-SIGNAL-HIGH',
                'name_bn' => 'ঘূর্ণিঝড় বিপদ সংকেত (৭ নম্বর বা তদূর্ধ্ব)',
                'name_en' => 'Cyclone Danger Signal Threshold (>= Signal 7)',
                'description_bn' => 'উপকূলীয় জেলায় ৭ নম্বর বা তদূর্ধ্ব বিপদ সংকেত জারি হলে স্বয়ংক্রিয় জরুরি সতর্কতা।',
                'description_en' => 'Triggers immediate critical alert when BMD issues Cyclone Danger Signal 7 or higher.',
                'category' => NotificationTemplate::CATEGORY_DISASTER_WARNING,
                'event_type' => 'CYCLONE_SIGNAL_THRESHOLD',
                'severity' => 'CRITICAL',
                'condition_config' => ['metric' => 'signal_number', 'operator' => '>=', 'threshold' => 7],
                'channels' => ['IN_APP', 'SMS', 'EMAIL', 'PUSH'],
                'target_roles' => ['ROLE_NEOC_DIRECTOR', 'ROLE_DEPUTY_COMMISSIONER', 'ROLE_DRRO', 'ROLE_UNO', 'ROLE_PIO', 'ROLE_CPP_LEADER'],
                'escalation_delay_minutes' => 15,
                'escalation_role_code' => 'ROLE_MINISTRY_EXECUTIVE',
                'cooldown_minutes' => 30,
                'administrative_unit_id' => $khulnaDistrict?->id ?? $national?->id,
            ],
            [
                'rule_code' => 'RULE-WAREHOUSE-LOW-STOCK',
                'name_bn' => 'গুদাম জরুরি ত্রাণ মজুদ ন্যূনতম সীমা সতর্কতা',
                'name_en' => 'Warehouse Emergency Stock Minimum Threshold',
                'description_bn' => 'খাদ্যশস্য বা জরুরি ত্রাণ মজুদ নির্ধারিত সীমার নিচে নামলে সতর্কতা জারি করে।',
                'description_en' => 'Triggers alert when warehouse commodity available stock drops below configured minimum threshold.',
                'category' => NotificationTemplate::CATEGORY_INVENTORY_ALERT,
                'event_type' => 'LOW_STOCK_THRESHOLD',
                'severity' => 'HIGH',
                'condition_config' => ['metric' => 'stock_ratio_pct', 'operator' => '<=', 'threshold' => 25],
                'channels' => ['IN_APP', 'EMAIL'],
                'target_roles' => ['ROLE_WAREHOUSE_OFFICER', 'ROLE_DRRO', 'ROLE_DDM_RELIEF_DIRECTOR'],
                'escalation_delay_minutes' => 60,
                'escalation_role_code' => 'ROLE_DDM_DIRECTOR_GENERAL',
                'cooldown_minutes' => 60,
                'administrative_unit_id' => $national?->id,
            ],
            [
                'rule_code' => 'RULE-WORKFLOW-SLA-OVERDUE',
                'name_bn' => 'কর্মপ্রবাহ অনুমোদন এসএলএ অতিক্রম সতর্কতা',
                'name_en' => 'Workflow Approval SLA Breach Escalation',
                'description_bn' => 'অনুমোদন সারি ২৪ ঘণ্টার বেশি অপেক্ষমাণ থাকলে সংশ্লিষ্ট কর্মকর্তাকে সতর্ক করে।',
                'description_en' => 'Escalates overdue workflow approvals pending past configured SLA window.',
                'category' => NotificationTemplate::CATEGORY_WORKFLOW_APPROVAL,
                'event_type' => 'WORKFLOW_SLA_OVERDUE',
                'severity' => 'WARNING',
                'condition_config' => ['metric' => 'overdue_hours', 'operator' => '>=', 'threshold' => 24],
                'channels' => ['IN_APP', 'EMAIL'],
                'target_roles' => ['ROLE_UNO', 'ROLE_DRRO', 'ROLE_DDM_RELIEF_DIRECTOR'],
                'escalation_delay_minutes' => 120,
                'escalation_role_code' => 'ROLE_DEPUTY_COMMISSIONER',
                'cooldown_minutes' => 120,
                'administrative_unit_id' => $national?->id,
            ],
        ];

        $ruleMap = [];
        foreach ($rules as $rule) {
            $record = AlertRule::withTrashed()->updateOrCreate(
                ['rule_code' => $rule['rule_code']],
                array_merge($rule, [
                    'is_active' => true,
                    'created_by' => $creator?->id,
                    'updated_by' => $creator?->id,
                ])
            );
            if ($record->trashed()) {
                $record->restore();
            }
            $ruleMap[$record->rule_code] = $record;
        }

        // 3. Initial Operational Alerts (only seed if table is empty)
        if (Alert::count() === 0) {
            Alert::create([
                'alert_no' => 'ALT-2026-0001',
                'alert_rule_id' => $ruleMap['RULE-CYCLONE-SIGNAL-HIGH']->id ?? null,
                'category' => NotificationTemplate::CATEGORY_DISASTER_WARNING,
                'severity' => 'CRITICAL',
                'status' => Alert::STATUS_ACTIVE,
                'title_bn' => 'দাকোপ উপকূলীয় অঞ্চলে মহাবিপদ সংকেত-৮ সতর্কতা',
                'title_en' => 'Coastal Cyclone Signal 8 Warning - Dacope Sector',
                'message_bn' => 'খুলনা ও দাকোপ উপকূলীয় ইউনিয়নে ঘূর্ণিঝড় আশ্রয়কেন্দ্র প্রস্তুত ও সিপিপি দল মোতায়েনের জন্য সতর্কতা সক্রিয় করা হয়েছে।',
                'message_en' => 'Coastal cyclone preparedness alert active for Dacope Upazila shelters and CPP response units.',
                'source_module' => 'Disaster',
                'metric_payload' => ['signal_number' => 8, 'wind_speed_kmh' => 115],
                'administrative_unit_id' => $dacopeUpazila?->id ?? $khulnaDistrict?->id ?? $national?->id,
                'idempotency_key' => 'seed-alert-cyclone-2026-0001',
                'triggered_at' => now()->subMinutes(20),
                'created_by' => $creator?->id,
            ]);

            Alert::create([
                'alert_no' => 'ALT-2026-0002',
                'alert_rule_id' => $ruleMap['RULE-WAREHOUSE-LOW-STOCK']->id ?? null,
                'category' => NotificationTemplate::CATEGORY_INVENTORY_ALERT,
                'severity' => 'HIGH',
                'status' => Alert::STATUS_ACKNOWLEDGED,
                'title_bn' => 'খুলনা জেলা গুদামে পানি বিশুদ্ধকরণ ট্যাবলেট মজুদ কম',
                'title_en' => 'Low Water Purification Tablet Stock at Khulna District Godown',
                'message_bn' => 'খুলনা জেলা গুদামে পানি বিশুদ্ধকরণ ট্যাবলেটের প্রাপ্য মজুদ ন্যূনতম সীমার নিচে (২২%) রয়েছে।',
                'message_en' => 'Available stock of Water Purification Tablets is at 22% of minimum threshold in Khulna District Godown.',
                'source_module' => 'Warehouse',
                'metric_payload' => ['stock_ratio_pct' => 22, 'threshold' => 25],
                'administrative_unit_id' => $khulnaDistrict?->id ?? $national?->id,
                'idempotency_key' => 'seed-alert-stock-2026-0002',
                'triggered_at' => now()->subHours(3),
                'acknowledged_by' => $creator?->id,
                'acknowledged_at' => now()->subHours(2),
                'acknowledgement_notes' => 'Replenishment transfer requisition initiated from Central CSD.',
                'created_by' => $creator?->id,
            ]);
        }

        // 4. Sample Notification & Dispatches (only seed if empty)
        if (Notification::count() === 0 && $creator) {
            $notification = Notification::create([
                'notification_no' => 'NTF-2026-0001',
                'template_id' => $templateMap['DISASTER_EARLY_WARNING']->id ?? null,
                'category' => NotificationTemplate::CATEGORY_DISASTER_WARNING,
                'severity' => 'CRITICAL',
                'priority' => 'URGENT',
                'channels' => ['IN_APP', 'SMS'],
                'title_bn' => 'ঘূর্ণিঝড় প্রস্তুতি জরুরি নির্দেশনা',
                'title_en' => 'Cyclone Preparedness Emergency Bulletin',
                'message_bn' => 'উপকূলীয় সব জেলা ও উপজেলা দুর্যোগ ব্যবস্থাপনা কমিটিকে আশ্রয়কেন্দ্র সচল রাখার নির্দেশ প্রদান করা হলো।',
                'message_en' => 'All coastal district and upazila disaster management committees are directed to activate emergency shelters.',
                'target_type' => 'GEOGRAPHY',
                'target_criteria' => ['administrative_unit_id' => $khulnaDistrict?->id ?? $national?->id],
                'administrative_unit_id' => $khulnaDistrict?->id ?? $national?->id,
                'status' => Notification::STATUS_DISPATCHED,
                'idempotency_key' => 'seed-notif-2026-0001',
                'dispatched_at' => now()->subMinutes(15),
                'total_recipients' => 1,
                'delivered_count' => 2,
                'failed_count' => 0,
                'created_by' => $creator->id,
            ]);

            NotificationDispatch::create([
                'notification_id' => $notification->id,
                'template_id' => $notification->template_id,
                'recipient_user_id' => $creator->id,
                'administrative_unit_id' => $notification->administrative_unit_id,
                'channel' => 'IN_APP',
                'destination_address' => 'in-app',
                'title_bn' => $notification->title_bn,
                'title_en' => $notification->title_en,
                'message_body' => $notification->message_bn,
                'message_body_bn' => $notification->message_bn,
                'message_body_en' => $notification->message_en,
                'delivery_status' => NotificationDispatch::STATUS_DELIVERED,
                'provider_name' => 'in_app',
                'provider_reference' => 'SEED-INAPP-001',
                'attempt_count' => 1,
                'dedup_hash' => hash('sha256', $notification->id . ':' . $creator->id . ':IN_APP'),
                'dispatched_at' => now()->subMinutes(15),
                'delivered_at' => now()->subMinutes(15),
            ]);
        }

        // 5. Ensure critical emergency preferences are policy-locked for existing users
        $users = User::query()->take(50)->get();
        foreach ($users as $u) {
            foreach (NotificationPreference::CRITICAL_LOCKED_CATEGORIES as $criticalCategory) {
                NotificationPreference::firstOrCreate(
                    ['user_id' => $u->id, 'category' => $criticalCategory],
                    [
                        'in_app_enabled' => true,
                        'email_enabled' => true,
                        'sms_enabled' => true,
                        'push_enabled' => true,
                        'policy_locked' => true,
                    ]
                );
            }
        }
    }
}
