<?php
use App\Middleware\CsrfMiddleware;
$csrfToken = CsrfMiddleware::token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <title><?= htmlspecialchars($pageTitle ?? 'PediCare Clinic', ENT_QUOTES, 'UTF-8') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #FF6B9A;
            --primary-light: #FF8FB1;
            --primary-dark: #E0527F;
            --sidebar-width: 260px;
            --sidebar-collapsed: 70px;
        }
        * { font-family: 'Inter', sans-serif; }
        body { background: #f5f7fa; min-height: 100vh; }

        /* Sidebar */
        .sidebar {
            position: fixed; top: 0; left: 0; bottom: 0;
            width: var(--sidebar-width); background: linear-gradient(180deg, #1a1d29 0%, #2d3142 100%);
            color: #fff; z-index: 1000; transition: width .3s; overflow-x: hidden;
        }
        .sidebar .brand { padding: 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,.1); }
        .sidebar .brand h4 { color: var(--primary); margin: 0; font-weight: 700; }
        .sidebar .brand small { color: #aaa; font-size: .75rem; }
        .sidebar .nav-link {
            color: #ccc; padding: 12px 20px; display: flex; align-items: center; gap: 12px;
            transition: all .2s; border-left: 3px solid transparent; font-size: .9rem;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            color: #fff; background: rgba(255,107,154,.1); border-left-color: var(--primary);
        }
        .sidebar .nav-link i { width: 20px; text-align: center; font-size: 1.1rem; }

        /* Main content */
        .main-content { margin-left: var(--sidebar-width); min-height: 100vh; transition: margin-left .3s; }
        .top-bar {
            background: #fff; padding: 15px 30px; display: flex; justify-content: space-between;
            align-items: center; box-shadow: 0 1px 3px rgba(0,0,0,.08); position: sticky; top: 0; z-index: 100;
        }

        /* Breadcrumbs */
        .breadcrumb { margin: 0; background: transparent; padding: 0; font-size: .85rem; }
        .breadcrumb-item a { color: var(--primary); text-decoration: none; }

        /* Cards */
        .stat-card {
            background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,.06);
            transition: transform .2s; border: none;
        }
        .stat-card:hover { transform: translateY(-2px); }
        .stat-card .stat-icon {
            width: 48px; height: 48px; border-radius: 12px; display: flex;
            align-items: center; justify-content: center; font-size: 1.3rem;
        }

        /* Notification bell */
        .notification-bell { position: relative; cursor: pointer; }
        .notification-badge {
            position: absolute; top: -5px; right: -5px; background: var(--primary);
            color: white; border-radius: 50%; width: 18px; height: 18px;
            font-size: .65rem; display: flex; align-items: center; justify-content: center;
        }
        .notification-dropdown {
            position: absolute; right: 0; top: 100%; width: 360px; background: #fff;
            border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,.15);
            display: none; max-height: 400px; overflow-y: auto; z-index: 1001;
        }
        .notification-dropdown.show { display: block; }
        .notification-item {
            padding: 12px 16px; border-bottom: 1px solid #f0f0f0;
            display: flex; gap: 10px; cursor: pointer; transition: background .2s;
        }
        .notification-item:hover { background: #f9f9f9; }
        .notification-item.unread { background: #fff5f8; }

        /* Toast notifications */
        .toast-container { position: fixed; top: 20px; right: 20px; z-index: 9999; }
        .toast-custom {
            background: #fff; border-radius: 10px; padding: 14px 20px; margin-bottom: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,.12); display: flex; align-items: center; gap: 10px;
            animation: slideIn .3s ease-out; min-width: 300px;
        }
        .toast-custom.success { border-left: 4px solid #28a745; }
        .toast-custom.error { border-left: 4px solid #dc3545; }
        .toast-custom.info { border-left: 4px solid #17a2b8; }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

        /* Loading skeleton */
        .skeleton { background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%); background-size: 200% 100%; animation: shimmer 1.5s infinite; border-radius: 6px; }
        @keyframes shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .mobile-toggle { display: block !important; }
        }

        /* Pagination */
        .pagination { gap: 4px; }
        .page-link { border-radius: 8px; border: none; color: #555; padding: 8px 14px; }
        .page-item.active .page-link { background: var(--primary); border-color: var(--primary); }

        /* Modal styling */
        .modal-content { border: none; border-radius: 16px; }
        .modal-header { border-bottom: 1px solid #f0f0f0; }
        .modal-footer { border-top: 1px solid #f0f0f0; }

        .btn-primary { background: var(--primary); border-color: var(--primary); }
        .btn-primary:hover { background: var(--primary-dark); border-color: var(--primary-dark); }
        .btn-outline-primary { color: var(--primary); border-color: var(--primary); }
        .btn-outline-primary:hover { background: var(--primary); border-color: var(--primary); }

        /* Empty state */
        .empty-state { text-align: center; padding: 60px 20px; }
        .empty-state i { font-size: 4rem; color: #ddd; margin-bottom: 20px; }
        .empty-state h5 { color: #999; }
        .empty-state p { color: #bbb; }
    </style>
    <?php if (!empty($extraStyles)): ?>
    <?= $extraStyles ?>
    <?php endif; ?>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="brand">
            <h4><i class="bi bi-heart-pulse"></i> PediCare</h4>
            <small>Pediatric Clinic</small>
        </div>
        <div class="p-2">
            <?php if (!empty($sidebarNav)): ?>
                <?= $sidebarNav ?>
            <?php endif; ?>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-sm d-md-none mobile-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')" style="display:none;">
                    <i class="bi bi-list fs-4"></i>
                </button>
                <?php if (!empty($breadcrumbs)): ?>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <?php foreach ($breadcrumbs as $crumb): ?>
                            <?php if (!empty($crumb['url'])): ?>
                                <li class="breadcrumb-item"><a href="<?= htmlspecialchars($crumb['url']) ?>"><?= htmlspecialchars($crumb['label']) ?></a></li>
                            <?php else: ?>
                                <li class="breadcrumb-item active"><?= htmlspecialchars($crumb['label']) ?></li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ol>
                </nav>
                <?php endif; ?>
            </div>
            <div class="d-flex align-items-center gap-3">
                <!-- Notification Bell -->
                <div class="notification-bell" id="notifBell" onclick="toggleNotifications()">
                    <i class="bi bi-bell fs-5"></i>
                    <span class="notification-badge" id="notifCount" style="display:none;">0</span>
                    <div class="notification-dropdown" id="notifDropdown">
                        <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                            <strong>Notifications</strong>
                            <a href="#" onclick="markAllRead(event)" class="text-decoration-none" style="color:var(--primary);font-size:.85rem;">Mark all read</a>
                        </div>
                        <div id="notifList">
                            <div class="empty-state py-4">
                                <i class="bi bi-bell-slash"></i>
                                <p class="mb-0">No new notifications</p>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- User dropdown -->
                <div class="dropdown">
                    <button class="btn btn-sm dropdown-toggle d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown">
                        <div class="bg-light rounded-circle d-flex align-items-center justify-content-center" style="width:32px;height:32px;">
                            <i class="bi bi-person" style="color:var(--primary);"></i>
                        </div>
                        <span class="d-none d-md-inline"><?= htmlspecialchars($user['first_name'] ?? 'User') ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="/change-password"><i class="bi bi-key me-2"></i>Change Password</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="/logout"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Page Content -->
        <div class="p-4">
            <?= $content ?? '' ?>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
    // CSRF Token
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

    // Toast notification system (replaces alert/confirm)
    function showToast(message, type = 'info', duration = 4000) {
        const container = document.getElementById('toastContainer');
        const icons = { success: 'check-circle-fill', error: 'x-circle-fill', info: 'info-circle-fill' };
        const colors = { success: '#28a745', error: '#dc3545', info: '#17a2b8' };

        const toast = document.createElement('div');
        toast.className = `toast-custom ${type}`;
        toast.innerHTML = `
            <i class="bi bi-${icons[type] || 'info-circle-fill'}" style="color:${colors[type]};font-size:1.2rem;"></i>
            <span style="flex:1;">${message}</span>
            <button onclick="this.parentElement.remove()" style="border:none;background:none;color:#999;cursor:pointer;">
                <i class="bi bi-x"></i>
            </button>
        `;
        container.appendChild(toast);
        setTimeout(() => toast.remove(), duration);
    }

    // Confirmation modal (replaces confirm())
    function showConfirm(message, onConfirm) {
        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.innerHTML = `
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Confirm Action</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body"><p>${message}</p></div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="confirmBtn">Confirm</button>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
        const bsModal = new bootstrap.Modal(modal);
        modal.querySelector('#confirmBtn').onclick = () => { bsModal.hide(); onConfirm(); };
        modal.addEventListener('hidden.bs.modal', () => modal.remove());
        bsModal.show();
    }

    // AJAX helper
    async function apiRequest(url, method = 'GET', data = null) {
        const options = { method, headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken } };

        if (data && method !== 'GET') {
            if (data instanceof FormData) {
                options.body = data;
                data.append('csrf_token', csrfToken);
            } else {
                options.headers['Content-Type'] = 'application/x-www-form-urlencoded';
                const params = new URLSearchParams(data);
                params.append('csrf_token', csrfToken);
                options.body = params;
            }
        }

        try {
            const response = await fetch(url, options);
            const json = await response.json();

            if (!json.success) {
                showToast(json.message || 'An error occurred', 'error');
            }
            return json;
        } catch (err) {
            showToast('Network error. Please try again.', 'error');
            return { success: false, message: 'Network error' };
        }
    }

    // Notification bell
    async function loadNotifications() {
        const result = await apiRequest('/api/notifications/bell');
        if (result.success) {
            const badge = document.getElementById('notifCount');
            const count = result.data.unread_count;
            badge.textContent = count;
            badge.style.display = count > 0 ? 'flex' : 'none';

            const list = document.getElementById('notifList');
            if (result.data.notifications.length === 0) {
                list.innerHTML = '<div class="empty-state py-4"><i class="bi bi-bell-slash"></i><p class="mb-0">No new notifications</p></div>';
            } else {
                list.innerHTML = result.data.notifications.map(n => `
                    <div class="notification-item unread" onclick="markNotificationRead(${n.id})">
                        <i class="bi bi-${n.type === 'APPOINTMENT' ? 'calendar' : n.type === 'VACCINATION' ? 'shield-plus' : 'bell'}"
                           style="color:var(--primary);font-size:1.2rem;margin-top:2px;"></i>
                        <div>
                            <div style="font-weight:500;font-size:.9rem;">${n.title}</div>
                            <div style="font-size:.8rem;color:#888;">${n.message}</div>
                            <div style="font-size:.7rem;color:#aaa;">${timeAgo(n.created_at)}</div>
                        </div>
                    </div>
                `).join('');
            }
        }
    }

    function toggleNotifications() {
        document.getElementById('notifDropdown').classList.toggle('show');
    }

    async function markNotificationRead(id) {
        await apiRequest(`/api/notifications/${id}/read`, 'POST');
        loadNotifications();
    }

    async function markAllRead(e) {
        e.preventDefault();
        e.stopPropagation();
        await apiRequest('/api/notifications/read-all', 'POST');
        loadNotifications();
        showToast('All notifications marked as read', 'success');
    }

    function timeAgo(dateStr) {
        const diff = (Date.now() - new Date(dateStr)) / 1000;
        if (diff < 60) return 'Just now';
        if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
        return Math.floor(diff / 86400) + 'd ago';
    }

    // Close notification dropdown when clicking outside
    document.addEventListener('click', (e) => {
        if (!e.target.closest('#notifBell')) {
            document.getElementById('notifDropdown')?.classList.remove('show');
        }
    });

    // Chart.js cleanup utility
    const chartInstances = {};
    function createChart(canvasId, config) {
        if (chartInstances[canvasId]) {
            chartInstances[canvasId].destroy();
        }
        const ctx = document.getElementById(canvasId)?.getContext('2d');
        if (ctx) {
            chartInstances[canvasId] = new Chart(ctx, config);
        }
        return chartInstances[canvasId];
    }

    // Loading skeleton
    function showSkeleton(container, rows = 3) {
        let html = '';
        for (let i = 0; i < rows; i++) {
            html += `<div class="skeleton mb-3" style="height:20px;width:${80 + Math.random()*20}%;"></div>`;
        }
        container.innerHTML = html;
    }

    // Load notifications on page load
    document.addEventListener('DOMContentLoaded', () => {
        loadNotifications();
        setInterval(loadNotifications, 60000); // Refresh every minute
    });

    // Inline validation helper
    function addInlineValidation(form) {
        form.querySelectorAll('input[required], select[required]').forEach(el => {
            el.addEventListener('blur', () => {
                if (!el.value.trim()) {
                    el.classList.add('is-invalid');
                    if (!el.nextElementSibling?.classList.contains('invalid-feedback')) {
                        const fb = document.createElement('div');
                        fb.className = 'invalid-feedback';
                        fb.textContent = `${el.getAttribute('placeholder') || 'This field'} is required`;
                        el.after(fb);
                    }
                } else {
                    el.classList.remove('is-invalid');
                    el.classList.add('is-valid');
                }
            });
        });
    }
    </script>
    <?php if (!empty($extraScripts)): ?>
    <?= $extraScripts ?>
    <?php endif; ?>
</body>
</html>
