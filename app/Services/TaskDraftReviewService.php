<?php

namespace App\Services;

use App\Enums\ApprovalAction;
use App\Enums\TaskDraftStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskType;
use App\Exceptions\OverrideRequiresReasonException;
use App\Exceptions\TaskDraftAlreadyProcessedException;
use App\Models\ApprovalDecision;
use App\Models\TaskDraft;
use Illuminate\Support\Facades\DB;

class TaskDraftReviewService
{
    private const OVERRIDABLE_FIELDS = [
        'type',
        'title',
        'summary',
        'priority',
        'suggested_project',
        'suggested_team',
        'suggested_next_action',
    ];

    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function approve(TaskDraft $draft, string $operatorName, ?string $note = null): TaskDraft
    {
        return $this->recordDecision(
            draft: $draft,
            action: ApprovalAction::Approved,
            operatorName: $operatorName,
            note: $note,
            nextStatus: TaskDraftStatus::Approved,
            overrideFields: null,
            overrideReason: null,
        );
    }

    public function reject(TaskDraft $draft, string $operatorName, ?string $note = null): TaskDraft
    {
        return $this->recordDecision(
            draft: $draft,
            action: ApprovalAction::Rejected,
            operatorName: $operatorName,
            note: $note,
            nextStatus: TaskDraftStatus::Rejected,
            overrideFields: null,
            overrideReason: null,
        );
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    public function override(TaskDraft $draft, string $operatorName, array $fields, ?string $overrideReason = null, ?string $note = null): TaskDraft
    {
        $sanitizedFields = $this->sanitizeOverrideFields($fields);

        if ($sanitizedFields !== [] && blank($overrideReason)) {
            throw new OverrideRequiresReasonException();
        }

        return DB::transaction(function () use ($draft, $operatorName, $sanitizedFields, $overrideReason, $note) {
            $this->assertPendingReview($draft);

            if ($sanitizedFields !== []) {
                $draft->fill($this->castOverrideFields($sanitizedFields));
                $draft->save();
            }

            return $this->recordDecision(
                draft: $draft,
                action: ApprovalAction::Overridden,
                operatorName: $operatorName,
                note: $note,
                nextStatus: TaskDraftStatus::Overridden,
                overrideFields: $sanitizedFields !== [] ? $sanitizedFields : null,
                overrideReason: $overrideReason,
            );
        });
    }

    /**
     * @param  array<string, mixed>|null  $overrideFields
     */
    private function recordDecision(
        TaskDraft $draft,
        ApprovalAction $action,
        string $operatorName,
        ?string $note,
        TaskDraftStatus $nextStatus,
        ?array $overrideFields,
        ?string $overrideReason,
    ): TaskDraft {
        return DB::transaction(function () use ($draft, $action, $operatorName, $note, $nextStatus, $overrideFields, $overrideReason) {
            $draft->refresh();
            $this->assertPendingReview($draft);

            ApprovalDecision::query()->create([
                'task_draft_id' => $draft->id,
                'action' => $action,
                'operator_name' => $operatorName,
                'note' => $note,
                'override_fields' => $overrideFields,
                'override_reason' => $overrideReason,
            ]);

            $draft->update(['status' => $nextStatus]);

            $this->auditLogger->log('task_draft', $draft->id, $action->value, $operatorName, [
                'note' => $note,
                'override_fields' => $overrideFields,
                'override_reason' => $overrideReason,
            ]);

            return $draft->fresh(['incomingEmail', 'aiEvaluation', 'approvalDecisions']);
        });
    }

    private function assertPendingReview(TaskDraft $draft): void
    {
        if (! $draft->isPendingReview()) {
            throw new TaskDraftAlreadyProcessedException($draft->status);
        }
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private function sanitizeOverrideFields(array $fields): array
    {
        return collect($fields)
            ->only(self::OVERRIDABLE_FIELDS)
            ->reject(fn ($value) => $value === null || $value === '')
            ->all();
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private function castOverrideFields(array $fields): array
    {
        if (isset($fields['type'])) {
            $fields['type'] = TaskType::from($fields['type']);
        }

        if (isset($fields['priority'])) {
            $fields['priority'] = TaskPriority::from($fields['priority']);
        }

        return $fields;
    }
}
