<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditTrail
{
    /** @param array<string, mixed> $metadata */
    public function record(User $actor, Model $subject, string $event, array $metadata = []): AuditEvent
    {
        return AuditEvent::query()->create([
            'institution_id' => $actor->institution_id,
            'actor_id' => $actor->id,
            'actor_role' => $actor->role->value,
            'event_type' => $event,
            'auditable_type' => $subject::class,
            'auditable_id' => (string) $subject->getKey(),
            'metadata' => $metadata,
        ]);
    }
}
