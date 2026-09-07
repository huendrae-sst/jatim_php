<?php

namespace App\Services;

use App\Models\Notification;

class NotificationService
{
    public static function sendActionRequired(
        string $title,
        string $message,
        string $targetRole,
        ?int $orgId = null,
        ?string $txType = null,
        ?int $txId = null,
        ?string $url = null
    ): Notification {
        return Notification::create([
            'target_role' => $targetRole,
            'target_organization_id' => $orgId,
            'type' => 'ACTION_REQUIRED',
            'priority' => 'HIGH',
            'title' => $title,
            'message' => $message,
            'reference_transaction_type' => $txType,
            'reference_transaction_id' => $txId,
            'action_url' => $url,
            'is_read' => false,
        ]);
    }

    public static function sendAlert(
        string $title,
        string $message,
        string $priority = 'WARNING',
        ?string $targetRole = null,
        ?int $orgId = null,
        ?string $url = null
    ): Notification {
        return Notification::create([
            'target_role' => $targetRole,
            'target_organization_id' => $orgId,
            'type' => 'ALERT',
            'priority' => $priority,
            'title' => $title,
            'message' => $message,
            'action_url' => $url,
            'is_read' => false,
        ]);
    }

    public static function sendInfo(
        string $title,
        string $message,
        ?int $userId = null,
        ?string $targetRole = null,
        ?string $url = null
    ): Notification {
        return Notification::create([
            'user_id' => $userId,
            'target_role' => $targetRole,
            'type' => 'INFORMATION',
            'priority' => 'INFO',
            'title' => $title,
            'message' => $message,
            'action_url' => $url,
            'is_read' => false,
        ]);
    }
}
