<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Services\AuthService;
use App\Helpers\Response;

class AuthController extends Controller
{
    private AuthService $authService;

    public function __construct()
    {
        parent::__construct();
        $this->authService = new AuthService();
    }

    /**
     * Show login page.
     */
    public function showLogin(): void
    {
        if ($this->userId()) {
            $this->redirect($this->getDashboardUrl());
            return;
        }
        $this->view('auth/login');
    }

    /**
     * Handle login form submission.
     */
    public function login(): void
    {
        $email = $this->input('email');
        $password = $_POST['password'] ?? '';

        if (!$email || !$password) {
            if ($this->isAjax()) {
                Response::error('Email and password are required.');
                return;
            }
            $_SESSION['flash_error'] = 'Email and password are required.';
            $this->redirect('/login');
            return;
        }

        $result = $this->authService->login($email, $password);

        if ($this->isAjax()) {
            if ($result['success']) {
                $redirect = !empty($result['force_password_change']) ? '/change-password' : $result['redirect'];
                Response::success(['redirect' => $redirect], $result['message']);
            } elseif (!empty($result['needs_2fa'])) {
                Response::success(['needs_2fa' => true], $result['message']);
            } else {
                Response::error($result['message'], 401);
            }
            return;
        }

        if ($result['success']) {
            $this->redirect(!empty($result['force_password_change']) ? '/change-password' : $result['redirect']);
        } else {
            $_SESSION['flash_error'] = $result['message'];
            $this->redirect('/login');
        }
    }

    /**
     * Handle 2FA verification.
     */
    public function verify2FA(): void
    {
        $code = $this->input('code', '');
        $result = $this->authService->verify2FA($code);

        if ($this->isAjax()) {
            $result['success'] ? Response::success(['redirect' => $result['redirect']], $result['message']) : Response::error($result['message']);
            return;
        }

        $this->redirect($result['success'] ? $result['redirect'] : '/login');
    }

    /**
     * Show registration page.
     */
    public function showRegister(): void
    {
        if ($this->userId()) {
            $this->redirect($this->getDashboardUrl());
            return;
        }
        $this->view('auth/register');
    }

    /**
     * Handle registration.
     */
    public function register(): void
    {
        $validation = $this->validate([
            'first_name' => 'required|max:50',
            'last_name' => 'required|max:50',
            'email' => 'required|email',
            'phone' => 'max:20',
            'password' => 'required|min:8',
        ]);

        if (!empty($validation['errors'])) {
            if ($this->isAjax()) {
                Response::validationError($validation['errors']);
                return;
            }
            $this->redirect('/register');
            return;
        }

        $data = $validation['data'];
        $data['password'] = $_POST['password']; // Use raw password for hashing
        $result = $this->authService->register($data);

        if ($this->isAjax()) {
            $result['success'] ? Response::success(null, $result['message']) : Response::error($result['message']);
            return;
        }

        $_SESSION[$result['success'] ? 'flash_success' : 'flash_error'] = $result['message'];
        $this->redirect($result['success'] ? '/login' : '/register');
    }

    /**
     * Verify email.
     */
    public function verifyEmail(): void
    {
        $token = $_GET['token'] ?? '';
        $result = $this->authService->verifyEmailToken($token);

        $_SESSION[$result['success'] ? 'flash_success' : 'flash_error'] = $result['message'];
        $this->redirect('/login');
    }

    /**
     * Show forgot password page.
     */
    public function showForgotPassword(): void
    {
        $this->view('auth/forgot-password');
    }

    /**
     * Handle forgot password request.
     */
    public function forgotPassword(): void
    {
        $email = $this->input('email');
        $result = $this->authService->requestPasswordReset($email);

        if ($this->isAjax()) {
            Response::success(null, $result['message']);
            return;
        }

        $_SESSION['flash_success'] = $result['message'];
        $this->redirect('/forgot-password');
    }

    /**
     * Show reset password form.
     */
    public function showResetPassword(): void
    {
        $token = $_GET['token'] ?? '';
        $this->view('auth/reset-password', ['token' => $token]);
    }

    /**
     * Handle password reset.
     */
    public function resetPassword(): void
    {
        $token = $this->input('token');
        $password = $_POST['password'] ?? '';

        $result = $this->authService->resetPassword($token, $password);

        if ($this->isAjax()) {
            $result['success'] ? Response::success(null, $result['message']) : Response::error($result['message']);
            return;
        }

        $_SESSION[$result['success'] ? 'flash_success' : 'flash_error'] = $result['message'];
        $this->redirect($result['success'] ? '/login' : '/reset-password?token=' . $token);
    }

    /**
     * Show change password form.
     */
    public function showChangePassword(): void
    {
        $this->view('auth/change-password');
    }

    /**
     * Handle password change.
     */
    public function changePassword(): void
    {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';

        $result = $this->authService->changePassword($this->userId(), $currentPassword, $newPassword);

        if ($this->isAjax()) {
            $result['success'] ? Response::success(null, $result['message']) : Response::error($result['message']);
            return;
        }

        $_SESSION[$result['success'] ? 'flash_success' : 'flash_error'] = $result['message'];
        $this->redirect($result['success'] ? $this->getDashboardUrl() : '/change-password');
    }

    /**
     * Handle logout.
     */
    public function logout(): void
    {
        $this->authService->logout();
        $this->redirect('/login');
    }

    /**
     * Get dashboard URL for current user.
     */
    private function getDashboardUrl(): string
    {
        return match ($this->userType()) {
            'ADMIN' => '/admin/dashboard',
            'DOCTOR', 'DOCTOR_OWNER' => '/doctor/dashboard',
            'PARENT' => '/parent/dashboard',
            default => '/login',
        };
    }
}
