<?php

namespace App\Models;

use App\Enums\TaskDraftStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TaskDraft extends Model
{
    protected $fillable = [
        'incoming_email_id',
        'type',
        'title',
        'summary',
        'priority',
        'suggested_project',
        'suggested_team',
        'confidence',
        'missing_information',
        'suggested_next_action',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'type' => TaskType::class,
            'priority' => TaskPriority::class,
            'status' => TaskDraftStatus::class,
            'confidence' => 'float',
            'missing_information' => 'array',
        ];
    }

    public function incomingEmail(): BelongsTo
    {
        return $this->belongsTo(IncomingEmail::class);
    }

    public function aiEvaluation(): HasOne
    {
        return $this->hasOne(AiEvaluation::class);
    }

    public function approvalDecisions(): HasMany
    {
        return $this->hasMany(ApprovalDecision::class);
    }

    public function latestApprovalDecision(): HasOne
    {
        return $this->hasOne(ApprovalDecision::class)->latestOfMany();
    }

    public function isPendingReview(): bool
    {
        return $this->status === TaskDraftStatus::PendingReview;
    }
}
