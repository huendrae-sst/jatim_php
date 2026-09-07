<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

class AuditTrailService
{
    public static function log(string $action, Model $model, ?array $oldValues = null, ?array $newValues = null, ?User $user = null): AuditLog
    {
        $user = $user ?: auth()->user();

        return AuditLog::create([
            'user_id' => $user?->id,
            'action' => $action,
            'auditable_type' => get_class($model),
            'auditable_id' => $model->id,
            'organization_id' => $user?->organization_id,
            'ip_address' => Request::ip() ?? '127.0.0.1',
            'user_agent' => Request::userAgent() ?? 'System / CLI',
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);
    }
}
