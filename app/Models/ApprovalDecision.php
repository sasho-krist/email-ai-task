<?php

namespace App\Models;

use App\Enums\ApprovalAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalDecision extends Model
{
    protected $fillable = [
        'task_draft_id',
        'action',
        'operator_name',
        'note',
        'override_fields',
        'override_reason',
    ];

    protected function casts(): array
    {
        return [
            'action' => ApprovalAction::class,
            'override_fields' => 'array',
        ];
    }

    public function taskDraft(): BelongsTo
    {
        return $this->belongsTo(TaskDraft::class);
    }
}
