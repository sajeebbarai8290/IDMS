<?php

namespace App\Modules\Workflow\Contracts;

use App\Modules\Workflow\Models\WorkflowInstance;
use App\Modules\Workflow\Models\WorkflowNotification;
use App\Modules\Workflow\Models\WorkflowStageTask;

interface WorkflowNotificationInterface
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
    ): WorkflowNotification;
}
