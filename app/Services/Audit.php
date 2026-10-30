<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Models\AdminAuditLog;
use App\Models\Device;
use App\Models\User;

/** Запис у admin_audit_logs. Викликається з кожної дії адміна, що змінює доступи, квитки, окуляри чи гроші */
class Audit
{
    public static function log(
        AuditAction $action,
        ?User $targetUser = null,
        ?Device $targetDevice = null,
        ?array $old = null,
        ?array $new = null,
        ?string $reason = null,
    ): AdminAuditLog {
        return AdminAuditLog::create([
            'admin_user_id' => auth()->id(),
            'target_user_id' => $targetUser?->id,
            'target_device_id' => $targetDevice?->id,
            'action_type' => $action,
            'old_value' => $old,
            'new_value' => $new,
            'reason_comment' => $reason,
            'ip_address' => request()?->ip(),
        ]);
    }
}
