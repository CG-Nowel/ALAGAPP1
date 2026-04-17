<?php
$layout = 'app';
$pageTitle = 'User Management - PediCare';
$breadcrumbs = [['label' => 'Admin', 'url' => '/admin/dashboard'], ['label' => 'Users']];
$sidebarNav = '
<a class="nav-link" href="/admin/dashboard"><i class="bi bi-speedometer2"></i> Dashboard</a>
<a class="nav-link active" href="/admin/users"><i class="bi bi-people"></i> Users</a>
<a class="nav-link" href="/admin/activity-logs"><i class="bi bi-journal-text"></i> Activity Logs</a>
<a class="nav-link" href="/admin/settings"><i class="bi bi-gear"></i> Settings</a>
';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h5 class="mb-0">User Management</h5>
    <div class="d-flex gap-2">
        <a href="/admin/export/users?<?= http_build_query($filters) ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-download me-1"></i>Export CSV</a>
        <button class="btn btn-primary btn-sm" onclick="showCreateDoctorModal()"><i class="bi bi-plus me-1"></i>Add Doctor</button>
    </div>
</div>

<!-- Filters -->
<div class="stat-card p-3 mb-3">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-4">
            <label class="form-label small">Search</label>
            <input type="text" name="search" class="form-control form-control-sm" placeholder="Name or email..." value="<?= htmlspecialchars($filters['search'] ?? '') ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label small">User Type</label>
            <select name="user_type" class="form-select form-select-sm">
                <option value="">All Types</option>
                <option value="PARENT" <?= ($filters['user_type'] ?? '') === 'PARENT' ? 'selected' : '' ?>>Parent</option>
                <option value="DOCTOR" <?= ($filters['user_type'] ?? '') === 'DOCTOR' ? 'selected' : '' ?>>Doctor</option>
                <option value="ADMIN" <?= ($filters['user_type'] ?? '') === 'ADMIN' ? 'selected' : '' ?>>Admin</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small">Status</label>
            <select name="status" class="form-select form-select-sm">
                <option value="">All Statuses</option>
                <option value="active" <?= ($filters['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= ($filters['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                <option value="suspended" <?= ($filters['status'] ?? '') === 'suspended' ? 'selected' : '' ?>>Suspended</option>
            </select>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-primary btn-sm w-100">Filter</button>
        </div>
    </form>
</div>

<!-- Bulk Actions -->
<div class="mb-2 d-flex gap-2" id="bulkActions" style="display:none!important;">
    <span class="text-muted small me-2"><span id="selectedCount">0</span> selected</span>
    <button class="btn btn-success btn-sm" onclick="bulkAction('activate')">Activate</button>
    <button class="btn btn-warning btn-sm" onclick="bulkAction('deactivate')">Deactivate</button>
    <button class="btn btn-danger btn-sm" onclick="bulkAction('suspend')">Suspend</button>
</div>

<!-- Users Table -->
<div class="stat-card p-0">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead style="background:#f8f9fa;">
                <tr>
                    <th><input type="checkbox" id="selectAll" onchange="toggleSelectAll()"></th>
                    <th>Name</th><th>Email</th><th>Type</th><th>Status</th><th>Joined</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($users)): ?>
                <tr><td colspan="7">
                    <div class="empty-state">
                        <i class="bi bi-people"></i>
                        <h5>No users found</h5>
                        <p>Try adjusting your filters.</p>
                    </div>
                </td></tr>
            <?php else: ?>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td><input type="checkbox" class="user-check" value="<?= $u['id'] ?>" onchange="updateBulkActions()"></td>
                    <td><strong><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></strong></td>
                    <td><?= htmlspecialchars($u['email']) ?></td>
                    <td><span class="badge" style="background:<?= match($u['user_type']) { 'ADMIN' => '#dc3545', 'DOCTOR' => '#007bff', default => '#6c757d' } ?>"><?= $u['user_type'] ?></span></td>
                    <td><span class="badge" style="background:<?= match($u['status']) { 'active' => '#28a745', 'inactive' => '#ffc107', default => '#dc3545' } ?>"><?= $u['status'] ?></span></td>
                    <td style="font-size:.85rem;"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
                    <td>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-light" data-bs-toggle="dropdown"><i class="bi bi-three-dots"></i></button>
                            <ul class="dropdown-menu">
                                <?php if ($u['status'] === 'active'): ?>
                                    <li><a class="dropdown-item" href="#" onclick="toggleStatus(<?= $u['id'] ?>, 'inactive')"><i class="bi bi-x-circle me-2"></i>Deactivate</a></li>
                                <?php else: ?>
                                    <li><a class="dropdown-item" href="#" onclick="toggleStatus(<?= $u['id'] ?>, 'active')"><i class="bi bi-check-circle me-2"></i>Activate</a></li>
                                <?php endif; ?>
                                <li><a class="dropdown-item text-danger" href="#" onclick="toggleStatus(<?= $u['id'] ?>, 'suspended')"><i class="bi bi-ban me-2"></i>Suspend</a></li>
                            </ul>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if (($pagination['total_pages'] ?? 0) > 1): ?>
    <div class="p-3 d-flex justify-content-between align-items-center border-top">
        <small class="text-muted">Showing <?= count($users) ?> of <?= $pagination['total'] ?> users</small>
        <nav>
            <ul class="pagination pagination-sm mb-0">
                <?php for ($i = 1; $i <= $pagination['total_pages']; $i++): ?>
                <li class="page-item <?= $i === $pagination['current_page'] ? 'active' : '' ?>">
                    <a class="page-link" href="?<?= http_build_query(array_merge($filters, ['page' => $i])) ?>"><?= $i ?></a>
                </li>
                <?php endfor; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<!-- Create Doctor Modal -->
<div class="modal fade" id="createDoctorModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Add Doctor</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <form id="createDoctorForm">
                    <div class="row mb-3">
                        <div class="col-6"><label class="form-label">First Name</label><input type="text" name="first_name" class="form-control" required></div>
                        <div class="col-6"><label class="form-label">Last Name</label><input type="text" name="last_name" class="form-control" required></div>
                    </div>
                    <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Phone</label><input type="tel" name="phone" class="form-control"></div>
                    <div class="mb-3"><label class="form-label">Specialization</label><input type="text" name="specialization" class="form-control" required></div>
                    <div class="row">
                        <div class="col-6"><label class="form-label">License Number</label><input type="text" name="license_number" class="form-control" required></div>
                        <div class="col-6"><label class="form-label">Experience (yrs)</label><input type="number" name="years_of_experience" class="form-control" min="0"></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer"><button class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" onclick="createDoctor()">Create</button></div>
        </div>
    </div>
</div>

<?php ob_start(); ?>
<script>
function showCreateDoctorModal() { new bootstrap.Modal(document.getElementById('createDoctorModal')).show(); }

async function createDoctor() {
    const result = await apiRequest('/admin/users/create-doctor', 'POST', new FormData(document.getElementById('createDoctorForm')));
    if (result.success) { showToast(result.message, 'success'); setTimeout(() => location.reload(), 1000); }
}

async function toggleStatus(userId, status) {
    showConfirm(`Change user status to "${status}"?`, async () => {
        const result = await apiRequest('/admin/users/toggle-status', 'POST', { user_id: userId, status });
        if (result.success) { showToast(result.message, 'success'); setTimeout(() => location.reload(), 800); }
    });
}

function toggleSelectAll() {
    const checked = document.getElementById('selectAll').checked;
    document.querySelectorAll('.user-check').forEach(cb => cb.checked = checked);
    updateBulkActions();
}

function updateBulkActions() {
    const checked = document.querySelectorAll('.user-check:checked');
    const bar = document.getElementById('bulkActions');
    document.getElementById('selectedCount').textContent = checked.length;
    bar.style.display = checked.length > 0 ? 'flex' : 'none';
    bar.style.cssText = checked.length > 0 ? '' : 'display:none!important';
}

async function bulkAction(action) {
    const ids = Array.from(document.querySelectorAll('.user-check:checked')).map(cb => cb.value);
    showConfirm(`${action} ${ids.length} user(s)?`, async () => {
        const formData = new FormData();
        formData.append('action', action);
        ids.forEach(id => formData.append('user_ids[]', id));
        const result = await apiRequest('/admin/users/bulk-action', 'POST', formData);
        if (result.success) { showToast(result.message, 'success'); setTimeout(() => location.reload(), 800); }
    });
}
</script>
<?php $extraScripts = ob_get_clean(); ?>
