<?php
$layout = 'app';
$pageTitle = 'Clinic Settings - PediCare';
$breadcrumbs = [['label' => 'Admin', 'url' => '/admin/dashboard'], ['label' => 'Settings']];
$sidebarNav = '
<a class="nav-link" href="/admin/dashboard"><i class="bi bi-speedometer2"></i> Dashboard</a>
<a class="nav-link" href="/admin/users"><i class="bi bi-people"></i> Users</a>
<a class="nav-link" href="/admin/activity-logs"><i class="bi bi-journal-text"></i> Activity Logs</a>
<a class="nav-link active" href="/admin/settings"><i class="bi bi-gear"></i> Settings</a>
';
use App\Middleware\CsrfMiddleware;
?>

<h5 class="mb-4">Clinic Settings</h5>

<form id="settingsForm">
    <div class="row g-3">
        <div class="col-md-6">
            <div class="stat-card p-3 mb-3">
                <h6 class="mb-3"><i class="bi bi-building me-1"></i>Clinic Information</h6>
                <div class="mb-3"><label class="form-label small">Clinic Name</label><input type="text" name="settings[clinic_name]" class="form-control" value="<?= htmlspecialchars($settings['clinic_name'] ?? '') ?>"></div>
                <div class="mb-3"><label class="form-label small">Phone</label><input type="tel" name="settings[clinic_phone]" class="form-control" value="<?= htmlspecialchars($settings['clinic_phone'] ?? '') ?>"></div>
                <div class="mb-3"><label class="form-label small">Email</label><input type="email" name="settings[clinic_email]" class="form-control" value="<?= htmlspecialchars($settings['clinic_email'] ?? '') ?>"></div>
                <div class="mb-3"><label class="form-label small">Address</label><textarea name="settings[clinic_address]" class="form-control" rows="2"><?= htmlspecialchars($settings['clinic_address'] ?? '') ?></textarea></div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="stat-card p-3 mb-3">
                <h6 class="mb-3"><i class="bi bi-calendar me-1"></i>Scheduling</h6>
                <div class="mb-3"><label class="form-label small">Default Slot Duration (min)</label><input type="number" name="settings[appointment_slot_duration]" class="form-control" value="<?= $settings['appointment_slot_duration'] ?? 30 ?>"></div>
                <div class="mb-3"><label class="form-label small">Max Advance Booking (days)</label><input type="number" name="settings[max_advance_booking_days]" class="form-control" value="<?= $settings['max_advance_booking_days'] ?? 60 ?>"></div>
                <div class="mb-3"><label class="form-label small">Cancellation Policy (hours before)</label><input type="number" name="settings[cancellation_hours]" class="form-control" value="<?= $settings['cancellation_hours'] ?? 24 ?>"></div>
            </div>
            <div class="stat-card p-3">
                <h6 class="mb-3"><i class="bi bi-envelope me-1"></i>Email (SMTP)</h6>
                <div class="mb-3"><label class="form-label small">SMTP Host</label><input type="text" name="settings[smtp_host]" class="form-control" value="<?= htmlspecialchars($settings['smtp_host'] ?? '') ?>"></div>
                <div class="row">
                    <div class="col-6 mb-3"><label class="form-label small">SMTP Port</label><input type="number" name="settings[smtp_port]" class="form-control" value="<?= $settings['smtp_port'] ?? 587 ?>"></div>
                    <div class="col-6 mb-3"><label class="form-label small">Username</label><input type="text" name="settings[smtp_username]" class="form-control" value="<?= htmlspecialchars($settings['smtp_username'] ?? '') ?>"></div>
                </div>
            </div>
        </div>
    </div>
    <div class="mt-3"><button type="button" class="btn btn-primary" onclick="saveSettings()"><i class="bi bi-check-lg me-1"></i>Save Settings</button></div>
</form>

<?php ob_start(); ?>
<script>
async function saveSettings() {
    const result = await apiRequest('/admin/settings', 'POST', new FormData(document.getElementById('settingsForm')));
    if (result.success) showToast('Settings saved!', 'success');
}
</script>
<?php $extraScripts = ob_get_clean(); ?>
