<?php

namespace App\Modules\Notification\Services;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\AuditLog;
use App\Core\Models\User;
use App\Core\Services\AdministrativeScopeService;
use App\Modules\Notification\Models\Alert;
use App\Modules\Notification\Models\AlertRule;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AlertEngineService
{
    public function __construct(
        protected AdministrativeScopeService $scopeService,
        protected NotificationService $notificationService
    ) {}

    public function listRules(User $actor, array $filters = []): LengthAwarePaginator
    {
        $query = AlertRule::query()->with(['administrativeUnit'])->inJurisdiction($actor);

        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }
        if (!empty($filters['event_type'])) {
            $query->where('event_type', $filters['event_type']);
        }
        if (!empty($filters['severity'])) {
            $query->where('severity', $filters['severity']);
        }
        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }
        if (!empty($filters['search'])) {
            $term = '%' . trim((string) $filters['search']) . '%';
            $query->where(function (Builder $q) use ($term) {
                $q->where('rule_code', 'like', $term)
                    ->orWhere('name_bn', 'like', $term)
                    ->orWhere('name_en', 'like', $term);
            });
        }

        $perPage = min(100, max(1, (int) ($filters['per_page'] ?? 20)));

        return $query->orderByDesc('created_at')->paginate($perPage);
    }

    public function createRule(array $data, User $actor): AlertRule
    {
        $unitId = $data['administrative_unit_id'] ?? $actor->administrative_unit_id;
        if ($unitId) {
            $unit = AdministrativeUnit::findOrFail($unitId);
            if (!$this->scopeService->canAccessScope($actor, $unit->ancestry_path)) {
                throw new AuthorizationException('Alert rule unit lies outside your jurisdiction.');
            }
        }

        $rule = AlertRule::create([
            'rule_code' => strtoupper(trim($data['rule_code'])),
            'name_bn' => $data['name_bn'],
            'name_en' => $data['name_en'],
            'description_bn' => $data['description_bn'] ?? null,
            'description_en' => $data['description_en'] ?? null,
            'category' => $data['category'],
            'event_type' => $data['event_type'],
            'severity' => $data['severity'] ?? 'HIGH',
            'condition_config' => $data['condition_config'] ?? [],
            'channels' => $data['channels'] ?? ['IN_APP', 'EMAIL'],
            'target_roles' => $data['target_roles'] ?? [],
            'escalation_delay_minutes' => (int) ($data['escalation_delay_minutes'] ?? 30),
            'escalation_role_code' => $data['escalation_role_code'] ?? null,
            'cooldown_minutes' => (int) ($data['cooldown_minutes'] ?? 15),
            'administrative_unit_id' => $unitId,
            'is_active' => $data['is_active'] ?? true,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        AuditLog::log(
            'ALERT_RULE_CREATED',
            AlertRule::class,
            $rule->id,
            null,
            ['rule_code' => $rule->rule_code, 'event_type' => $rule->event_type, 'severity' => $rule->severity],
            $actor,
            $unitId
        );

        return $rule->fresh(['administrativeUnit']);
    }

    public function updateRule(AlertRule $rule, array $data, User $actor): AlertRule
    {
        if ($rule->administrativeUnit && !$this->scopeService->canAccessScope($actor, $rule->administrativeUnit->ancestry_path)) {
            throw new AuthorizationException('Alert rule lies outside your jurisdiction.');
        }

        $old = $rule->only(['severity', 'condition_config', 'escalation_delay_minutes', 'escalation_role_code', 'is_active']);

        $rule->update([
            'name_bn' => $data['name_bn'] ?? $rule->name_bn,
            'name_en' => $data['name_en'] ?? $rule->name_en,
            'description_bn' => array_key_exists('description_bn', $data) ? $data['description_bn'] : $rule->description_bn,
            'description_en' => array_key_exists('description_en', $data) ? $data['description_en'] : $rule->description_en,
            'category' => $data['category'] ?? $rule->category,
            'event_type' => $data['event_type'] ?? $rule->event_type,
            'severity' => $data['severity'] ?? $rule->severity,
            'condition_config' => array_key_exists('condition_config', $data) ? $data['condition_config'] : $rule->condition_config,
            'channels' => $data['channels'] ?? $rule->channels,
            'target_roles' => array_key_exists('target_roles', $data) ? $data['target_roles'] : $rule->target_roles,
            'escalation_delay_minutes' => $data['escalation_delay_minutes'] ?? $rule->escalation_delay_minutes,
            'escalation_role_code' => array_key_exists('escalation_role_code', $data) ? $data['escalation_role_code'] : $rule->escalation_role_code,
            'cooldown_minutes' => $data['cooldown_minutes'] ?? $rule->cooldown_minutes,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $rule->is_active,
            'updated_by' => $actor->id,
        ]);

        AuditLog::log(
            'ALERT_RULE_UPDATED',
            AlertRule::class,
            $rule->id,
            $old,
            $rule->only(['severity', 'condition_config', 'escalation_delay_minutes', 'escalation_role_code', 'is_active']),
            $actor,
            $rule->administrative_unit_id
        );

        return $rule->fresh(['administrativeUnit']);
    }

    public function deleteRule(AlertRule $rule, User $actor): void
    {
        if ($rule->administrativeUnit && !$this->scopeService->canAccessScope($actor, $rule->administrativeUnit->ancestry_path)) {
            throw new AuthorizationException('Alert rule lies outside your jurisdiction.');
        }

        AuditLog::log(
            'ALERT_RULE_DELETED',
            AlertRule::class,
            $rule->id,
            ['rule_code' => $rule->rule_code],
            null,
            $actor,
            $rule->administrative_unit_id
        );

        $rule->delete();
    }

    public function listAlerts(User $actor, array $filters = []): LengthAwarePaginator
    {
        $query = Alert::query()
            ->with(['rule', 'administrativeUnit', 'acknowledgedByUser', 'resolvedByUser'])
            ->inJurisdiction($actor);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['severity'])) {
            $query->where('severity', $filters['severity']);
        }
        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }
        if (!empty($filters['unacknowledged_only']) && filter_var($filters['unacknowledged_only'], FILTER_VALIDATE_BOOLEAN)) {
            $query->whereNull('acknowledged_at')->whereIn('status', [Alert::STATUS_ACTIVE, Alert::STATUS_ESCALATED]);
        }
        if (!empty($filters['search'])) {
            $term = '%' . trim((string) $filters['search']) . '%';
            $query->where(function (Builder $q) use ($term) {
                $q->where('alert_no', 'like', $term)
                    ->orWhere('title_bn', 'like', $term)
                    ->orWhere('title_en', 'like', $term)
                    ->orWhere('source_module', 'like', $term);
            });
        }

        $perPage = min(100, max(1, (int) ($filters['per_page'] ?? 20)));

        return $query->orderByDesc('triggered_at')->paginate($perPage);
    }

    /**
     * Evaluate rule threshold (if rule provided) and trigger a deduplicated alert + notification fanout.
     */
    public function triggerAlert(array $payload, User $actor): array
    {
        if (!empty($payload['idempotency_key'])) {
            $existing = Alert::where('idempotency_key', $payload['idempotency_key'])->first();
            if ($existing) {
                return ['triggered' => true, 'deduplicated' => true, 'alert' => $existing->load(['rule', 'administrativeUnit'])];
            }
        }

        $rule = !empty($payload['alert_rule_id'])
            ? AlertRule::find($payload['alert_rule_id'])
            : (!empty($payload['rule_code']) ? AlertRule::where('rule_code', $payload['rule_code'])->first() : null);

        $unitId = $payload['administrative_unit_id'] ?? $rule?->administrative_unit_id ?? $actor->administrative_unit_id;
        if ($unitId) {
            $unit = AdministrativeUnit::findOrFail($unitId);
            if (!$this->scopeService->canAccessScope($actor, $unit->ancestry_path)) {
                throw new AuthorizationException('Target unit lies outside your jurisdiction.');
            }
        }

        // Optional threshold evaluation when metric_value is supplied against an active rule
        if ($rule && array_key_exists('metric_value', $payload) && !empty($rule->condition_config)) {
            if (!$this->matchesCondition($payload['metric_value'], $rule->condition_config)) {
                return ['triggered' => false, 'reason' => 'Threshold condition not breached.', 'alert' => null];
            }
        }

        // Cooldown guard against duplicate alerts for the same rule & source entity
        if ($rule && $rule->cooldown_minutes > 0) {
            $recentQuery = Alert::where('alert_rule_id', $rule->id)
                ->whereIn('status', [Alert::STATUS_ACTIVE, Alert::STATUS_ESCALATED])
                ->where('triggered_at', '>=', now()->subMinutes($rule->cooldown_minutes));

            if (!empty($payload['source_entity_id'])) {
                $recentQuery->where('source_entity_id', $payload['source_entity_id']);
            }
            if ($unitId) {
                $recentQuery->where('administrative_unit_id', $unitId);
            }

            if ($recentExisting = $recentQuery->first()) {
                return ['triggered' => true, 'deduplicated' => true, 'alert' => $recentExisting->load(['rule', 'administrativeUnit'])];
            }
        }

        $alert = DB::transaction(function () use ($payload, $rule, $unitId, $actor) {
            $alert = Alert::create([
                'alert_no' => $this->generateAlertNumber(),
                'alert_rule_id' => $rule?->id,
                'category' => $payload['category'] ?? ($rule?->category ?? 'EMERGENCY_ALERT'),
                'severity' => $payload['severity'] ?? ($rule?->severity ?? 'HIGH'),
                'status' => Alert::STATUS_ACTIVE,
                'title_bn' => $payload['title_bn'] ?? ($rule?->name_bn ?? 'সতর্কতা'),
                'title_en' => $payload['title_en'] ?? ($rule?->name_en ?? 'Operational Alert'),
                'message_bn' => $payload['message_bn'],
                'message_en' => $payload['message_en'],
                'source_module' => $payload['source_module'] ?? 'Notification',
                'source_entity_type' => $payload['source_entity_type'] ?? null,
                'source_entity_id' => $payload['source_entity_id'] ?? null,
                'metric_payload' => $payload['metric_payload'] ?? (
                    array_key_exists('metric_value', $payload) ? ['value' => $payload['metric_value']] : null
                ),
                'administrative_unit_id' => $unitId,
                'idempotency_key' => $payload['idempotency_key'] ?? null,
                'triggered_at' => now(),
                'created_by' => $actor->id,
            ]);

            AuditLog::log(
                'ALERT_TRIGGERED',
                Alert::class,
                $alert->id,
                null,
                [
                    'alert_no' => $alert->alert_no,
                    'category' => $alert->category,
                    'severity' => $alert->severity,
                    'source_module' => $alert->source_module,
                ],
                $actor,
                $unitId
            );

            return $alert;
        });

        $channels = $payload['channels'] ?? ($rule?->channels ?? ['IN_APP']);
        $targetRoles = $payload['target_roles'] ?? ($rule?->target_roles ?? []);

        if (!empty($targetRoles)) {
            $this->notificationService->sendNotification([
                'category' => $alert->category,
                'severity' => $alert->severity,
                'priority' => $alert->severity === 'CRITICAL' ? 'URGENT' : 'HIGH',
                'channels' => $channels,
                'title_bn' => $alert->title_bn,
                'title_en' => $alert->title_en,
                'message_bn' => $alert->message_bn,
                'message_en' => $alert->message_en,
                'target_type' => 'ROLE',
                'target_criteria' => ['roles' => $targetRoles],
                'administrative_unit_id' => $unitId,
                'related_entity_type' => Alert::class,
                'related_entity_id' => $alert->id,
                'idempotency_key' => 'alert-dispatch-' . $alert->id,
            ], $actor);
        }

        $this->notificationService->bumpDashboardCache();

        return ['triggered' => true, 'deduplicated' => false, 'alert' => $alert->fresh(['rule', 'administrativeUnit'])];
    }

    public function acknowledgeAlert(Alert $alert, User $actor, ?string $notes = null): Alert
    {
        if ($alert->status === Alert::STATUS_RESOLVED) {
            throw ValidationException::withMessages([
                'alert' => ['Resolved alerts cannot be acknowledged.'],
            ]);
        }

        if ($alert->acknowledged_at !== null) {
            throw ValidationException::withMessages([
                'alert' => ['Alert has already been acknowledged.'],
            ]);
        }

        $old = ['status' => $alert->status, 'acknowledged_at' => $alert->acknowledged_at];

        $alert->update([
            'status' => Alert::STATUS_ACKNOWLEDGED,
            'acknowledged_by' => $actor->id,
            'acknowledged_at' => now(),
            'acknowledgement_notes' => $notes,
        ]);

        AuditLog::log(
            'ALERT_ACKNOWLEDGED',
            Alert::class,
            $alert->id,
            $old,
            ['status' => Alert::STATUS_ACKNOWLEDGED, 'acknowledged_by' => $actor->id, 'notes' => $notes],
            $actor,
            $alert->administrative_unit_id
        );

        $this->notificationService->bumpDashboardCache();

        return $alert->fresh(['rule', 'administrativeUnit', 'acknowledgedByUser', 'resolvedByUser']);
    }

    public function resolveAlert(Alert $alert, User $actor, ?string $notes = null): Alert
    {
        if ($alert->status === Alert::STATUS_RESOLVED) {
            throw ValidationException::withMessages([
                'alert' => ['Alert is already resolved.'],
            ]);
        }

        $old = ['status' => $alert->status];

        $alert->update([
            'status' => Alert::STATUS_RESOLVED,
            'acknowledged_by' => $alert->acknowledged_by ?? $actor->id,
            'acknowledged_at' => $alert->acknowledged_at ?? now(),
            'resolved_by' => $actor->id,
            'resolved_at' => now(),
            'resolution_notes' => $notes,
        ]);

        AuditLog::log(
            'ALERT_RESOLVED',
            Alert::class,
            $alert->id,
            $old,
            ['status' => Alert::STATUS_RESOLVED, 'resolved_by' => $actor->id, 'notes' => $notes],
            $actor,
            $alert->administrative_unit_id
        );

        $this->notificationService->bumpDashboardCache();

        return $alert->fresh(['rule', 'administrativeUnit', 'acknowledgedByUser', 'resolvedByUser']);
    }

    public function processPendingEscalations(?User $actor = null): array
    {
        $candidates = Alert::with('rule')
            ->where('status', Alert::STATUS_ACTIVE)
            ->whereNull('acknowledged_at')
            ->whereNull('escalated_at')
            ->whereNotNull('alert_rule_id')
            ->get();

        $escalatedCount = 0;

        foreach ($candidates as $alert) {
            $rule = $alert->rule;
            if (!$rule || empty($rule->escalation_role_code)) {
                continue;
            }

            $delayMinutes = max(1, (int) $rule->escalation_delay_minutes);
            if ($alert->triggered_at->copy()->addMinutes($delayMinutes)->isFuture()) {
                continue;
            }

            $alert->update([
                'status' => Alert::STATUS_ESCALATED,
                'escalated_at' => now(),
                'escalated_to_role' => $rule->escalation_role_code,
            ]);

            $systemActor = $actor ?? User::where('role_code', 'ROLE_NEOC_DIRECTOR')->first() ?? User::first();
            if ($systemActor) {
                $this->notificationService->sendNotification([
                    'category' => $alert->category,
                    'severity' => 'CRITICAL',
                    'priority' => 'URGENT',
                    'channels' => ['IN_APP', 'EMAIL', 'SMS'],
                    'title_bn' => '[এস্কেলেশন] ' . $alert->title_bn,
                    'title_en' => '[ESCALATED] ' . $alert->title_en,
                    'message_bn' => "সতর্কতা {$alert->alert_no} নির্ধারিত {$delayMinutes} মিনিটে গৃহীত না হওয়ায় ঊর্ধ্বতন পর্যায়ে প্রেরণ করা হয়েছে।",
                    'message_en' => "Alert {$alert->alert_no} was not acknowledged within {$delayMinutes} minutes and has been escalated.",
                    'target_type' => 'ROLE',
                    'target_criteria' => ['roles' => [$rule->escalation_role_code]],
                    'administrative_unit_id' => $alert->administrative_unit_id,
                    'related_entity_type' => Alert::class,
                    'related_entity_id' => $alert->id,
                    'idempotency_key' => 'alert-escalation-' . $alert->id,
                ], $systemActor);
            }

            AuditLog::log(
                'ALERT_ESCALATED',
                Alert::class,
                $alert->id,
                ['status' => Alert::STATUS_ACTIVE],
                ['status' => Alert::STATUS_ESCALATED, 'escalated_to_role' => $rule->escalation_role_code],
                $systemActor,
                $alert->administrative_unit_id
            );

            $escalatedCount++;
        }

        if ($escalatedCount > 0) {
            $this->notificationService->bumpDashboardCache();
        }

        return ['checked' => $candidates->count(), 'escalated' => $escalatedCount];
    }

    protected function matchesCondition(mixed $metricValue, array $config): bool
    {
        if (!is_numeric($metricValue) || !isset($config['threshold'])) {
            return true;
        }

        $val = (float) $metricValue;
        $threshold = (float) $config['threshold'];
        $operator = $config['operator'] ?? '>=';

        return match ($operator) {
            '>' => $val > $threshold,
            '>=' => $val >= $threshold,
            '<' => $val < $threshold,
            '<=' => $val <= $threshold,
            '==' => abs($val - $threshold) < 0.0001,
            default => $val >= $threshold,
        };
    }

    protected function generateAlertNumber(): string
    {
        $prefix = 'ALT-' . now()->format('Y') . '-';
        $count = Alert::withTrashed()->count() + 1;
        return $prefix . str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }
}
