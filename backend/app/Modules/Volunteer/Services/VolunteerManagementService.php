<?php

namespace App\Modules\Volunteer\Services;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\AuditLog;
use App\Core\Models\User;
use App\Core\Services\AdministrativeScopeService;
use App\Modules\Disaster\Models\DisasterEvent;
use App\Modules\NidVerification\Services\NidVerificationService;
use App\Modules\Response\Models\ResponseOperation;
use App\Modules\Response\Models\ResponseTeam;
use App\Modules\Volunteer\Models\SkillCatalog;
use App\Modules\Volunteer\Models\Volunteer;
use App\Modules\Volunteer\Models\VolunteerAttendance;
use App\Modules\Volunteer\Models\VolunteerCategory;
use App\Modules\Volunteer\Models\VolunteerDeployment;
use App\Modules\Volunteer\Models\VolunteerOrganization;
use App\Modules\Volunteer\Models\VolunteerOrganizationCoverage;
use App\Modules\Volunteer\Models\VolunteerSkillAssignment;
use App\Modules\Volunteer\Models\VolunteerTrainingEnrollment;
use App\Modules\Volunteer\Models\VolunteerTrainingProgram;
use App\Modules\Volunteer\Models\VolunteerTrainingSession;
use App\Modules\Volunteer\Models\VolunteerVerificationLog;
use App\Modules\Volunteer\Policies\VolunteerPolicy;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VolunteerManagementService
{
    public function __construct(
        protected AdministrativeScopeService $scopeService,
        protected VolunteerSpatialService $spatialService,
        protected VolunteerPolicy $policy,
        protected NidVerificationService $nidVerificationService
    ) {}

    public function bumpDashboardCacheVersion(): void
    {
        $current = (int) Cache::get('volunteer:dashboard:version', 1);
        Cache::forever('volunteer:dashboard:version', $current + 1);
    }

    public function assertUnitInUserScope(User $user, string $unitId, string $context = 'target administrative unit'): AdministrativeUnit
    {
        $unit = AdministrativeUnit::find($unitId);
        if (!$unit) {
            throw ValidationException::withMessages([
                'administrative_unit_id' => "Selected {$context} does not exist.",
            ]);
        }

        if (!$this->scopeService->canAccessScope($user, $unit->ancestry_path)) {
            throw new AuthorizationException("Selected {$context} is outside your authorized administrative jurisdiction.");
        }

        return $unit;
    }

    // =========================================================================
    // Organizations
    // =========================================================================

    public function createOrganization(array $data, User $actor): VolunteerOrganization
    {
        $this->assertUnitInUserScope($actor, $data['administrative_unit_id'], 'organization unit');

        foreach ($data['coverage_unit_ids'] ?? [] as $covUnitId) {
            $this->assertUnitInUserScope($actor, $covUnitId, 'coverage unit');
        }

        return DB::transaction(function () use ($data, $actor) {
            $orgCode = strtoupper(trim((string) ($data['org_code'] ?? ('ORG-' . strtoupper(Str::random(6))))));

            $org = VolunteerOrganization::create([
                'org_code' => $orgCode,
                'name_en' => $data['name_en'],
                'name_bn' => $data['name_bn'] ?? null,
                'org_type' => strtoupper((string) ($data['org_type'] ?? 'COMMUNITY')),
                'registration_no' => $data['registration_no'] ?? null,
                'administrative_unit_id' => $data['administrative_unit_id'],
                'address' => $data['address'] ?? null,
                'contact_person_name' => $data['contact_person_name'],
                'contact_person_designation' => $data['contact_person_designation'] ?? null,
                'contact_person_phone' => $data['contact_person_phone'],
                'contact_person_email' => $data['contact_person_email'] ?? null,
                'description' => $data['description'] ?? null,
                'verification_status' => 'PENDING',
                'is_active' => $data['is_active'] ?? true,
                'created_by' => $actor->id,
            ]);

            $coverageIds = array_unique(array_filter(array_merge(
                [$data['administrative_unit_id']],
                $data['coverage_unit_ids'] ?? []
            )));

            foreach ($coverageIds as $unitId) {
                VolunteerOrganizationCoverage::firstOrCreate([
                    'organization_id' => $org->id,
                    'administrative_unit_id' => $unitId,
                ]);
            }

            AuditLog::log(
                'VOLUNTEER_ORG_CREATED',
                VolunteerOrganization::class,
                $org->id,
                null,
                ['org_code' => $org->org_code, 'org_type' => $org->org_type],
                $actor,
                $org->administrative_unit_id
            );

            $this->bumpDashboardCacheVersion();

            return $org->load(['administrativeUnit', 'coverages.administrativeUnit']);
        });
    }

    public function updateOrganization(VolunteerOrganization $org, array $data, User $actor): VolunteerOrganization
    {
        $unitId = $data['administrative_unit_id'] ?? $org->administrative_unit_id;
        $this->assertUnitInUserScope($actor, $unitId, 'organization unit');

        foreach ($data['coverage_unit_ids'] ?? [] as $covUnitId) {
            $this->assertUnitInUserScope($actor, $covUnitId, 'coverage unit');
        }

        return DB::transaction(function () use ($org, $data, $actor) {
            $old = ['name_en' => $org->name_en, 'is_active' => $org->is_active];

            $org->update(array_merge(
                collect($data)->only([
                    'name_en',
                    'name_bn',
                    'org_type',
                    'registration_no',
                    'administrative_unit_id',
                    'address',
                    'contact_person_name',
                    'contact_person_designation',
                    'contact_person_phone',
                    'contact_person_email',
                    'description',
                    'is_active',
                ])->toArray(),
                ['updated_by' => $actor->id]
            ));

            if (array_key_exists('coverage_unit_ids', $data)) {
                VolunteerOrganizationCoverage::where('organization_id', $org->id)->delete();
                $coverageIds = array_unique(array_filter(array_merge(
                    [$org->administrative_unit_id],
                    $data['coverage_unit_ids'] ?? []
                )));
                foreach ($coverageIds as $covId) {
                    VolunteerOrganizationCoverage::firstOrCreate([
                        'organization_id' => $org->id,
                        'administrative_unit_id' => $covId,
                    ]);
                }
            }

            AuditLog::log(
                'VOLUNTEER_ORG_UPDATED',
                VolunteerOrganization::class,
                $org->id,
                $old,
                ['name_en' => $org->name_en, 'is_active' => $org->is_active],
                $actor,
                $org->administrative_unit_id
            );

            $this->bumpDashboardCacheVersion();

            return $org->fresh(['administrativeUnit', 'coverages.administrativeUnit']);
        });
    }

    public function verifyOrganization(VolunteerOrganization $org, string $status, ?string $remarks, User $actor): VolunteerOrganization
    {
        $upper = strtoupper($status);
        if (!in_array($upper, ['VERIFIED', 'REJECTED'], true)) {
            throw ValidationException::withMessages([
                'verification_status' => 'Organization verification status must be VERIFIED or REJECTED.',
            ]);
        }

        $old = $org->verification_status;
        $org->update([
            'verification_status' => $upper,
            'verified_by' => $actor->id,
            'verified_at' => now(),
            'verification_remarks' => $remarks,
            'updated_by' => $actor->id,
        ]);

        AuditLog::log(
            'VOLUNTEER_ORG_VERIFIED',
            VolunteerOrganization::class,
            $org->id,
            ['verification_status' => $old],
            ['verification_status' => $upper, 'remarks' => $remarks],
            $actor,
            $org->administrative_unit_id
        );

        $this->bumpDashboardCacheVersion();

        return $org->fresh(['administrativeUnit', 'coverages.administrativeUnit']);
    }

    // =========================================================================
    // Volunteers & Skills
    // =========================================================================

    public function registerVolunteer(array $data, User $actor): Volunteer
    {
        $unit = $this->assertUnitInUserScope($actor, $data['administrative_unit_id'], 'volunteer administrative unit');

        if (!empty($data['organization_id'])) {
            $org = VolunteerOrganization::find($data['organization_id']);
            if (!$org) {
                throw ValidationException::withMessages(['organization_id' => 'Selected volunteer organization not found.']);
            }
        }

        $rawNid = isset($data['nid_number']) ? trim((string) $data['nid_number']) : null;
        $nidHash = $rawNid ? Volunteer::hashNid($rawNid) : null;

        if ($nidHash && Volunteer::where('nid_hash', $nidHash)->exists()) {
            throw ValidationException::withMessages([
                'nid_number' => 'A volunteer with this National ID is already registered in the roster.',
            ]);
        }

        $rawMobile = trim((string) $data['mobile_number']);
        $mobileHash = Volunteer::hashMobile($rawMobile);

        return DB::transaction(function () use ($data, $unit, $actor, $rawNid, $nidHash, $rawMobile, $mobileHash) {
            $code = !empty($data['volunteer_code'])
                ? strtoupper(trim((string) $data['volunteer_code']))
                : sprintf('VOL-%s-%s', $unit->code ?: '00', strtoupper(Str::random(5)));

            $rawEmail = isset($data['email']) && trim((string) $data['email']) !== '' ? trim((string) $data['email']) : null;
            $rawEmergencyPhone = trim((string) $data['emergency_contact_phone']);

            $consent = (bool) ($data['location_consent_given'] ?? false);
            $lat = $consent && isset($data['latitude']) ? (float) $data['latitude'] : null;
            $lng = $consent && isset($data['longitude']) ? (float) $data['longitude'] : null;

            $volunteer = Volunteer::create([
                'volunteer_code' => $code,
                'administrative_unit_id' => $unit->id,
                'organization_id' => $data['organization_id'] ?? null,
                'primary_category_id' => $data['primary_category_id'] ?? null,
                'user_id' => $data['user_id'] ?? null,
                'full_name_en' => $data['full_name_en'],
                'full_name_bn' => $data['full_name_bn'],
                'gender' => isset($data['gender']) ? strtoupper((string) $data['gender']) : null,
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'blood_group' => $data['blood_group'] ?? null,
                'nid_hash' => $nidHash,
                'nid_encrypted' => $rawNid ? Crypt::encryptString($rawNid) : null,
                'nid_masked' => Volunteer::maskNid($rawNid),
                'mobile_hash' => $mobileHash,
                'mobile_encrypted' => Crypt::encryptString($rawMobile),
                'mobile_masked' => Volunteer::maskMobile($rawMobile),
                'email_encrypted' => $rawEmail ? Crypt::encryptString($rawEmail) : null,
                'email_masked' => Volunteer::maskEmail($rawEmail),
                'present_address' => $data['present_address'] ?? null,
                'permanent_address' => $data['permanent_address'] ?? null,
                'ward_village' => $data['ward_village'] ?? null,
                'emergency_contact_name' => $data['emergency_contact_name'],
                'emergency_contact_relation' => $data['emergency_contact_relation'] ?? null,
                'emergency_contact_encrypted' => Crypt::encryptString($rawEmergencyPhone),
                'emergency_contact_masked' => Volunteer::maskMobile($rawEmergencyPhone),
                'identity_verification_status' => 'UNVERIFIED',
                'organization_verification_status' => 'UNVERIFIED',
                'training_verification_status' => 'UNVERIFIED',
                'overall_verification_status' => !empty($data['submit_for_verification'])
                    ? Volunteer::VERIFICATION_STATUS_PENDING
                    : Volunteer::VERIFICATION_STATUS_DRAFT,
                'submitted_by' => !empty($data['submit_for_verification']) ? $actor->id : null,
                'submitted_at' => !empty($data['submit_for_verification']) ? now() : null,
                'availability_status' => strtoupper((string) ($data['availability_status'] ?? Volunteer::AVAILABILITY_AVAILABLE)),
                'latitude' => $lat,
                'longitude' => $lng,
                'location_consent_given' => $consent,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
            ]);

            if (!empty($data['skills'])) {
                $this->syncVolunteerSkillsInternal($volunteer, $data['skills']);
            }

            // Optional hybrid Module 02 NID Verification initiation when requested & NID + DoB provided
            if (!empty($data['initiate_nid_check']) && $rawNid) {
                try {
                    $nidVer = $this->nidVerificationService->initiateVerification(
                        rawNid: $rawNid,
                        dob: $data['date_of_birth'] ?? null,
                        nameEn: $data['full_name_en'],
                        household: null,
                        member: null,
                        administrativeUnitId: $unit->id,
                        user: $actor,
                        async: false
                    );

                    $mappedStatus = $nidVer->verification_status === 'VERIFIED' ? 'VERIFIED' : 'PENDING';
                    $volunteer->update([
                        'nid_verification_id' => $nidVer->id,
                        'identity_verification_status' => $mappedStatus,
                    ]);

                    VolunteerVerificationLog::create([
                        'volunteer_id' => $volunteer->id,
                        'pillar' => 'IDENTITY',
                        'action' => 'NID_ADAPTER_CHECK',
                        'from_status' => 'UNVERIFIED',
                        'to_status' => $mappedStatus,
                        'remarks' => "Module 02 NID check reference: {$nidVer->verification_reference} ({$nidVer->verification_status})",
                        'actor_user_id' => $actor->id,
                        'created_at' => now(),
                    ]);
                } catch (\Throwable $e) {
                    VolunteerVerificationLog::create([
                        'volunteer_id' => $volunteer->id,
                        'pillar' => 'IDENTITY',
                        'action' => 'NID_ADAPTER_FAILED',
                        'from_status' => 'UNVERIFIED',
                        'to_status' => 'PENDING',
                        'remarks' => 'NID adapter lookup unavailable; marked PENDING for manual 4-eye review.',
                        'actor_user_id' => $actor->id,
                        'created_at' => now(),
                    ]);
                    $volunteer->update(['identity_verification_status' => 'PENDING']);
                }
            }

            $this->spatialService->syncVolunteerGeometry($volunteer);

            AuditLog::log(
                'VOLUNTEER_REGISTERED',
                Volunteer::class,
                $volunteer->id,
                null,
                [
                    'volunteer_code' => $volunteer->volunteer_code,
                    'organization_id' => $volunteer->organization_id,
                    'overall_verification_status' => $volunteer->overall_verification_status,
                ],
                $actor,
                $volunteer->administrative_unit_id
            );

            $this->bumpDashboardCacheVersion();

            return $volunteer->fresh($this->volunteerRelations());
        });
    }

    public function updateVolunteer(Volunteer $volunteer, array $data, User $actor): Volunteer
    {
        if (!empty($data['administrative_unit_id'])) {
            $this->assertUnitInUserScope($actor, $data['administrative_unit_id'], 'volunteer administrative unit');
        }

        if (!empty($data['nid_number'])) {
            $newHash = Volunteer::hashNid((string) $data['nid_number']);
            if ($newHash !== $volunteer->nid_hash && Volunteer::where('nid_hash', $newHash)->where('id', '!=', $volunteer->id)->exists()) {
                throw ValidationException::withMessages([
                    'nid_number' => 'Another volunteer already uses this National ID.',
                ]);
            }
        }

        return DB::transaction(function () use ($volunteer, $data, $actor) {
            $old = ['availability_status' => $volunteer->availability_status];

            $payload = collect($data)->only([
                'administrative_unit_id',
                'organization_id',
                'primary_category_id',
                'full_name_en',
                'full_name_bn',
                'gender',
                'date_of_birth',
                'blood_group',
                'present_address',
                'permanent_address',
                'ward_village',
                'emergency_contact_name',
                'emergency_contact_relation',
                'notes',
            ])->toArray();

            if (!empty($data['availability_status'])) {
                $requestedStatus = strtoupper((string) $data['availability_status']);
                if ($volunteer->availability_status === Volunteer::AVAILABILITY_DEPLOYED
                    && $requestedStatus === Volunteer::AVAILABILITY_AVAILABLE
                    && $volunteer->deployments()->whereIn('deployment_status', VolunteerDeployment::ACTIVE_STATUSES)->exists()
                ) {
                    throw ValidationException::withMessages([
                        'availability_status' => 'Cannot mark volunteer AVAILABLE while an active deployment is in progress.',
                    ]);
                }
                $payload['availability_status'] = $requestedStatus;
            }

            if (!empty($data['nid_number'])) {
                $rawNid = trim((string) $data['nid_number']);
                $payload['nid_hash'] = Volunteer::hashNid($rawNid);
                $payload['nid_encrypted'] = Crypt::encryptString($rawNid);
                $payload['nid_masked'] = Volunteer::maskNid($rawNid);
            }

            if (!empty($data['mobile_number'])) {
                $rawMobile = trim((string) $data['mobile_number']);
                $payload['mobile_hash'] = Volunteer::hashMobile($rawMobile);
                $payload['mobile_encrypted'] = Crypt::encryptString($rawMobile);
                $payload['mobile_masked'] = Volunteer::maskMobile($rawMobile);
            }

            if (array_key_exists('email', $data)) {
                $rawEmail = trim((string) ($data['email'] ?? ''));
                $payload['email_encrypted'] = $rawEmail !== '' ? Crypt::encryptString($rawEmail) : null;
                $payload['email_masked'] = $rawEmail !== '' ? Volunteer::maskEmail($rawEmail) : null;
            }

            if (!empty($data['emergency_contact_phone'])) {
                $rawEmerg = trim((string) $data['emergency_contact_phone']);
                $payload['emergency_contact_encrypted'] = Crypt::encryptString($rawEmerg);
                $payload['emergency_contact_masked'] = Volunteer::maskMobile($rawEmerg);
            }

            if (array_key_exists('location_consent_given', $data)) {
                $consent = (bool) $data['location_consent_given'];
                $payload['location_consent_given'] = $consent;
                if (!$consent) {
                    $payload['latitude'] = null;
                    $payload['longitude'] = null;
                } elseif (isset($data['latitude'], $data['longitude'])) {
                    $payload['latitude'] = (float) $data['latitude'];
                    $payload['longitude'] = (float) $data['longitude'];
                }
            } elseif ($volunteer->location_consent_given && isset($data['latitude'], $data['longitude'])) {
                $payload['latitude'] = (float) $data['latitude'];
                $payload['longitude'] = (float) $data['longitude'];
            }

            $payload['updated_by'] = $actor->id;
            $volunteer->update($payload);

            if (array_key_exists('skills', $data)) {
                $this->syncVolunteerSkillsInternal($volunteer, $data['skills'] ?? []);
            }

            $this->spatialService->syncVolunteerGeometry($volunteer);

            AuditLog::log(
                'VOLUNTEER_UPDATED',
                Volunteer::class,
                $volunteer->id,
                $old,
                ['availability_status' => $volunteer->availability_status],
                $actor,
                $volunteer->administrative_unit_id
            );

            $this->bumpDashboardCacheVersion();

            return $volunteer->fresh($this->volunteerRelations());
        });
    }

    public function syncSkills(Volunteer $volunteer, array $skills, User $actor): Volunteer
    {
        DB::transaction(function () use ($volunteer, $skills, $actor) {
            $this->syncVolunteerSkillsInternal($volunteer, $skills);

            AuditLog::log(
                'VOLUNTEER_SKILLS_UPDATED',
                Volunteer::class,
                $volunteer->id,
                null,
                ['skills_count' => count($skills)],
                $actor,
                $volunteer->administrative_unit_id
            );
        });

        $this->bumpDashboardCacheVersion();

        return $volunteer->fresh($this->volunteerRelations());
    }

    protected function syncVolunteerSkillsInternal(Volunteer $volunteer, array $skills): void
    {
        VolunteerSkillAssignment::where('volunteer_id', $volunteer->id)->delete();

        foreach ($skills as $item) {
            if (empty($item['skill_id'])) {
                continue;
            }
            VolunteerSkillAssignment::create([
                'volunteer_id' => $volunteer->id,
                'skill_id' => $item['skill_id'],
                'proficiency_level' => strtoupper((string) ($item['proficiency_level'] ?? 'BASIC')),
                'certification_level' => $item['certification_level'] ?? null,
                'certified_by' => $item['certified_by'] ?? null,
                'issued_at' => $item['issued_at'] ?? null,
                'expires_at' => $item['expires_at'] ?? null,
                'verification_status' => strtoupper((string) ($item['verification_status'] ?? 'PENDING')),
                'notes' => $item['notes'] ?? null,
            ]);
        }
    }

    public function uploadPhoto(Volunteer $volunteer, UploadedFile $file, User $actor): Volunteer
    {
        $realMime = $file->getMimeType() ?: 'application/octet-stream';
        if (!in_array($realMime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw ValidationException::withMessages([
                'photo' => 'Photograph must be a valid JPEG, PNG, or WebP image.',
            ]);
        }

        $ext = $file->guessExtension() ?: 'jpg';
        $path = $file->storeAs("private/volunteers/{$volunteer->id}/photos", Str::uuid() . '.' . $ext, 'local');
        $sha256 = hash_file('sha256', Storage::disk('local')->path($path));

        if ($volunteer->photo_path && Storage::disk('local')->exists($volunteer->photo_path)) {
            Storage::disk('local')->delete($volunteer->photo_path);
        }

        $volunteer->update([
            'photo_path' => $path,
            'photo_original_name' => $file->getClientOriginalName(),
            'photo_mime' => $realMime,
            'photo_size_bytes' => $file->getSize(),
            'photo_sha256' => $sha256,
            'updated_by' => $actor->id,
        ]);

        AuditLog::log(
            'VOLUNTEER_PHOTO_UPLOADED',
            Volunteer::class,
            $volunteer->id,
            null,
            ['sha256' => $sha256, 'mime' => $realMime],
            $actor,
            $volunteer->administrative_unit_id
        );

        return $volunteer->fresh($this->volunteerRelations());
    }

    public function revealPii(Volunteer $volunteer, User $actor): array
    {
        $decrypted = [
            'nid_number' => $volunteer->getDecryptedNid(),
            'mobile_number' => $volunteer->getDecryptedMobile(),
            'email' => $volunteer->getDecryptedEmail(),
            'emergency_contact_phone' => $volunteer->getDecryptedEmergencyContact(),
        ];

        AuditLog::log(
            'VOLUNTEER_PII_DECRYPTED',
            Volunteer::class,
            $volunteer->id,
            null,
            ['decrypted_fields' => array_keys(array_filter($decrypted, fn ($v) => $v !== null))],
            $actor,
            $volunteer->administrative_unit_id
        );

        return $decrypted;
    }

    // =========================================================================
    // 3-Pillar Verification & Approval Workflow
    // =========================================================================

    public function submitForVerification(Volunteer $volunteer, ?string $remarks, User $actor): Volunteer
    {
        if ($volunteer->overall_verification_status === Volunteer::VERIFICATION_STATUS_VERIFIED) {
            throw ValidationException::withMessages([
                'overall_verification_status' => 'Volunteer profile is already verified.',
            ]);
        }

        $from = $volunteer->overall_verification_status;
        $volunteer->update([
            'overall_verification_status' => Volunteer::VERIFICATION_STATUS_PENDING,
            'submitted_by' => $actor->id,
            'submitted_at' => now(),
            'updated_by' => $actor->id,
        ]);

        VolunteerVerificationLog::create([
            'volunteer_id' => $volunteer->id,
            'pillar' => 'OVERALL',
            'action' => 'SUBMITTED',
            'from_status' => $from,
            'to_status' => Volunteer::VERIFICATION_STATUS_PENDING,
            'remarks' => $remarks,
            'actor_user_id' => $actor->id,
            'created_at' => now(),
        ]);

        $this->bumpDashboardCacheVersion();

        return $volunteer->fresh($this->volunteerRelations());
    }

    public function verifyPillar(Volunteer $volunteer, string $pillar, string $status, ?string $remarks, User $actor): Volunteer
    {
        $pillarUpper = strtoupper($pillar);
        $statusUpper = strtoupper($status);

        $columnMap = [
            'IDENTITY' => 'identity_verification_status',
            'ORGANIZATION' => 'organization_verification_status',
            'TRAINING' => 'training_verification_status',
        ];

        if (!isset($columnMap[$pillarUpper])) {
            throw ValidationException::withMessages(['pillar' => 'Invalid verification pillar.']);
        }
        if (!in_array($statusUpper, ['PENDING', 'VERIFIED', 'REJECTED'], true)) {
            throw ValidationException::withMessages(['status' => 'Invalid verification status.']);
        }

        $col = $columnMap[$pillarUpper];
        $from = $volunteer->{$col};

        DB::transaction(function () use ($volunteer, $col, $pillarUpper, $from, $statusUpper, $remarks, $actor) {
            $volunteer->update([
                $col => $statusUpper,
                'verified_by' => $actor->id,
                'verified_at' => now(),
                'updated_by' => $actor->id,
            ]);

            if ($pillarUpper === 'TRAINING' && $statusUpper === 'VERIFIED') {
                VolunteerSkillAssignment::where('volunteer_id', $volunteer->id)
                    ->where('verification_status', 'PENDING')
                    ->update(['verification_status' => 'VERIFIED']);
            }

            VolunteerVerificationLog::create([
                'volunteer_id' => $volunteer->id,
                'pillar' => $pillarUpper,
                'action' => $statusUpper === 'VERIFIED' ? 'PILLAR_VERIFIED' : 'PILLAR_REJECTED',
                'from_status' => $from,
                'to_status' => $statusUpper,
                'remarks' => $remarks,
                'actor_user_id' => $actor->id,
                'created_at' => now(),
            ]);

            AuditLog::log(
                'VOLUNTEER_PILLAR_VERIFIED',
                Volunteer::class,
                $volunteer->id,
                ['pillar' => $pillarUpper, 'status' => $from],
                ['pillar' => $pillarUpper, 'status' => $statusUpper, 'remarks' => $remarks],
                $actor,
                $volunteer->administrative_unit_id
            );
        });

        $this->bumpDashboardCacheVersion();

        return $volunteer->fresh($this->volunteerRelations());
    }

    public function approveVolunteer(Volunteer $volunteer, ?string $remarks, User $actor): Volunteer
    {
        if ($volunteer->identity_verification_status === 'REJECTED'
            || $volunteer->organization_verification_status === 'REJECTED'
        ) {
            throw ValidationException::withMessages([
                'overall_verification_status' => 'Cannot approve volunteer while identity or organization pillar is REJECTED.',
            ]);
        }

        $from = $volunteer->overall_verification_status;

        DB::transaction(function () use ($volunteer, $from, $remarks, $actor) {
            $updates = [
                'overall_verification_status' => Volunteer::VERIFICATION_STATUS_VERIFIED,
                'approved_by' => $actor->id,
                'approved_at' => now(),
                'verified_by' => $volunteer->verified_by ?? $actor->id,
                'verified_at' => $volunteer->verified_at ?? now(),
                'rejection_reason' => null,
                'updated_by' => $actor->id,
            ];

            if ($volunteer->identity_verification_status !== 'VERIFIED') {
                $updates['identity_verification_status'] = 'VERIFIED';
            }
            if ($volunteer->organization_verification_status !== 'VERIFIED' && $volunteer->organization_id) {
                $updates['organization_verification_status'] = 'VERIFIED';
            }

            $volunteer->update($updates);

            VolunteerVerificationLog::create([
                'volunteer_id' => $volunteer->id,
                'pillar' => 'OVERALL',
                'action' => 'APPROVED',
                'from_status' => $from,
                'to_status' => Volunteer::VERIFICATION_STATUS_VERIFIED,
                'remarks' => $remarks,
                'actor_user_id' => $actor->id,
                'created_at' => now(),
            ]);

            AuditLog::log(
                'VOLUNTEER_APPROVED',
                Volunteer::class,
                $volunteer->id,
                ['overall_verification_status' => $from],
                ['overall_verification_status' => Volunteer::VERIFICATION_STATUS_VERIFIED, 'remarks' => $remarks],
                $actor,
                $volunteer->administrative_unit_id
            );
        });

        $this->bumpDashboardCacheVersion();

        return $volunteer->fresh($this->volunteerRelations());
    }

    public function rejectVolunteer(Volunteer $volunteer, string $reason, User $actor): Volunteer
    {
        $from = $volunteer->overall_verification_status;

        DB::transaction(function () use ($volunteer, $from, $reason, $actor) {
            $volunteer->update([
                'overall_verification_status' => Volunteer::VERIFICATION_STATUS_REJECTED,
                'rejected_by' => $actor->id,
                'rejected_at' => now(),
                'rejection_reason' => $reason,
                'updated_by' => $actor->id,
            ]);

            VolunteerVerificationLog::create([
                'volunteer_id' => $volunteer->id,
                'pillar' => 'OVERALL',
                'action' => 'REJECTED',
                'from_status' => $from,
                'to_status' => Volunteer::VERIFICATION_STATUS_REJECTED,
                'remarks' => $reason,
                'actor_user_id' => $actor->id,
                'created_at' => now(),
            ]);

            AuditLog::log(
                'VOLUNTEER_REJECTED',
                Volunteer::class,
                $volunteer->id,
                ['overall_verification_status' => $from],
                ['overall_verification_status' => Volunteer::VERIFICATION_STATUS_REJECTED, 'reason' => $reason],
                $actor,
                $volunteer->administrative_unit_id
            );
        });

        $this->bumpDashboardCacheVersion();

        return $volunteer->fresh($this->volunteerRelations());
    }

    // =========================================================================
    // Deployments & Availability Locking
    // =========================================================================

    public function deployVolunteer(array $data, User $actor): VolunteerDeployment
    {
        $unit = $this->assertUnitInUserScope($actor, $data['administrative_unit_id'], 'deployment unit');

        if (!empty($data['disaster_event_id']) && !DisasterEvent::whereKey($data['disaster_event_id'])->exists()) {
            throw ValidationException::withMessages(['disaster_event_id' => 'Selected disaster event does not exist.']);
        }
        if (!empty($data['response_operation_id']) && !ResponseOperation::whereKey($data['response_operation_id'])->exists()) {
            throw ValidationException::withMessages(['response_operation_id' => 'Selected response operation does not exist.']);
        }
        if (!empty($data['response_team_id']) && !ResponseTeam::whereKey($data['response_team_id'])->exists()) {
            throw ValidationException::withMessages(['response_team_id' => 'Selected response team does not exist.']);
        }

        return DB::transaction(function () use ($data, $unit, $actor) {
            /** @var Volunteer|null $volunteer */
            $volunteer = Volunteer::whereKey($data['volunteer_id'])->lockForUpdate()->first();
            if (!$volunteer) {
                throw ValidationException::withMessages(['volunteer_id' => 'Selected volunteer does not exist.']);
            }

            $volunteer->loadMissing('administrativeUnit');
            if (!$this->scopeService->canAccessScope($actor, $volunteer->administrativeUnit?->ancestry_path ?? '/')) {
                throw new AuthorizationException('Volunteer belongs to an administrative unit outside your scope.');
            }

            if ($volunteer->overall_verification_status !== Volunteer::VERIFICATION_STATUS_VERIFIED) {
                throw ValidationException::withMessages([
                    'volunteer_id' => 'Only verified volunteers can be deployed to disaster or response operations.',
                ]);
            }

            if (!in_array($volunteer->availability_status, Volunteer::DEPLOYABLE_AVAILABILITIES, true)) {
                throw ValidationException::withMessages([
                    'volunteer_id' => "Volunteer is currently {$volunteer->availability_status} and cannot be deployed.",
                ]);
            }

            $hasActive = VolunteerDeployment::where('volunteer_id', $volunteer->id)
                ->whereIn('deployment_status', VolunteerDeployment::ACTIVE_STATUSES)
                ->exists();

            if ($hasActive) {
                throw ValidationException::withMessages([
                    'volunteer_id' => 'Volunteer already has an active deployment.',
                ]);
            }

            $deployment = VolunteerDeployment::create([
                'deployment_no' => 'VDEP-' . now()->format('Ymd') . '-' . strtoupper(Str::random(5)),
                'volunteer_id' => $volunteer->id,
                'disaster_event_id' => $data['disaster_event_id'] ?? null,
                'response_operation_id' => $data['response_operation_id'] ?? null,
                'response_team_id' => $data['response_team_id'] ?? null,
                'administrative_unit_id' => $unit->id,
                'deployment_location' => $data['deployment_location'],
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'assigned_role' => $data['assigned_role'],
                'assigned_duties' => $data['assigned_duties'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'] ?? null,
                'supervisor_user_id' => $data['supervisor_user_id'] ?? null,
                'supervisor_name' => $data['supervisor_name'],
                'supervisor_contact' => $data['supervisor_contact'] ?? null,
                'deployment_status' => !empty($data['activate_immediately'])
                    ? VolunteerDeployment::STATUS_ACTIVE
                    : VolunteerDeployment::STATUS_ASSIGNED,
                'assigned_by' => $actor->id,
                'notes' => $data['notes'] ?? null,
            ]);

            $volunteer->update([
                'availability_status' => Volunteer::AVAILABILITY_DEPLOYED,
                'updated_by' => $actor->id,
            ]);

            $this->spatialService->syncDeploymentGeometry($deployment);

            AuditLog::log(
                'VOLUNTEER_DEPLOYED',
                VolunteerDeployment::class,
                $deployment->id,
                null,
                [
                    'deployment_no' => $deployment->deployment_no,
                    'volunteer_id' => $volunteer->id,
                    'disaster_event_id' => $deployment->disaster_event_id,
                    'response_operation_id' => $deployment->response_operation_id,
                ],
                $actor,
                $deployment->administrative_unit_id
            );

            $this->bumpDashboardCacheVersion();

            return $deployment->load($this->deploymentRelations());
        });
    }

    public function transitionDeployment(VolunteerDeployment $deployment, string $targetStatus, array $meta, User $actor): VolunteerDeployment
    {
        return DB::transaction(function () use ($deployment, $targetStatus, $meta, $actor) {
            /** @var VolunteerDeployment $locked */
            $locked = VolunteerDeployment::whereKey($deployment->id)->lockForUpdate()->firstOrFail();
            $from = $locked->deployment_status;

            if ($targetStatus === VolunteerDeployment::STATUS_ACTIVE) {
                if ($from !== VolunteerDeployment::STATUS_ASSIGNED) {
                    throw ValidationException::withMessages(['deployment_status' => 'Only ASSIGNED deployments can be activated.']);
                }
                $locked->update([
                    'deployment_status' => VolunteerDeployment::STATUS_ACTIVE,
                    'notes' => $meta['notes'] ?? $locked->notes,
                ]);
            } elseif (in_array($targetStatus, [VolunteerDeployment::STATUS_COMPLETED, VolunteerDeployment::STATUS_CANCELLED], true)) {
                if (!in_array($from, VolunteerDeployment::ACTIVE_STATUSES, true)) {
                    throw ValidationException::withMessages(['deployment_status' => 'Deployment is already closed.']);
                }
                $locked->update([
                    'deployment_status' => $targetStatus,
                    'actual_end_date' => $meta['actual_end_date'] ?? now()->toDateString(),
                    'released_by' => $actor->id,
                    'notes' => $meta['notes'] ?? $locked->notes,
                ]);

                $stillActive = VolunteerDeployment::where('volunteer_id', $locked->volunteer_id)
                    ->where('id', '!=', $locked->id)
                    ->whereIn('deployment_status', VolunteerDeployment::ACTIVE_STATUSES)
                    ->exists();

                if (!$stillActive) {
                    Volunteer::whereKey($locked->volunteer_id)->update([
                        'availability_status' => Volunteer::AVAILABILITY_AVAILABLE,
                        'updated_by' => $actor->id,
                    ]);
                }
            }

            AuditLog::log(
                'VOLUNTEER_DEPLOYMENT_TRANSITIONED',
                VolunteerDeployment::class,
                $locked->id,
                ['deployment_status' => $from],
                ['deployment_status' => $locked->deployment_status],
                $actor,
                $locked->administrative_unit_id
            );

            $this->bumpDashboardCacheVersion();

            return $locked->fresh($this->deploymentRelations());
        });
    }

    // =========================================================================
    // Attendance, Activity Reports & Consent-Gated GPS
    // =========================================================================

    public function computeDurationMinutes(Carbon $checkIn, ?Carbon $checkOut): ?int
    {
        if (!$checkOut) {
            return null;
        }
        return max(0, (int) $checkIn->diffInMinutes($checkOut, false));
    }

    public function checkIn(VolunteerDeployment $deployment, array $data, User $actor): VolunteerAttendance
    {
        if (!in_array($deployment->deployment_status, VolunteerDeployment::ACTIVE_STATUSES, true)) {
            throw ValidationException::withMessages([
                'deployment_id' => 'Check-in is only allowed for ASSIGNED or ACTIVE deployments.',
            ]);
        }

        $openAttendance = VolunteerAttendance::where('deployment_id', $deployment->id)
            ->whereNull('check_out_at')
            ->exists();

        if ($openAttendance) {
            throw ValidationException::withMessages([
                'deployment_id' => 'Volunteer already has an open check-in for this deployment. Please check out first.',
            ]);
        }

        return DB::transaction(function () use ($deployment, $data, $actor) {
            if ($deployment->deployment_status === VolunteerDeployment::STATUS_ASSIGNED) {
                $deployment->update(['deployment_status' => VolunteerDeployment::STATUS_ACTIVE]);
            }

            $gpsConsent = (bool) ($data['gps_consent_given'] ?? false);
            $checkInAt = !empty($data['check_in_at']) ? Carbon::parse($data['check_in_at']) : now();

            $attendance = VolunteerAttendance::create([
                'deployment_id' => $deployment->id,
                'volunteer_id' => $deployment->volunteer_id,
                'administrative_unit_id' => $deployment->administrative_unit_id,
                'attendance_date' => $checkInAt->toDateString(),
                'check_in_at' => $checkInAt,
                'gps_consent_given' => $gpsConsent,
                'check_in_lat' => $gpsConsent && isset($data['latitude']) ? (float) $data['latitude'] : null,
                'check_in_lng' => $gpsConsent && isset($data['longitude']) ? (float) $data['longitude'] : null,
                'activity_summary' => $data['activity_summary'] ?? null,
                'confirmation_status' => VolunteerAttendance::CONFIRMATION_PENDING,
                'recorded_by' => $actor->id,
            ]);

            AuditLog::log(
                'VOLUNTEER_CHECK_IN',
                VolunteerAttendance::class,
                $attendance->id,
                null,
                [
                    'deployment_id' => $deployment->id,
                    'gps_consent_given' => $gpsConsent,
                ],
                $actor,
                $attendance->administrative_unit_id
            );

            $this->bumpDashboardCacheVersion();

            return $attendance->load(['deployment', 'volunteer', 'administrativeUnit']);
        });
    }

    public function checkOut(VolunteerAttendance $attendance, array $data, User $actor): VolunteerAttendance
    {
        if ($attendance->check_out_at !== null) {
            throw ValidationException::withMessages([
                'attendance' => 'This attendance session has already been checked out.',
            ]);
        }

        $checkOutAt = !empty($data['check_out_at']) ? Carbon::parse($data['check_out_at']) : now();
        if ($checkOutAt->lt($attendance->check_in_at)) {
            throw ValidationException::withMessages([
                'check_out_at' => 'Check-out timestamp cannot be earlier than check-in timestamp.',
            ]);
        }

        $gpsConsent = $attendance->gps_consent_given || (bool) ($data['gps_consent_given'] ?? false);
        $duration = $this->computeDurationMinutes($attendance->check_in_at, $checkOutAt);

        $attendance->update([
            'check_out_at' => $checkOutAt,
            'duration_minutes' => $duration,
            'gps_consent_given' => $gpsConsent,
            'check_out_lat' => $gpsConsent && isset($data['latitude']) ? (float) $data['latitude'] : null,
            'check_out_lng' => $gpsConsent && isset($data['longitude']) ? (float) $data['longitude'] : null,
            'activity_summary' => $data['activity_summary'] ?? $attendance->activity_summary,
            'tasks_completed' => $data['tasks_completed'] ?? $attendance->tasks_completed,
            'beneficiaries_assisted' => (int) ($data['beneficiaries_assisted'] ?? $attendance->beneficiaries_assisted),
        ]);

        AuditLog::log(
            'VOLUNTEER_CHECK_OUT',
            VolunteerAttendance::class,
            $attendance->id,
            null,
            ['duration_minutes' => $duration],
            $actor,
            $attendance->administrative_unit_id
        );

        $this->bumpDashboardCacheVersion();

        return $attendance->fresh(['deployment', 'volunteer', 'administrativeUnit']);
    }

    public function confirmAttendance(VolunteerAttendance $attendance, string $status, ?string $remarks, User $actor): VolunteerAttendance
    {
        $upper = strtoupper($status);
        if (!in_array($upper, [VolunteerAttendance::CONFIRMATION_CONFIRMED, VolunteerAttendance::CONFIRMATION_DISPUTED], true)) {
            throw ValidationException::withMessages([
                'confirmation_status' => 'Confirmation status must be CONFIRMED or DISPUTED.',
            ]);
        }

        $old = $attendance->confirmation_status;
        $attendance->update([
            'confirmation_status' => $upper,
            'supervisor_confirmed_by' => $actor->id,
            'supervisor_confirmed_at' => now(),
            'supervisor_remarks' => $remarks,
        ]);

        AuditLog::log(
            'VOLUNTEER_ATTENDANCE_CONFIRMED',
            VolunteerAttendance::class,
            $attendance->id,
            ['confirmation_status' => $old],
            ['confirmation_status' => $upper, 'remarks' => $remarks],
            $actor,
            $attendance->administrative_unit_id
        );

        $this->bumpDashboardCacheVersion();

        return $attendance->fresh(['deployment', 'volunteer', 'administrativeUnit', 'confirmedBy']);
    }

    public function uploadAttendanceEvidence(VolunteerAttendance $attendance, UploadedFile $file, User $actor): VolunteerAttendance
    {
        $realMime = $file->getMimeType() ?: 'application/octet-stream';
        if (!in_array($realMime, ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'], true)) {
            throw ValidationException::withMessages([
                'file' => 'Activity evidence must be JPEG, PNG, WebP, or PDF.',
            ]);
        }

        $ext = $file->guessExtension() ?: 'bin';
        $path = $file->storeAs("private/volunteers/{$attendance->volunteer_id}/attendance", Str::uuid() . '.' . $ext, 'local');
        $sha256 = hash_file('sha256', Storage::disk('local')->path($path));

        if ($attendance->evidence_path && Storage::disk('local')->exists($attendance->evidence_path)) {
            Storage::disk('local')->delete($attendance->evidence_path);
        }

        $attendance->update([
            'evidence_path' => $path,
            'evidence_original_name' => $file->getClientOriginalName(),
            'evidence_mime' => $realMime,
            'evidence_sha256' => $sha256,
        ]);

        AuditLog::log(
            'VOLUNTEER_ATTENDANCE_EVIDENCE_UPLOADED',
            VolunteerAttendance::class,
            $attendance->id,
            null,
            ['sha256' => $sha256],
            $actor,
            $attendance->administrative_unit_id
        );

        return $attendance->fresh(['deployment', 'volunteer', 'administrativeUnit']);
    }

    // =========================================================================
    // Training Management
    // =========================================================================

    public function createTrainingProgram(array $data, User $actor): VolunteerTrainingProgram
    {
        $program = VolunteerTrainingProgram::create([
            'program_code' => strtoupper(trim((string) ($data['program_code'] ?? ('TRN-PROG-' . strtoupper(Str::random(5)))))),
            'title_en' => $data['title_en'],
            'title_bn' => $data['title_bn'],
            'category_id' => $data['category_id'] ?? null,
            'skill_id' => $data['skill_id'] ?? null,
            'provider_organization_id' => $data['provider_organization_id'] ?? null,
            'provider_name' => $data['provider_name'],
            'duration_hours' => (int) ($data['duration_hours'] ?? 8),
            'validity_months' => $data['validity_months'] ?? null,
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'created_by' => $actor->id,
        ]);

        AuditLog::log(
            'VOLUNTEER_TRAINING_PROGRAM_CREATED',
            VolunteerTrainingProgram::class,
            $program->id,
            null,
            ['program_code' => $program->program_code],
            $actor
        );

        return $program->load(['category', 'skill', 'providerOrganization']);
    }

    public function createTrainingSession(array $data, User $actor): VolunteerTrainingSession
    {
        $unit = $this->assertUnitInUserScope($actor, $data['administrative_unit_id'], 'training session unit');

        $session = VolunteerTrainingSession::create([
            'session_code' => strtoupper(trim((string) ($data['session_code'] ?? ('TRN-SES-' . strtoupper(Str::random(6)))))),
            'program_id' => $data['program_id'],
            'administrative_unit_id' => $unit->id,
            'venue' => $data['venue'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'trainer_name' => $data['trainer_name'],
            'trainer_contact' => $data['trainer_contact'] ?? null,
            'max_participants' => (int) ($data['max_participants'] ?? 40),
            'status' => strtoupper((string) ($data['status'] ?? 'SCHEDULED')),
            'notes' => $data['notes'] ?? null,
            'created_by' => $actor->id,
        ]);

        AuditLog::log(
            'VOLUNTEER_TRAINING_SESSION_CREATED',
            VolunteerTrainingSession::class,
            $session->id,
            null,
            ['session_code' => $session->session_code],
            $actor,
            $unit->id
        );

        return $session->load(['program', 'administrativeUnit']);
    }

    public function enrollVolunteer(VolunteerTrainingSession $session, string $volunteerId, User $actor): VolunteerTrainingEnrollment
    {
        $volunteer = Volunteer::with('administrativeUnit')->findOrFail($volunteerId);
        if (!$this->scopeService->canAccessScope($actor, $volunteer->administrativeUnit?->ancestry_path ?? '/')) {
            throw new AuthorizationException('Volunteer is outside your administrative scope.');
        }

        $enrolledCount = $session->enrollments()->where('enrollment_status', '!=', 'DROPPED')->count();
        if ($enrolledCount >= $session->max_participants) {
            throw ValidationException::withMessages([
                'session_id' => 'Training session has reached maximum participant capacity.',
            ]);
        }

        $existing = VolunteerTrainingEnrollment::where('session_id', $session->id)
            ->where('volunteer_id', $volunteer->id)
            ->first();

        if ($existing) {
            throw ValidationException::withMessages([
                'volunteer_id' => 'Volunteer is already enrolled in this training session.',
            ]);
        }

        $enrollment = VolunteerTrainingEnrollment::create([
            'session_id' => $session->id,
            'volunteer_id' => $volunteer->id,
            'enrollment_status' => 'ENROLLED',
        ]);

        AuditLog::log(
            'VOLUNTEER_TRAINING_ENROLLED',
            VolunteerTrainingEnrollment::class,
            $enrollment->id,
            null,
            ['session_id' => $session->id, 'volunteer_id' => $volunteer->id],
            $actor,
            $session->administrative_unit_id
        );

        $this->bumpDashboardCacheVersion();

        return $enrollment->load(['session.program', 'volunteer']);
    }

    public function updateTrainingEnrollment(VolunteerTrainingEnrollment $enrollment, array $data, User $actor): VolunteerTrainingEnrollment
    {
        return DB::transaction(function () use ($enrollment, $data, $actor) {
            $session = $enrollment->session()->with('program')->firstOrFail();

            $passed = array_key_exists('passed', $data) ? (bool) $data['passed'] : $enrollment->passed;
            $certNo = $enrollment->certificate_no;
            $issuedAt = $enrollment->issued_at;
            $expiresAt = $enrollment->expires_at;

            if ($passed && !$certNo) {
                $certNo = !empty($data['certificate_no'])
                    ? strtoupper(trim((string) $data['certificate_no']))
                    : 'CERT-' . now()->format('Y') . '-' . strtoupper(Str::random(6));
                $issuedAt = !empty($data['issued_at']) ? $data['issued_at'] : now()->toDateString();
                if (!empty($data['expires_at'])) {
                    $expiresAt = $data['expires_at'];
                } elseif ($session->program?->validity_months) {
                    $expiresAt = Carbon::parse($issuedAt)->addMonths($session->program->validity_months)->toDateString();
                }
            }

            $enrollment->update([
                'enrollment_status' => isset($data['enrollment_status'])
                    ? strtoupper((string) $data['enrollment_status'])
                    : $enrollment->enrollment_status,
                'attendance_hours' => $data['attendance_hours'] ?? $enrollment->attendance_hours,
                'assessment_score' => $data['assessment_score'] ?? $enrollment->assessment_score,
                'passed' => $passed,
                'certificate_no' => $certNo,
                'issued_at' => $issuedAt,
                'expires_at' => $expiresAt,
                'remarks' => $data['remarks'] ?? $enrollment->remarks,
                'verified_by' => $passed ? $actor->id : $enrollment->verified_by,
                'verified_at' => $passed ? now() : $enrollment->verified_at,
            ]);

            if ($passed && $session->program?->skill_id) {
                VolunteerSkillAssignment::updateOrCreate(
                    [
                        'volunteer_id' => $enrollment->volunteer_id,
                        'skill_id' => $session->program->skill_id,
                    ],
                    [
                        'proficiency_level' => 'INTERMEDIATE',
                        'certification_level' => $certNo,
                        'certified_by' => $session->program->provider_name,
                        'issued_at' => $issuedAt,
                        'expires_at' => $expiresAt,
                        'verification_status' => 'VERIFIED',
                    ]
                );
            }

            AuditLog::log(
                'VOLUNTEER_TRAINING_UPDATED',
                VolunteerTrainingEnrollment::class,
                $enrollment->id,
                null,
                ['passed' => $passed, 'certificate_no' => $certNo],
                $actor,
                $session->administrative_unit_id
            );

            $this->bumpDashboardCacheVersion();

            return $enrollment->fresh(['session.program', 'volunteer']);
        });
    }

    public function uploadTrainingCertificate(VolunteerTrainingEnrollment $enrollment, UploadedFile $file, User $actor): VolunteerTrainingEnrollment
    {
        $realMime = $file->getMimeType() ?: 'application/octet-stream';
        if (!in_array($realMime, ['application/pdf', 'image/jpeg', 'image/png'], true)) {
            throw ValidationException::withMessages([
                'certificate' => 'Certificate file must be PDF, JPEG, or PNG.',
            ]);
        }

        $ext = $file->guessExtension() ?: 'pdf';
        $path = $file->storeAs("private/volunteers/{$enrollment->volunteer_id}/certificates", Str::uuid() . '.' . $ext, 'local');
        $sha256 = hash_file('sha256', Storage::disk('local')->path($path));

        if ($enrollment->certificate_path && Storage::disk('local')->exists($enrollment->certificate_path)) {
            Storage::disk('local')->delete($enrollment->certificate_path);
        }

        $enrollment->update([
            'certificate_path' => $path,
            'certificate_original_name' => $file->getClientOriginalName(),
            'certificate_mime' => $realMime,
            'certificate_sha256' => $sha256,
            'verified_by' => $actor->id,
            'verified_at' => now(),
        ]);

        return $enrollment->fresh(['session.program', 'volunteer']);
    }

    // =========================================================================
    // Dashboard, Reports & CSV Export
    // =========================================================================

    public function getDashboardSummary(User $user, array $filters = []): array
    {
        $version = Cache::get('volunteer:dashboard:version', 1);
        $scopePath = $user->isNationalScope() ? 'NATIONAL' : ($user->getAdministrativeScope()['ancestry_path'] ?? '/');
        $cacheKey = 'volunteer:dashboard:v' . $version . ':' . md5(json_encode([$scopePath, $filters]));

        return Cache::remember($cacheKey, now()->addSeconds(90), function () use ($user, $filters) {
            $volQuery = Volunteer::inJurisdiction($user);
            $this->applyVolunteerFilters($volQuery, $filters);

            $totalRegistered = (clone $volQuery)->count();
            $verifiedCount = (clone $volQuery)->where('overall_verification_status', Volunteer::VERIFICATION_STATUS_VERIFIED)->count();
            $pendingVerification = (clone $volQuery)->where('overall_verification_status', Volunteer::VERIFICATION_STATUS_PENDING)->count();
            $availableCount = (clone $volQuery)
                ->where('overall_verification_status', Volunteer::VERIFICATION_STATUS_VERIFIED)
                ->whereIn('availability_status', Volunteer::DEPLOYABLE_AVAILABILITIES)
                ->count();
            $deployedCount = (clone $volQuery)->where('availability_status', Volunteer::AVAILABILITY_DEPLOYED)->count();

            $depQuery = VolunteerDeployment::inJurisdiction($user);
            if (!empty($filters['disaster_event_id'])) {
                $depQuery->where('disaster_event_id', $filters['disaster_event_id']);
            }
            $activeDeployments = (clone $depQuery)->whereIn('deployment_status', VolunteerDeployment::ACTIVE_STATUSES)->count();
            $completedDeployments = (clone $depQuery)->where('deployment_status', VolunteerDeployment::STATUS_COMPLETED)->count();

            $orgCount = VolunteerOrganization::inJurisdiction($user)->where('is_active', true)->count();
            $trainingSessionsCount = VolunteerTrainingSession::inJurisdiction($user)->count();
            $certificatesIssued = VolunteerTrainingEnrollment::where('passed', true)
                ->whereHas('volunteer', fn ($q) => $q->inJurisdiction($user))
                ->count();

            // By Geographical Area
            $byArea = (clone $volQuery)
                ->with('administrativeUnit:id,name_en,name_bn,tier_level')
                ->get(['id', 'administrative_unit_id', 'overall_verification_status', 'availability_status'])
                ->groupBy('administrative_unit_id')
                ->map(function ($group) {
                    $unit = $group->first()->administrativeUnit;
                    return [
                        'administrative_unit_id' => $unit?->id,
                        'name_en' => $unit?->name_en ?? 'Unknown',
                        'name_bn' => $unit?->name_bn ?? 'অজানা',
                        'tier_level' => $unit?->tier_level,
                        'total' => $group->count(),
                        'verified' => $group->where('overall_verification_status', Volunteer::VERIFICATION_STATUS_VERIFIED)->count(),
                        'available' => $group->whereIn('availability_status', Volunteer::DEPLOYABLE_AVAILABILITIES)->count(),
                        'deployed' => $group->where('availability_status', Volunteer::AVAILABILITY_DEPLOYED)->count(),
                    ];
                })
                ->values()
                ->sortByDesc('total')
                ->take(10)
                ->values();

            // By Category
            $byCategory = (clone $volQuery)
                ->with('primaryCategory:id,code,name_en,name_bn,color_hex')
                ->get(['id', 'primary_category_id'])
                ->groupBy('primary_category_id')
                ->map(function ($group) {
                    $cat = $group->first()->primaryCategory;
                    return [
                        'category_id' => $cat?->id,
                        'code' => $cat?->code ?? 'UNASSIGNED',
                        'name_en' => $cat?->name_en ?? 'Uncategorized',
                        'name_bn' => $cat?->name_bn ?? 'অশ্রেণীবদ্ধ',
                        'color_hex' => $cat?->color_hex ?? '#6c757d',
                        'count' => $group->count(),
                    ];
                })
                ->values()
                ->sortByDesc('count')
                ->values();

            // Skills distribution
            $volunteerIds = (clone $volQuery)->pluck('id');
            $bySkill = VolunteerSkillAssignment::with('skill:id,skill_code,name_en,name_bn')
                ->whereIn('volunteer_id', $volunteerIds)
                ->get()
                ->groupBy('skill_id')
                ->map(function ($group) {
                    $skill = $group->first()->skill;
                    return [
                        'skill_id' => $skill?->id,
                        'skill_code' => $skill?->skill_code,
                        'name_en' => $skill?->name_en ?? 'Unknown',
                        'name_bn' => $skill?->name_bn ?? 'অজানা',
                        'volunteers_count' => $group->count(),
                        'verified_count' => $group->where('verification_status', 'VERIFIED')->count(),
                    ];
                })
                ->values()
                ->sortByDesc('volunteers_count')
                ->values();

            return [
                'kpis' => [
                    'total_registered_volunteers' => $totalRegistered,
                    'verified_volunteers' => $verifiedCount,
                    'pending_verification' => $pendingVerification,
                    'available_volunteers' => $availableCount,
                    'deployed_volunteers' => $deployedCount,
                    'active_deployments' => $activeDeployments,
                    'completed_deployments' => $completedDeployments,
                    'registered_organizations' => $orgCount,
                    'training_sessions' => $trainingSessionsCount,
                    'certificates_issued' => $certificatesIssued,
                ],
                'by_area' => $byArea,
                'by_category' => $byCategory,
                'skills_distribution' => $bySkill,
            ];
        });
    }

    public function getReports(User $user, array $filters = []): array
    {
        $isExternal = $this->policy->isExternalPartnerOnly($user);

        $volQuery = Volunteer::inJurisdiction($user)
            ->with(['administrativeUnit', 'organization', 'primaryCategory', 'skillAssignments.skill']);
        $this->applyVolunteerFilters($volQuery, $filters);
        if ($isExternal) {
            $volQuery->where('overall_verification_status', Volunteer::VERIFICATION_STATUS_VERIFIED);
        }

        $volunteers = $volQuery->orderBy('volunteer_code')->limit(200)->get()->map(function ($v) use ($isExternal) {
            return [
                'id' => $v->id,
                'volunteer_code' => $v->volunteer_code,
                'full_name_en' => $isExternal ? 'Verified Volunteer' : $v->full_name_en,
                'full_name_bn' => $isExternal ? 'যাচাইকৃত স্বেচ্ছাসেবক' : $v->full_name_bn,
                'organization' => $v->organization?->name_en,
                'org_type' => $v->organization?->org_type,
                'category' => $v->primaryCategory?->name_en,
                'unit_en' => $v->administrativeUnit?->name_en,
                'unit_bn' => $v->administrativeUnit?->name_bn,
                'overall_verification_status' => $v->overall_verification_status,
                'availability_status' => $v->availability_status,
                'skills_count' => $v->skillAssignments->count(),
            ];
        });

        $deployments = VolunteerDeployment::inJurisdiction($user)
            ->with(['volunteer', 'disasterEvent', 'responseOperation', 'administrativeUnit'])
            ->when(!empty($filters['disaster_event_id']), fn ($q) => $q->where('disaster_event_id', $filters['disaster_event_id']))
            ->orderByDesc('start_date')
            ->limit(200)
            ->get()
            ->map(fn ($d) => [
                'id' => $d->id,
                'deployment_no' => $d->deployment_no,
                'volunteer_code' => $d->volunteer?->volunteer_code,
                'volunteer_name' => $isExternal ? 'Verified Volunteer' : $d->volunteer?->full_name_en,
                'disaster_code' => $d->disasterEvent?->disaster_code,
                'operation_no' => $d->responseOperation?->operation_no,
                'assigned_role' => $d->assigned_role,
                'deployment_location' => $isExternal ? $d->administrativeUnit?->name_en : $d->deployment_location,
                'start_date' => $d->start_date?->toDateString(),
                'end_date' => $d->end_date?->toDateString(),
                'actual_end_date' => $d->actual_end_date?->toDateString(),
                'deployment_status' => $d->deployment_status,
                'supervisor_name' => $isExternal ? null : $d->supervisor_name,
            ]);

        $attendances = VolunteerAttendance::inJurisdiction($user)
            ->with(['volunteer', 'deployment', 'administrativeUnit'])
            ->orderByDesc('attendance_date')
            ->limit(200)
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'attendance_date' => $a->attendance_date?->toDateString(),
                'deployment_no' => $a->deployment?->deployment_no,
                'volunteer_code' => $a->volunteer?->volunteer_code,
                'volunteer_name' => $isExternal ? 'Verified Volunteer' : $a->volunteer?->full_name_en,
                'check_in_at' => $a->check_in_at?->toIso8601String(),
                'check_out_at' => $a->check_out_at?->toIso8601String(),
                'duration_minutes' => $a->duration_minutes,
                'beneficiaries_assisted' => $a->beneficiaries_assisted,
                'confirmation_status' => $a->confirmation_status,
                'unit_en' => $a->administrativeUnit?->name_en,
            ]);

        $trainings = VolunteerTrainingEnrollment::with(['session.program', 'session.administrativeUnit', 'volunteer'])
            ->whereHas('volunteer', fn ($q) => $q->inJurisdiction($user))
            ->orderByDesc('created_at')
            ->limit(200)
            ->get()
            ->map(fn ($e) => [
                'id' => $e->id,
                'session_code' => $e->session?->session_code,
                'program_title_en' => $e->session?->program?->title_en,
                'program_title_bn' => $e->session?->program?->title_bn,
                'volunteer_code' => $e->volunteer?->volunteer_code,
                'volunteer_name' => $isExternal ? 'Verified Volunteer' : $e->volunteer?->full_name_en,
                'enrollment_status' => $e->enrollment_status,
                'attendance_hours' => $e->attendance_hours,
                'passed' => $e->passed,
                'certificate_no' => $e->certificate_no,
                'issued_at' => $e->issued_at?->toDateString(),
                'expires_at' => $e->expires_at?->toDateString(),
            ]);

        return [
            'volunteer_register' => $volunteers,
            'deployment_history' => $deployments,
            'attendance_history' => $attendances,
            'training_certifications' => $trainings,
        ];
    }

    public function exportCsv(User $user, string $reportType, array $filters = []): StreamedResponse
    {
        $reports = $this->getReports($user, $filters);

        AuditLog::log(
            'VOLUNTEER_REPORT_EXPORTED',
            Volunteer::class,
            null,
            null,
            ['report_type' => $reportType],
            $user
        );

        $rows = match ($reportType) {
            'deployments' => $reports['deployment_history'],
            'attendance' => $reports['attendance_history'],
            'training' => $reports['training_certifications'],
            default => $reports['volunteer_register'],
        };

        $filename = "idms-volunteer-{$reportType}-" . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            // UTF-8 BOM for Bangla Excel support
            fwrite($handle, "\xEF\xBB\xBF");

            $collection = collect($rows);
            if ($collection->isEmpty()) {
                fputcsv($handle, ['message']);
                fputcsv($handle, ['No records found']);
                fclose($handle);
                return;
            }

            $headers = array_keys((array) $collection->first());
            fputcsv($handle, $headers);
            foreach ($collection as $row) {
                fputcsv($handle, array_values((array) $row));
            }
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function applyVolunteerFilters($query, array $filters): void
    {
        $likeOp = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';

        if (!empty($filters['administrative_unit_id'])) {
            $unit = AdministrativeUnit::find($filters['administrative_unit_id']);
            if ($unit) {
                $adminTable = (new AdministrativeUnit())->getTable();
                $volTable = (new Volunteer())->getTable();
                $norm = rtrim($unit->ancestry_path, '/') . '/';
                $query->whereExists(function ($sub) use ($adminTable, $volTable, $norm) {
                    $sub->selectRaw('1')
                        ->from($adminTable)
                        ->whereColumn("{$adminTable}.id", "{$volTable}.administrative_unit_id")
                        ->where("{$adminTable}.ancestry_path", 'like', $norm . '%');
                });
            }
        }
        if (!empty($filters['organization_id'])) {
            $query->where('organization_id', $filters['organization_id']);
        }
        if (!empty($filters['primary_category_id'])) {
            $query->where('primary_category_id', $filters['primary_category_id']);
        }
        if (!empty($filters['skill_id'])) {
            $skillId = $filters['skill_id'];
            $query->whereHas('skillAssignments', fn ($q) => $q->where('skill_id', $skillId));
        }
        if (!empty($filters['overall_verification_status'])) {
            $query->where('overall_verification_status', strtoupper((string) $filters['overall_verification_status']));
        }
        if (!empty($filters['availability_status'])) {
            $query->where('availability_status', strtoupper((string) $filters['availability_status']));
        }
        if (!empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function ($q) use ($search, $likeOp) {
                $q->where('volunteer_code', $likeOp, "%{$search}%")
                    ->orWhere('full_name_en', $likeOp, "%{$search}%")
                    ->orWhere('full_name_bn', 'like', "%{$search}%")
                    ->orWhere('ward_village', $likeOp, "%{$search}%");
            });
        }
    }

    public function volunteerRelations(): array
    {
        return [
            'administrativeUnit',
            'organization',
            'primaryCategory',
            'skillAssignments.skill.category',
            'verificationLogs.actor:id,full_name_en,role_code',
            'deployments.disasterEvent:id,disaster_code,name_en,name_bn',
            'deployments.responseOperation:id,operation_no,title_en,title_bn',
            'deployments.administrativeUnit:id,name_en,name_bn',
            'trainingEnrollments.session.program',
        ];
    }

    public function deploymentRelations(): array
    {
        return [
            'volunteer.organization',
            'volunteer.primaryCategory',
            'disasterEvent',
            'responseOperation',
            'responseTeam',
            'administrativeUnit',
            'attendances',
        ];
    }
}
