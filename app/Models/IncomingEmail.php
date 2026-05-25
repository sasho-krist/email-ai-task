<?php

namespace App\Models;

use App\Enums\IncomingEmailStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class IncomingEmail extends Model
{
    protected $fillable = [
        'from',
        'subject',
        'body',
        'content_hash',
        'status',
        'failure_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => IncomingEmailStatus::class,
        ];
    }

    public function taskDraft(): HasOne
    {
        return $this->hasOne(TaskDraft::class);
    }

    public function aiEvaluations(): HasMany
    {
        return $this->hasMany(AiEvaluation::class);
    }
}
