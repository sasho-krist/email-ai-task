<?php

namespace App\Services;

use App\Models\AuditLog;

class AuditLogger
{
    /**
     * @param  array<string, mixed>|null  $payload
     */
    public function log(string $entityType, int $entityId, string $action, ?string $actor = null, ?array $payload = null): AuditLog
    {
        return AuditLog::query()->create([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'action' => $action,
            'actor' => $actor,
            'payload' => $payload,
        ]);
    }
}
