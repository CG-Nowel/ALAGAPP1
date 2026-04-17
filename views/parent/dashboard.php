<?php
$layout = 'app';
$pageTitle = 'Parent Dashboard - PediCare';
$breadcrumbs = [['label' => 'Parent'], ['label' => 'Dashboard']];
$sidebarNav = '
<a class="nav-link active" href="/parent/dashboard"><i class="bi bi-speedometer2"></i> Dashboard</a>
<a class="nav-link" href="/parent/children"><i class="bi bi-people"></i> My Children</a>
<a class="nav-link" href="#" onclick="loadSection(\'appointments\')"><i class="bi bi-calendar"></i> Appointments</a>
<a class="nav-link" href="#" onclick="loadSection(\'book\')"><i class="bi bi-calendar-plus"></i> Book Appointment</a>
';
use App\Middleware\CsrfMiddleware;
?>

<!-- Welcome -->
<div class="mb-4">
    <h4>Welcome back, <?= htmlspecialchars($user['first_name'] ?? '') ?>!</h4>
    <p class="text-muted">Here's an overview of your children's healthcare.</p>
</div>

<!-- Children Cards -->
<h6 class="mb-3"><i class="bi bi-people me-1"></i>My Children</h6>
<div class="row g-3 mb-4">
    <?php if (empty($children)): ?>
    <div class="col-12">
        <div class="stat-card text-center py-4">
            <i class="bi bi-person-plus" style="font-size:3rem;color:#ddd;"></i>
            <h6 class="text-muted mt-2">No children registered yet</h6>
            <button class="btn btn-primary btn-sm mt-2" onclick="showAddChildModal()">Add Your First Child</button>
        </div>
    </div>
    <?php else: ?>
        <?php foreach ($children as $child): ?>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="d-flex align-items-center gap-3 mb-2">
                    <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:48px;height:48px;background:<?= $child['gender'] === 'MALE' ? 'rgba(0,123,255,.1)' : 'rgba(255,107,154,.1)' ?>;">
                        <i class="bi bi-person" style="color:<?= $child['gender'] === 'MALE' ? '#007bff' : '#FF6B9A' ?>;font-size:1.3rem;"></i>
                    </div>
                    <div>
                        <strong><?= htmlspecialchars($child['first_name'] . ' ' . $child['last_name']) ?></strong><br>
                        <small class="text-muted"><?= date('M j, Y', strtotime($child['date_of_birth'])) ?> (<?= (int)((time()-strtotime($child['date_of_birth']))/31557600) ?>y)</small>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-outline-primary flex-fill" onclick="viewRecords(<?= $child['id'] ?>)"><i class="bi bi-file-medical me-1"></i>Records</button>
                    <button class="btn btn-sm btn-outline-primary flex-fill" onclick="viewVaccinations(<?= $child['id'] ?>)"><i class="bi bi-shield-plus me-1"></i>Vaccines</button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <div class="col-md-4">
            <div class="stat-card text-center d-flex align-items-center justify-content-center" style="min-height:120px;cursor:pointer;border:2px dashed #ddd;" onclick="showAddChildModal()">
                <div><i class="bi bi-plus-circle" style="font-size:2rem;color:#ccc;"></i><br><small class="text-muted">Add Child</small></div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Upcoming Appointments -->
