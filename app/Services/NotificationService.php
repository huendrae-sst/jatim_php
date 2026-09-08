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
        ?string $url = null,
        ?int $userId = null,
        string $priority = 'HIGH'
    ): Notification {
        return Notification::create([
            'user_id' => $userId,
            'target_role' => $targetRole,
            'target_organization_id' => $orgId,
            'type' => 'ACTION_REQUIRED',
            'priority' => $priority,
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
        ?string $txType = null,
        ?int $txId = null,
        ?string $url = null,
        ?int $userId = null
    ): Notification {
        return Notification::create([
            'user_id' => $userId,
            'target_role' => $targetRole,
            'target_organization_id' => $orgId,
            'type' => 'ALERT',
            'priority' => $priority,
            'title' => $title,
            'message' => $message,
            'reference_transaction_type' => $txType,
            'reference_transaction_id' => $txId,
            'action_url' => $url,
            'is_read' => false,
        ]);
    }

    public static function sendInfo(
        string $title,
        string $message,
        ?int $userId = null,
        ?string $targetRole = null,
        ?string $url = null,
        ?int $orgId = null,
        ?string $txType = null,
        ?int $txId = null,
        string $priority = 'INFO'
    ): Notification {
        return Notification::create([
            'user_id' => $userId,
            'target_role' => $targetRole,
            'target_organization_id' => $orgId,
            'type' => 'INFORMATION',
            'priority' => $priority,
            'title' => $title,
            'message' => $message,
            'reference_transaction_type' => $txType,
            'reference_transaction_id' => $txId,
            'action_url' => $url,
            'is_read' => false,
        ]);
    }

    public static function sendUser(
        int $userId,
        string $title,
        string $message,
        string $type = 'INFORMATION',
        string $priority = 'INFO',
        ?string $txType = null,
        ?int $txId = null,
        ?string $url = null
    ): Notification {
        return Notification::create([
            'user_id' => $userId,
            'target_role' => null,
            'target_organization_id' => null,
            'type' => $type,
            'priority' => $priority,
            'title' => $title,
            'message' => $message,
            'reference_transaction_type' => $txType,
            'reference_transaction_id' => $txId,
            'action_url' => $url,
            'is_read' => false,
        ]);
    }

    public static function sendRole(
        string $targetRole,
        string $title,
        string $message,
        ?int $orgId = null,
        string $type = 'ACTION_REQUIRED',
        string $priority = 'HIGH',
        ?string $txType = null,
        ?int $txId = null,
        ?string $url = null
    ): Notification {
        return Notification::create([
            'user_id' => null,
            'target_role' => $targetRole,
            'target_organization_id' => $orgId,
            'type' => $type,
            'priority' => $priority,
            'title' => $title,
            'message' => $message,
            'reference_transaction_type' => $txType,
            'reference_transaction_id' => $txId,
            'action_url' => $url,
            'is_read' => false,
        ]);
    }
}
