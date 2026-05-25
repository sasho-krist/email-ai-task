<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\TaskDraft */
class TaskDraftResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'title' => $this->title,
            'summary' => $this->summary,
            'priority' => $this->priority->value,
            'suggested_project' => $this->suggested_project,
            'suggested_team' => $this->suggested_team,
            'confidence' => $this->confidence,
            'missing_information' => $this->missing_information ?? [],
            'suggested_next_action' => $this->suggested_next_action,
            'status' => $this->status->value,
            'incoming_email' => $this->whenLoaded('incomingEmail', fn () => [
                'id' => $this->incomingEmail->id,
                'from' => $this->incomingEmail->from,
                'subject' => $this->incomingEmail->subject,
                'body' => $this->incomingEmail->body,
                'status' => $this->incomingEmail->status->value,
            ]),
            'ai_evaluation' => $this->whenLoaded('aiEvaluation', fn () => $this->aiEvaluation ? [
                'id' => $this->aiEvaluation->id,
                'provider' => $this->aiEvaluation->provider,
                'prompt_version' => $this->aiEvaluation->prompt_version,
                'status' => $this->aiEvaluation->status->value,
                'processing_time_ms' => $this->aiEvaluation->processing_time_ms,
            ] : null),
            'approval_decisions' => $this->whenLoaded('approvalDecisions', fn () => $this->approvalDecisions->map(fn ($decision) => [
                'id' => $decision->id,
                'action' => $decision->action->value,
                'operator_name' => $decision->operator_name,
                'note' => $decision->note,
                'override_fields' => $decision->override_fields,
                'override_reason' => $decision->override_reason,
                'created_at' => $decision->created_at?->toIso8601String(),
            ])),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
