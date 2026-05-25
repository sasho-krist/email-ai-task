<?php

namespace App\Models;

use App\Enums\AiEvaluationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiEvaluation extends Model
{
    protected $fillable = [
        'incoming_email_id',
        'task_draft_id',
        'provider',
        'prompt_version',
        'raw_request',
        'raw_response',
        'status',
        'error_message',
        'processing_time_ms',
    ];

    protected function casts(): array
    {
        return [
            'status' => AiEvaluationStatus::class,
            'raw_request' => 'array',
            'raw_response' => 'array',
        ];
    }

    public function incomingEmail(): BelongsTo
    {
        return $this->belongsTo(IncomingEmail::class);
    }

    public function taskDraft(): BelongsTo
    {
        return $this->belongsTo(TaskDraft::class);
    }
}
