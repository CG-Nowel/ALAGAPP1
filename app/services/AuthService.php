<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\ActivityLog;

class AuthService
{
    private User $userModel;
    private ActivityLog $activityLog;

    public function __construct()
    {
        $this->userModel = new User();
        $this->activityLog = new ActivityLog();
    }

    /**
     * Attempt to log in a user.
     */
    public function login(string $email, string $password): array
    {
        $user = $this->userModel->findByEmail($email);

        if (!$user) {
            return ['success' => false, 'message' => 'Invalid email or password.'];
        }

        // Check if account is locked
        if ($this->userModel->isLocked($user['id'])) {
            $this->activityLog->log('LOGIN_LOCKED', $user['id'], 'user', $user['id'], 'Login attempt while locked');
            return ['success' => false, 'message' => 'Account is temporarily locked. Please try again later.'];
        }

        // Check account status
        if ($user['status'] !== 'active' && $user['status'] !== 'pending') {
            return ['success' => false, 'message' => 'Your account has been deactivated. Contact support.'];
        }

        // Verify password
        if (!password_verify($password, $user['password'])) {
            $this->userModel->incrementLoginAttempts($user['id']);
            $this->activityLog->log('LOGIN_FAILED', $user['id'], 'user', $user['id'], 'Invalid password');
            return ['success' => false, 'message' => 'Invalid email or password.'];
        }

        // Check email verification
        if (!$user['email_verified_at'] && $user['user_type'] === 'PARENT') {
            return ['success' => false, 'message' => 'Please verify your email address first.', 'needs_verification' => true];
        }

        // Check 2FA
        if ($user['two_factor_enabled']) {
            $_SESSION['2fa_user_id'] = $user['id'];
            return ['success' => false, 'needs_2fa' => true, 'message' => 'Two-factor authentication required.'];
        }

        // Successful login
        $this->createSession($user);
        $this->userModel->resetLoginAttempts($user['id']);
        $this->activityLog->log('LOGIN', $user['id'], 'user', $user['id'], 'Successful login');

        return [
            'success' => true,
            'message' => 'Login successful.',
            'user' => $user,
            'redirect' => $this->getDashboardUrl($user['user_type']),
            'force_password_change' => (bool) $user['force_password_change'],
        ];
    }

    /**
     * Verify 2FA code and complete login.
     */
    public function verify2FA(string $code): array
    {
        $userId = $_SESSION['2fa_user_id'] ?? null;
        if (!$userId) {
            return ['success' => false, 'message' => 'Session expired. Please log in again.'];
        }

        $user = $this->userModel->find($userId);
        if (!$user || !$user['two_factor_secret']) {
            return ['success' => false, 'message' => 'Invalid 2FA configuration.'];
        }

        // Verify TOTP code (using simple time-based check)
        if (!$this->verifyTOTP($user['two_factor_secret'], $code)) {
            return ['success' => false, 'message' => 'Invalid authentication code.'];
        }

        unset($_SESSION['2fa_user_id']);
        $this->createSession($user);
        $this->userModel->resetLoginAttempts($user['id']);
        $this->activityLog->log('LOGIN_2FA', $user['id'], 'user', $user['id'], 'Login with 2FA');

        return [
            'success' => true,
            'message' => 'Login successful.',
            'redirect' => $this->getDashboardUrl($user['user_type']),
        ];
    }

    /**
     * Register a new parent user.
     */
    public function register(array $data): array
    {
        // Check if email already exists
        if ($this->userModel->findByEmail($data['email'])) {
            return ['success' => false, 'message' => 'Email address is already registered.'];
        }

        // Validate password strength
        $passwordCheck = $this->validatePasswordStrength($data['password']);
        if (!$passwordCheck['valid']) {
            return ['success' => false, 'message' => $passwordCheck['message']];
        }

        $token = bin2hex(random_bytes(32));

        $userId = $this->userModel->createUser([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
            'user_type' => 'PARENT',
            'status' => 'pending',
            'email_verification_token' => $token,
        ]);

        $this->activityLog->log('REGISTER', $userId, 'user', $userId, 'New parent registration');

        // Send verification email
        $notificationService = new NotificationService();
        $notificationService->sendEmailVerification($userId, $data['email'], $token);

        return [
            'success' => true,
            'message' => 'Registration successful. Please check your email to verify your account.',
            'user_id' => $userId,
        ];
    }

    /**
     * Create a doctor account (admin only).
     */
    public function createDoctorAccount(array $data): array
    {
        if ($this->userModel->findByEmail($data['email'])) {
            return ['success' => false, 'message' => 'Email address is already registered.'];
        }

        $tempPassword = $this->generateTemporaryPassword();

        $userId = $this->userModel->createUser([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => $tempPassword,
            'user_type' => $data['user_type'] ?? 'DOCTOR',
            'status' => 'active',
            'specialization' => $data['specialization'] ?? null,
            'license_number' => $data['license_number'] ?? null,
            'years_of_experience' => isset($data['years_of_experience']) ? (int) $data['years_of_experience'] : null,
            'email_verified_at' => date('Y-m-d H:i:s'),
            'force_password_change' => 1,
        ]);

        // Send credentials email
        $notificationService = new NotificationService();
        $notificationService->sendDoctorCredentials($userId, $data['email'], $tempPassword);

        $this->activityLog->log('CREATE_DOCTOR', null, 'user', $userId, 'Doctor account created');

        return [
            'success' => true,
            'message' => 'Doctor account created successfully. Credentials sent via email.',
            'user_id' => $userId,
        ];
    }

