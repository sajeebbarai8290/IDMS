<?php

namespace App\Modules\Workflow\Adapters;

use App\Modules\Workflow\Contracts\WorkflowNotificationInterface;
use App\Modules\Workflow\Models\WorkflowInstance;
use App\Modules\Workflow\Models\WorkflowNotification;
use App\Modules\Workflow\Models\WorkflowStageTask;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Persists workflow notifications in workflow.workflow_notifications and dispatches
 * structured log hooks ready for Module 13 (SMS / Email / WebPush) integration.
 */
class DatabaseWorkflowNotificationAdapter implements WorkflowNotificationInterface
{
    public function sendNotification(
        WorkflowInstance $instance,
        string $notificationType,
        string $titleEn,
        string $titleBn,
        string $messageEn,
        string $messageBn,
        ?string $recipientUserId = null,
        ?string $recipientRoleCode = null,
        ?WorkflowStageTask $task = null,
        string $channel = 'IN_APP'
    ): WorkflowNotification {
        $ref = 'WF-NOTIF-' . strtoupper(Str::random(10));

        $notification = WorkflowNotification::create([
            'workflow_instance_id' => $instance->id,
            'workflow_stage_task_id' => $task?->id,
            'recipient_user_id' => $recipientUserId,
            'recipient_role_code' => $recipientRoleCode,
            'administrative_unit_id' => $instance->administrative_unit_id,
            'notification_type' => $notificationType,
            'channel' => $channel,
            'title_en' => $titleEn,
            'title_bn' => $titleBn,
            'message_en' => $messageEn,
            'message_bn' => $messageBn,
            'delivery_status' => WorkflowNotification::DELIVERY_SENT,
            'provider_reference' => $ref,
            'dispatched_at' => now(),
        ]);

        Log::info('Workflow notification dispatched.', [
            'notification_id' => $notification->id,
            'instance_id' => $instance->id,
            'instance_no' => $instance->instance_no,
            'type' => $notificationType,
            'recipient_user_id' => $recipientUserId,
            'recipient_role_code' => $recipientRoleCode,
            'reference' => $ref,
        ]);

        return $notification;
    }
}
