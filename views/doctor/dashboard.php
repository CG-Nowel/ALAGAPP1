<?php
$layout = 'app';
$pageTitle = 'Doctor Dashboard - PediCare';
$breadcrumbs = [['label' => 'Doctor'], ['label' => 'Dashboard']];
$sidebarNav = '
<a class="nav-link active" href="/doctor/dashboard"><i class="bi bi-speedometer2"></i> Dashboard</a>
<a class="nav-link" href="#" onclick="loadSection(\'appointments\')"><i class="bi bi-calendar"></i> Appointments</a>
<a class="nav-link" href="#" onclick="loadSection(\'patients\')"><i class="bi bi-people"></i> My Patients</a>
<a class="nav-link" href="#" onclick="loadSection(\'availability\')"><i class="bi bi-clock"></i> Availability</a>
';
?>

<!-- Stats Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stat-card">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon" style="background:rgba(255,107,154,.1);color:#FF6B9A;"><i class="bi bi-calendar-day"></i></div>
                <div>
                    <div style="font-size:.85rem;color:#888;">Today</div>
                    <div style="font-size:1.5rem;font-weight:700;"><?= $stats['today'] ?? 0 ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon" style="background:rgba(0,123,255,.1);color:#007bff;"><i class="bi bi-calendar-week"></i></div>
                <div>
                    <div style="font-size:.85rem;color:#888;">This Week</div>
                    <div style="font-size:1.5rem;font-weight:700;"><?= $stats['this_week'] ?? 0 ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon" style="background:rgba(40,167,69,.1);color:#28a745;"><i class="bi bi-calendar-range"></i></div>
                <div>
                    <div style="font-size:.85rem;color:#888;">This Month</div>
                    <div style="font-size:1.5rem;font-weight:700;"><?= $stats['this_month'] ?? 0 ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon" style="background:rgba(111,66,193,.1);color:#6f42c1;"><i class="bi bi-check-circle"></i></div>
                <div>
                    <div style="font-size:.85rem;color:#888;">Completed</div>
                    <div style="font-size:1.5rem;font-weight:700;"><?= $stats['completed'] ?? 0 ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Today's Schedule -->
<div class="stat-card p-3 mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="mb-0"><i class="bi bi-calendar-day me-1"></i>Today's Schedule (<?= date('F j, Y') ?>)</h6>
        <div class="d-flex gap-2">
            <input type="date" id="filterDate" class="form-control form-control-sm" style="width:160px;" value="<?= date('Y-m-d') ?>" onchange="loadDayAppointments()">
        </div>
    </div>
    <div id="appointmentsList">
        <?php if (empty($todayAppointments)): ?>
            <div class="empty-state py-4">
                <i class="bi bi-calendar-x"></i>
                <h5>No appointments today</h5>
                <p>Enjoy your free time!</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead><tr><th>Time</th><th>Patient</th><th>Age</th><th>Type</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($todayAppointments as $apt): ?>
                    <tr>
                        <td><strong><?= date('g:i A', strtotime($apt['appointment_time'])) ?></strong></td>
                        <td><?= htmlspecialchars($apt['patient_first_name'] . ' ' . $apt['patient_last_name']) ?></td>
                        <td><?= !empty($apt['patient_dob']) ? (int)((time() - strtotime($apt['patient_dob'])) / 31557600) . 'y' : '-' ?></td>
                        <td><span class="badge bg-light text-dark"><?= $apt['type'] ?></span></td>
                        <td>
                            <span class="badge" style="background:<?= match($apt['status']) { 'SCHEDULED' => '#ffc107', 'CONFIRMED' => '#17a2b8', 'IN_PROGRESS' => '#007bff', 'COMPLETED' => '#28a745', 'CANCELLED' => '#dc3545', default => '#6c757d' } ?>"><?= $apt['status'] ?></span>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-primary" onclick="showAppointmentDetails(<?= $apt['id'] ?>)" title="Details"><i class="bi bi-eye"></i></button>
                                <?php if ($apt['status'] === 'SCHEDULED'): ?>
                                    <button class="btn btn-outline-success" onclick="updateStatus(<?= $apt['id'] ?>, 'CONFIRMED')" title="Confirm"><i class="bi bi-check"></i></button>
                                <?php elseif ($apt['status'] === 'CONFIRMED'): ?>
                                    <button class="btn btn-outline-primary" onclick="updateStatus(<?= $apt['id'] ?>, 'IN_PROGRESS')" title="Start"><i class="bi bi-play"></i></button>
                                <?php elseif ($apt['status'] === 'IN_PROGRESS'): ?>
                                    <button class="btn btn-outline-success" onclick="updateStatus(<?= $apt['id'] ?>, 'COMPLETED')" title="Complete"><i class="bi bi-check-lg"></i></button>
                                <?php endif; ?>
                                <button class="btn btn-outline-info" onclick="openRecords(<?= $apt['patient_id'] ?>)" title="Records"><i class="bi bi-file-medical"></i></button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Appointment Details Modal -->
<div class="modal fade" id="appointmentModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Appointment Details</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body" id="appointmentModalBody"><div class="skeleton" style="height:200px;"></div></div>
        </div>
    </div>
</div>

<!-- Patient Records Modal -->
<div class="modal fade" id="recordsModal">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Patient Medical Records</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body" id="recordsModalBody"><div class="skeleton" style="height:300px;"></div></div>
        </div>
    </div>
</div>

