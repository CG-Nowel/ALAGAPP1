<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class ActivityLog extends Model
{
    protected string $table = 'activity_logs';

    /**
     * Log an activity.
     */
    public function log(
        string $action,
        ?int $userId = null,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $details = null
    ): int {
        return $this->create([
            'user_id' => $userId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'details' => $details,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);
    }

    /**
     * Get recent activity logs with user info.
     */
    public function getRecent(int $limit = 50, int $offset = 0): array
    {
        return $this->db->fetchAll(
            "SELECT al.*, u.first_name, u.last_name, u.email, u.user_type
             FROM activity_logs al
             LEFT JOIN users u ON al.user_id = u.id
             ORDER BY al.created_at DESC
             LIMIT ? OFFSET ?",
            [$limit, $offset]
        );
    }

    /**
     * Search activity logs.
     */
    public function search(string $query, ?string $action = null, ?string $dateFrom = null, ?string $dateTo = null, int $limit = 50, int $offset = 0): array
    {
        $sql = "SELECT al.*, u.first_name, u.last_name, u.email
                FROM activity_logs al
                LEFT JOIN users u ON al.user_id = u.id
                WHERE 1=1";
        $params = [];

        if ($query) {
            $sql .= " AND (al.action LIKE ? OR al.details LIKE ? OR u.email LIKE ?)";
            $params[] = "%$query%";
            $params[] = "%$query%";
            $params[] = "%$query%";
        }

        if ($action) {
            $sql .= " AND al.action = ?";
            $params[] = $action;
        }

        if ($dateFrom) {
            $sql .= " AND al.created_at >= ?";
            $params[] = $dateFrom;
        }

        if ($dateTo) {
            $sql .= " AND al.created_at <= ?";
            $params[] = $dateTo . ' 23:59:59';
        }

        $sql .= " ORDER BY al.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        return $this->db->fetchAll($sql, $params);
    }
}
