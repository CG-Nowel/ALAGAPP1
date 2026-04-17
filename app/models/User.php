<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class User extends Model
{
    protected string $table = 'users';

    public function findByEmail(string $email): ?array
    {
        return $this->findBy('email', $email);
    }

    public function getDoctors(int $limit = 100, int $offset = 0): array
    {
        return $this->db->fetchAll(
            "SELECT id, first_name, last_name, email, phone, specialization, license_number,
                    years_of_experience, status, profile_picture, created_at
             FROM users
             WHERE user_type IN ('DOCTOR', 'DOCTOR_OWNER') AND status = 'active'
             ORDER BY last_name, first_name
             LIMIT ? OFFSET ?",
            [$limit, $offset]
        );
    }

    public function getParents(int $limit = 100, int $offset = 0): array
    {
        return $this->db->fetchAll(
            "SELECT id, first_name, last_name, email, phone, status, created_at
             FROM users WHERE user_type = 'PARENT'
             ORDER BY last_name, first_name
             LIMIT ? OFFSET ?",
            [$limit, $offset]
        );
    }

    public function createUser(array $data): int
    {
        $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        return $this->create($data);
    }

    public function verifyPassword(int $userId, string $password): bool
    {
        $user = $this->find($userId);
        if (!$user) {
            return false;
        }
        return password_verify($password, $user['password']);
    }

    public function updatePassword(int $userId, string $newPassword): int
    {
        return $this->updateById($userId, [
            'password' => password_hash($newPassword, PASSWORD_DEFAULT),
            'force_password_change' => 0,
            'password_reset_token' => null,
            'password_reset_expires' => null,
        ]);
    }

    public function incrementLoginAttempts(int $userId): void
    {
        $config = require dirname(__DIR__, 2) . '/config/app.php';
        $maxAttempts = $config['security']['max_login_attempts'];
        $lockoutDuration = $config['security']['lockout_duration'];

        $this->db->query(
            "UPDATE users SET login_attempts = login_attempts + 1,
             locked_until = IF(login_attempts + 1 >= ?, DATE_ADD(NOW(), INTERVAL ? SECOND), locked_until)
             WHERE id = ?",
            [$maxAttempts, $lockoutDuration, $userId]
        );
    }

    public function resetLoginAttempts(int $userId): void
    {
        $this->updateById($userId, [
            'login_attempts' => 0,
            'locked_until' => null,
            'last_login_at' => date('Y-m-d H:i:s'),
            'last_login_ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    }

    public function isLocked(int $userId): bool
    {
        $user = $this->db->fetchOne(
            "SELECT locked_until FROM users WHERE id = ? AND locked_until > NOW()",
            [$userId]
        );
        return $user !== null;
    }

    public function setEmailVerificationToken(int $userId, string $token): void
    {
        $this->updateById($userId, ['email_verification_token' => $token]);
    }

    public function verifyEmail(string $token): bool
    {
        $user = $this->findBy('email_verification_token', $token);
        if (!$user) {
            return false;
        }

        $this->updateById($user['id'], [
            'email_verified_at' => date('Y-m-d H:i:s'),
            'email_verification_token' => null,
            'status' => 'active',
        ]);

        return true;
    }

    public function setPasswordResetToken(int $userId, string $token, int $expirySeconds = 3600): void
    {
        $this->updateById($userId, [
            'password_reset_token' => $token,
            'password_reset_expires' => date('Y-m-d H:i:s', time() + $expirySeconds),
        ]);
    }

    public function findByResetToken(string $token): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM users WHERE password_reset_token = ? AND password_reset_expires > NOW()",
            [$token]
        );
    }

    public function searchUsers(string $search, ?string $userType = null, int $limit = 15, int $offset = 0): array
    {
        $sql = "SELECT id, first_name, last_name, email, phone, user_type, status, created_at
                FROM users WHERE (first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)";
        $params = ["%$search%", "%$search%", "%$search%"];

        if ($userType) {
            $sql .= " AND user_type = ?";
            $params[] = $userType;
        }

        $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        return $this->db->fetchAll($sql, $params);
    }

    public function getUserStats(): array
    {
        return [
            'total_users' => (int) $this->db->fetchColumn("SELECT COUNT(*) FROM users"),
            'total_parents' => (int) $this->db->fetchColumn("SELECT COUNT(*) FROM users WHERE user_type = 'PARENT'"),
            'total_doctors' => (int) $this->db->fetchColumn("SELECT COUNT(*) FROM users WHERE user_type IN ('DOCTOR', 'DOCTOR_OWNER')"),
            'total_admins' => (int) $this->db->fetchColumn("SELECT COUNT(*) FROM users WHERE user_type = 'ADMIN'"),
            'active_users' => (int) $this->db->fetchColumn("SELECT COUNT(*) FROM users WHERE status = 'active'"),
            'new_this_month' => (int) $this->db->fetchColumn(
                "SELECT COUNT(*) FROM users WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')"
            ),
        ];
    }
}
