<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/** Writes append-only audit rows. Called by services and observers, never by controllers. */
final class Audit
{
    public static function record(
        string $action,
        ?Model $subject = null,
        ?array $old = null,
        ?array $new = null,
        ?int $actorId = null,
        ?int $impersonatorId = null,
    ): AuditLog {
        $request = app()->runningInConsole() ? null : request();

        return AuditLog::create([
            'actor_id' => $actorId,
            'impersonator_id' => $impersonatorId,
            'action' => $action,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'old' => $old,
            'new' => $new,
            'ip' => $request?->ip(),
            'user_agent' => $request ? substr((string) $request->userAgent(), 0, 500) : null,
        ]);
    }
}
