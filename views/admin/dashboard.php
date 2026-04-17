<?php
$layout = 'app';
$pageTitle = 'Admin Dashboard - PediCare';
$breadcrumbs = [['label' => 'Admin'], ['label' => 'Dashboard']];
$sidebarNav = '
<a class="nav-link active" href="/admin/dashboard"><i class="bi bi-speedometer2"></i> Dashboard</a>
<a class="nav-link" href="/admin/users"><i class="bi bi-people"></i> Users</a>
<a class="nav-link" href="/admin/activity-logs"><i class="bi bi-journal-text"></i> Activity Logs</a>
<a class="nav-link" href="/admin/settings"><i class="bi bi-gear"></i> Settings</a>
';
?>

<!-- Stats Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stat-card">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon" style="background:rgba(255,107,154,.1);color:#FF6B9A;">
                    <i class="bi bi-people-fill"></i>
                </div>
                <div>
                    <div style="font-size:.85rem;color:#888;">Total Users</div>
                    <div style="font-size:1.5rem;font-weight:700;"><?= $stats['total_users'] ?? 0 ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon" style="background:rgba(40,167,69,.1);color:#28a745;">
                    <i class="bi bi-person-hearts"></i>
                </div>
                <div>
                    <div style="font-size:.85rem;color:#888;">Total Patients</div>
                    <div style="font-size:1.5rem;font-weight:700;"><?= $stats['total_patients'] ?? 0 ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon" style="background:rgba(0,123,255,.1);color:#007bff;">
                    <i class="bi bi-calendar-check"></i>
                </div>
                <div>
                    <div style="font-size:.85rem;color:#888;">Today's Appointments</div>
                    <div style="font-size:1.5rem;font-weight:700;"><?= $stats['today'] ?? 0 ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon" style="background:rgba(255,193,7,.1);color:#ffc107;">
                    <i class="bi bi-shield-plus"></i>
                </div>
                <div>
                    <div style="font-size:.85rem;color:#888;">Vaccinations (Month)</div>
                    <div style="font-size:1.5rem;font-weight:700;"><?= $stats['this_month'] ?? 0 ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row g-3 mb-4">
    <div class="col-md-8">
        <div class="stat-card p-3">
            <h6 class="mb-3">Appointment Trends</h6>
            <canvas id="appointmentTrendChart" height="250"></canvas>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card p-3">
            <h6 class="mb-3">Appointments by Type</h6>
            <canvas id="appointmentTypeChart" height="250"></canvas>
        </div>
    </div>
</div>

<!-- Quick Actions & Recent Activity -->
<div class="row g-3">
    <div class="col-md-4">
        <div class="stat-card p-3">
            <h6 class="mb-3"><i class="bi bi-lightning me-1"></i>Quick Actions</h6>
            <div class="d-grid gap-2">
                <button class="btn btn-outline-primary btn-sm text-start" onclick="showCreateDoctorModal()">
                    <i class="bi bi-person-plus me-2"></i>Add Doctor
                </button>
                <a href="/admin/users" class="btn btn-outline-primary btn-sm text-start">
                    <i class="bi bi-people me-2"></i>Manage Users
                </a>
                <a href="/admin/activity-logs" class="btn btn-outline-primary btn-sm text-start">
                    <i class="bi bi-journal me-2"></i>View Logs
                </a>
                <a href="/admin/export/users" class="btn btn-outline-primary btn-sm text-start">
                    <i class="bi bi-download me-2"></i>Export Users CSV
                </a>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="stat-card p-3">
            <h6 class="mb-3"><i class="bi bi-clock-history me-1"></i>Recent Activity</h6>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead><tr><th>User</th><th>Action</th><th>Details</th><th>Time</th></tr></thead>
                    <tbody>
                    <?php if (empty($recentActivity)): ?>
                        <tr><td colspan="4" class="text-center text-muted py-4">No recent activity</td></tr>
                    <?php else: ?>
                        <?php foreach ($recentActivity as $log): ?>
                        <tr>
                            <td><?= htmlspecialchars(($log['first_name'] ?? '') . ' ' . ($log['last_name'] ?? '')) ?></td>
                            <td><span class="badge bg-light text-dark"><?= htmlspecialchars($log['action']) ?></span></td>
                            <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($log['details'] ?? '') ?>"><?= htmlspecialchars($log['details'] ?? '-') ?></td>
                            <td style="font-size:.8rem;color:#888;"><?= date('M j, g:i A', strtotime($log['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Create Doctor Modal -->
<div class="modal fade" id="createDoctorModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Add Doctor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="createDoctorForm">
                    <div class="row mb-3">
                        <div class="col-6"><label class="form-label">First Name</label><input type="text" name="first_name" class="form-control" required></div>
                        <div class="col-6"><label class="form-label">Last Name</label><input type="text" name="last_name" class="form-control" required></div>
                    </div>
                    <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Phone</label><input type="tel" name="phone" class="form-control"></div>
                    <div class="mb-3"><label class="form-label">Specialization</label><input type="text" name="specialization" class="form-control" required></div>
                    <div class="row mb-3">
                        <div class="col-6"><label class="form-label">License Number</label><input type="text" name="license_number" class="form-control" required></div>
                        <div class="col-6"><label class="form-label">Years of Experience</label><input type="number" name="years_of_experience" class="form-control" min="0"></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" onclick="createDoctor()">Create Account</button>
            </div>
        </div>
    </div>
</div>

<?php ob_start(); ?>
<script>
document.addEventListener('DOMContentLoaded', loadCharts);

async function loadCharts() {
    const result = await apiRequest('/admin/analytics');
    if (!result.success) return;
    const d = result.data;

    // Appointment Trends Line Chart
    createChart('appointmentTrendChart', {
        type: 'line',
        data: {
            labels: d.appointment_trends.map(r => r.month),
            datasets: [
                { label: 'Total', data: d.appointment_trends.map(r => r.total), borderColor: '#FF6B9A', tension: .4, fill: false },
                { label: 'Completed', data: d.appointment_trends.map(r => r.completed), borderColor: '#28a745', tension: .4, fill: false },
                { label: 'Cancelled', data: d.appointment_trends.map(r => r.cancelled), borderColor: '#dc3545', tension: .4, fill: false },
            ]
        },
        options: { responsive: true, plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero: true } } }
    });

    // Appointment Type Doughnut
    createChart('appointmentTypeChart', {
        type: 'doughnut',
        data: {
            labels: d.appointments_by_type.map(r => r.type),
            datasets: [{ data: d.appointments_by_type.map(r => r.total), backgroundColor: ['#FF6B9A','#28a745','#007bff','#ffc107','#6f42c1','#fd7e14'] }]
        },
        options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
    });
}

function showCreateDoctorModal() {
    new bootstrap.Modal(document.getElementById('createDoctorModal')).show();
}

async function createDoctor() {
    const form = document.getElementById('createDoctorForm');
    const result = await apiRequest('/admin/users/create-doctor', 'POST', new FormData(form));
    if (result.success) {
        showToast(result.message, 'success');
        bootstrap.Modal.getInstance(document.getElementById('createDoctorModal')).hide();
        form.reset();
    }
}
</script>
<?php $extraScripts = ob_get_clean(); ?>