<?php ob_start(); ?>
<script>
async function showAppointmentDetails(id) {
    const modal = new bootstrap.Modal(document.getElementById('appointmentModal'));
    modal.show();
    const body = document.getElementById('appointmentModalBody');
    showSkeleton(body);

    const result = await apiRequest(`/doctor/appointments/${id}`);
    if (!result.success) { body.innerHTML = '<p class="text-danger">Failed to load.</p>'; return; }
    const a = result.data;
    body.innerHTML = `
        <div class="row">
            <div class="col-md-6">
                <h6>Patient Information</h6>
                <p><strong>Name:</strong> ${a.patient_first_name} ${a.patient_last_name}</p>
                <p><strong>DOB:</strong> ${a.patient_dob || 'N/A'}</p>
                <p><strong>Gender:</strong> ${a.patient_gender || 'N/A'}</p>
                <p><strong>Allergies:</strong> ${a.patient_allergies || 'None reported'}</p>
                <p><strong>Parent:</strong> ${a.parent_first_name} ${a.parent_last_name}</p>
                <p><strong>Contact:</strong> ${a.parent_phone || 'N/A'}</p>
            </div>
            <div class="col-md-6">
                <h6>Appointment Details</h6>
                <p><strong>Date:</strong> ${a.appointment_date}</p>
                <p><strong>Time:</strong> ${a.appointment_time}</p>
                <p><strong>Type:</strong> ${a.type}</p>
                <p><strong>Status:</strong> <span class="badge bg-primary">${a.status}</span></p>
                <p><strong>Reason:</strong> ${a.reason || 'Not specified'}</p>
                <p><strong>Notes:</strong> ${a.notes || 'None'}</p>
            </div>
        </div>
    `;
}

async function updateStatus(id, status) {
    showConfirm(`Change status to "${status}"?`, async () => {
        const result = await apiRequest('/doctor/appointments/update-status', 'POST', { appointment_id: id, status });
        if (result.success) { showToast(result.message, 'success'); setTimeout(() => location.reload(), 800); }
    });
}

async function openRecords(patientId) {
    const modal = new bootstrap.Modal(document.getElementById('recordsModal'));
    modal.show();
    const body = document.getElementById('recordsModalBody');
    showSkeleton(body, 5);

    const result = await apiRequest(`/doctor/patients/${patientId}/records`);
    if (!result.success) { body.innerHTML = '<p class="text-danger">Failed to load.</p>'; return; }
    const d = result.data;
    body.innerHTML = `
        <ul class="nav nav-tabs mb-3" role="tablist">
            <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-notes">Notes (${d.consultation_notes?.length || 0})</a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-rx">Prescriptions (${d.prescriptions?.length || 0})</a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-vax">Vaccinations (${d.vaccination_history?.length || 0})</a></li>
        </ul>
        <div class="tab-content">
            <div class="tab-pane fade show active" id="tab-notes">
                ${d.consultation_notes?.length ? d.consultation_notes.map(n => `
                    <div class="card mb-2"><div class="card-body p-3">
                        <div class="d-flex justify-content-between"><strong>${n.consultation_date}</strong><span class="text-muted">Dr. ${n.doctor_first_name} ${n.doctor_last_name}</span></div>
                        <p class="mb-1"><strong>Diagnosis:</strong> ${n.diagnosis || 'N/A'}</p>
                        <p class="mb-0 text-muted">${n.notes || ''}</p>
                    </div></div>
                `).join('') : '<p class="text-muted">No consultation notes.</p>'}
            </div>
            <div class="tab-pane fade" id="tab-rx">
                ${d.prescriptions?.length ? d.prescriptions.map(p => `
                    <div class="card mb-2"><div class="card-body p-3">
                        <div class="d-flex justify-content-between"><strong>${p.prescription_number}</strong><span>${p.prescription_date}</span></div>
                        <p class="mb-0">${p.diagnosis || 'N/A'}</p>
                    </div></div>
                `).join('') : '<p class="text-muted">No prescriptions.</p>'}
            </div>
            <div class="tab-pane fade" id="tab-vax">
                ${d.vaccination_history?.length ? '<table class="table table-sm"><thead><tr><th>Vaccine</th><th>Dose</th><th>Date</th><th>Status</th></tr></thead><tbody>' +
                d.vaccination_history.map(v => `<tr><td>${v.vaccine_name}</td><td>${v.dose_number}/${v.total_doses}</td><td>${v.administration_date}</td><td><span class="badge bg-success">${v.status}</span></td></tr>`).join('') +
                '</tbody></table>' : '<p class="text-muted">No vaccination records.</p>'}
            </div>
        </div>
    `;
}

async function loadDayAppointments() {
    const date = document.getElementById('filterDate').value;
    const result = await apiRequest(`/doctor/appointments?date_from=${date}&date_to=${date}`);
    if (result.success && result.data) {
        const list = document.getElementById('appointmentsList');
        if (!result.data.length) {
            list.innerHTML = '<div class="empty-state py-4"><i class="bi bi-calendar-x"></i><h5>No appointments</h5></div>';
            return;
        }
        list.innerHTML = '<div class="table-responsive"><table class="table table-hover table-sm"><thead><tr><th>Time</th><th>Patient</th><th>Type</th><th>Status</th><th>Actions</th></tr></thead><tbody>' +
            result.data.map(a => `<tr>
                <td><strong>${a.appointment_time}</strong></td>
                <td>${a.patient_first_name} ${a.patient_last_name}</td>
                <td><span class="badge bg-light text-dark">${a.type}</span></td>
                <td><span class="badge bg-primary">${a.status}</span></td>
                <td><button class="btn btn-sm btn-outline-primary" onclick="showAppointmentDetails(${a.id})"><i class="bi bi-eye"></i></button></td>
            </tr>`).join('') + '</tbody></table></div>';
    }
}
</script>
<?php $extraScripts = ob_get_clean(); ?>
