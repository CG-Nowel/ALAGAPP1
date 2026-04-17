/* ============================================
   AlagApp Clinic - Admin Dashboard Scripts
   Handles section nav, modals, notifications
   ============================================ */

// ---- Mobile Sidebar Toggle ----
function toggleAdminSidebar() {
    var sidebar = document.getElementById('adminSidebar');
    var overlay = document.getElementById('adminSidebarOverlay');
    if (sidebar) sidebar.classList.toggle('open');
    if (overlay) overlay.classList.toggle('hidden');
}

// ---- Section Navigation ----
function showSection(sectionName) {
    document.querySelectorAll('.section-content').forEach(function(section) {
        section.classList.add('hidden');
    });

    // Close sidebar on mobile
    var sidebar = document.getElementById('adminSidebar');
    var overlay = document.getElementById('adminSidebarOverlay');
    if (sidebar && sidebar.classList.contains('open')) {
        sidebar.classList.remove('open');
        if (overlay) overlay.classList.add('hidden');
    }

    var targetSection = document.getElementById(sectionName + '-section');
    if (targetSection) {
        targetSection.classList.remove('hidden');
    }

    document.querySelectorAll('nav a').forEach(function(link) {
        link.classList.remove('bg-white/20');
    });

    // Use event.target if available (called from onclick), otherwise find by data attribute
    if (typeof event !== 'undefined' && event && event.target) {
        var activeLink = event.target.closest('a');
        if (activeLink) {
            activeLink.classList.add('bg-white/20');
        }
    }
}

// ---- Notification System ----
function showNotification(message, type) {
    type = type || 'success';
    var notification = document.getElementById('notification');
    if (!notification) return;

    notification.textContent = message;
    notification.className = 'notification ' + type + ' show';

    setTimeout(function() {
        notification.classList.remove('show');
    }, 3000);
}

// ---- Modal Functions ----
function openAddUserModal() {
    var el = document.getElementById('addUserModal');
    if (el) el.classList.remove('hidden');
}

function closeAddUserModal() {
    var el = document.getElementById('addUserModal');
    if (el) el.classList.add('hidden');
    var form = document.getElementById('addUserForm');
    if (form) form.reset();
}

function openAddScheduleModal() {
    var el = document.getElementById('addScheduleModal');
    if (el) el.classList.remove('hidden');
}

function closeAddScheduleModal() {
    var el = document.getElementById('addScheduleModal');
    if (el) el.classList.add('hidden');
    var form = document.getElementById('addScheduleForm');
    if (form) form.reset();
}

function openAddServiceModal() {
    var el = document.getElementById('addServiceModal');
    if (el) el.classList.remove('hidden');
}

function closeAddServiceModal() {
    var el = document.getElementById('addServiceModal');
    if (el) el.classList.add('hidden');
    var form = document.getElementById('addServiceForm');
    if (form) form.reset();
}

// ---- User Management ----
function toggleUserStatus(userId) {
    if (!confirm('Are you sure you want to change this user\'s status?')) {
        return;
    }

    var formData = new FormData();
    formData.append('user_id', userId);
    formData.append('action', 'toggle_user_status');
    if (window.CSRF_TOKEN) formData.append('csrf_token', window.CSRF_TOKEN);

    fetch('admin-actions-secure.php', {
        method: 'POST',
        body: formData
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
        if (data.success) {
            showNotification('User status updated successfully!');
            setTimeout(function() { location.reload(); }, 1000);
        } else {
            showNotification(data.message || 'Error updating user status', 'error');
        }
    })
    .catch(function(error) {
        console.error('Error:', error);
        showNotification('Error updating user status', 'error');
    });
}

function editUser(userId) {
    // Open the add user modal pre-filled for editing
    var row = document.querySelector('tr[data-user-id="' + userId + '"]');
    if (!row) {
        showNotification('User data not found. Please reload the page.', 'error');
        return;
    }
    showNotification('Edit user feature coming soon!', 'info');
}

function filterUsers() {
    var roleFilter = document.getElementById('userRoleFilter');
    var statusFilter = document.getElementById('userStatusFilter');
    var role = roleFilter ? roleFilter.value.toUpperCase() : '';
    var status = statusFilter ? statusFilter.value.toLowerCase() : '';

    var rows = document.querySelectorAll('#users-section tbody tr');
    rows.forEach(function(row) {
        var roleCell = row.querySelector('td:nth-child(4)');
        var statusCell = row.querySelector('td:nth-child(5)');
        var rowRole = roleCell ? roleCell.textContent.trim().toUpperCase() : '';
        var rowStatus = statusCell ? statusCell.textContent.trim().toLowerCase() : '';

        var roleMatch = !role || rowRole.indexOf(role) !== -1;
        var statusMatch = !status || rowStatus.indexOf(status) !== -1;

        row.style.display = (roleMatch && statusMatch) ? '' : 'none';
    });
}

