<?php
$layout = 'app';
$pageTitle = 'Activity Logs - PediCare';
$breadcrumbs = [['label' => 'Admin', 'url' => '/admin/dashboard'], ['label' => 'Activity Logs']];
$sidebarNav = '
<a class="nav-link" href="/admin/dashboard"><i class="bi bi-speedometer2"></i> Dashboard</a>
<a class="nav-link" href="/admin/users"><i class="bi bi-people"></i> Users</a>
<a class="nav-link active" href="/admin/activity-logs"><i class="bi bi-journal-text"></i> Activity Logs</a>
<a class="nav-link" href="/admin/settings"><i class="bi bi-gear"></i> Settings</a>
';
?>

<h5 class="mb-4">Activity Logs</h5>

<!-- Filters -->
<div class="stat-card p-3 mb-3">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label small">Search</label>
            <input type="text" name="search" class="form-control form-control-sm" placeholder="Search logs..." value="<?= htmlspecialchars($filters['search'] ?? '') ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label small">Action</label>
            <input type="text" name="action" class="form-control form-control-sm" placeholder="e.g. LOGIN" value="<?= htmlspecialchars($filters['action'] ?? '') ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label small">From</label>
            <input type="date" name="date_from" class="form-control form-control-sm" value="<?= htmlspecialchars($filters['date_from'] ?? '') ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label small">To</label>
            <input type="date" name="date_to" class="form-control form-control-sm" value="<?= htmlspecialchars($filters['date_to'] ?? '') ?>">
        </div>
        <div class="col-md-1"><button type="submit" class="btn btn-primary btn-sm w-100">Filter</button></div>
        <div class="col-md-2"><a href="/admin/activity-logs" class="btn btn-outline-secondary btn-sm w-100">Clear</a></div>
    </form>
</div>

<div class="stat-card p-0">
    <div class="table-responsive">
        <table class="table table-hover table-sm mb-0">
            <thead style="background:#f8f9fa;"><tr><th>Time</th><th>User</th><th>Action</th><th>Details</th><th>IP</th></tr></thead>
            <tbody>
            <?php if (empty($logs)): ?>
                <tr><td colspan="5" class="text-center py-4 text-muted">No logs found</td></tr>
            <?php else: ?>
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td style="font-size:.8rem;white-space:nowrap;"><?= date('M j, g:i A', strtotime($log['created_at'])) ?></td>
                    <td><?= htmlspecialchars(($log['first_name'] ?? '') . ' ' . ($log['last_name'] ?? 'System')) ?></td>
                    <td><span class="badge bg-light text-dark"><?= htmlspecialchars($log['action']) ?></span></td>
                    <td style="max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($log['details'] ?? '-') ?></td>
                    <td style="font-size:.8rem;color:#888;"><?= htmlspecialchars($log['ip_address'] ?? '-') ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if (($pagination['total_pages'] ?? 0) > 1): ?>
    <div class="p-3 d-flex justify-content-between align-items-center border-top">
        <small class="text-muted">Page <?= $pagination['current_page'] ?> of <?= $pagination['total_pages'] ?></small>
        <nav><ul class="pagination pagination-sm mb-0">
            <?php for ($i = max(1, $pagination['current_page']-2); $i <= min($pagination['total_pages'], $pagination['current_page']+2); $i++): ?>
            <li class="page-item <?= $i === $pagination['current_page'] ? 'active' : '' ?>">
                <a class="page-link" href="?<?= http_build_query(array_merge($filters, ['page' => $i])) ?>"><?= $i ?></a>
            </li>
            <?php endfor; ?>
        </ul></nav>
    </div>
    <?php endif; ?>
</div>