    /**
     * Request password reset.
     */
    public function requestPasswordReset(string $email): array
    {
        $user = $this->userModel->findByEmail($email);

        // Always return success to prevent email enumeration
        if (!$user) {
            return ['success' => true, 'message' => 'If an account exists with that email, a reset link has been sent.'];
        }

        $token = bin2hex(random_bytes(32));
        $config = require dirname(__DIR__, 2) . '/config/app.php';
        $this->userModel->setPasswordResetToken($user['id'], $token, $config['security']['password_reset_expiry']);

        $notificationService = new NotificationService();
        $notificationService->sendPasswordReset($user['id'], $email, $token);

        $this->activityLog->log('PASSWORD_RESET_REQUEST', $user['id'], 'user', $user['id'], 'Password reset requested');

        return ['success' => true, 'message' => 'If an account exists with that email, a reset link has been sent.'];
    }

    /**
     * Reset password with token.
     */
    public function resetPassword(string $token, string $newPassword): array
    {
        $user = $this->userModel->findByResetToken($token);
        if (!$user) {
            return ['success' => false, 'message' => 'Invalid or expired reset token.'];
        }

        $passwordCheck = $this->validatePasswordStrength($newPassword);
        if (!$passwordCheck['valid']) {
            return ['success' => false, 'message' => $passwordCheck['message']];
        }

        $this->userModel->updatePassword($user['id'], $newPassword);
        $this->activityLog->log('PASSWORD_RESET', $user['id'], 'user', $user['id'], 'Password reset completed');

        return ['success' => true, 'message' => 'Password has been reset successfully. You can now log in.'];
    }

    /**
     * Change password for currently logged-in user.
     */
    public function changePassword(int $userId, string $currentPassword, string $newPassword): array
    {
        if (!$this->userModel->verifyPassword($userId, $currentPassword)) {
            return ['success' => false, 'message' => 'Current password is incorrect.'];
        }

        $passwordCheck = $this->validatePasswordStrength($newPassword);
        if (!$passwordCheck['valid']) {
            return ['success' => false, 'message' => $passwordCheck['message']];
        }

        $this->userModel->updatePassword($userId, $newPassword);
        $this->activityLog->log('PASSWORD_CHANGE', $userId, 'user', $userId, 'Password changed');

        return ['success' => true, 'message' => 'Password changed successfully.'];
    }

    /**
     * Verify email address.
     */
    public function verifyEmailToken(string $token): array
    {
        if ($this->userModel->verifyEmail($token)) {
            return ['success' => true, 'message' => 'Email verified successfully. You can now log in.'];
        }
        return ['success' => false, 'message' => 'Invalid or expired verification token.'];
    }

    /**
     * Log out the current user.
     */
    public function logout(): void
    {
        $userId = $_SESSION['user_id'] ?? null;
        if ($userId) {
            $this->activityLog->log('LOGOUT', $userId, 'user', $userId, 'User logged out');
        }

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'], $params['secure'], $params['httponly']
            );
        }
        session_destroy();
    }

    /**
     * Create a user session.
     */
    private function createSession(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_type'] = $user['user_type'];
        $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['_last_regeneration'] = time();
    }

    /**
     * Get dashboard URL based on user type.
     */
    private function getDashboardUrl(string $userType): string
    {
        return match ($userType) {
            'ADMIN' => '/admin/dashboard',
            'DOCTOR', 'DOCTOR_OWNER' => '/doctor/dashboard',
            'PARENT' => '/parent/dashboard',
            default => '/dashboard',
        };
    }

    /**
     * Validate password strength.
     */
    private function validatePasswordStrength(string $password): array
    {
        if (strlen($password) < 8) {
            return ['valid' => false, 'message' => 'Password must be at least 8 characters long.'];
        }
        if (!preg_match('/[A-Z]/', $password)) {
            return ['valid' => false, 'message' => 'Password must contain at least one uppercase letter.'];
        }
        if (!preg_match('/[a-z]/', $password)) {
            return ['valid' => false, 'message' => 'Password must contain at least one lowercase letter.'];
        }
        if (!preg_match('/[0-9]/', $password)) {
            return ['valid' => false, 'message' => 'Password must contain at least one number.'];
        }
        if (!preg_match('/[!@#$%^&*()_+\-=\[\]{}|;:,.<>?]/', $password)) {
            return ['valid' => false, 'message' => 'Password must contain at least one special character.'];
        }
        return ['valid' => true, 'message' => ''];
    }

    /**
     * Generate a temporary password.
     */
    private function generateTemporaryPassword(): string
    {
        $upper = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lower = 'abcdefghijklmnopqrstuvwxyz';
        $digits = '0123456789';
        $special = '!@#$%^&*';

        $password = $upper[random_int(0, 25)]
            . $lower[random_int(0, 25)]
            . $digits[random_int(0, 9)]
            . $special[random_int(0, 7)];

        $all = $upper . $lower . $digits . $special;
        for ($i = 0; $i < 8; $i++) {
            $password .= $all[random_int(0, strlen($all) - 1)];
        }

        return str_shuffle($password);
    }

    /**
     * Simple TOTP verification.
     */
    private function verifyTOTP(string $secret, string $code): bool
    {
        $timeSlice = floor(time() / 30);

        // Check current and adjacent time windows
        for ($i = -1; $i <= 1; $i++) {
            $calculatedCode = $this->generateTOTPCode($secret, $timeSlice + $i);
            if (hash_equals($calculatedCode, $code)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Generate TOTP code for a given time slice.
     */
    private function generateTOTPCode(string $secret, int $timeSlice): string
    {
        $secretKey = base64_decode($secret);
        $time = pack('N*', 0, $timeSlice);
        $hash = hash_hmac('sha1', $time, $secretKey, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $code = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        ) % 1000000;

        return str_pad((string) $code, 6, '0', STR_PAD_LEFT);
    }
}
