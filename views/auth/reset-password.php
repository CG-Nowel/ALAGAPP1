<?php use App\Middleware\CsrfMiddleware; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - PediCare Clinic</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        body { background: linear-gradient(135deg, #FFE5ED 0%, #FFF0F5 50%, #F8F9FA 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .card-custom { background: white; border-radius: 20px; box-shadow: 0 20px 60px rgba(255,107,154,.15); max-width: 440px; width: 100%; padding: 40px; }
        .form-control { border-radius: 10px; padding: 12px 16px; }
        .form-control:focus { border-color: #FF6B9A; box-shadow: 0 0 0 3px rgba(255,107,154,.1); }
        .btn-primary { background: #FF6B9A; border: none; border-radius: 10px; padding: 12px; font-weight: 600; }
        .btn-primary:hover { background: #E0527F; }
    </style>
</head>
<body>
    <div class="card-custom">
        <div class="text-center mb-4">
            <h2 style="color:#FF6B9A;font-weight:700;"><i class="bi bi-heart-pulse"></i> PediCare</h2>
            <h5>Reset Your Password</h5>
        </div>
        <form method="POST" action="/reset-password">
            <?= CsrfMiddleware::field() ?>
            <input type="hidden" name="token" value="<?= htmlspecialchars($token ?? '', ENT_QUOTES, 'UTF-8') ?>">
            <div class="mb-3">
                <label class="form-label">New Password</label>
                <input type="password" name="password" class="form-control" placeholder="Min 8 characters" required minlength="8">
            </div>
            <div class="mb-4">
                <label class="form-label">Confirm Password</label>
                <input type="password" name="password_confirm" class="form-control" placeholder="Re-enter password" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Reset Password</button>
        </form>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
