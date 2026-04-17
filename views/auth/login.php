<?php use App\Middleware\CsrfMiddleware; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - PediCare Clinic</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        body { background: linear-gradient(135deg, #FFE5ED 0%, #FFF0F5 50%, #F8F9FA 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .login-card { background: white; border-radius: 20px; box-shadow: 0 20px 60px rgba(255,107,154,.15); max-width: 440px; width: 100%; padding: 40px; }
        .login-card .brand { text-align: center; margin-bottom: 30px; }
        .login-card .brand h2 { color: #FF6B9A; font-weight: 700; }
        .login-card .brand p { color: #888; font-size: .9rem; }
        .form-control { border-radius: 10px; padding: 12px 16px; border: 1px solid #e0e0e0; }
        .form-control:focus { border-color: #FF6B9A; box-shadow: 0 0 0 3px rgba(255,107,154,.1); }
        .btn-primary { background: #FF6B9A; border: none; border-radius: 10px; padding: 12px; font-weight: 600; }
        .btn-primary:hover { background: #E0527F; }
        .form-label { font-weight: 500; color: #555; font-size: .9rem; }
        .alert { border-radius: 10px; }
        .input-group-text { border-radius: 10px 0 0 10px; background: #f8f9fa; border-right: none; }
        .input-group .form-control { border-left: none; }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="brand">
            <h2><i class="bi bi-heart-pulse"></i> PediCare</h2>
            <p>Pediatric Clinic Management System</p>
        </div>

        <?php if (!empty($_SESSION['flash_error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($_SESSION['flash_error']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['flash_error']); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION['flash_success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($_SESSION['flash_success']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['flash_success']); ?>
        <?php endif; ?>

        <form id="loginForm" method="POST" action="/login" novalidate>
            <?= CsrfMiddleware::field() ?>

            <div class="mb-3">
                <label class="form-label">Email Address</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                    <input type="email" name="email" class="form-control" placeholder="Enter your email" required autofocus>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input type="password" name="password" class="form-control" placeholder="Enter your password" required>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="remember">
                    <label class="form-check-label" for="remember" style="font-size:.85rem;">Remember me</label>
                </div>
                <a href="/forgot-password" style="color:#FF6B9A;font-size:.85rem;text-decoration:none;">Forgot password?</a>
            </div>

            <button type="submit" class="btn btn-primary w-100 mb-3" id="loginBtn">
                <span class="spinner-border spinner-border-sm d-none me-2" id="loginSpinner"></span>
                Sign In
            </button>

            <p class="text-center mb-0" style="font-size:.9rem;color:#888;">
                Don't have an account? <a href="/register" style="color:#FF6B9A;text-decoration:none;font-weight:500;">Register</a>
            </p>
        </form>

        <!-- 2FA Modal -->
        <div class="modal fade" id="tfaModal" data-bs-backdrop="static">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content" style="border-radius:16px;">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-shield-lock me-2"></i>Two-Factor Authentication</h5>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted">Enter the 6-digit code from your authenticator app.</p>
                        <input type="text" id="tfaCode" class="form-control text-center fs-4" maxlength="6" pattern="[0-9]{6}" placeholder="000000" autocomplete="one-time-code">
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-primary w-100" onclick="verify2FA()">Verify</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.getElementById('loginForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = document.getElementById('loginBtn');
        const spinner = document.getElementById('loginSpinner');
        btn.disabled = true;
        spinner.classList.remove('d-none');

        const formData = new FormData(e.target);
        try {
            const response = await fetch('/login', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            });
            const data = await response.json();

            if (data.success) {
                window.location.href = data.data.redirect;
            } else if (data.data?.needs_2fa) {
                new bootstrap.Modal(document.getElementById('tfaModal')).show();
            } else {
                showError(data.message);
            }
        } catch (err) {
            showError('Network error. Please try again.');
        } finally {
            btn.disabled = false;
            spinner.classList.add('d-none');
        }
    });

    async function verify2FA() {
        const code = document.getElementById('tfaCode').value;
        const formData = new URLSearchParams({ code, csrf_token: document.querySelector('[name=csrf_token]').value });
        const response = await fetch('/verify-2fa', {
            method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData
        });
        const data = await response.json();
        if (data.success) {
            window.location.href = data.data.redirect;
        } else {
            showError(data.message);
        }
    }

    function showError(msg) {
        const existing = document.querySelector('.alert-danger');
        if (existing) existing.remove();
        const alert = document.createElement('div');
        alert.className = 'alert alert-danger alert-dismissible fade show';
        alert.innerHTML = `${msg}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
        document.getElementById('loginForm').prepend(alert);
    }
    </script>
</body>
</html>
