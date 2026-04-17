<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Notification;
use App\Helpers\Response;

class NotificationController extends Controller
{
    private Notification $notificationModel;

    public function __construct()
    {
        parent::__construct();
        $this->notificationModel = new Notification();
    }

    /**
     * Get notifications for the bell dropdown.
     */
    public function bell(): void
    {
        $unreadCount = $this->notificationModel->getUnreadCount($this->userId());
        $notifications = $this->notificationModel->getUnread($this->userId(), 10);

        Response::success([
            'unread_count' => $unreadCount,
            'notifications' => $notifications,
        ]);
    }

    /**
     * Get all notifications (paginated).
     */
    public function index(): void
    {
        $page = (int) ($_GET['page'] ?? 1);
        $perPage = 20;
        $offset = ($page - 1) * $perPage;

        $notifications = $this->notificationModel->getForUser($this->userId(), $perPage, $offset);
        $total = $this->notificationModel->count('user_id = ?', [$this->userId()]);

        Response::paginated($notifications, [
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => (int) ceil($total / $perPage),
        ]);
    }

    /**
     * Mark a notification as read.
     */
    public function markRead(string $id): void
    {
        $this->notificationModel->markRead((int) $id, $this->userId());
        Response::success(null, 'Notification marked as read.');
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllRead(): void
    {
        $count = $this->notificationModel->markAllRead($this->userId());
        Response::success(['count' => $count], "$count notifications marked as read.");
    }
}
