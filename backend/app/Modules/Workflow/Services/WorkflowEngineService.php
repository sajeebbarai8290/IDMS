<?php

namespace App\Modules\Workflow\Services;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\AuditLog;
use App\Core\Models\User;
use App\Core\Services\AdministrativeScopeService;
use App\Modules\Workflow\Contracts\WorkflowNotificationInterface;
use App\Modules\Workflow\Models\WorkflowAttachment;
use App\Modules\Workflow\Models\WorkflowDefinition;
use App\Modules\Workflow\Models\WorkflowDelegation;
use App\Modules\Workflow\Models\WorkflowInstance;
use App\Modules\Workflow\Models\WorkflowNotification;
use App\Modules\Workflow\Models\WorkflowStage;
use App\Modules\Workflow\Models\WorkflowStageAssignment;
use App\Modules\Workflow\Models\WorkflowStageTask;
use App\Modules\Workflow\Models\WorkflowTransition;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WorkflowEngineService
{
    public function __construct(
        protected AdministrativeScopeService $scopeService,
        protected WorkflowEntityIntegrationService $integrationService,
        protected WorkflowNotificationInterface $notificationAdapter,
        protected WorkflowConfigurationService $configService
    ) {}

    /**
     * Start / submit a new configurable workflow instance for an IDMS entity.
     */
    public function startWorkflow(array $data, User $actor): WorkflowInstance
    {
        if ($actor->hasRole('ROLE_SUPER_ADMIN')) {
            throw new AuthorizationException('Super Admin is prohibited from initiating operational workflow approvals.');
        }

        $entityType = strtoupper(trim((string) $data['entity_type']));
        $entityId = (string) ($data['entity_id'] ?? Str::uuid());

        $definition = $this->resolveDefinition(
            $data['workflow_definition_id'] ?? null,
            $data['module_code'] ?? null,
            $entityType
        );

        $stages = $definition->stages()->with('assignments')->orderBy('stage_order')->get();
        if ($stages->isEmpty()) {
            throw ValidationException::withMessages([
                'workflow_definition_id' => ['Selected workflow definition has no configured stages.'],
            ]);
        }

        $snapshot = $this->integrationService->resolveTargetEntity(
            $entityType,
            $entityId,
            $actor,
            $data['administrative_unit_id'] ?? null,
            $data['title_en'] ?? null,
            $data['title_bn'] ?? null,
            $data['entity_reference'] ?? null
        );

        // Prevent duplicate active workflow for same entity under same workflow definition
        $duplicateActive = WorkflowInstance::where('workflow_definition_id', $definition->id)
            ->where('entity_type', $snapshot['entity_type'])
            ->where('entity_id', $snapshot['entity_id'])
            ->whereNotIn('current_state', WorkflowInstance::TERMINAL_STATES)
            ->exists();

        if ($duplicateActive) {
            throw ValidationException::withMessages([
                'entity_id' => ['An active workflow approval instance already exists for this record under the selected workflow.'],
            ]);
        }

        $firstStage = $stages->first();

        return DB::transaction(function () use ($data, $actor, $definition, $snapshot, $firstStage) {
            $now = now();
            $dueAt = $now->copy()->addHours(max(1, (int) $firstStage->sla_hours));

            $instance = WorkflowInstance::create([
                'id' => (string) Str::uuid(),
                'instance_no' => $this->generateInstanceNo(),
                'workflow_definition_id' => $definition->id,
                'module_code' => $snapshot['module_code'],
                'entity_type' => $snapshot['entity_type'],
                'entity_id' => $snapshot['entity_id'],
                'entity_reference' => $snapshot['entity_reference'],
                'title_en' => $snapshot['title_en'],
                'title_bn' => $snapshot['title_bn'],
                'administrative_unit_id' => $snapshot['administrative_unit_id'],
                'current_stage_id' => $firstStage->id,
                'current_state' => $this->mapStageToState($firstStage),
                'priority' => strtoupper((string) ($data['priority'] ?? 'NORMAL')),
                'submitted_by' => $actor->id,
                'submitted_at' => $now,
                'due_at' => $dueAt,
                'last_action_by' => $actor->id,
                'last_action_at' => $now,
                'submission_notes' => $data['comments'] ?? $data['submission_notes'] ?? null,
                'metadata' => array_merge($snapshot['metadata'] ?? [], $data['metadata'] ?? []),
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $tasks = $this->createStageTasks($instance, $firstStage, $dueAt);
            $this->syncPrimaryAssigneeFields($instance, $tasks);

            WorkflowTransition::create([
                'id' => (string) Str::uuid(),
                'workflow_instance_id' => $instance->id,
                'from_stage_id' => null,
                'to_stage_id' => $firstStage->id,
                'action' => WorkflowTransition::ACTION_SUBMIT,
                'from_state' => WorkflowInstance::STATE_SUBMITTED,
                'to_state' => $instance->current_state,
                'action_by_user_id' => $actor->id,
                'actor_name' => $actor->full_name_en,
                'actor_role' => $actor->role_code,
                'remarks' => $data['comments'] ?? 'Workflow submitted for review and approval.',
                'metadata' => [
                    'definition_code' => $definition->code,
                    'stage_code' => $firstStage->stage_code,
                ],
                'transitioned_at' => $now,
            ]);

            $this->notifyAssignedTasks($instance, $tasks, WorkflowNotification::TYPE_ASSIGNED);

            AuditLog::log(
                'WORKFLOW_INSTANCE_SUBMITTED',
                WorkflowInstance::class,
                $instance->id,
                null,
                [
                    'instance_no' => $instance->instance_no,
                    'definition_code' => $definition->code,
                    'entity_type' => $instance->entity_type,
                    'entity_id' => $instance->entity_id,
                    'current_state' => $instance->current_state,
                ],
                $actor,
                $instance->administrative_unit_id
            );

            $this->configService->bumpCacheVersion();

            return $this->loadFullInstance($instance);
        });
    }

    /**
     * Execute a transaction-safe workflow transition action.
     */
    public function executeAction(
        WorkflowInstance $instance,
        string $action,
        User $actor,
        array $payload = []
    ): WorkflowInstance {
        if ($actor->hasRole('ROLE_SUPER_ADMIN')) {
            throw new AuthorizationException('Super Admin is prohibited from executing operational workflow transitions.');
        }

        $actionUpper = strtoupper(trim($action));
        $comments = isset($payload['comments']) ? trim((string) $payload['comments']) : null;

        return DB::transaction(function () use ($instance, $actionUpper, $actor, $payload, $comments) {
            /** @var WorkflowInstance $locked */
            $locked = WorkflowInstance::with([
                'definition.stages.assignments',
                'currentStage.assignments',
                'administrativeUnit',
            ])->whereKey($instance->id)->lockForUpdate()->firstOrFail();

            if ($locked->isTerminal()) {
                throw ValidationException::withMessages([
                    'current_state' => ["Workflow instance is already in terminal state {$locked->current_state}."],
                ]);
            }

            $definition = $locked->definition;
            $currentStage = $locked->currentStage;
            $fromState = $locked->current_state;
            $fromStageId = $locked->current_stage_id;
            $now = now();

            $isSubmitter = ($actor->id === $locked->submitted_by || $actor->id === $locked->created_by);

            // Enforce Self-Approval prohibition on decision actions
            if (
                in_array($actionUpper, [
                    WorkflowTransition::ACTION_APPROVE,
                    WorkflowTransition::ACTION_REJECT,
                    WorkflowTransition::ACTION_RETURN_FOR_CORRECTION,
                    WorkflowTransition::ACTION_REQUEST_INFO,
                    WorkflowTransition::ACTION_FORWARD,
                ], true)
                && !$definition->allow_self_approval
                && $isSubmitter
            ) {
                throw ValidationException::withMessages([
                    'authorization' => ['4-Eye Separation of Duties: Submitters cannot approve, reject, return, or forward their own workflow request.'],
                ]);
            }

            if ($actionUpper === WorkflowTransition::ACTION_PROVIDE_INFO) {
                if ($locked->current_state !== WorkflowInstance::STATE_INFO_REQUESTED) {
                    throw ValidationException::withMessages([
                        'current_state' => ['Additional information can only be provided when status is INFO_REQUESTED.'],
                    ]);
                }
                if (!$comments) {
                    throw ValidationException::withMessages([
                        'comments' => ['Response comments are required when providing additional information.'],
                    ]);
                }

                $restoredState = $currentStage
                    ? $this->mapStageToState($currentStage)
                    : WorkflowInstance::STATE_INITIAL_REVIEW;

                $locked->update([
                    'current_state' => $restoredState,
                    'last_action_by' => $actor->id,
                    'last_action_at' => $now,
                    'updated_by' => $actor->id,
                ]);

                $transition = $this->recordTransition(
                    $locked,
                    WorkflowTransition::ACTION_PROVIDE_INFO,
                    $fromStageId,
                    $fromStageId,
                    $fromState,
                    $restoredState,
                    $actor,
                    $comments
                );

                $pendingTasks = $locked->tasks()->where('status', WorkflowStageTask::STATUS_PENDING)->get();
                $this->notifyAssignedTasks($locked, $pendingTasks, WorkflowNotification::TYPE_STATUS_CHANGED);
                $this->logAndInvalidate($locked, $actionUpper, $fromState, $actor, $transition);

                return $this->loadFullInstance($locked);
            }

            if ($actionUpper === WorkflowTransition::ACTION_RESUBMIT) {
                if ($locked->current_state !== WorkflowInstance::STATE_RETURNED_FOR_CORRECTION) {
                    throw ValidationException::withMessages([
                        'current_state' => ['Only requests in RETURNED_FOR_CORRECTION state can be resubmitted.'],
                    ]);
                }

                $stages = $definition->stages()->with('assignments')->orderBy('stage_order')->get();
                $restartStage = (!empty($payload['resume_current_stage']) && $currentStage)
                    ? $currentStage
                    : $stages->first();

                // Cancel any leftover open tasks
                $locked->tasks()->where('status', WorkflowStageTask::STATUS_PENDING)->update([
                    'status' => WorkflowStageTask::STATUS_CANCELLED,
                ]);

                $dueAt = $now->copy()->addHours(max(1, (int) $restartStage->sla_hours));
                $newState = $this->mapStageToState($restartStage);

                $locked->update([
                    'current_stage_id' => $restartStage->id,
                    'current_state' => $newState,
                    'due_at' => $dueAt,
                    'last_action_by' => $actor->id,
                    'last_action_at' => $now,
                    'updated_by' => $actor->id,
                ]);

                $tasks = $this->createStageTasks($locked, $restartStage, $dueAt);
                $this->syncPrimaryAssigneeFields($locked, $tasks);

                $transition = $this->recordTransition(
                    $locked,
                    WorkflowTransition::ACTION_RESUBMIT,
                    $fromStageId,
                    $restartStage->id,
                    $fromState,
                    $newState,
                    $actor,
                    $comments ?: 'Submitter revised and resubmitted the request.'
                );

                $this->notifyAssignedTasks($locked, $tasks, WorkflowNotification::TYPE_ASSIGNED);
                $this->logAndInvalidate($locked, $actionUpper, $fromState, $actor, $transition);

                return $this->loadFullInstance($locked);
            }

            if ($actionUpper === WorkflowTransition::ACTION_CANCEL) {
                if (!$isSubmitter && !$actor->isNationalScope()) {
                    throw new AuthorizationException('Only the submitter or national authority may cancel a pending workflow.');
                }

                $locked->tasks()->where('status', WorkflowStageTask::STATUS_PENDING)->update([
                    'status' => WorkflowStageTask::STATUS_CANCELLED,
                ]);

                $locked->update([
                    'current_state' => WorkflowInstance::STATE_CANCELLED,
                    'completed_at' => $now,
                    'final_remarks' => $comments,
                    'last_action_by' => $actor->id,
                    'last_action_at' => $now,
                    'updated_by' => $actor->id,
                ]);

                $transition = $this->recordTransition(
                    $locked,
                    WorkflowTransition::ACTION_CANCEL,
                    $fromStageId,
                    $fromStageId,
                    $fromState,
                    WorkflowInstance::STATE_CANCELLED,
                    $actor,
                    $comments ?: 'Workflow cancelled by authorized officer.'
                );

                $this->logAndInvalidate($locked, $actionUpper, $fromState, $actor, $transition);

                return $this->loadFullInstance($locked);
            }

            // Decision actions require the instance to be awaiting active stage review/approval
            if (in_array($locked->current_state, [
                WorkflowInstance::STATE_RETURNED_FOR_CORRECTION,
                WorkflowInstance::STATE_INFO_REQUESTED,
            ], true)) {
                throw ValidationException::withMessages([
                    'current_state' => ["Cannot execute {$actionUpper} while workflow is awaiting submitter response ({$locked->current_state})."],
                ]);
            }

            // Resolve matching task or delegation authority
            $match = $this->resolveMatchingTaskForActor($locked, $actor);
            if (!$match) {
                throw new AuthorizationException('You are not assigned or delegated as an authorized reviewer/approver for the current workflow stage.');
            }

            /** @var WorkflowStageTask $task */
            $task = $match['task'];
            $delegatedFromUserId = $match['delegated_from_user_id'];

            if ($actionUpper === WorkflowTransition::ACTION_APPROVE) {
                $task->update([
                    'status' => WorkflowStageTask::STATUS_APPROVED,
                    'delegated_from_user_id' => $delegatedFromUserId,
                    'acted_by_user_id' => $actor->id,
                    'acted_at' => $now,
                    'comments' => $comments,
                ]);

                $stageCompleted = $this->isStageSatisfied($locked, $currentStage);
                $nextStage = $stageCompleted ? $this->findNextStage($definition, $currentStage) : null;

                if ($stageCompleted && $nextStage) {
                    $dueAt = $now->copy()->addHours(max(1, (int) $nextStage->sla_hours));
                    $nextState = $this->mapStageToState($nextStage);

                    $locked->update([
                        'current_stage_id' => $nextStage->id,
                        'current_state' => $nextState,
                        'due_at' => $dueAt,
                        'last_action_by' => $actor->id,
                        'last_action_at' => $now,
                        'updated_by' => $actor->id,
                    ]);

                    $newTasks = $this->createStageTasks($locked, $nextStage, $dueAt);
                    $this->syncPrimaryAssigneeFields($locked, $newTasks);

                    $transition = $this->recordTransition(
                        $locked,
                        WorkflowTransition::ACTION_APPROVE,
                        $fromStageId,
                        $nextStage->id,
                        $fromState,
                        $nextState,
                        $actor,
                        $comments ?: "Approved stage {$currentStage->stage_code} and routed to {$nextStage->stage_code}.",
                        $task->id,
                        $delegatedFromUserId
                    );

                    $this->notifyAssignedTasks($locked, $newTasks, WorkflowNotification::TYPE_ASSIGNED);
                    $this->notifySubmitter(
                        $locked,
                        WorkflowNotification::TYPE_STATUS_CHANGED,
                        "Workflow {$locked->instance_no} advanced to {$nextStage->name_en}",
                        "কর্মপ্রবাহ {$locked->instance_no} পরবর্তী ধাপে ({$nextStage->name_bn}) অগ্রসর হয়েছে",
                        "Stage '{$currentStage->name_en}' was approved. Current stage: '{$nextStage->name_en}'.",
                        "'{$currentStage->name_bn}' ধাপ অনুমোদিত হয়েছে। বর্তমান ধাপ: '{$nextStage->name_bn}'।"
                    );
                } elseif ($stageCompleted && !$nextStage) {
                    $locked->update([
                        'current_state' => WorkflowInstance::STATE_FINAL_APPROVED,
                        'completed_at' => $now,
                        'assigned_to_user_id' => null,
                        'assigned_role_code' => null,
                        'final_remarks' => $comments,
                        'last_action_by' => $actor->id,
                        'last_action_at' => $now,
                        'updated_by' => $actor->id,
                    ]);

                    $transition = $this->recordTransition(
                        $locked,
                        WorkflowTransition::ACTION_APPROVE,
                        $fromStageId,
                        $fromStageId,
                        $fromState,
                        WorkflowInstance::STATE_FINAL_APPROVED,
                        $actor,
                        $comments ?: 'Final approval granted.',
                        $task->id,
                        $delegatedFromUserId
                    );

                    $this->notifySubmitter(
                        $locked,
                        WorkflowNotification::TYPE_COMPLETED,
                        "Workflow {$locked->instance_no} Finally Approved",
                        "কর্মপ্রবাহ {$locked->instance_no} চূড়ান্তভাবে অনুমোদিত হয়েছে",
                        "Your request '{$locked->title_en}' has received final approval.",
                        "আপনার আবেদন '{$locked->title_bn}' চূড়ান্ত অনুমোদন লাভ করেছে।"
                    );
                } else {
                    // Parallel stage still awaiting remaining approvals
                    $locked->update([
                        'last_action_by' => $actor->id,
                        'last_action_at' => $now,
                        'updated_by' => $actor->id,
                    ]);

                    $transition = $this->recordTransition(
                        $locked,
                        WorkflowTransition::ACTION_APPROVE,
                        $fromStageId,
                        $fromStageId,
                        $fromState,
                        $fromState,
                        $actor,
                        $comments ?: "Parallel approval recorded for stage {$currentStage->stage_code}; awaiting remaining approvers.",
                        $task->id,
                        $delegatedFromUserId
                    );
                }

                $this->logAndInvalidate($locked, $actionUpper, $fromState, $actor, $transition);
                return $this->loadFullInstance($locked);
            }

            if ($actionUpper === WorkflowTransition::ACTION_REJECT) {
                if (!$comments) {
                    throw ValidationException::withMessages([
                        'comments' => ['Rejection reason is mandatory.'],
                    ]);
                }

                $task->update([
                    'status' => WorkflowStageTask::STATUS_REJECTED,
                    'delegated_from_user_id' => $delegatedFromUserId,
                    'acted_by_user_id' => $actor->id,
                    'acted_at' => $now,
                    'comments' => $comments,
                ]);

                $locked->tasks()
                    ->where('id', '!=', $task->id)
                    ->where('status', WorkflowStageTask::STATUS_PENDING)
                    ->update(['status' => WorkflowStageTask::STATUS_CANCELLED]);

                $locked->update([
                    'current_state' => WorkflowInstance::STATE_REJECTED,
                    'completed_at' => $now,
                    'assigned_to_user_id' => null,
                    'assigned_role_code' => null,
                    'final_remarks' => $comments,
                    'last_action_by' => $actor->id,
                    'last_action_at' => $now,
                    'updated_by' => $actor->id,
                ]);

                $transition = $this->recordTransition(
                    $locked,
                    WorkflowTransition::ACTION_REJECT,
                    $fromStageId,
                    $fromStageId,
                    $fromState,
                    WorkflowInstance::STATE_REJECTED,
                    $actor,
                    $comments,
                    $task->id,
                    $delegatedFromUserId
                );

                $this->notifySubmitter(
                    $locked,
                    WorkflowNotification::TYPE_COMPLETED,
                    "Workflow {$locked->instance_no} Rejected",
                    "কর্মপ্রবাহ {$locked->instance_no} প্রত্যাখ্যাত হয়েছে",
                    "Your request '{$locked->title_en}' was rejected. Reason: {$comments}",
                    "আপনার আবেদন '{$locked->title_bn}' প্রত্যাখ্যাত হয়েছে। কারণ: {$comments}"
                );

                $this->logAndInvalidate($locked, $actionUpper, $fromState, $actor, $transition);
                return $this->loadFullInstance($locked);
            }

            if ($actionUpper === WorkflowTransition::ACTION_RETURN_FOR_CORRECTION) {
                if ($currentStage && !$currentStage->allow_return_for_correction) {
                    throw ValidationException::withMessages([
                        'action' => ['Current stage does not allow returning for correction.'],
                    ]);
                }
                if (!$comments) {
                    throw ValidationException::withMessages([
                        'comments' => ['Correction instructions are mandatory when returning a request.'],
                    ]);
                }

                $task->update([
                    'status' => WorkflowStageTask::STATUS_RETURNED,
                    'delegated_from_user_id' => $delegatedFromUserId,
                    'acted_by_user_id' => $actor->id,
                    'acted_at' => $now,
                    'comments' => $comments,
                ]);

                $locked->tasks()
                    ->where('id', '!=', $task->id)
                    ->where('status', WorkflowStageTask::STATUS_PENDING)
                    ->update(['status' => WorkflowStageTask::STATUS_CANCELLED]);

                $locked->update([
                    'current_state' => WorkflowInstance::STATE_RETURNED_FOR_CORRECTION,
                    'last_action_by' => $actor->id,
                    'last_action_at' => $now,
                    'updated_by' => $actor->id,
                ]);

                $transition = $this->recordTransition(
                    $locked,
                    WorkflowTransition::ACTION_RETURN_FOR_CORRECTION,
                    $fromStageId,
                    $fromStageId,
                    $fromState,
                    WorkflowInstance::STATE_RETURNED_FOR_CORRECTION,
                    $actor,
                    $comments,
                    $task->id,
                    $delegatedFromUserId
                );

                $this->notifySubmitter(
                    $locked,
                    WorkflowNotification::TYPE_RETURNED_FOR_CORRECTION,
                    "Correction Requested for {$locked->instance_no}",
                    "{$locked->instance_no} সংশোধনের জন্য ফেরত পাঠানো হয়েছে",
                    "Correction requested: {$comments}",
                    "সংশোধনের নির্দেশনা: {$comments}"
                );

                $this->logAndInvalidate($locked, $actionUpper, $fromState, $actor, $transition);
                return $this->loadFullInstance($locked);
            }

            if ($actionUpper === WorkflowTransition::ACTION_REQUEST_INFO) {
                if ($currentStage && !$currentStage->allow_info_request) {
                    throw ValidationException::withMessages([
                        'action' => ['Current stage does not allow requesting additional information.'],
                    ]);
                }
                if (!$comments) {
                    throw ValidationException::withMessages([
                        'comments' => ['Please specify what additional information is required.'],
                    ]);
                }

                $locked->update([
                    'current_state' => WorkflowInstance::STATE_INFO_REQUESTED,
                    'last_action_by' => $actor->id,
                    'last_action_at' => $now,
                    'updated_by' => $actor->id,
                ]);

                $transition = $this->recordTransition(
                    $locked,
                    WorkflowTransition::ACTION_REQUEST_INFO,
                    $fromStageId,
                    $fromStageId,
                    $fromState,
                    WorkflowInstance::STATE_INFO_REQUESTED,
                    $actor,
                    $comments,
                    $task->id,
                    $delegatedFromUserId
                );

                $this->notifySubmitter(
                    $locked,
                    WorkflowNotification::TYPE_INFO_REQUESTED,
                    "Additional Information Requested: {$locked->instance_no}",
                    "অতিরিক্ত তথ্য চাওয়া হয়েছে: {$locked->instance_no}",
                    "Officer requested clarification: {$comments}",
                    "কর্মকর্তা অতিরিক্ত তথ্য চেয়েছেন: {$comments}"
                );

                $this->logAndInvalidate($locked, $actionUpper, $fromState, $actor, $transition);
                return $this->loadFullInstance($locked);
            }

            if ($actionUpper === WorkflowTransition::ACTION_FORWARD) {
                if ($currentStage && !$currentStage->allow_forward) {
                    throw ValidationException::withMessages([
                        'action' => ['Current stage does not permit forwarding.'],
                    ]);
                }

                $targetStage = !empty($payload['target_stage_id'])
                    ? WorkflowStage::where('workflow_definition_id', $definition->id)->findOrFail($payload['target_stage_id'])
                    : ($this->findNextStage($definition, $currentStage) ?? $currentStage);

                if (!$targetStage) {
                    throw ValidationException::withMessages([
                        'target_stage_id' => ['No target stage available for forwarding.'],
                    ]);
                }

                $task->update([
                    'status' => WorkflowStageTask::STATUS_FORWARDED,
                    'delegated_from_user_id' => $delegatedFromUserId,
                    'acted_by_user_id' => $actor->id,
                    'acted_at' => $now,
                    'comments' => $comments,
                ]);

                $locked->tasks()
                    ->where('id', '!=', $task->id)
                    ->where('status', WorkflowStageTask::STATUS_PENDING)
                    ->update(['status' => WorkflowStageTask::STATUS_SKIPPED]);

                $dueAt = $now->copy()->addHours(max(1, (int) $targetStage->sla_hours));
                $nextState = $this->mapStageToState($targetStage);

                $locked->update([
                    'current_stage_id' => $targetStage->id,
                    'current_state' => $nextState,
                    'due_at' => $dueAt,
                    'last_action_by' => $actor->id,
                    'last_action_at' => $now,
                    'updated_by' => $actor->id,
                ]);

                if (!empty($payload['forward_to_user_id']) || !empty($payload['forward_to_role_code'])) {
                    if (!empty($payload['forward_to_user_id'])) {
                        $forwardUser = User::find($payload['forward_to_user_id']);
                        if (!$forwardUser || !$forwardUser->is_active) {
                            throw ValidationException::withMessages([
                                'forward_to_user_id' => ['Target forward officer must be an active user.'],
                            ]);
                        }
                        if (in_array($forwardUser->role_code, ['ROLE_SUPER_ADMIN', 'ROLE_EXTERNAL_PARTNER', 'ROLE_NATIONAL_AUDITOR'], true)) {
                            throw ValidationException::withMessages([
                                'forward_to_user_id' => ["Cannot forward operational approval tasks to {$forwardUser->role_code}."],
                            ]);
                        }
                    }

                    $forwardUnitId = $locked->administrative_unit_id;
                    if (!empty($payload['forward_to_unit_id'])) {
                        $forwardUnit = AdministrativeUnit::find($payload['forward_to_unit_id']);
                        if (!$forwardUnit || !$this->scopeService->canAccessScope($actor, $forwardUnit->ancestry_path)) {
                            throw ValidationException::withMessages([
                                'forward_to_unit_id' => ['Target administrative unit is invalid or outside your jurisdiction.'],
                            ]);
                        }
                        $forwardUnitId = $forwardUnit->id;
                    }

                    $customTask = WorkflowStageTask::create([
                        'id' => (string) Str::uuid(),
                        'workflow_instance_id' => $locked->id,
                        'workflow_stage_id' => $targetStage->id,
                        'step_sequence' => ($locked->tasks()->max('step_sequence') ?? 0) + 1,
                        'assignee_type' => !empty($payload['forward_to_user_id']) ? 'USER' : 'ROLE',
                        'assignee_user_id' => $payload['forward_to_user_id'] ?? null,
                        'assignee_role_code' => $payload['forward_to_role_code'] ?? null,
                        'administrative_unit_id' => $forwardUnitId,
                        'authority_type' => WorkflowStageAssignment::AUTHORITY_APPROVER,
                        'status' => WorkflowStageTask::STATUS_PENDING,
                        'due_at' => $dueAt,
                    ]);
                    $newTasks = collect([$customTask]);
                } else {
                    $newTasks = $this->createStageTasks($locked, $targetStage, $dueAt);
                }

                $this->syncPrimaryAssigneeFields($locked, $newTasks);

                $transition = $this->recordTransition(
                    $locked,
                    WorkflowTransition::ACTION_FORWARD,
                    $fromStageId,
                    $targetStage->id,
                    $fromState,
                    $nextState,
                    $actor,
                    $comments ?: "Forwarded to {$targetStage->name_en}.",
                    $task->id,
                    $delegatedFromUserId,
                    [
                        'forward_to_user_id' => $payload['forward_to_user_id'] ?? null,
                        'forward_to_role_code' => $payload['forward_to_role_code'] ?? null,
                    ]
                );

                $this->notifyAssignedTasks($locked, $newTasks, WorkflowNotification::TYPE_ASSIGNED);
                $this->logAndInvalidate($locked, $actionUpper, $fromState, $actor, $transition);

                return $this->loadFullInstance($locked);
            }

            throw ValidationException::withMessages([
                'action' => ["Unsupported workflow action '{$actionUpper}'."],
            ]);
        });
    }

    /**
     * List scoped workflow instances with queue presets, filters, search, sorting, and pagination.
     */
    public function listInstances(User $actor, array $filters = [])
    {
        $query = WorkflowInstance::with([
            'definition:id,code,name_en,name_bn,module_code,entity_type',
            'currentStage:id,stage_code,name_en,name_bn,approval_level,execution_mode,required_approvals',
            'administrativeUnit:id,code,name_en,name_bn,tier_level,ancestry_path',
            'submitter:id,full_name_en,full_name_bn,role_code',
            'assignedUser:id,full_name_en,full_name_bn,role_code',
        ])->inJurisdiction($actor);

        $queue = strtolower((string) ($filters['queue'] ?? 'all'));
        if ($queue === 'my_pending') {
            $this->applyMyPendingFilter($query, $actor);
        } elseif ($queue === 'submitted_by_me') {
            $query->where('submitted_by', $actor->id);
        } elseif ($queue === 'approved') {
            $query->where('current_state', WorkflowInstance::STATE_FINAL_APPROVED);
        } elseif ($queue === 'rejected') {
            $query->where('current_state', WorkflowInstance::STATE_REJECTED);
        } elseif ($queue === 'overdue') {
            $query->whereNotIn('current_state', WorkflowInstance::TERMINAL_STATES)
                ->whereNotNull('due_at')
                ->where('due_at', '<', now());
        }

        if (!empty($filters['module_code'])) {
            $query->where('module_code', strtoupper((string) $filters['module_code']));
        }
        if (!empty($filters['entity_type'])) {
            $query->where('entity_type', strtoupper((string) $filters['entity_type']));
        }
        if (!empty($filters['current_state'])) {
            $query->where('current_state', strtoupper((string) $filters['current_state']));
        }
        if (!empty($filters['priority'])) {
            $query->where('priority', strtoupper((string) $filters['priority']));
        }
        if (!empty($filters['administrative_unit_id'])) {
            $unit = AdministrativeUnit::find($filters['administrative_unit_id']);
            if ($unit) {
                $path = rtrim($unit->ancestry_path, '/') . '/';
                $query->whereHas('administrativeUnit', fn ($q) => $q->where('ancestry_path', 'like', $path . '%'));
            }
        }
        if (!empty($filters['workflow_definition_id'])) {
            $query->where('workflow_definition_id', $filters['workflow_definition_id']);
        }
        if (!empty($filters['date_from'])) {
            $query->whereDate('submitted_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->whereDate('submitted_at', '<=', $filters['date_to']);
        }
        if (!empty($filters['search'])) {
            $term = '%' . trim((string) $filters['search']) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('instance_no', 'like', $term)
                    ->orWhere('entity_reference', 'like', $term)
                    ->orWhere('title_en', 'like', $term)
                    ->orWhere('title_bn', 'like', $term);
            });
        }

        $sortBy = in_array($filters['sort_by'] ?? '', ['submitted_at', 'due_at', 'priority', 'current_state', 'instance_no'], true)
            ? $filters['sort_by']
            : 'submitted_at';
        $sortDir = strtolower((string) ($filters['sort_dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sortBy, $sortDir)
            ->paginate(max(5, min(100, (int) ($filters['per_page'] ?? 15))));
    }

    /**
     * Load full detail including stage tasks, immutable transition history, attachments, and available actions.
     */
    public function getInstanceDetail(WorkflowInstance $instance, User $actor): array
    {
        $loaded = $this->loadFullInstance($instance);
        $availableActions = $this->computeAvailableActions($loaded, $actor);
        $liveEntity = $this->integrationService->getLiveEntitySummary($loaded->entity_type, $loaded->entity_id);

        return [
            'instance' => $loaded,
            'available_actions' => $availableActions,
            'live_entity' => $liveEntity,
        ];
    }

    /**
     * Compute what actions the given officer may execute on this workflow instance right now.
     */
    public function computeAvailableActions(WorkflowInstance $instance, User $actor): array
    {
        if ($actor->hasRole('ROLE_SUPER_ADMIN') || $actor->hasRole('ROLE_NATIONAL_AUDITOR') || $actor->hasRole('ROLE_EXTERNAL_PARTNER')) {
            return [];
        }

        if ($instance->isTerminal()) {
            return [];
        }

        $actions = [];
        $isSubmitter = ($actor->id === $instance->submitted_by || $actor->id === $instance->created_by);

        if ($instance->current_state === WorkflowInstance::STATE_RETURNED_FOR_CORRECTION) {
            if ($isSubmitter || $this->scopeService->canAccessScope($actor, $instance->administrativeUnit?->ancestry_path ?? '/')) {
                $actions[] = WorkflowTransition::ACTION_RESUBMIT;
                $actions[] = WorkflowTransition::ACTION_CANCEL;
            }
            return $actions;
        }

        if ($instance->current_state === WorkflowInstance::STATE_INFO_REQUESTED) {
            if ($isSubmitter || $this->scopeService->canAccessScope($actor, $instance->administrativeUnit?->ancestry_path ?? '/')) {
                $actions[] = WorkflowTransition::ACTION_PROVIDE_INFO;
                $actions[] = WorkflowTransition::ACTION_CANCEL;
            }
            return $actions;
        }

        $definition = $instance->definition;
        $stage = $instance->currentStage;

        $canDecide = ($definition?->allow_self_approval || !$isSubmitter)
            && $this->resolveMatchingTaskForActor($instance, $actor) !== null;

        if ($canDecide) {
            $actions[] = WorkflowTransition::ACTION_APPROVE;
            $actions[] = WorkflowTransition::ACTION_REJECT;
            if (!$stage || $stage->allow_return_for_correction) {
                $actions[] = WorkflowTransition::ACTION_RETURN_FOR_CORRECTION;
            }
            if (!$stage || $stage->allow_info_request) {
                $actions[] = WorkflowTransition::ACTION_REQUEST_INFO;
            }
            if (!$stage || $stage->allow_forward) {
                $actions[] = WorkflowTransition::ACTION_FORWARD;
            }
        }

        if ($isSubmitter) {
            $actions[] = WorkflowTransition::ACTION_CANCEL;
        }

        return array_values(array_unique($actions));
    }

    /**
     * Upload a private supporting attachment with MIME verification and SHA-256 checksum.
     */
    public function uploadAttachment(
        WorkflowInstance $instance,
        UploadedFile $file,
        User $actor,
        ?string $title = null,
        ?string $transitionId = null
    ): WorkflowAttachment {
        $realMime = $file->getMimeType() ?: 'application/octet-stream';
        $allowedMimes = [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'image/webp',
            'text/plain',
            'text/csv',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];

        if (!in_array($realMime, $allowedMimes, true)) {
            throw ValidationException::withMessages([
                'file' => ["File MIME type '{$realMime}' is not permitted for workflow attachments."],
            ]);
        }

        $ext = strtolower($file->getClientOriginalExtension() ?: 'bin');
        $storedName = Str::uuid() . '.' . preg_replace('/[^a-z0-9]/i', '', $ext);
        $path = "workflow_attachments/{$instance->id}/{$storedName}";
        $contents = file_get_contents($file->getRealPath());
        $sha256 = hash('sha256', $contents ?: '');

        Storage::disk('local')->put($path, $contents ?: '');

        $attachment = WorkflowAttachment::create([
            'id' => (string) Str::uuid(),
            'workflow_instance_id' => $instance->id,
            'workflow_transition_id' => $transitionId,
            'title' => $title ?: $file->getClientOriginalName(),
            'original_name' => $file->getClientOriginalName(),
            'storage_path' => $path,
            'mime_type' => $realMime,
            'file_size_bytes' => $file->getSize(),
            'sha256_hash' => $sha256,
            'uploaded_by' => $actor->id,
        ]);

        AuditLog::log(
            'WORKFLOW_ATTACHMENT_UPLOADED',
            WorkflowInstance::class,
            $instance->id,
            null,
            ['attachment_id' => $attachment->id, 'sha256_hash' => $sha256, 'mime_type' => $realMime],
            $actor,
            $instance->administrative_unit_id
        );

        return $attachment;
    }

    public function downloadAttachment(WorkflowAttachment $attachment): StreamedResponse
    {
        if (!Storage::disk('local')->exists($attachment->storage_path)) {
            abort(404, 'Attachment file missing from secure storage.');
        }

        return Storage::disk('local')->download(
            $attachment->storage_path,
            $attachment->original_name,
            ['Content-Type' => $attachment->mime_type]
        );
    }

    /**
     * Send manual reminder notifications to pending approvers of an active workflow instance.
     */
    public function sendPendingReminder(WorkflowInstance $instance, User $actor): int
    {
        if ($instance->isTerminal()) {
            throw ValidationException::withMessages([
                'current_state' => ['Cannot send reminder for a completed workflow instance.'],
            ]);
        }

        $pendingTasks = $instance->tasks()->where('status', WorkflowStageTask::STATUS_PENDING)->get();
        if ($pendingTasks->isEmpty()) {
            return 0;
        }

        $this->notifyAssignedTasks($instance, $pendingTasks, WorkflowNotification::TYPE_REMINDER);
        $instance->update(['reminder_sent_at' => now()]);

        AuditLog::log(
            'WORKFLOW_REMINDER_SENT',
            WorkflowInstance::class,
            $instance->id,
            null,
            ['pending_tasks' => $pendingTasks->count()],
            $actor,
            $instance->administrative_unit_id
        );

        return $pendingTasks->count();
    }

    /**
     * Sweep overdue workflows, send reminders/escalations, and escalate to configured fallback roles/stages.
     *
     * @return array{checked: int, escalated: int, reminded: int}
     */
    public function processOverdueEscalations(?User $actor = null): array
    {
        $now = now();
        $overdueInstances = WorkflowInstance::with(['definition.stages.assignments', 'currentStage'])
            ->whereNotIn('current_state', WorkflowInstance::TERMINAL_STATES)
            ->whereNotNull('due_at')
            ->where('due_at', '<', $now)
            ->get();

        $escalated = 0;
        $reminded = 0;

        foreach ($overdueInstances as $instance) {
            if (!$instance->definition?->escalation_enabled) {
                continue;
            }

            // Avoid escalating repeatedly within the same 4-hour cooldown
            if ($instance->escalated_at && $instance->escalated_at->greaterThan($now->copy()->subHours(4))) {
                continue;
            }

            DB::transaction(function () use ($instance, $actor, $now, &$escalated, &$reminded) {
                /** @var WorkflowInstance $locked */
                $locked = WorkflowInstance::with(['currentStage', 'definition'])->whereKey($instance->id)->lockForUpdate()->first();
                if (!$locked || $locked->isTerminal()) {
                    return;
                }

                $stage = $locked->currentStage;
                $pendingTasks = $locked->tasks()->where('status', WorkflowStageTask::STATUS_PENDING)->get();

                // 1. Send overdue reminder to existing assignees
                $this->notifyAssignedTasks($locked, $pendingTasks, WorkflowNotification::TYPE_REMINDER);
                $reminded++;

                // 2. Escalate to fallback role if configured on the stage
                $escalationRole = $stage?->escalation_role_code;
                $escalationTask = null;

                if ($escalationRole) {
                    $alreadyHasEscalationRole = $pendingTasks->contains(fn ($t) => $t->assignee_role_code === $escalationRole);
                    if (!$alreadyHasEscalationRole) {
                        $escalationTask = WorkflowStageTask::create([
                            'id' => (string) Str::uuid(),
                            'workflow_instance_id' => $locked->id,
                            'workflow_stage_id' => $stage->id,
                            'step_sequence' => ($locked->tasks()->max('step_sequence') ?? 0) + 1,
                            'assignee_type' => WorkflowStageAssignment::ASSIGNEE_ROLE,
                            'assignee_role_code' => $escalationRole,
                            'administrative_unit_id' => $locked->administrative_unit_id,
                            'authority_type' => WorkflowStageAssignment::AUTHORITY_APPROVER,
                            'status' => WorkflowStageTask::STATUS_PENDING,
                            'due_at' => $now->copy()->addHours(max(1, (int) ($stage->sla_hours ?? 12))),
                        ]);
                    }
                }

                $locked->update([
                    'escalated_at' => $now,
                    'escalation_count' => $locked->escalation_count + 1,
                    'reminder_sent_at' => $now,
                    'assigned_role_code' => $escalationRole ?: $locked->assigned_role_code,
                ]);

                $systemActorId = $actor?->id ?? $locked->submitted_by;
                $systemActorName = $actor?->full_name_en ?? 'IDMS SLA Escalation Engine';
                $systemActorRole = $actor?->role_code ?? 'SYSTEM_ESCALATION';

                WorkflowTransition::create([
                    'id' => (string) Str::uuid(),
                    'workflow_instance_id' => $locked->id,
                    'workflow_stage_task_id' => $escalationTask?->id,
                    'from_stage_id' => $locked->current_stage_id,
                    'to_stage_id' => $locked->current_stage_id,
                    'action' => WorkflowTransition::ACTION_ESCALATE,
                    'from_state' => $locked->current_state,
                    'to_state' => $locked->current_state,
                    'action_by_user_id' => $systemActorId,
                    'actor_name' => $systemActorName,
                    'actor_role' => $systemActorRole,
                    'remarks' => $escalationRole
                        ? "SLA overdue: escalated to fallback authority {$escalationRole}."
                        : 'SLA overdue: escalation alert dispatched.',
                    'metadata' => ['escalation_count' => $locked->escalation_count],
                    'transitioned_at' => $now,
                ]);

                $this->notificationAdapter->sendNotification(
                    $locked,
                    WorkflowNotification::TYPE_ESCALATION,
                    "Overdue Workflow Escalated: {$locked->instance_no}",
                    "সময়সীমা অতিক্রান্ত কর্মপ্রবাহ এসকেলেট হয়েছে: {$locked->instance_no}",
                    "Workflow '{$locked->title_en}' exceeded SLA and has been escalated.",
                    "কর্মপ্রবাহ '{$locked->title_bn}' নির্ধারিত সময়সীমা অতিক্রম করায় ঊর্ধ্বতন পর্যায়ে পাঠানো হয়েছে।",
                    null,
                    $escalationRole ?: $locked->assigned_role_code,
                    $escalationTask
                );

                $escalated++;
            });
        }

        if ($escalated > 0) {
            $this->configService->bumpCacheVersion();
        }

        return [
            'checked' => $overdueInstances->count(),
            'escalated' => $escalated,
            'reminded' => $reminded,
        ];
    }

    /**
     * Scoped, Redis-cached Dashboard & Performance Statistics.
     */
    public function getDashboardSummary(User $actor, array $filters = []): array
    {
        $version = Cache::get(WorkflowConfigurationService::CACHE_VERSION_KEY, 1);
        $scopeKey = $actor->isNationalScope() ? 'NATIONAL' : ($actor->getAdministrativeScope()['ancestry_path'] ?? '/');
        $cacheKey = 'workflow:dashboard:v' . $version . ':' . md5($actor->id . '|' . $actor->role_code . '|' . $scopeKey . '|' . json_encode($filters));

        return Cache::remember($cacheKey, 60, function () use ($actor, $filters) {
            $baseQuery = WorkflowInstance::query()->inJurisdiction($actor);
            if (!empty($filters['module_code'])) {
                $baseQuery->where('module_code', strtoupper((string) $filters['module_code']));
            }

            $now = now();
            $terminalPlaceholders = "'" . implode("','", WorkflowInstance::TERMINAL_STATES) . "'";

            $myPendingQuery = (clone $baseQuery);
            $this->applyMyPendingFilter($myPendingQuery, $actor);
            $myPendingCount = $myPendingQuery->count();

            $agg = (clone $baseQuery)->selectRaw(
                "COUNT(*) as total_workflows, " .
                "SUM(CASE WHEN submitted_by = ? THEN 1 ELSE 0 END) as submitted_requests, " .
                "SUM(CASE WHEN current_state = ? THEN 1 ELSE 0 END) as approved_requests, " .
                "SUM(CASE WHEN current_state = ? THEN 1 ELSE 0 END) as rejected_requests, " .
                "SUM(CASE WHEN current_state = ? THEN 1 ELSE 0 END) as returned_requests, " .
                "SUM(CASE WHEN current_state = ? THEN 1 ELSE 0 END) as info_requested, " .
                "SUM(CASE WHEN current_state NOT IN ({$terminalPlaceholders}) THEN 1 ELSE 0 END) as active_workflows, " .
                "SUM(CASE WHEN current_state NOT IN ({$terminalPlaceholders}) AND due_at IS NOT NULL AND due_at < ? THEN 1 ELSE 0 END) as overdue_workflows, " .
                "SUM(CASE WHEN escalation_count > 0 THEN 1 ELSE 0 END) as escalated_workflows",
                [
                    $actor->id,
                    WorkflowInstance::STATE_FINAL_APPROVED,
                    WorkflowInstance::STATE_REJECTED,
                    WorkflowInstance::STATE_RETURNED_FOR_CORRECTION,
                    WorkflowInstance::STATE_INFO_REQUESTED,
                    $now,
                ]
            )->first();

            $completed = (clone $baseQuery)
                ->whereNotNull('completed_at')
                ->whereNotNull('submitted_at')
                ->get(['submitted_at', 'completed_at', 'due_at']);

            $avgTurnaroundHours = $completed->isNotEmpty()
                ? round($completed->avg(fn ($i) => $i->submitted_at->diffInMinutes($i->completed_at) / 60), 2)
                : 0.0;

            $slaCompliant = $completed->filter(fn ($i) => $i->due_at === null || $i->completed_at->lessThanOrEqualTo($i->due_at))->count();
            $slaComplianceRate = $completed->isNotEmpty()
                ? round(($slaCompliant / $completed->count()) * 100, 1)
                : 100.0;

            $byModule = (clone $baseQuery)
                ->selectRaw(
                    "module_code, COUNT(*) as total, " .
                    "SUM(CASE WHEN current_state NOT IN ({$terminalPlaceholders}) THEN 1 ELSE 0 END) as pending, " .
                    "SUM(CASE WHEN current_state = ? THEN 1 ELSE 0 END) as approved, " .
                    "SUM(CASE WHEN current_state = ? THEN 1 ELSE 0 END) as rejected, " .
                    "SUM(CASE WHEN current_state NOT IN ({$terminalPlaceholders}) AND due_at IS NOT NULL AND due_at < ? THEN 1 ELSE 0 END) as overdue",
                    [WorkflowInstance::STATE_FINAL_APPROVED, WorkflowInstance::STATE_REJECTED, $now]
                )
                ->groupBy('module_code')
                ->get()
                ->map(fn ($row) => [
                    'module_code' => $row->module_code,
                    'total' => (int) $row->total,
                    'pending' => (int) $row->pending,
                    'approved' => (int) $row->approved,
                    'rejected' => (int) $row->rejected,
                    'overdue' => (int) $row->overdue,
                ])->values()->all();

            $byState = (clone $baseQuery)
                ->selectRaw('current_state as state, COUNT(*) as count')
                ->groupBy('current_state')
                ->get()
                ->map(fn ($row) => [
                    'state' => $row->state,
                    'count' => (int) $row->count,
                ])->values()->all();

            $stagesTable = (new WorkflowStage())->getTable();
            $instancesTable = (new WorkflowInstance())->getTable();

            $byApprovalLevel = (clone $baseQuery)
                ->leftJoin($stagesTable, "{$instancesTable}.current_stage_id", '=', "{$stagesTable}.id")
                ->whereNotIn("{$instancesTable}.current_state", WorkflowInstance::TERMINAL_STATES)
                ->selectRaw(
                    "COALESCE({$stagesTable}.approval_level, 'UNASSIGNED') as approval_level, " .
                    "COUNT(*) as count, " .
                    "SUM(CASE WHEN {$instancesTable}.due_at IS NOT NULL AND {$instancesTable}.due_at < ? THEN 1 ELSE 0 END) as overdue",
                    [$now]
                )
                ->groupBy("{$stagesTable}.approval_level")
                ->get()
                ->map(fn ($row) => [
                    'approval_level' => $row->approval_level,
                    'count' => (int) $row->count,
                    'overdue' => (int) $row->overdue,
                ])->values()->all();

            return [
                'kpis' => [
                    'my_pending_approvals' => (int) $myPendingCount,
                    'submitted_requests' => (int) ($agg->submitted_requests ?? 0),
                    'approved_requests' => (int) ($agg->approved_requests ?? 0),
                    'rejected_requests' => (int) ($agg->rejected_requests ?? 0),
                    'returned_requests' => (int) ($agg->returned_requests ?? 0),
                    'info_requested' => (int) ($agg->info_requested ?? 0),
                    'active_workflows' => (int) ($agg->active_workflows ?? 0),
                    'overdue_workflows' => (int) ($agg->overdue_workflows ?? 0),
                    'escalated_workflows' => (int) ($agg->escalated_workflows ?? 0),
                    'total_workflows' => (int) ($agg->total_workflows ?? 0),
                    'avg_turnaround_hours' => $avgTurnaroundHours,
                    'sla_compliance_rate_pct' => $slaComplianceRate,
                ],
                'by_module' => $byModule,
                'by_state' => $byState,
                'by_approval_level' => $byApprovalLevel,
            ];
        });
    }

    public function getEntityHistory(string $entityType, string $entityId, User $actor)
    {
        return WorkflowInstance::with([
            'definition:id,code,name_en,name_bn',
            'currentStage:id,stage_code,name_en,name_bn,approval_level',
            'transitions.fromStage:id,stage_code,name_en,name_bn',
            'transitions.toStage:id,stage_code,name_en,name_bn',
            'attachments',
        ])
            ->inJurisdiction($actor)
            ->where('entity_type', strtoupper($entityType))
            ->where('entity_id', $entityId)
            ->orderByDesc('submitted_at')
            ->get();
    }

    protected function resolveDefinition(?string $definitionId, ?string $moduleCode, string $entityType): WorkflowDefinition
    {
        if ($definitionId) {
            $def = WorkflowDefinition::findOrFail($definitionId);
            if (!$def->is_active) {
                throw ValidationException::withMessages([
                    'workflow_definition_id' => ['Selected workflow definition is currently inactive.'],
                ]);
            }
            return $def;
        }

        $query = WorkflowDefinition::where('is_active', true)->where('entity_type', $entityType);
        if ($moduleCode) {
            $query->where('module_code', strtoupper($moduleCode));
        }

        $def = (clone $query)->where('is_default', true)->first() ?? $query->first();
        if (!$def) {
            throw ValidationException::withMessages([
                'workflow_definition_id' => ["No active workflow configuration found for entity type '{$entityType}'."],
            ]);
        }

        return $def;
    }

    protected function createStageTasks(WorkflowInstance $instance, WorkflowStage $stage, $dueAt)
    {
        $assignments = $stage->assignments()->orderBy('sort_order')->get();
        $nextSeq = ($instance->tasks()->max('step_sequence') ?? 0) + 1;
        $created = collect();

        if ($assignments->isEmpty()) {
            $created->push(WorkflowStageTask::create([
                'id' => (string) Str::uuid(),
                'workflow_instance_id' => $instance->id,
                'workflow_stage_id' => $stage->id,
                'step_sequence' => $nextSeq,
                'assignee_type' => WorkflowStageAssignment::ASSIGNEE_ROLE,
                'assignee_role_code' => $stage->escalation_role_code ?: 'ROLE_DRRO',
                'administrative_unit_id' => $instance->administrative_unit_id,
                'authority_type' => WorkflowStageAssignment::AUTHORITY_APPROVER,
                'status' => WorkflowStageTask::STATUS_PENDING,
                'due_at' => $dueAt,
            ]));
            return $created;
        }

        foreach ($assignments as $idx => $assignment) {
            $created->push(WorkflowStageTask::create([
                'id' => (string) Str::uuid(),
                'workflow_instance_id' => $instance->id,
                'workflow_stage_id' => $stage->id,
                'step_sequence' => $nextSeq + $idx,
                'assignee_type' => $assignment->assignee_type,
                'assignee_user_id' => $assignment->user_id,
                'assignee_role_code' => $assignment->role_code,
                'administrative_unit_id' => $assignment->administrative_unit_id ?: $instance->administrative_unit_id,
                'authority_type' => $assignment->authority_type,
                'status' => WorkflowStageTask::STATUS_PENDING,
                'due_at' => $dueAt,
            ]));
        }

        return $created;
    }

    protected function syncPrimaryAssigneeFields(WorkflowInstance $instance, $tasks): void
    {
        $first = $tasks->first();
        $instance->update([
            'assigned_to_user_id' => $first?->assignee_user_id,
            'assigned_role_code' => $first?->assignee_role_code,
        ]);
    }

    /**
     * Determine whether actor can act on an active pending task (directly, by role+scope, or via active delegation).
     *
     * @return array{task: WorkflowStageTask, delegated_from_user_id: string|null}|null
     */
    protected function resolveMatchingTaskForActor(WorkflowInstance $instance, User $actor): ?array
    {
        $pendingTasks = $instance->tasks()
            ->where('workflow_stage_id', $instance->current_stage_id)
            ->where('status', WorkflowStageTask::STATUS_PENDING)
            ->get();

        if ($pendingTasks->isEmpty()) {
            return null;
        }

        $unitPath = $instance->administrativeUnit?->ancestry_path ?? '/';

        // 1. Direct user or role+scope match
        foreach ($pendingTasks as $task) {
            if ($task->assignee_type === WorkflowStageAssignment::ASSIGNEE_USER && $task->assignee_user_id === $actor->id) {
                return ['task' => $task, 'delegated_from_user_id' => null];
            }

            if ($task->assignee_type === WorkflowStageAssignment::ASSIGNEE_ROLE && $task->assignee_role_code && $actor->hasRole($task->assignee_role_code)) {
                $taskUnitPath = $task->administrativeUnit?->ancestry_path ?? $unitPath;
                if ($this->scopeService->canAccessScope($actor, $taskUnitPath)) {
                    return ['task' => $task, 'delegated_from_user_id' => null];
                }
            }

            if ($task->assignee_type === WorkflowStageAssignment::ASSIGNEE_ADMINISTRATIVE_UNIT && $task->administrative_unit_id) {
                if ($actor->administrative_unit_id === $task->administrative_unit_id) {
                    return ['task' => $task, 'delegated_from_user_id' => null];
                }
            }
        }

        // 2. Authorized delegation match
        $definition = $instance->definition;
        $stage = $instance->currentStage;

        if (!$definition?->allow_delegation || ($stage && !$stage->allow_delegation)) {
            return null;
        }

        $delegations = WorkflowDelegation::with(['delegator', 'administrativeUnit'])
            ->currentlyValid()
            ->where('delegate_user_id', $actor->id)
            ->where(function ($q) use ($definition) {
                $q->whereNull('workflow_definition_id')
                    ->orWhere('workflow_definition_id', $definition->id);
            })
            ->get();

        foreach ($delegations as $delegation) {
            $delegator = $delegation->delegator;
            if ($delegator && !$delegator->isNationalScope() && $delegator->administrative_unit_id) {
                $delegatorUnit = AdministrativeUnit::find($delegator->administrative_unit_id);
                if ($delegatorUnit) {
                    $delegatorPath = rtrim($delegatorUnit->ancestry_path, '/') . '/';
                    if ($delegatorPath !== '/' && !str_starts_with(rtrim($unitPath, '/') . '/', $delegatorPath)) {
                        continue;
                    }
                }
            }
            if ($delegation->administrative_unit_id && $delegation->administrativeUnit) {
                $delPath = rtrim($delegation->administrativeUnit->ancestry_path, '/') . '/';
                if ($delPath !== '/' && !str_starts_with(rtrim($unitPath, '/') . '/', $delPath)) {
                    continue;
                }
            }

            foreach ($pendingTasks as $task) {
                $matchesDelegatorUser = $task->assignee_user_id && $task->assignee_user_id === $delegation->delegator_user_id;
                $matchesDelegatedRole = $task->assignee_role_code && (
                    $delegation->delegated_role_code === $task->assignee_role_code
                    || $delegation->delegator?->hasRole($task->assignee_role_code)
                );

                if ($matchesDelegatorUser || $matchesDelegatedRole) {
                    return [
                        'task' => $task,
                        'delegated_from_user_id' => $delegation->delegator_user_id,
                    ];
                }
            }
        }

        return null;
    }

    protected function isStageSatisfied(WorkflowInstance $instance, ?WorkflowStage $stage): bool
    {
        if (!$stage) {
            return true;
        }

        $stageTasks = $instance->tasks()->where('workflow_stage_id', $stage->id)->get();
        $approvedCount = $stageTasks->where('status', WorkflowStageTask::STATUS_APPROVED)->count();

        if ($stage->execution_mode === WorkflowStage::MODE_PARALLEL_ANY) {
            if ($approvedCount >= 1) {
                $instance->tasks()
                    ->where('workflow_stage_id', $stage->id)
                    ->where('status', WorkflowStageTask::STATUS_PENDING)
                    ->update(['status' => WorkflowStageTask::STATUS_SKIPPED]);
                return true;
            }
            return false;
        }

        if ($stage->execution_mode === WorkflowStage::MODE_PARALLEL_ALL) {
            $pendingCount = $stageTasks->where('status', WorkflowStageTask::STATUS_PENDING)->count();
            return $pendingCount === 0 && $approvedCount >= max(1, (int) $stage->required_approvals);
        }

        // SEQUENTIAL
        return $approvedCount >= 1;
    }

    protected function findNextStage(WorkflowDefinition $definition, ?WorkflowStage $currentStage): ?WorkflowStage
    {
        if (!$currentStage) {
            return null;
        }

        return $definition->stages()
            ->with('assignments')
            ->where('stage_order', '>', $currentStage->stage_order)
            ->orderBy('stage_order')
            ->first();
    }

    protected function mapStageToState(WorkflowStage $stage): string
    {
        return match ($stage->approval_level) {
            WorkflowStage::LEVEL_SUBMITTED => WorkflowInstance::STATE_SUBMITTED,
            WorkflowStage::LEVEL_INITIAL_REVIEW => WorkflowInstance::STATE_INITIAL_REVIEW,
            WorkflowStage::LEVEL_SUPERVISOR_APPROVAL => WorkflowInstance::STATE_SUPERVISOR_APPROVAL,
            WorkflowStage::LEVEL_DISTRICT_APPROVAL => WorkflowInstance::STATE_DISTRICT_APPROVAL,
            WorkflowStage::LEVEL_DIRECTORATE_APPROVAL => WorkflowInstance::STATE_DIRECTORATE_APPROVAL,
            WorkflowStage::LEVEL_MINISTRY_APPROVAL => WorkflowInstance::STATE_MINISTRY_APPROVAL,
            WorkflowStage::LEVEL_FINAL_APPROVAL => WorkflowInstance::STATE_MINISTRY_APPROVAL,
            default => WorkflowInstance::STATE_INITIAL_REVIEW,
        };
    }

    protected function recordTransition(
        WorkflowInstance $instance,
        string $action,
        ?string $fromStageId,
        ?string $toStageId,
        string $fromState,
        string $toState,
        User $actor,
        ?string $remarks = null,
        ?string $taskId = null,
        ?string $actedOnBehalfOfUserId = null,
        ?array $metadata = null
    ): WorkflowTransition {
        return WorkflowTransition::create([
            'id' => (string) Str::uuid(),
            'workflow_instance_id' => $instance->id,
            'workflow_stage_task_id' => $taskId,
            'from_stage_id' => $fromStageId,
            'to_stage_id' => $toStageId,
            'action' => $action,
            'from_state' => $fromState,
            'to_state' => $toState,
            'action_by_user_id' => $actor->id,
            'acted_on_behalf_of_user_id' => $actedOnBehalfOfUserId,
            'actor_name' => $actor->full_name_en,
            'actor_role' => $actor->role_code,
            'remarks' => $remarks,
            'metadata' => $metadata,
            'transitioned_at' => now(),
        ]);
    }

    protected function notifyAssignedTasks(WorkflowInstance $instance, $tasks, string $type): void
    {
        foreach ($tasks as $task) {
            $this->notificationAdapter->sendNotification(
                $instance,
                $type,
                "Action Required: {$instance->instance_no} ({$instance->title_en})",
                "অনুমোদন প্রয়োজন: {$instance->instance_no}",
                "Workflow '{$instance->title_en}' is awaiting your action.",
                "কর্মপ্রবাহ '{$instance->title_bn}' আপনার সিদ্ধান্তের জন্য অপেক্ষমাণ।",
                $task->assignee_user_id,
                $task->assignee_role_code,
                $task
            );
        }
    }

    protected function notifySubmitter(
        WorkflowInstance $instance,
        string $type,
        string $titleEn,
        string $titleBn,
        string $msgEn,
        string $msgBn
    ): void {
        $this->notificationAdapter->sendNotification(
            $instance,
            $type,
            $titleEn,
            $titleBn,
            $msgEn,
            $msgBn,
            $instance->submitted_by,
            null
        );
    }

    protected function logAndInvalidate(
        WorkflowInstance $instance,
        string $action,
        string $fromState,
        User $actor,
        WorkflowTransition $transition
    ): void {
        AuditLog::log(
            "WORKFLOW_TRANSITION_{$action}",
            WorkflowInstance::class,
            $instance->id,
            ['current_state' => $fromState],
            [
                'current_state' => $instance->current_state,
                'transition_id' => $transition->id,
                'remarks' => $transition->remarks,
            ],
            $actor,
            $instance->administrative_unit_id
        );

        $this->configService->bumpCacheVersion();
    }

    protected function applyMyPendingFilter(Builder $query, User $actor): void
    {
        $delegatorIds = WorkflowDelegation::currentlyValid()
            ->where('delegate_user_id', $actor->id)
            ->pluck('delegator_user_id')
            ->all();

        $delegatedRoles = WorkflowDelegation::currentlyValid()
            ->where('delegate_user_id', $actor->id)
            ->whereNotNull('delegated_role_code')
            ->pluck('delegated_role_code')
            ->all();

        $roles = array_values(array_unique(array_filter(array_merge(
            [$actor->role_code],
            $actor->getJwtClaims()['roles'] ?? [],
            $delegatedRoles
        ))));

        $tasksTable = (new WorkflowStageTask())->getTable();
        $instancesTable = (new WorkflowInstance())->getTable();

        $query->whereNotIn('current_state', WorkflowInstance::TERMINAL_STATES)
            ->whereNotIn('current_state', [
                WorkflowInstance::STATE_RETURNED_FOR_CORRECTION,
                WorkflowInstance::STATE_INFO_REQUESTED,
            ])
            ->whereHas('tasks', function (Builder $tq) use ($actor, $roles, $delegatorIds, $tasksTable, $instancesTable) {
                $tq->where('status', WorkflowStageTask::STATUS_PENDING)
                    ->whereColumn("{$tasksTable}.workflow_stage_id", "{$instancesTable}.current_stage_id")
                    ->where(function (Builder $aq) use ($actor, $roles, $delegatorIds) {
                        $aq->where('assignee_user_id', $actor->id);
                        if (!empty($roles)) {
                            $aq->orWhereIn('assignee_role_code', $roles);
                        }
                        if (!empty($delegatorIds)) {
                            $aq->orWhereIn('assignee_user_id', $delegatorIds);
                        }
                    });
            });
    }

    protected function loadFullInstance(WorkflowInstance $instance): WorkflowInstance
    {
        return $instance->fresh([
            'definition.stages.assignments',
            'currentStage.assignments',
            'administrativeUnit',
            'submitter',
            'assignedUser',
            'lastActor',
            'tasks.stage',
            'tasks.assigneeUser',
            'tasks.delegatedFromUser',
            'tasks.actor',
            'transitions.fromStage',
            'transitions.toStage',
            'transitions.actor',
            'transitions.actedOnBehalfOf',
            'attachments.uploader',
        ]);
    }

    protected function generateInstanceNo(): string
    {
        return 'WF-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
    }
}