<div class="stat-card p-3 mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="mb-0"><i class="bi bi-calendar-event me-1"></i>Upcoming Appointments</h6>
        <button class="btn btn-primary btn-sm" onclick="showBookModal()"><i class="bi bi-plus me-1"></i>Book New</button>
    </div>
    <?php if (empty($upcomingAppointments)): ?>
        <div class="empty-state py-3">
            <i class="bi bi-calendar-x"></i>
            <p class="mb-0">No upcoming appointments</p>
        </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover table-sm mb-0">
            <thead><tr><th>Date</th><th>Time</th><th>Child</th><th>Doctor</th><th>Type</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($upcomingAppointments as $apt): ?>
            <tr>
                <td><?= date('M j', strtotime($apt['appointment_date'])) ?></td>
                <td><?= date('g:i A', strtotime($apt['appointment_time'])) ?></td>
                <td><?= htmlspecialchars($apt['patient_first_name']) ?></td>
                <td>Dr. <?= htmlspecialchars($apt['doctor_first_name'] . ' ' . $apt['doctor_last_name']) ?></td>
                <td><span class="badge bg-light text-dark"><?= $apt['type'] ?></span></td>
                <td><span class="badge bg-info"><?= $apt['status'] ?></span></td>
                <td>
                    <?php if (in_array($apt['status'], ['SCHEDULED', 'CONFIRMED'])): ?>
                    <button class="btn btn-sm btn-outline-danger" onclick="cancelAppointment(<?= $apt['id'] ?>)" title="Cancel"><i class="bi bi-x"></i></button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Add Child Modal -->
<div class="modal fade" id="addChildModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Add Child</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <form id="addChildForm">
                    <div class="row mb-3">
                        <div class="col-6"><label class="form-label">First Name</label><input type="text" name="first_name" class="form-control" required></div>
                        <div class="col-6"><label class="form-label">Last Name</label><input type="text" name="last_name" class="form-control" required></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-6"><label class="form-label">Date of Birth</label><input type="date" name="date_of_birth" class="form-control" required></div>
                        <div class="col-6"><label class="form-label">Gender</label>
                            <select name="gender" class="form-select" required><option value="MALE">Male</option><option value="FEMALE">Female</option><option value="OTHER">Other</option></select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-6"><label class="form-label">Blood Type</label>
                            <select name="blood_type" class="form-select"><option value="">Unknown</option><option>A+</option><option>A-</option><option>B+</option><option>B-</option><option>AB+</option><option>AB-</option><option>O+</option><option>O-</option></select>
                        </div>
                        <div class="col-3"><label class="form-label">Height (cm)</label><input type="number" name="height" class="form-control" step="0.1"></div>
                        <div class="col-3"><label class="form-label">Weight (kg)</label><input type="number" name="weight" class="form-control" step="0.1"></div>
                    </div>
                    <div class="mb-3"><label class="form-label">Allergies</label><textarea name="allergies" class="form-control" rows="2" placeholder="List any allergies..."></textarea></div>
                    <div class="mb-3"><label class="form-label">Medical Conditions</label><textarea name="medical_conditions" class="form-control" rows="2" placeholder="Existing conditions..."></textarea></div>
                </form>
            </div>
            <div class="modal-footer"><button class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" onclick="addChild()">Add Child</button></div>
        </div>
    </div>
</div>

