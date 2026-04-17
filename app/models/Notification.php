<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class Notification extends Model
{
    protected string $table = 'notifications';

    /**
     * Get unread notifications for a user.
     */
    public function getUnread(int $userId, int $limit = 20): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT ?",
            [$userId, $limit]
        );
    }

    /**
     * Get unread count for a user.
     */
    public function getUnreadCount(int $userId): int
    {
        return (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0",
            [$userId]
        );
    }

    /**
     * Get all notifications for a user (paginated).
     */
    public function getForUser(int $userId, int $limit = 20, int $offset = 0): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?",
            [$userId, $limit, $offset]
        );
    }

    /**
     * Mark a notification as read.
     */
    public function markRead(int $notificationId, int $userId): bool
    {
        return $this->db->update(
            'notifications',
            ['is_read' => 1, 'read_at' => date('Y-m-d H:i:s')],
            'id = ? AND user_id = ?',
            [$notificationId, $userId]
        ) > 0;
    }

    /**
     * Mark all notifications as read for a user.
     */
    public function markAllRead(int $userId): int
    {
        return $this->db->update(
            'notifications',
            ['is_read' => 1, 'read_at' => date('Y-m-d H:i:s')],
            'user_id = ? AND is_read = 0',
            [$userId]
        );
    }

    /**
     * Create a notification.
     */
    public function createNotification(
        int $userId,
        string $title,
        string $message,
        string $type = 'SYSTEM',
        string $channel = 'IN_APP',
        ?string $relatedType = null,
        ?int $relatedId = null
    ): int {
        return $this->create([
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'channel' => $channel,
            'related_type' => $relatedType,
            'related_id' => $relatedId,
        ]);
    }
}
