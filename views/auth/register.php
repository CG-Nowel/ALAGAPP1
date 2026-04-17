<?php use App\Middleware\CsrfMiddleware; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - PediCare Clinic</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        body { background: linear-gradient(135deg, #FFE5ED 0%, #FFF0F5 50%, #F8F9FA 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .register-card { background: white; border-radius: 20px; box-shadow: 0 20px 60px rgba(255,107,154,.15); max-width: 500px; width: 100%; padding: 40px; }
        .register-card .brand { text-align: center; margin-bottom: 30px; }
        .register-card .brand h2 { color: #FF6B9A; font-weight: 700; }
        .form-control { border-radius: 10px; padding: 12px 16px; border: 1px solid #e0e0e0; }
        .form-control:focus { border-color: #FF6B9A; box-shadow: 0 0 0 3px rgba(255,107,154,.1); }
        .btn-primary { background: #FF6B9A; border: none; border-radius: 10px; padding: 12px; font-weight: 600; }
        .btn-primary:hover { background: #E0527F; }
        .password-strength { height: 4px; border-radius: 2px; transition: all .3s; margin-top: 6px; }
        .invalid-feedback { font-size: .8rem; }
    </style>
</head>
<body>
    <div class="register-card">
        <div class="brand">
            <h2><i class="bi bi-heart-pulse"></i> PediCare</h2>
            <p class="text-muted">Create your parent account</p>
        </div>

        <form id="registerForm" method="POST" action="/register" novalidate>
            <?= CsrfMiddleware::field() ?>

            <div class="row mb-3">
                <div class="col-6">
                    <label class="form-label">First Name</label>
                    <input type="text" name="first_name" class="form-control" placeholder="First name" required>
                </div>
                <div class="col-6">
                    <label class="form-label">Last Name</label>
                    <input type="text" name="last_name" class="form-control" placeholder="Last name" required>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Email Address</label>
                <input type="email" name="email" class="form-control" placeholder="your@email.com" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Phone Number</label>
                <input type="tel" name="phone" class="form-control" placeholder="09XX XXX XXXX">
            </div>

            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" id="password" class="form-control" placeholder="Min 8 chars, uppercase, number, special" required minlength="8">
                <div class="password-strength" id="pwStrength"></div>
                <small class="text-muted">Must include uppercase, lowercase, number, and special character.</small>
            </div>

            <div class="mb-4">
                <label class="form-label">Confirm Password</label>
                <input type="password" name="password_confirm" id="passwordConfirm" class="form-control" placeholder="Re-enter password" required>
                <div class="invalid-feedback">Passwords do not match.</div>
            </div>

            <button type="submit" class="btn btn-primary w-100 mb-3" id="registerBtn">
                <span class="spinner-border spinner-border-sm d-none me-2" id="regSpinner"></span>
                Create Account
            </button>

            <p class="text-center mb-0" style="font-size:.9rem;color:#888;">
                Already have an account? <a href="/login" style="color:#FF6B9A;text-decoration:none;font-weight:500;">Sign In</a>
            </p>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Password strength indicator
    document.getElementById('password').addEventListener('input', function() {
        const bar = document.getElementById('pwStrength');
        const val = this.value;
        let score = 0;
        if (val.length >= 8) score++;
        if (/[A-Z]/.test(val)) score++;
        if (/[0-9]/.test(val)) score++;
        if (/[!@#$%^&*()_+\-=\[\]{}|;:,.<>?]/.test(val)) score++;
        const colors = ['#dc3545', '#fd7e14', '#ffc107', '#28a745'];
        const widths = ['25%', '50%', '75%', '100%'];
        bar.style.width = widths[score - 1] || '0';
        bar.style.background = colors[score - 1] || '#ddd';
    });

    // Confirm password match
    document.getElementById('passwordConfirm').addEventListener('input', function() {
        if (this.value !== document.getElementById('password').value) {
            this.classList.add('is-invalid');
        } else {
            this.classList.remove('is-invalid');
            this.classList.add('is-valid');
        }
    });

    // Form submission
    document.getElementById('registerForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const pw = document.getElementById('password').value;
        const pwc = document.getElementById('passwordConfirm').value;
        if (pw !== pwc) { document.getElementById('passwordConfirm').classList.add('is-invalid'); return; }

        const btn = document.getElementById('registerBtn');
        const spinner = document.getElementById('regSpinner');
        btn.disabled = true; spinner.classList.remove('d-none');

        const formData = new FormData(e.target);
        try {
            const response = await fetch('/register', {
                method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: formData
            });
            const data = await response.json();
            if (data.success) {
                window.location.href = '/login?registered=1';
            } else {
                const alert = document.createElement('div');
                alert.className = 'alert alert-danger alert-dismissible fade show';
                alert.innerHTML = `${data.message || 'Registration failed.'}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
                e.target.prepend(alert);
            }
        } catch (err) {
            (window.showToast || window.alert)('Network error', 'error');
        } finally {
            btn.disabled = false; spinner.classList.add('d-none');
        }
    });
    </script>
</body>
</html>
