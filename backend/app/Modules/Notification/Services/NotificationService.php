<?php

namespace App\Modules\Notification\Services;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\AuditLog;
use App\Core\Models\User;
use App\Core\Services\AdministrativeScopeService;
use App\Modules\Notification\Contracts\PushNotificationInterface;
use App\Modules\Notification\Contracts\SmsGatewayInterface;
use App\Modules\Notification\Jobs\DeliverNotificationDispatchJob;
use App\Modules\Notification\Jobs\DispatchNotificationJob;
use App\Modules\Notification\Mail\NotificationMail;
use App\Modules\Notification\Models\Alert;
use App\Modules\Notification\Models\Notification;
use App\Modules\Notification\Models\NotificationDevice;
use App\Modules\Notification\Models\NotificationDispatch;
use App\Modules\Notification\Models\NotificationPreference;
use App\Modules\Notification\Models\NotificationTemplate;
use App\Modules\UserManagement\Models\Organization;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

class NotificationService
{
    public const DASHBOARD_VERSION_KEY = 'notif:dashboard:version';

    public function __construct(
        protected AdministrativeScopeService $scopeService,
        protected SmsGatewayInterface $smsGateway,
        protected PushNotificationInterface $pushGateway
    ) {}

    public function bumpDashboardCache(): void
    {
        try {
            $current = (int) Cache::get(self::DASHBOARD_VERSION_KEY, 1);
            Cache::forever(self::DASHBOARD_VERSION_KEY, $current + 1);
        } catch (Throwable) {
            // Ignore transient cache failures
        }
    }

    public function getDashboardSummary(User $actor): array
    {
        $version = (int) Cache::get(self::DASHBOARD_VERSION_KEY, 1);
        $scopeKey = $actor->isNationalScope()
            ? 'national'
            : md5(implode('|', $actor->getAccessibleAncestryPaths()));
        $cacheKey = "notif:dashboard:v{$version}:{$actor->id}:{$scopeKey}";

        return Cache::remember($cacheKey, 120, function () use ($actor) {
            $notifQuery = Notification::query()->inJurisdiction($actor);
            $dispatchQuery = NotificationDispatch::query()->inJurisdiction($actor);
            $alertQuery = Alert::query()->inJurisdiction($actor);

            $totalNotifications = (clone $notifQuery)->count();
            $totalDispatches = (clone $dispatchQuery)->count();
            $deliveredDispatches = (clone $dispatchQuery)
                ->whereIn('delivery_status', [NotificationDispatch::STATUS_DELIVERED, NotificationDispatch::STATUS_SENT])
                ->count();
            $failedDispatches = (clone $dispatchQuery)
                ->where('delivery_status', NotificationDispatch::STATUS_FAILED)
                ->count();
            $pendingDispatches = (clone $dispatchQuery)
                ->whereIn('delivery_status', [NotificationDispatch::STATUS_QUEUED, NotificationDispatch::STATUS_RETRYING])
                ->count();

            $activeAlerts = (clone $alertQuery)
                ->whereIn('status', [Alert::STATUS_ACTIVE, Alert::STATUS_ESCALATED])
                ->count();
            $unacknowledgedAlerts = (clone $alertQuery)
                ->whereNull('acknowledged_at')
                ->whereIn('status', [Alert::STATUS_ACTIVE, Alert::STATUS_ESCALATED])
                ->count();
            $escalatedAlerts = (clone $alertQuery)->where('status', Alert::STATUS_ESCALATED)->count();
            $resolvedAlerts = (clone $alertQuery)->where('status', Alert::STATUS_RESOLVED)->count();

            $unreadInbox = NotificationDispatch::query()
                ->where('recipient_user_id', $actor->id)
                ->where('channel', 'IN_APP')
                ->whereNull('read_at')
                ->count();

            $byChannel = (clone $dispatchQuery)
                ->selectRaw('channel, delivery_status, COUNT(*) as aggregate')
                ->groupBy('channel', 'delivery_status')
                ->get()
                ->groupBy('channel')
                ->map(function ($rows, $channel) {
                    return [
                        'channel' => $channel,
                        'total' => (int) $rows->sum('aggregate'),
                        'delivered' => (int) $rows->whereIn('delivery_status', [NotificationDispatch::STATUS_DELIVERED, NotificationDispatch::STATUS_SENT])->sum('aggregate'),
                        'failed' => (int) $rows->where('delivery_status', NotificationDispatch::STATUS_FAILED)->sum('aggregate'),
                        'pending' => (int) $rows->whereIn('delivery_status', [NotificationDispatch::STATUS_QUEUED, NotificationDispatch::STATUS_RETRYING])->sum('aggregate'),
                    ];
                })
                ->values()
                ->all();

            $byCategory = (clone $notifQuery)
                ->selectRaw('category, COUNT(*) as total')
                ->groupBy('category')
                ->orderByDesc('total')
                ->get()
                ->map(fn ($row) => ['category' => $row->category, 'total' => (int) $row->total])
                ->all();

            $bySeverity = (clone $notifQuery)
                ->selectRaw('severity, COUNT(*) as total')
                ->groupBy('severity')
                ->get()
                ->map(fn ($row) => ['severity' => $row->severity, 'total' => (int) $row->total])
                ->all();

            $recentNotifications = (clone $notifQuery)
                ->with(['administrativeUnit', 'creator'])
                ->orderByDesc('created_at')
                ->limit(6)
                ->get();

            $recentAlerts = (clone $alertQuery)
                ->with(['administrativeUnit', 'rule'])
                ->orderByDesc('triggered_at')
                ->limit(6)
                ->get();

            return [
                'kpis' => [
                    'total_notifications' => $totalNotifications,
                    'total_dispatches' => $totalDispatches,
                    'delivered_notifications' => $deliveredDispatches,
                    'failed_notifications' => $failedDispatches,
                    'pending_notifications' => $pendingDispatches,
                    'active_alerts' => $activeAlerts,
                    'unacknowledged_alerts' => $unacknowledgedAlerts,
                    'escalated_alerts' => $escalatedAlerts,
                    'resolved_alerts' => $resolvedAlerts,
                    'unread_in_app' => $unreadInbox,
                ],
                'by_channel' => $byChannel,
                'by_category' => $byCategory,
                'by_severity' => $bySeverity,
                'recent_notifications' => $recentNotifications,
                'recent_alerts' => $recentAlerts,
                'provider_health' => [
                    'sms' => $this->smsGateway->healthCheck(),
                    'push' => $this->pushGateway->healthCheck(),
                    'email' => [
                        'driver' => (string) config('mail.default', 'log'),
                        'configured' => !empty(config('mail.from.address')),
                        'status' => 'READY',
                    ],
                ],
            ];
        });
    }

