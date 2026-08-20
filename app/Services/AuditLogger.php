<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLogger
{
    public function log(
        string $action,
        ?Model $subject = null,
        array $before = [],
        array $after = [],
        ?string $reason = null,
    ): AuditLog {
        return AuditLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'auditable_type' => $subject?->getMorphClass(),
            'auditable_id' => $subject?->getKey(),
            'before' => $before ?: null,
            'after' => $after ?: null,
            'reason' => $reason,
            'ip_address' => Request::ip(),
            'user_agent' => substr((string) Request::userAgent(), 0, 255),
        ]);
    }

    /** Convenience for admin actions that change a model's state. */
    public function logChange(string $action, Model $subject, array $changes, ?string $reason = null): AuditLog
    {
        return $this->log(
            $action,
            $subject,
            before: collect($changes)->keys()->mapWithKeys(
                fn ($key) => [$key => $subject->getOriginal($key)]
            )->all(),
            after: $changes,
            reason: $reason,
        );
    }
}
