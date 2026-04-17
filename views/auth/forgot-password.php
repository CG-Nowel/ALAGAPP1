<?php use App\Middleware\CsrfMiddleware; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - PediCare Clinic</title>
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
            <h5>Forgot Password?</h5>
            <p class="text-muted">Enter your email and we'll send you a reset link.</p>
        </div>

        <?php if (!empty($_SESSION['flash_success'])): ?>
            <div class="alert alert-success"><?= htmlspecialchars($_SESSION['flash_success']) ?></div>
            <?php unset($_SESSION['flash_success']); ?>
        <?php endif; ?>

        <form method="POST" action="/forgot-password">
            <?= CsrfMiddleware::field() ?>
            <div class="mb-3">
                <label class="form-label">Email Address</label>
                <input type="email" name="email" class="form-control" placeholder="your@email.com" required autofocus>
            </div>
            <button type="submit" class="btn btn-primary w-100 mb-3">Send Reset Link</button>
            <p class="text-center"><a href="/login" style="color:#FF6B9A;text-decoration:none;">Back to Login</a></p>
        </form>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