// ---- Schedule Management ----
function editSchedule(scheduleId) {
    // Open schedule modal for editing (re-use add modal)
    openAddScheduleModal();
    showNotification('Modify the schedule details and save.', 'info');
}

function deleteSchedule(scheduleId) {
    if (!confirm('Are you sure you want to delete this schedule?')) {
        return;
    }

    var formData = new FormData();
    formData.append('schedule_id', scheduleId);
    formData.append('action', 'delete_schedule');
    if (window.CSRF_TOKEN) formData.append('csrf_token', window.CSRF_TOKEN);

    fetch('admin-actions-secure.php', {
        method: 'POST',
        body: formData
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
        if (data.success) {
            showNotification('Schedule deleted successfully!');
            setTimeout(function() { location.reload(); }, 1000);
        } else {
            showNotification(data.message || 'Error deleting schedule', 'error');
        }
    })
    .catch(function(error) {
        console.error('Error:', error);
        showNotification('Error deleting schedule', 'error');
    });
}

// ---- Service Management ----
function editService(serviceId) {
    // Open service modal for editing (re-use add modal)
    openAddServiceModal();
    showNotification('Modify the service details and save.', 'info');
}

function toggleServiceStatus(serviceId) {
    if (!confirm('Are you sure you want to change this service\'s status?')) {
        return;
    }

    var formData = new FormData();
    formData.append('service_id', serviceId);
    formData.append('action', 'toggle_service_status');
    if (window.CSRF_TOKEN) formData.append('csrf_token', window.CSRF_TOKEN);

    fetch('admin-actions-secure.php', {
        method: 'POST',
        body: formData
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
        if (data.success) {
            showNotification('Service status updated successfully!');
            setTimeout(function() { location.reload(); }, 1000);
        } else {
            showNotification(data.message || 'Error updating service status', 'error');
        }
    })
    .catch(function(error) {
        console.error('Error:', error);
        showNotification('Error updating service status', 'error');
    });
}

// ---- Appointment Management ----
function editAppointment(appointmentId) {
    showNotification('Edit appointment feature coming soon!', 'info');
}

function updateAppointmentStatus(appointmentId) {
    showNotification('Update appointment status feature coming soon!', 'info');
}

function filterAppointments() {
    showNotification('Filter functionality coming soon!', 'info');
}

function filterLogs() {
    var dateFilter = document.getElementById('logDateFilter');
    var actionFilter = document.getElementById('logActionFilter');
    var filterDate = dateFilter ? dateFilter.value : '';
    var filterAction = actionFilter ? actionFilter.value.toUpperCase() : '';

    var rows = document.querySelectorAll('#logs-section tbody tr');
    rows.forEach(function(row) {
        var timestampCell = row.querySelector('td:nth-child(1)');
        var actionCell = row.querySelector('td:nth-child(3)');
        var rowTimestamp = timestampCell ? timestampCell.textContent.trim() : '';
        var rowAction = actionCell ? actionCell.textContent.trim().toUpperCase() : '';

        var dateMatch = true;
        if (filterDate) {
            var rowDate = new Date(rowTimestamp);
            var filterDateObj = new Date(filterDate + 'T00:00:00');
            dateMatch = rowDate.getFullYear() === filterDateObj.getFullYear() &&
                        rowDate.getMonth() === filterDateObj.getMonth() &&
                        rowDate.getDate() === filterDateObj.getDate();
        }

        var actionMatch = !filterAction || rowAction.indexOf(filterAction) !== -1;

        row.style.display = (dateMatch && actionMatch) ? '' : 'none';
    });
}

function filterSchedulesByDoctor() {
    var filter = document.getElementById('scheduleDoctorFilter');
    var doctorId = filter ? filter.value : '';

    document.querySelectorAll('.schedule-entry').forEach(function(entry) {
        if (!doctorId || entry.getAttribute('data-doctor-id') === doctorId) {
            entry.style.display = '';
        } else {
            entry.style.display = 'none';
        }
    });
}