    public function listTemplates(array $filters = []): LengthAwarePaginator
    {
        $query = NotificationTemplate::query();

        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }
        if (!empty($filters['channel'])) {
            $query->where('channel', $filters['channel']);
        }
        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }
        if (!empty($filters['search'])) {
            $term = '%' . trim((string) $filters['search']) . '%';
            $query->where(function (Builder $q) use ($term) {
                $q->where('template_code', 'like', $term)
                    ->orWhere('title_en', 'like', $term)
                    ->orWhere('title_bn', 'like', $term);
            });
        }

        $perPage = min(100, max(1, (int) ($filters['per_page'] ?? 20)));

        return $query->orderBy('category')->orderBy('template_code')->paginate($perPage);
    }

    public function createTemplate(array $data, User $actor): NotificationTemplate
    {
        $template = NotificationTemplate::create([
            'template_code' => strtoupper(trim($data['template_code'])),
            'category' => $data['category'],
            'channel' => $data['channel'],
            'default_severity' => $data['default_severity'] ?? 'INFO',
            'title_bn' => $data['title_bn'],
            'title_en' => $data['title_en'],
            'subject_bn' => $data['subject_bn'] ?? $data['title_bn'],
            'subject_en' => $data['subject_en'] ?? $data['title_en'],
            'body_bn' => $data['body_bn'],
            'body_en' => $data['body_en'],
            'variables' => $data['variables'] ?? [],
            'is_system' => false,
            'is_active' => $data['is_active'] ?? true,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        AuditLog::log(
            'NOTIFICATION_TEMPLATE_CREATED',
            NotificationTemplate::class,
            $template->id,
            null,
            ['template_code' => $template->template_code, 'category' => $template->category],
            $actor
        );

        return $template;
    }

    public function updateTemplate(NotificationTemplate $template, array $data, User $actor): NotificationTemplate
    {
        $old = $template->only(['category', 'channel', 'default_severity', 'title_bn', 'title_en', 'is_active']);

        $template->update([
            'category' => $data['category'] ?? $template->category,
            'channel' => $data['channel'] ?? $template->channel,
            'default_severity' => $data['default_severity'] ?? $template->default_severity,
            'title_bn' => $data['title_bn'] ?? $template->title_bn,
            'title_en' => $data['title_en'] ?? $template->title_en,
            'subject_bn' => $data['subject_bn'] ?? $template->subject_bn,
            'subject_en' => $data['subject_en'] ?? $template->subject_en,
            'body_bn' => $data['body_bn'] ?? $template->body_bn,
            'body_en' => $data['body_en'] ?? $template->body_en,
            'variables' => array_key_exists('variables', $data) ? $data['variables'] : $template->variables,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $template->is_active,
            'updated_by' => $actor->id,
        ]);

        AuditLog::log(
            'NOTIFICATION_TEMPLATE_UPDATED',
            NotificationTemplate::class,
            $template->id,
            $old,
            $template->only(['category', 'channel', 'default_severity', 'title_bn', 'title_en', 'is_active']),
            $actor
        );

        return $template->fresh();
    }

    public function deleteTemplate(NotificationTemplate $template, User $actor): void
    {
        if ($template->is_system) {
            throw ValidationException::withMessages([
                'template' => ['System notification templates cannot be deleted.'],
            ]);
        }

        AuditLog::log(
            'NOTIFICATION_TEMPLATE_DELETED',
            NotificationTemplate::class,
            $template->id,
            ['template_code' => $template->template_code],
            null,
            $actor
        );

        $template->delete();
    }

    public function listNotifications(User $actor, array $filters = []): LengthAwarePaginator
    {
        $query = Notification::query()
            ->with(['template', 'administrativeUnit', 'creator'])
            ->inJurisdiction($actor);

        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }
        if (!empty($filters['severity'])) {
            $query->where('severity', $filters['severity']);
        }
        if (!empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['search'])) {
            $term = '%' . trim((string) $filters['search']) . '%';
            $query->where(function (Builder $q) use ($term) {
                $q->where('notification_no', 'like', $term)
                    ->orWhere('title_bn', 'like', $term)
                    ->orWhere('title_en', 'like', $term)
                    ->orWhere('message_bn', 'like', $term)
                    ->orWhere('message_en', 'like', $term);
            });
        }

        $sortBy = in_array($filters['sort_by'] ?? '', ['created_at', 'scheduled_at', 'dispatched_at', 'severity', 'priority'], true)
            ? $filters['sort_by']
            : 'created_at';
        $sortDir = strtolower((string) ($filters['sort_dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        $perPage = min(100, max(1, (int) ($filters['per_page'] ?? 20)));

        return $query->orderBy($sortBy, $sortDir)->paginate($perPage);
    }

    /**
     * Create, target, deduplicate, and schedule/dispatch a notification campaign.
     */
    public function sendNotification(array $payload, User $actor): Notification
    {
        if (!empty($payload['idempotency_key'])) {
            $existing = Notification::where('idempotency_key', $payload['idempotency_key'])->first();
            if ($existing) {
                return $existing->load(['template', 'administrativeUnit', 'dispatches']);
            }
        }

        $template = null;
        if (!empty($payload['template_id'])) {
            $template = NotificationTemplate::where('is_active', true)->find($payload['template_id']);
            if (!$template) {
                throw ValidationException::withMessages([
                    'template_id' => ['Selected notification template was not found or is inactive.'],
                ]);
            }
        }

        $rendered = $template
            ? $template->render($payload['template_variables'] ?? [])
            : [];

        $titleBn = $payload['title_bn'] ?? $rendered['title_bn'] ?? null;
        $titleEn = $payload['title_en'] ?? $rendered['title_en'] ?? null;
        $messageBn = $payload['message_bn'] ?? $rendered['body_bn'] ?? null;
        $messageEn = $payload['message_en'] ?? $rendered['body_en'] ?? null;

        if (empty($titleBn) || empty($titleEn) || empty($messageBn) || empty($messageEn)) {
            throw ValidationException::withMessages([
                'message' => ['Bilingual titles and messages (Bangla and English) are required.'],
            ]);
        }

        $targetUnitId = $this->resolveAndAuthorizeAdminUnit($payload, $actor);
        $targetType = $payload['target_type'] ?? 'USER';
        $criteria = $payload['target_criteria'] ?? [];

        if ($targetType === 'USER' && empty(array_filter((array) ($criteria['user_ids'] ?? [$criteria['user_id'] ?? null])))) {
            throw ValidationException::withMessages(['target_criteria' => ['At least one target user is required.']]);
        }
        if ($targetType === 'ROLE' && empty(array_filter((array) ($criteria['roles'] ?? [$criteria['role_code'] ?? null])))) {
            throw ValidationException::withMessages(['target_criteria' => ['At least one target role is required.']]);
        }
        if ($targetType === 'ORGANIZATION' && empty($criteria['organization_id'])) {
            throw ValidationException::withMessages(['target_criteria' => ['Target organization is required.']]);
        }

        $channels = array_values(array_unique($payload['channels'] ?? ['IN_APP']));
        $severity = $payload['severity'] ?? ($template?->default_severity ?? 'INFO');
        $priority = $payload['priority'] ?? ($severity === 'CRITICAL' ? 'URGENT' : 'NORMAL');
        $scheduledAt = !empty($payload['scheduled_at']) ? now()->parse($payload['scheduled_at']) : null;
        $isScheduledFuture = $scheduledAt !== null && $scheduledAt->isFuture();

        // Pre-validate target permissions and resolve recipient list before inserting
        $recipients = $this->resolveRecipients(
            $targetType,
            $payload['target_criteria'] ?? [],
            $targetUnitId,
            $actor
        );

        $notification = DB::transaction(function () use (
            $payload,
            $template,
            $titleBn,
            $titleEn,
            $messageBn,
            $messageEn,
            $targetType,
            $targetUnitId,
            $channels,
            $severity,
            $priority,
            $scheduledAt,
            $isScheduledFuture,
            $recipients,
            $actor
        ) {
            $notification = Notification::create([
                'notification_no' => $this->generateNotificationNumber(),
                'template_id' => $template?->id,
                'category' => $payload['category'] ?? ($template?->category ?? NotificationTemplate::CATEGORY_SYSTEM_ANNOUNCEMENT),
                'severity' => $severity,
                'priority' => $priority,
                'channels' => $channels,
                'title_bn' => $titleBn,
                'title_en' => $titleEn,
                'message_bn' => $messageBn,
                'message_en' => $messageEn,
                'target_type' => $targetType,
                'target_criteria' => $payload['target_criteria'] ?? [],
                'administrative_unit_id' => $targetUnitId,
                'related_entity_type' => $payload['related_entity_type'] ?? null,
                'related_entity_id' => $payload['related_entity_id'] ?? null,
                'status' => $isScheduledFuture ? Notification::STATUS_SCHEDULED : Notification::STATUS_QUEUED,
                'idempotency_key' => $payload['idempotency_key'] ?? null,
                'scheduled_at' => $scheduledAt,
                'total_recipients' => $recipients->count(),
                'metadata' => $payload['metadata'] ?? null,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            AuditLog::log(
                'NOTIFICATION_CREATED',
                Notification::class,
                $notification->id,
                null,
                [
                    'notification_no' => $notification->notification_no,
                    'category' => $notification->category,
                    'severity' => $notification->severity,
                    'target_type' => $notification->target_type,
                    'channels' => $notification->channels,
                    'total_recipients' => $notification->total_recipients,
                    'status' => $notification->status,
                ],
                $actor,
                $targetUnitId
            );

            return $notification;
        });

        if (!$isScheduledFuture) {
            $queueName = in_array($priority, ['URGENT', 'HIGH'], true) || $severity === 'CRITICAL'
                ? 'notifications-urgent'
                : 'notifications-default';

            DispatchNotificationJob::dispatch($notification->id)->onQueue($queueName);
        }

        $this->bumpDashboardCache();

        return $notification->fresh(['template', 'administrativeUnit', 'dispatches']);
    }

    /**
     * Execute fan-out creation of per-recipient channel dispatches and queue delivery jobs.
     */
    public function processNotificationFanout(string $notificationId): void
    {
        $notification = Notification::find($notificationId);
        if (!$notification || $notification->status === Notification::STATUS_CANCELLED) {
            return;
        }

        $notification->update(['status' => Notification::STATUS_PROCESSING]);

        $recipients = $this->resolveRecipientsInternal(
            $notification->target_type,
            $notification->target_criteria ?? [],
            $notification->administrative_unit_id
        );

        $notification->update(['total_recipients' => $recipients->count()]);

        if ($recipients->isEmpty()) {
            $notification->update([
                'status' => Notification::STATUS_DISPATCHED,
                'dispatched_at' => now(),
            ]);
            $this->bumpDashboardCache();
            return;
        }

        $queueName = in_array($notification->priority, ['URGENT', 'HIGH'], true) || $notification->severity === 'CRITICAL'
            ? 'notifications-urgent'
            : 'notifications-default';

        $dispatchesToQueue = [];

        $recipientIds = $recipients->pluck('id')->all();
        $preferencesByUser = NotificationPreference::whereIn('user_id', $recipientIds)
            ->where('category', $notification->category)
            ->get()
            ->keyBy('user_id');

        $existingDedupHashes = array_flip(
            NotificationDispatch::where('notification_id', $notification->id)
                ->pluck('dedup_hash')
                ->filter()
                ->all()
        );

        foreach ($recipients as $recipient) {
            $preference = $preferencesByUser->get($recipient->id);

            foreach ($notification->channels as $channel) {
                $dedupHash = hash('sha256', $notification->id . ':' . $recipient->id . ':' . $channel);
                if (isset($existingDedupHashes[$dedupHash])) {
                    continue;
                }
                $existingDedupHashes[$dedupHash] = true;

                $allowed = $preference
                    ? $preference->allowsChannel($channel, $notification->severity)
                    : true;

                $rawAddress = $this->resolveRawDestination($recipient, $channel);
                $maskedAddress = NotificationDispatch::maskDestination($rawAddress, $channel);

                if (!$allowed) {
                    NotificationDispatch::create([
                        'notification_id' => $notification->id,
                        'template_id' => $notification->template_id,
                        'recipient_user_id' => $recipient->id,
                        'administrative_unit_id' => $recipient->administrative_unit_id ?? $notification->administrative_unit_id,
                        'channel' => $channel,
                        'destination_address' => $maskedAddress,
                        'title_bn' => $notification->title_bn,
                        'title_en' => $notification->title_en,
                        'message_body' => $notification->message_bn,
                        'message_body_bn' => $notification->message_bn,
                        'message_body_en' => $notification->message_en,
                        'delivery_status' => NotificationDispatch::STATUS_SKIPPED,
                        'provider_name' => 'preference_policy',
                        'last_error' => 'Channel disabled by user notification preference.',
                        'dedup_hash' => $dedupHash,
                    ]);
                    continue;
                }

                $dispatch = NotificationDispatch::create([
                    'notification_id' => $notification->id,
                    'template_id' => $notification->template_id,
                    'recipient_user_id' => $recipient->id,
                    'administrative_unit_id' => $recipient->administrative_unit_id ?? $notification->administrative_unit_id,
                    'channel' => $channel,
                    'destination_address' => $maskedAddress,
                    'title_bn' => $notification->title_bn,
                    'title_en' => $notification->title_en,
                    'message_body' => $notification->message_bn,
                    'message_body_bn' => $notification->message_bn,
                    'message_body_en' => $notification->message_en,
                    'delivery_status' => NotificationDispatch::STATUS_QUEUED,
                    'dedup_hash' => $dedupHash,
                ]);

                $dispatchesToQueue[] = $dispatch->id;
            }
        }

        foreach ($dispatchesToQueue as $dispatchId) {
            DeliverNotificationDispatchJob::dispatch($dispatchId)->onQueue($queueName);
        }

        $this->refreshNotificationRollup($notification->id);
    }

    /**
     * Deliver a single NotificationDispatch record through its channel adapter.
     */
    public function deliverDispatch(string $dispatchId, int $attempt = 1, int $maxAttempts = 3): NotificationDispatch
    {
        $dispatch = NotificationDispatch::with(['notification', 'recipient'])->findOrFail($dispatchId);
        $notification = $dispatch->notification;
        $recipient = $dispatch->recipient;

        if (in_array($dispatch->delivery_status, [NotificationDispatch::STATUS_DELIVERED, NotificationDispatch::STATUS_SKIPPED], true)) {
            return $dispatch;
        }

        $attemptCount = max($attempt, $dispatch->attempt_count + 1);

        try {
            $result = match ($dispatch->channel) {
                'IN_APP' => [
                    'status' => NotificationDispatch::STATUS_DELIVERED,
                    'provider' => 'in_app',
                    'reference' => 'INAPP-' . strtoupper(substr(str_replace('-', '', $dispatch->id), 0, 12)),
                ],
                'EMAIL' => $this->deliverEmail($dispatch, $recipient, $notification),
                'SMS' => $this->deliverSms($dispatch, $recipient, $notification),
                'PUSH' => $this->deliverPush($dispatch, $recipient, $notification),
                default => throw new \RuntimeException("Unsupported notification channel: {$dispatch->channel}"),
            };

            $dispatch->update([
                'delivery_status' => $result['status'],
                'provider_name' => $result['provider'],
                'provider_reference' => $result['reference'],
                'attempt_count' => $attemptCount,
                'last_error' => null,
                'dispatched_at' => $dispatch->dispatched_at ?? now(),
                'delivered_at' => $result['status'] === NotificationDispatch::STATUS_DELIVERED ? now() : null,
            ]);
        } catch (Throwable $e) {
            $isRetrying = $attempt < $maxAttempts;
            $dispatch->update([
                'delivery_status' => $isRetrying ? NotificationDispatch::STATUS_RETRYING : NotificationDispatch::STATUS_FAILED,
                'attempt_count' => $attemptCount,
                'last_error' => mb_substr($e->getMessage(), 0, 500),
                'dispatched_at' => $dispatch->dispatched_at ?? now(),
            ]);

            $this->refreshNotificationRollup($dispatch->notification_id);

            if ($isRetrying) {
                throw $e;
            }

            return $dispatch->fresh();
        }

        $this->refreshNotificationRollup($dispatch->notification_id);

        return $dispatch->fresh();
    }

    protected function deliverEmail(NotificationDispatch $dispatch, ?User $recipient, ?Notification $notification): array
    {
        if (!$recipient || empty($recipient->email)) {
            throw new \RuntimeException('Recipient email address is unavailable.');
        }

        Mail::to($recipient->email)->send(new NotificationMail(
            subjectLine: ($notification?->title_bn ?? $dispatch->title_bn ?? 'IDMS Notification') . ' | ' . ($notification?->title_en ?? $dispatch->title_en ?? ''),
            bodyBn: $dispatch->message_body_bn ?? $dispatch->message_body,
            bodyEn: $dispatch->message_body_en ?? $dispatch->message_body,
            severity: $notification?->severity ?? 'INFO',
            category: $notification?->category ?? 'SYSTEM_ANNOUNCEMENT'
        ));

        return [
            'status' => NotificationDispatch::STATUS_DELIVERED,
            'provider' => 'laravel_mail:' . config('mail.default', 'log'),
            'reference' => 'MAIL-' . strtoupper(substr(str_replace('-', '', $dispatch->id), 0, 12)),
        ];
    }

    protected function deliverSms(NotificationDispatch $dispatch, ?User $recipient, ?Notification $notification): array
    {
        if (!$recipient || empty($recipient->mobile_number)) {
            throw new \RuntimeException('Recipient mobile number is unavailable.');
        }

        $smsText = ($notification?->title_bn ?? '') . ': ' . ($dispatch->message_body_bn ?? $dispatch->message_body);

        return $this->smsGateway->sendSms($recipient->mobile_number, $smsText, [
            'dispatch_id' => $dispatch->id,
            'notification_id' => $dispatch->notification_id,
            'severity' => $notification?->severity,
        ]);
    }

    protected function deliverPush(NotificationDispatch $dispatch, ?User $recipient, ?Notification $notification): array
    {
        if (!$recipient) {
            throw new \RuntimeException('Recipient user record not found for push delivery.');
        }

        $devices = NotificationDevice::where('user_id', $recipient->id)
            ->where('is_active', true)
            ->get();

        if ($devices->isEmpty()) {
            throw new \RuntimeException('No active Flutter/mobile push device registered for recipient.');
        }

        $lastResult = null;
        foreach ($devices as $device) {
            $token = $device->getPlainPushToken();
            if (empty($token)) {
                continue;
            }
            $lastResult = $this->pushGateway->sendPush(
                $token,
                $device->platform,
                $dispatch->title_bn ?? $dispatch->title_en ?? 'IDMS Alert',
                $dispatch->message_body_bn ?? $dispatch->message_body,
                [
                    'notification_id' => $dispatch->notification_id,
                    'category' => $notification?->category,
                    'severity' => $notification?->severity,
                    'priority' => $notification?->priority ?? 'NORMAL',
                    'related_entity_type' => $notification?->related_entity_type,
                    'related_entity_id' => $notification?->related_entity_id,
                ]
            );
            $device->update(['last_seen_at' => now()]);
        }

        if (!$lastResult) {
            throw new \RuntimeException('Registered push devices have no valid encrypted tokens.');
        }

        return $lastResult;
    }

    public function refreshNotificationRollup(string $notificationId): void
    {
        $notification = Notification::find($notificationId);
        if (!$notification) {
            return;
        }

        $delivered = NotificationDispatch::where('notification_id', $notificationId)
            ->whereIn('delivery_status', [NotificationDispatch::STATUS_DELIVERED, NotificationDispatch::STATUS_SENT])
            ->count();

        $failed = NotificationDispatch::where('notification_id', $notificationId)
            ->where('delivery_status', NotificationDispatch::STATUS_FAILED)
            ->count();

        $pending = NotificationDispatch::where('notification_id', $notificationId)
            ->whereIn('delivery_status', [NotificationDispatch::STATUS_QUEUED, NotificationDispatch::STATUS_RETRYING])
            ->count();

        $status = $notification->status;
        if ($pending === 0) {
            if ($failed > 0 && $delivered === 0) {
                $status = Notification::STATUS_FAILED;
            } elseif ($failed > 0 && $delivered > 0) {
                $status = Notification::STATUS_PARTIALLY_FAILED;
            } else {
                $status = Notification::STATUS_DISPATCHED;
            }
        }

        $notification->update([
            'delivered_count' => $delivered,
            'failed_count' => $failed,
            'status' => $status,
            'dispatched_at' => $notification->dispatched_at ?? now(),
        ]);

        $this->bumpDashboardCache();
    }

    public function retryDispatch(NotificationDispatch $dispatch, User $actor): NotificationDispatch
    {
        if ($dispatch->delivery_status !== NotificationDispatch::STATUS_FAILED) {
            throw ValidationException::withMessages([
                'dispatch' => ['Only FAILED notification dispatches can be retried.'],
            ]);
        }

        $dispatch->update([
            'delivery_status' => NotificationDispatch::STATUS_QUEUED,
            'last_error' => null,
        ]);

        DeliverNotificationDispatchJob::dispatch($dispatch->id)->onQueue('notifications-urgent');

        AuditLog::log(
            'NOTIFICATION_DISPATCH_RETRIED',
            NotificationDispatch::class,
            $dispatch->id,
            ['delivery_status' => NotificationDispatch::STATUS_FAILED],
            ['delivery_status' => NotificationDispatch::STATUS_QUEUED],
            $actor,
            $dispatch->administrative_unit_id
        );

        $this->bumpDashboardCache();

        return $dispatch->fresh(['notification', 'recipient', 'administrativeUnit']);
    }

    public function processScheduledNotifications(): int
    {
        $due = Notification::where('status', Notification::STATUS_SCHEDULED)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->get();

        $count = 0;
        foreach ($due as $notification) {
            $notification->update(['status' => Notification::STATUS_QUEUED]);
            $queueName = in_array($notification->priority, ['URGENT', 'HIGH'], true) || $notification->severity === 'CRITICAL'
                ? 'notifications-urgent'
                : 'notifications-default';
            DispatchNotificationJob::dispatch($notification->id)->onQueue($queueName);
            $count++;
        }

        if ($count > 0) {
            $this->bumpDashboardCache();
        }

        return $count;
    }

    public function listInbox(User $actor, array $filters = []): LengthAwarePaginator
    {
        $query = NotificationDispatch::query()
            ->with(['notification', 'administrativeUnit'])
            ->where('recipient_user_id', $actor->id)
            ->where('channel', 'IN_APP');

        if (!empty($filters['unread_only']) && filter_var($filters['unread_only'], FILTER_VALIDATE_BOOLEAN)) {
            $query->whereNull('read_at');
        }
        if (!empty($filters['category'])) {
            $query->whereHas('notification', fn (Builder $q) => $q->where('category', $filters['category']));
        }
        if (!empty($filters['severity'])) {
            $query->whereHas('notification', fn (Builder $q) => $q->where('severity', $filters['severity']));
        }
        if (!empty($filters['search'])) {
            $term = '%' . trim((string) $filters['search']) . '%';
            $query->where(function (Builder $q) use ($term) {
                $q->where('title_bn', 'like', $term)
                    ->orWhere('title_en', 'like', $term)
                    ->orWhere('message_body_bn', 'like', $term)
                    ->orWhere('message_body_en', 'like', $term);
            });
        }

        $perPage = min(100, max(1, (int) ($filters['per_page'] ?? 20)));

        return $query->orderByDesc('created_at')->paginate($perPage);
    }

    public function markInboxRead(NotificationDispatch $dispatch, User $actor): NotificationDispatch
    {
        if ($dispatch->recipient_user_id !== $actor->id) {
            throw new AuthorizationException('You may only mark your own notifications as read.');
        }

        if ($dispatch->read_at === null) {
            $dispatch->update(['read_at' => now()]);
            $this->bumpDashboardCache();
        }

        return $dispatch->fresh(['notification', 'administrativeUnit']);
    }

    public function markAllInboxRead(User $actor): int
    {
        $updated = NotificationDispatch::where('recipient_user_id', $actor->id)
            ->where('channel', 'IN_APP')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        if ($updated > 0) {
            $this->bumpDashboardCache();
        }

        return $updated;
    }

    public function listDispatches(User $actor, array $filters = []): LengthAwarePaginator
    {
        $query = NotificationDispatch::query()
            ->with(['notification', 'recipient', 'administrativeUnit'])
            ->inJurisdiction($actor);

        if (!empty($filters['notification_id'])) {
            $query->where('notification_id', $filters['notification_id']);
        }
        if (!empty($filters['channel'])) {
            $query->where('channel', $filters['channel']);
        }
        if (!empty($filters['delivery_status'])) {
            $query->where('delivery_status', $filters['delivery_status']);
        }
        if (!empty($filters['search'])) {
            $term = '%' . trim((string) $filters['search']) . '%';
            $query->where(function (Builder $q) use ($term) {
                $q->where('title_bn', 'like', $term)
                    ->orWhere('title_en', 'like', $term)
                    ->orWhere('provider_reference', 'like', $term);
            });
        }

        $perPage = min(100, max(1, (int) ($filters['per_page'] ?? 20)));

        return $query->orderByDesc('created_at')->paginate($perPage);
    }

    public function getPreferences(User $actor, ?User $targetUser = null): array
    {
        $subject = $targetUser ?? $actor;

        if ($subject->id !== $actor->id) {
            $this->authorizeTargetUserAccess($actor, $subject);
        }

        $existing = NotificationPreference::where('user_id', $subject->id)
            ->get()
            ->keyBy('category');

        $rows = [];
        foreach (NotificationTemplate::CATEGORIES as $category) {
            $isCriticalLocked = in_array($category, NotificationPreference::CRITICAL_LOCKED_CATEGORIES, true);
            $pref = $existing->get($category);

            if (!$pref) {
                $pref = NotificationPreference::create([
                    'user_id' => $subject->id,
                    'category' => $category,
                    'in_app_enabled' => true,
                    'email_enabled' => true,
                    'sms_enabled' => $isCriticalLocked,
                    'push_enabled' => true,
                    'policy_locked' => $isCriticalLocked,
                ]);
            }

            $rows[] = $pref;
        }

        return $rows;
    }

    public function updatePreferences(User $actor, array $items, ?User $targetUser = null): array
    {
        $subject = $targetUser ?? $actor;

        if ($subject->id !== $actor->id) {
            $this->authorizeTargetUserAccess($actor, $subject);
        }

        $canManagePolicy = $actor->hasPermission('notification.preference.manage');

        foreach ($items as $item) {
            $category = $item['category'];
            $isCriticalCategory = in_array($category, NotificationPreference::CRITICAL_LOCKED_CATEGORIES, true);

            $pref = NotificationPreference::firstOrNew([
                'user_id' => $subject->id,
                'category' => $category,
            ]);

            if (($pref->policy_locked || $isCriticalCategory) && !$canManagePolicy) {
                // Critical emergency channels (IN_APP and SMS) cannot be disabled by standard users
                if (
                    (array_key_exists('in_app_enabled', $item) && !$item['in_app_enabled']) ||
                    (array_key_exists('sms_enabled', $item) && !$item['sms_enabled'])
                ) {
                    throw ValidationException::withMessages([
                        'preferences' => ["Critical emergency notification channels (IN_APP, SMS) for {$category} are locked by MoDMR policy."],
                    ]);
                }
            }

            $inApp = array_key_exists('in_app_enabled', $item) ? (bool) $item['in_app_enabled'] : ($pref->in_app_enabled ?? true);
            $sms = array_key_exists('sms_enabled', $item) ? (bool) $item['sms_enabled'] : ($pref->sms_enabled ?? $isCriticalCategory);

            if ($isCriticalCategory) {
                $inApp = true;
                $sms = true;
            }

            $pref->fill([
                'in_app_enabled' => $inApp,
                'email_enabled' => array_key_exists('email_enabled', $item) ? (bool) $item['email_enabled'] : ($pref->email_enabled ?? true),
                'sms_enabled' => $sms,
                'push_enabled' => array_key_exists('push_enabled', $item) ? (bool) $item['push_enabled'] : ($pref->push_enabled ?? true),
                'quiet_hours_start' => $item['quiet_hours_start'] ?? $pref->quiet_hours_start,
                'quiet_hours_end' => $item['quiet_hours_end'] ?? $pref->quiet_hours_end,
                'policy_locked' => $isCriticalCategory
                    ? true
                    : ($canManagePolicy && array_key_exists('policy_locked', $item) ? (bool) $item['policy_locked'] : ($pref->policy_locked ?? false)),
                'updated_by' => $actor->id,
            ]);
            $pref->save();
        }

        AuditLog::log(
            'NOTIFICATION_PREFERENCES_UPDATED',
            NotificationPreference::class,
            $subject->id,
            null,
            ['target_user_id' => $subject->id, 'categories_updated' => count($items)],
            $actor,
            $subject->administrative_unit_id
        );

        return $this->getPreferences($actor, $subject);
    }

    public function registerDevice(User $actor, array $payload): NotificationDevice
    {
        $device = NotificationDevice::firstOrNew([
            'user_id' => $actor->id,
            'device_uuid' => trim($payload['device_uuid']),
        ]);

        $device->fill([
            'platform' => strtoupper($payload['platform']),
            'push_provider' => strtoupper($payload['push_provider'] ?? ($payload['platform'] === 'IOS' ? 'APNS' : 'FCM')),
            'device_name' => $payload['device_name'] ?? null,
            'app_version' => $payload['app_version'] ?? null,
            'locale' => $payload['locale'] ?? 'bn',
            'is_active' => true,
            'last_seen_at' => now(),
        ]);
        $device->setPlainPushToken(trim($payload['push_token']));
        $device->save();

        AuditLog::log(
            'NOTIFICATION_DEVICE_REGISTERED',
            NotificationDevice::class,
            $device->id,
            null,
            ['platform' => $device->platform, 'push_provider' => $device->push_provider],
            $actor
        );

        return $device;
    }

    public function listDevices(User $actor): array
    {
        return NotificationDevice::where('user_id', $actor->id)
            ->orderByDesc('last_seen_at')
            ->get()
            ->all();
    }

    public function deactivateDevice(User $actor, NotificationDevice $device): void
    {
        if ($device->user_id !== $actor->id && !$actor->isNationalScope()) {
            throw new AuthorizationException('Cannot revoke another user device token.');
        }

        $device->update(['is_active' => false]);

        AuditLog::log(
            'NOTIFICATION_DEVICE_DEACTIVATED',
            NotificationDevice::class,
            $device->id,
            ['is_active' => true],
            ['is_active' => false],
            $actor
        );
    }

    protected function resolveAndAuthorizeAdminUnit(array $payload, User $actor): ?string
    {
        $unitId = $payload['administrative_unit_id']
            ?? ($payload['target_criteria']['administrative_unit_id'] ?? null)
            ?? $actor->administrative_unit_id;

        if (!$unitId) {
            return null;
        }

        $unit = AdministrativeUnit::find($unitId);
        if (!$unit) {
            throw ValidationException::withMessages([
                'administrative_unit_id' => ['Target administrative unit does not exist.'],
            ]);
        }

        if (!$this->scopeService->canAccessScope($actor, $unit->ancestry_path)) {
            throw new AuthorizationException('Target administrative unit lies outside your authorized jurisdiction.');
        }

        return $unit->id;
    }

    protected function resolveRecipients(string $targetType, array $criteria, ?string $adminUnitId, User $actor)
    {
        $users = $this->resolveRecipientsInternal($targetType, $criteria, $adminUnitId);

        if ($targetType === 'USER' && !$actor->isNationalScope()) {
            foreach ($users as $target) {
                $this->authorizeTargetUserAccess($actor, $target);
            }
        }

        if ($targetType === 'ORGANIZATION' && !$actor->isNationalScope() && !empty($criteria['organization_id'])) {
            $org = Organization::with('administrativeUnit')->find($criteria['organization_id']);
            if ($org && $org->administrativeUnit && !$this->scopeService->canAccessScope($actor, $org->administrativeUnit->ancestry_path)) {
                throw new AuthorizationException('Target organization lies outside your jurisdiction.');
            }
        }

        if ($targetType === 'ALL' && !$actor->isNationalScope()) {
            // Restrict ALL broadcasts by regional officers strictly to their jurisdiction subtree
            return $users->filter(function (User $u) use ($actor) {
                $unit = $u->administrativeUnit;
                return $unit ? $this->scopeService->canAccessScope($actor, $unit->ancestry_path) : false;
            })->values();
        }

        return $users;
    }

    protected function resolveRecipientsInternal(string $targetType, array $criteria, ?string $adminUnitId)
    {
        $query = User::query()
            ->with('administrativeUnit')
            ->where('is_active', true)
            ->where(function (Builder $q) {
                $q->where('account_status', User::STATUS_ACTIVE)->orWhereNull('account_status');
            });

        if ($targetType === 'USER') {
            $userIds = array_values(array_filter((array) ($criteria['user_ids'] ?? [$criteria['user_id'] ?? null])));
            if (empty($userIds)) {
                return collect();
            }
            $query->whereIn('id', $userIds);
        } elseif ($targetType === 'ROLE') {
            $roles = array_values(array_filter((array) ($criteria['roles'] ?? [$criteria['role_code'] ?? null])));
            if (empty($roles)) {
                return collect();
            }
            $query->where(function (Builder $q) use ($roles) {
                $q->whereIn('role_code', $roles)
                    ->orWhereHas('roles', fn (Builder $rq) => $rq->whereIn('code', $roles));
            });
            $this->applyAdminSubtreeFilter($query, $adminUnitId);
        } elseif ($targetType === 'ORGANIZATION') {
            $orgId = $criteria['organization_id'] ?? null;
            if (!$orgId) {
                return collect();
            }
            $query->where('organization_id', $orgId);
        } elseif ($targetType === 'GEOGRAPHY') {
            $geoUnitId = $criteria['administrative_unit_id'] ?? $adminUnitId;
            if (!$geoUnitId) {
                return collect();
            }
            $this->applyAdminSubtreeFilter($query, $geoUnitId);
        } elseif ($targetType === 'ALL') {
            $this->applyAdminSubtreeFilter($query, $adminUnitId);
        }

        return $query->get();
    }

    protected function applyAdminSubtreeFilter(Builder $query, ?string $adminUnitId): void
    {
        if (!$adminUnitId) {
            return;
        }

        $unit = AdministrativeUnit::find($adminUnitId);
        if (!$unit || $unit->ancestry_path === '/') {
            return;
        }

        $normPath = rtrim($unit->ancestry_path, '/') . '/';
        $query->whereHas('administrativeUnit', function (Builder $aq) use ($normPath) {
            $aq->where('ancestry_path', 'like', $normPath . '%');
        });
    }

    protected function authorizeTargetUserAccess(User $actor, User $target): void
    {
        if ($actor->isNationalScope()) {
            return;
        }

        $unit = $target->administrativeUnit;
        if (!$unit || !$this->scopeService->canAccessScope($actor, $unit->ancestry_path)) {
            throw new AuthorizationException('Target recipient lies outside your administrative jurisdiction.');
        }
    }

    protected function resolveRawDestination(User $recipient, string $channel): ?string
    {
        return match ($channel) {
            'EMAIL' => $recipient->email,
            'SMS' => $recipient->mobile_number,
            'PUSH' => $recipient->id,
            default => 'in-app',
        };
    }

    protected function generateNotificationNumber(): string
    {
        $prefix = 'NTF-' . now()->format('Y') . '-';
        $count = Notification::withTrashed()->count() + 1;
        return $prefix . str_pad((string) $count, 5, '0', STR_PAD_LEFT);
    }
}