<!-- Book Appointment Modal -->
<div class="modal fade" id="bookModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Book Appointment</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <form id="bookForm">
                    <div class="mb-3"><label class="form-label">Child</label>
                        <select name="patient_id" class="form-select" required>
                            <option value="">Select child...</option>
                            <?php foreach ($children as $child): ?>
                            <option value="<?= $child['id'] ?>"><?= htmlspecialchars($child['first_name'] . ' ' . $child['last_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3"><label class="form-label">Doctor</label>
                        <select name="doctor_id" id="doctorSelect" class="form-select" required onchange="loadSlots()"><option value="">Loading doctors...</option></select>
                    </div>
                    <div class="mb-3"><label class="form-label">Date</label><input type="date" name="appointment_date" id="appointmentDate" class="form-control" required min="<?= date('Y-m-d') ?>" onchange="loadSlots()"></div>
                    <div class="mb-3"><label class="form-label">Time Slot</label>
                        <select name="appointment_time" id="timeSlots" class="form-select" required><option value="">Select date and doctor first</option></select>
                    </div>
                    <div class="mb-3"><label class="form-label">Type</label>
                        <select name="type" class="form-select"><option value="CONSULTATION">Consultation</option><option value="VACCINATION">Vaccination</option><option value="CHECKUP">Checkup</option><option value="FOLLOW_UP">Follow-up</option></select>
                    </div>
                    <div class="mb-3"><label class="form-label">Reason</label><textarea name="reason" class="form-control" rows="2" placeholder="Reason for visit..."></textarea></div>
                </form>
            </div>
            <div class="modal-footer"><button class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" onclick="bookAppointment()">Book Appointment</button></div>
        </div>
    </div>
</div>

<!-- Records Modal -->
<div class="modal fade" id="recordsModal">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Medical Records</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body" id="recordsBody"><div class="skeleton" style="height:300px;"></div></div>
        </div>
    </div>
</div>

<?php ob_start(); ?>
<script>
function showAddChildModal() { new bootstrap.Modal(document.getElementById('addChildModal')).show(); }

async function addChild() {
    const result = await apiRequest('/parent/children', 'POST', new FormData(document.getElementById('addChildForm')));
    if (result.success) { showToast(result.message, 'success'); setTimeout(() => location.reload(), 800); }
}

function showBookModal() {
    new bootstrap.Modal(document.getElementById('bookModal')).show();
    loadDoctors();
}

async function loadDoctors() {
    const result = await apiRequest('/parent/doctors');
    const select = document.getElementById('doctorSelect');
    select.innerHTML = '<option value="">Select doctor...</option>';
    if (result.success) {
        result.data.forEach(d => {
            select.innerHTML += `<option value="${d.id}">Dr. ${d.first_name} ${d.last_name} - ${d.specialization || 'General'}</option>`;
        });
    }
}

async function loadSlots() {
    const doctorId = document.getElementById('doctorSelect').value;
    const date = document.getElementById('appointmentDate').value;
    const select = document.getElementById('timeSlots');
    if (!doctorId || !date) { select.innerHTML = '<option value="">Select date and doctor first</option>'; return; }

    select.innerHTML = '<option value="">Loading slots...</option>';
    const result = await fetch(`available-slots.php?doctor_id=${doctorId}&date=${date}`).then(r => r.json());
    select.innerHTML = '<option value="">Select time...</option>';
    if (result.success) {
        result.data.filter(s => s.available).forEach(s => {
            select.innerHTML += `<option value="${s.time}">${s.formatted}</option>`;
        });
        if (select.options.length === 1) select.innerHTML = '<option value="">No available slots</option>';
    }
}

async function bookAppointment() {
    const result = await apiRequest('/parent/appointments', 'POST', new FormData(document.getElementById('bookForm')));
    if (result.success) { showToast(result.message, 'success'); setTimeout(() => location.reload(), 800); }
}

async function cancelAppointment(id) {
    showConfirm('Are you sure you want to cancel this appointment?', async () => {
        const result = await apiRequest(`/parent/appointments/${id}/cancel`, 'POST', { reason: 'Cancelled by parent' });
        if (result.success) { showToast(result.message, 'success'); setTimeout(() => location.reload(), 800); }
    });
}

async function viewRecords(patientId) {
    const modal = new bootstrap.Modal(document.getElementById('recordsModal'));
    modal.show();
    const body = document.getElementById('recordsBody');
    showSkeleton(body, 5);

    const result = await apiRequest(`/parent/patients/${patientId}/records`);
    if (!result.success) { body.innerHTML = '<p class="text-danger">Failed to load records.</p>'; return; }
    const d = result.data;
    body.innerHTML = `
        <ul class="nav nav-tabs mb-3">
            <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#p-notes">Consultations</a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#p-rx">Prescriptions</a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#p-vax">Vaccinations</a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#p-files">Files</a></li>
        </ul>
        <div class="tab-content">
            <div class="tab-pane fade show active" id="p-notes">
                ${d.consultation_notes?.length ? d.consultation_notes.map(n => `
                    <div class="card mb-2"><div class="card-body p-3">
                        <div class="d-flex justify-content-between"><strong>${n.consultation_date}</strong><span class="text-muted">Dr. ${n.doctor_first_name} ${n.doctor_last_name}</span></div>
                        ${n.diagnosis ? `<p class="mb-1"><strong>Diagnosis:</strong> ${n.diagnosis}</p>` : ''}
                        ${n.treatment_plan ? `<p class="mb-1"><strong>Treatment:</strong> ${n.treatment_plan}</p>` : ''}
                        ${n.notes ? `<p class="mb-0 text-muted">${n.notes}</p>` : ''}
                    </div></div>
                `).join('') : '<div class="empty-state py-3"><i class="bi bi-journal-x"></i><p>No consultation notes yet.</p></div>'}
            </div>
            <div class="tab-pane fade" id="p-rx">
                ${d.prescriptions?.length ? d.prescriptions.map(p => `
                    <div class="card mb-2"><div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div><strong>${p.prescription_number}</strong> - ${p.prescription_date}</div>
                            <a href="/parent/prescriptions/${p.id}/print" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-printer me-1"></i>Print</a>
                        </div>
                        ${p.diagnosis ? `<p class="mb-0 mt-1">${p.diagnosis}</p>` : ''}
                    </div></div>
                `).join('') : '<div class="empty-state py-3"><i class="bi bi-capsule"></i><p>No prescriptions yet.</p></div>'}
            </div>
            <div class="tab-pane fade" id="p-vax">
                ${d.vaccination_history?.length ? '<table class="table table-sm"><thead><tr><th>Vaccine</th><th>Dose</th><th>Date</th><th>Administered By</th></tr></thead><tbody>' +
                d.vaccination_history.map(v => `<tr><td>${v.vaccine_name}</td><td>${v.dose_number}/${v.total_doses}</td><td>${v.administration_date}</td><td>${v.admin_first_name} ${v.admin_last_name}</td></tr>`).join('') +
                '</tbody></table>' : '<div class="empty-state py-3"><i class="bi bi-shield-x"></i><p>No vaccination records yet.</p></div>'}
            </div>
            <div class="tab-pane fade" id="p-files">
                ${d.files?.length ? d.files.map(f => `
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <div><i class="bi bi-file-earmark me-2"></i>${f.original_filename}<br><small class="text-muted">${f.file_category} - ${(f.file_size/1024).toFixed(1)}KB</small></div>
                        <a href="/parent/files/${f.id}/download" class="btn btn-sm btn-outline-primary"><i class="bi bi-download"></i></a>
                    </div>
                `).join('') : '<div class="empty-state py-3"><i class="bi bi-folder-x"></i><p>No files uploaded yet.</p></div>'}
            </div>
        </div>
    `;
}

async function viewVaccinations(patientId) {
    const modal = new bootstrap.Modal(document.getElementById('recordsModal'));
    modal.show();
    const body = document.getElementById('recordsBody');
    showSkeleton(body, 5);

    const result = await apiRequest(`/parent/patients/${patientId}/vaccinations`);
    if (!result.success) { body.innerHTML = '<p class="text-danger">Failed to load.</p>'; return; }
    const d = result.data;
    body.innerHTML = `
        <h6>Completed Vaccinations</h6>
        ${d.history?.length ? '<table class="table table-sm mb-4"><thead><tr><th>Vaccine</th><th>Dose</th><th>Date</th><th>Next Due</th></tr></thead><tbody>' +
        d.history.map(v => `<tr><td>${v.vaccine_name}</td><td>${v.dose_number}/${v.total_doses}</td><td>${v.administration_date}</td><td>${v.next_due_date || '-'}</td></tr>`).join('') +
        '</tbody></table>' : '<p class="text-muted">No completed vaccinations.</p>'}
        <h6>Pending Vaccinations</h6>
        ${d.pending?.length ? '<table class="table table-sm"><thead><tr><th>Vaccine</th><th>Dose</th><th>Recommended Date</th><th>Status</th></tr></thead><tbody>' +
        d.pending.map(v => `<tr><td>${v.vaccine_name}</td><td>${v.dose_number}</td><td>${v.recommended_date || 'TBD'}</td><td><span class="badge bg-${v.status==='MISSED'?'danger':'warning'}">${v.status}</span></td></tr>`).join('') +
        '</tbody></table>' : '<p class="text-muted">No pending vaccinations.</p>'}
    `;
}
</script>
<?php $extraScripts = ob_get_clean(); ?>
