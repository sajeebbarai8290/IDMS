<?php

namespace App\Modules\UserManagement\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    use HasFactory, HasUuids;

    public const ACTION_TYPES = [
        'VIEW',
        'CREATE',
        'UPDATE',
        'DELETE',
        'APPROVE',
        'REJECT',
        'VERIFY',
        'EXPORT',
        'DECRYPT_PII',
        'GIS',
        'REPORT',
        'MANAGE',
    ];

    public $timestamps = false;

    protected $table = 'core.permissions';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'core.permissions'
            : 'permissions';
    }

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'code',
        'module_code',
        'name_en',
        'name_bn',
        'action_type',
        'is_sensitive',
        'description',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'is_sensitive' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function roles(): BelongsToMany
    {
        $pivotTable = $this->getConnection()->getDriverName() === 'pgsql'
            ? 'core.role_permissions'
            : 'role_permissions';

        return $this->belongsToMany(Role::class, $pivotTable, 'permission_id', 'role_id')
            ->withPivot('created_at');
    }
}
