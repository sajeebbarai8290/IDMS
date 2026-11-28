<?php

namespace App\Modules\Volunteer\Models;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\User;
use App\Core\Traits\HasJurisdictionScope;
use App\Modules\NidVerification\Models\NidVerification;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class Volunteer extends Model
{
    use HasUuids, SoftDeletes, HasJurisdictionScope;

    public const GENDERS = ['MALE', 'FEMALE', 'OTHER'];

    public const PILLAR_STATUSES = ['UNVERIFIED', 'PENDING', 'VERIFIED', 'REJECTED'];

    public const VERIFICATION_STATUS_DRAFT = 'DRAFT';
    public const VERIFICATION_STATUS_PENDING = 'PENDING_VERIFICATION';
    public const VERIFICATION_STATUS_VERIFIED = 'VERIFIED';
    public const VERIFICATION_STATUS_REJECTED = 'REJECTED';

    public const OVERALL_VERIFICATION_STATUSES = [
        self::VERIFICATION_STATUS_DRAFT,
        self::VERIFICATION_STATUS_PENDING,
        self::VERIFICATION_STATUS_VERIFIED,
        self::VERIFICATION_STATUS_REJECTED,
    ];

    public const AVAILABILITY_AVAILABLE = 'AVAILABLE';
    public const AVAILABILITY_STANDBY = 'STANDBY';
    public const AVAILABILITY_DEPLOYED = 'DEPLOYED';
    public const AVAILABILITY_ON_LEAVE = 'ON_LEAVE';
    public const AVAILABILITY_UNAVAILABLE = 'UNAVAILABLE';
    public const AVAILABILITY_INACTIVE = 'INACTIVE';

    public const AVAILABILITY_STATUSES = [
        self::AVAILABILITY_AVAILABLE,
        self::AVAILABILITY_STANDBY,
        self::AVAILABILITY_DEPLOYED,
        self::AVAILABILITY_ON_LEAVE,
        self::AVAILABILITY_UNAVAILABLE,
        self::AVAILABILITY_INACTIVE,
    ];

    public const DEPLOYABLE_AVAILABILITIES = [
        self::AVAILABILITY_AVAILABLE,
        self::AVAILABILITY_STANDBY,
    ];

    protected $table = 'volunteer.volunteers';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'volunteer.volunteers'
            : 'volunteers';
    }

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'volunteer_code',
        'administrative_unit_id',
        'organization_id',
        'primary_category_id',
        'user_id',
        'full_name_en',
        'full_name_bn',
        'gender',
        'date_of_birth',
        'blood_group',
        'nid_hash',
        'nid_encrypted',
        'nid_masked',
        'mobile_hash',
        'mobile_encrypted',
        'mobile_masked',
        'email_encrypted',
        'email_masked',
        'present_address',
        'permanent_address',
        'ward_village',
        'emergency_contact_name',
        'emergency_contact_relation',
        'emergency_contact_encrypted',
        'emergency_contact_masked',
        'photo_path',
        'photo_original_name',
        'photo_mime',
        'photo_size_bytes',
        'photo_sha256',
        'identity_verification_status',
        'nid_verification_id',
        'organization_verification_status',
        'training_verification_status',
        'overall_verification_status',
        'submitted_by',
        'submitted_at',
        'verified_by',
        'verified_at',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'availability_status',
        'latitude',
        'longitude',
        'location_consent_given',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $hidden = [
        'nid_encrypted',
        'mobile_encrypted',
        'email_encrypted',
        'emergency_contact_encrypted',
        'deleted_at',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'photo_size_bytes' => 'integer',
            'submitted_at' => 'datetime',
            'verified_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'location_consent_given' => 'boolean',
        ];
    }

    public static function hashNid(string $nid): string
    {
        return hash_hmac('sha256', trim($nid), (string) config('app.key'));
    }

    public static function hashMobile(?string $mobile): ?string
    {
        if (!$mobile) {
            return null;
        }
        $normalized = preg_replace('/[^0-9]/', '', $mobile);
        return hash_hmac('sha256', $normalized, (string) config('app.key'));
    }

    public static function maskNid(?string $nid): ?string
    {
        if (!$nid) {
            return null;
        }
        $clean = trim($nid);
        $len = strlen($clean);
        if ($len <= 4) {
            return str_repeat('*', $len);
        }
        $prefixLen = min(4, (int) floor($len / 3));
        $suffixLen = min(3, (int) floor($len / 4));
        return substr($clean, 0, $prefixLen) . str_repeat('*', $len - $prefixLen - $suffixLen) . substr($clean, -$suffixLen);
    }

    public static function maskMobile(?string $mobile): ?string
    {
        if (!$mobile) {
            return null;
        }
        $clean = trim($mobile);
        $len = strlen($clean);
        if ($len <= 5) {
            return str_repeat('*', $len);
        }
        return substr($clean, 0, 3) . str_repeat('*', $len - 6) . substr($clean, -3);
    }

    public static function maskEmail(?string $email): ?string
    {
        if (!$email || !str_contains($email, '@')) {
            return null;
        }
        [$local, $domain] = explode('@', trim($email), 2);
        $visible = strlen($local) > 2 ? substr($local, 0, 2) : substr($local, 0, 1);
        return $visible . '***@' . $domain;
    }

    public function getDecryptedNid(): ?string
    {
        return $this->decryptColumn($this->nid_encrypted);
    }

    public function getDecryptedMobile(): ?string
    {
        return $this->decryptColumn($this->mobile_encrypted);
    }

    public function getDecryptedEmail(): ?string
    {
        return $this->decryptColumn($this->email_encrypted);
    }

    public function getDecryptedEmergencyContact(): ?string
    {
        return $this->decryptColumn($this->emergency_contact_encrypted);
    }

    private function decryptColumn(?string $cipher): ?string
    {
        if (!$cipher) {
            return null;
        }
        try {
            return Crypt::decryptString($cipher);
        } catch (\Throwable) {
            return null;
        }
    }

    public function administrativeUnit(): BelongsTo
    {
        return $this->belongsTo(AdministrativeUnit::class, 'administrative_unit_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(VolunteerOrganization::class, 'organization_id');
    }

    public function primaryCategory(): BelongsTo
    {
        return $this->belongsTo(VolunteerCategory::class, 'primary_category_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function nidVerification(): BelongsTo
    {
        return $this->belongsTo(NidVerification::class, 'nid_verification_id');
    }

    public function skillAssignments(): HasMany
    {
        return $this->hasMany(VolunteerSkillAssignment::class, 'volunteer_id');
    }

    public function verificationLogs(): HasMany
    {
        return $this->hasMany(VolunteerVerificationLog::class, 'volunteer_id')->orderByDesc('created_at');
    }

    public function deployments(): HasMany
    {
        return $this->hasMany(VolunteerDeployment::class, 'volunteer_id')->orderByDesc('start_date');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(VolunteerAttendance::class, 'volunteer_id')->orderByDesc('attendance_date');
    }

    public function trainingEnrollments(): HasMany
    {
        return $this->hasMany(VolunteerTrainingEnrollment::class, 'volunteer_id')->orderByDesc('created_at');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
