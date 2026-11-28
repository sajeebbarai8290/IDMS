<?php

namespace Database\Seeders;

use App\Core\Models\User;
use App\Modules\Document\Models\DocumentCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DocumentSeeder extends Seeder
{
    public function run(): void
    {
        $creator = User::where('role_code', 'ROLE_SUPER_ADMIN')->first()
            ?? User::where('role_code', 'ROLE_DDM_DIRECTOR_GENERAL')->first()
            ?? User::first();

        $categories = [
            [
                'code' => DocumentCategory::CODE_GOVERNMENT_ORDER,
                'name_en' => 'Government Orders & Circulars',
                'name_bn' => 'সরকারি আদেশ ও পরিপত্র',
                'description_en' => 'Official MoDMR/Cabinet Government Orders (GOs), circulars, gazettes, and statutory directives.',
                'description_bn' => 'দুর্যোগ ব্যবস্থাপনা ও ত্রাণ মন্ত্রণালয় এবং সরকারের সরকারি আদেশ, পরিপত্র ও প্রজ্ঞাপন।',
                'default_classification' => 'PUBLIC',
                'allowed_formats' => ['PDF', 'DOC', 'DOCX', 'JPEG', 'PNG'],
                'retention_days' => 3650,
                'requires_approval' => true,
                'is_system' => true,
                'sort_order' => 1,
            ],
            [
                'code' => DocumentCategory::CODE_DISASTER_REPORT,
                'name_en' => 'Disaster Situation Reports',
                'name_bn' => 'দুর্যোগ পরিস্থিতি প্রতিবেদন',
                'description_en' => 'Daily SitReps, cyclone/flood bulletins, early warning advisories, and field incident reports.',
                'description_bn' => 'দৈনিক পরিস্থিতি প্রতিবেদন, ঘূর্ণিঝড়/বন্যা বুলেটিন ও আগাম সতর্কতা নথি।',
                'default_classification' => 'INTERNAL',
                'allowed_formats' => ['PDF', 'DOC', 'DOCX', 'XLS', 'XLSX', 'CSV'],
                'retention_days' => 1825,
                'requires_approval' => true,
                'is_system' => true,
                'sort_order' => 2,
            ],
            [
                'code' => DocumentCategory::CODE_ASSESSMENT_REPORT,
                'name_en' => 'Damage & Needs Assessment Reports',
                'name_bn' => 'ক্ষয়ক্ষতি ও চাহিদা নিরূপণ প্রতিবেদন',
                'description_en' => 'D-Form assessment reports, sectoral damage surveys, and engineering loss verifications.',
                'description_bn' => 'ডি-ফর্ম ক্ষয়ক্ষতি নিরূপণ প্রতিবেদন, খাতভিত্তিক জরিপ ও যাচাই নথি।',
                'default_classification' => 'INTERNAL',
                'allowed_formats' => ['PDF', 'DOC', 'DOCX', 'XLS', 'XLSX', 'CSV', 'JPEG', 'PNG'],
                'retention_days' => 2555,
                'requires_approval' => true,
                'is_system' => true,
                'sort_order' => 3,
            ],
            [
                'code' => DocumentCategory::CODE_APPROVAL_DOCUMENT,
                'name_en' => 'Approval & Sanction Documents',
                'name_bn' => 'অনুমোদন ও মঞ্জুরি নথি',
                'description_en' => 'Sanction orders, committee minutes, fund release approvals, and workflow authorizations.',
                'description_bn' => 'বরাদ্দ মঞ্জুরি আদেশ, কমিটির কার্যবিবরণী ও অনুমোদনপত্র।',
                'default_classification' => 'CONFIDENTIAL',
                'allowed_formats' => ['PDF', 'DOC', 'DOCX', 'JPEG', 'PNG'],
                'retention_days' => 3650,
                'requires_approval' => true,
                'is_system' => true,
                'sort_order' => 4,
            ],
            [
                'code' => DocumentCategory::CODE_PROJECT_DOCUMENT,
                'name_en' => 'Project & Rehabilitation Documents',
                'name_bn' => 'প্রকল্প ও পুনর্বাসন নথি',
                'description_en' => 'TR/KABITA/DRR project proposals, engineering blueprints, milestone certificates, and monitoring reports.',
                'description_bn' => 'টিআর/কাবিটা ও পুনর্বাসন প্রকল্পের প্রস্তাবনা, নকশা, মাইলস্টোন প্রত্যয়ন ও পরিবীক্ষণ প্রতিবেদন।',
                'default_classification' => 'INTERNAL',
                'allowed_formats' => ['PDF', 'DOC', 'DOCX', 'XLS', 'XLSX', 'CSV', 'JPEG', 'PNG'],
                'retention_days' => 3650,
                'requires_approval' => true,
                'is_system' => true,
                'sort_order' => 5,
            ],
            [
                'code' => DocumentCategory::CODE_RELIEF_DISTRIBUTION_DOCUMENT,
                'name_en' => 'Relief Distribution & Muster Rolls',
                'name_bn' => 'ত্রাণ বিতরণ ও মাস্টার রোল নথি',
                'description_en' => 'Signed beneficiary muster rolls, distribution vouchers, GRN challans, and warehouse receipts.',
                'description_bn' => 'স্বাক্ষরিত মাস্টার রোল, বিতরণ ভাউচার, চালান ও গুদাম রশিদ।',
                'default_classification' => 'CONFIDENTIAL',
                'allowed_formats' => ['PDF', 'XLS', 'XLSX', 'CSV', 'JPEG', 'PNG'],
                'retention_days' => 2555,
                'requires_approval' => true,
                'is_system' => true,
                'sort_order' => 6,
            ],
            [
                'code' => DocumentCategory::CODE_AGREEMENT,
                'name_en' => 'Agreements, MoUs & Contracts',
                'name_bn' => 'চুক্তিপত্র ও সমঝোতা স্মারক',
                'description_en' => 'Humanitarian partner MoUs, supplier procurement agreements, and contractor deeds.',
                'description_bn' => 'সহযোগী সংস্থার সমঝোতা স্মারক, সরবরাহকারী ও ঠিকাদার চুক্তিপত্র।',
                'default_classification' => 'CONFIDENTIAL',
                'allowed_formats' => ['PDF', 'DOC', 'DOCX'],
                'retention_days' => 3650,
                'requires_approval' => true,
                'is_system' => true,
                'sort_order' => 7,
            ],
            [
                'code' => DocumentCategory::CODE_PHOTOGRAPH,
                'name_en' => 'Field Evidence Photographs',
                'name_bn' => 'মাঠ পর্যায়ের প্রমাণ চিত্র',
                'description_en' => 'Geo-tagged damage photos, relief handover photos, shelter operations, and site inspection images.',
                'description_bn' => 'ক্ষয়ক্ষতি, ত্রাণ বিতরণ, আশ্রয়কেন্দ্র ও প্রকল্প পরিদর্শনের আলোকচিত্র।',
                'default_classification' => 'INTERNAL',
                'allowed_formats' => ['JPEG', 'PNG', 'WEBP'],
                'retention_days' => 1825,
                'requires_approval' => false,
                'is_system' => true,
                'sort_order' => 8,
            ],
            [
                'code' => DocumentCategory::CODE_OTHER,
                'name_en' => 'Other General Documents',
                'name_bn' => 'অন্যান্য সাধারণ নথি',
                'description_en' => 'Miscellaneous supporting correspondence, beneficiary ID proofs, and administrative attachments.',
                'description_bn' => 'অন্যান্য সহায়ক চিঠিপত্র, পরিচয়পত্রের অনুলিপি ও প্রশাসনিক সংযুক্তি।',
                'default_classification' => 'INTERNAL',
                'allowed_formats' => ['PDF', 'DOC', 'DOCX', 'XLS', 'XLSX', 'CSV', 'JPEG', 'PNG'],
                'retention_days' => 1095,
                'requires_approval' => false,
                'is_system' => true,
                'sort_order' => 9,
            ],
        ];

        foreach ($categories as $cat) {
            $existing = DocumentCategory::withTrashed()->where('code', $cat['code'])->first();
            if ($existing) {
                $existing->update(array_merge($cat, [
                    'is_active' => true,
                    'updated_by' => $creator?->id,
                ]));
                if ($existing->trashed()) {
                    $existing->restore();
                }
            } else {
                DocumentCategory::create(array_merge($cat, [
                    'id' => (string) Str::uuid(),
                    'is_active' => true,
                    'created_by' => $creator?->id,
                    'updated_by' => $creator?->id,
                ]));
            }
        }
    }
}
