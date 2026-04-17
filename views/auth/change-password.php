<?php
use App\Middleware\CsrfMiddleware;
$layout = 'app';
$pageTitle = 'Change Password';
$breadcrumbs = [['label' => 'Dashboard', 'url' => '/dashboard'], ['label' => 'Change Password']];
$sidebarNav = '';
?>
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card border-0" style="border-radius:16px;box-shadow:0 2px 8px rgba(0,0,0,.06);">
            <div class="card-body p-4">
                <h5 class="mb-4"><i class="bi bi-key me-2"></i>Change Password</h5>
                <form id="changePasswordForm" method="POST" action="/change-password">
                    <?= CsrfMiddleware::field() ?>
                    <div class="mb-3">
                        <label class="form-label">Current Password</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <input type="password" name="new_password" class="form-control" required minlength="8">
                        <small class="text-muted">Min 8 chars with uppercase, lowercase, number, and special character.</small>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Update Password</button>
                </form>
            </div>
        </div>
    </div>
</div>
