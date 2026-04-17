<?php
$layout = 'app';
$pageTitle = 'My Children - PediCare';
$breadcrumbs = [['label' => 'Parent', 'url' => '/parent/dashboard'], ['label' => 'My Children']];
$sidebarNav = '
<a class="nav-link" href="/parent/dashboard"><i class="bi bi-speedometer2"></i> Dashboard</a>
<a class="nav-link active" href="/parent/children"><i class="bi bi-people"></i> My Children</a>
<a class="nav-link" href="/parent/dashboard#appointments"><i class="bi bi-calendar"></i> Appointments</a>
';
use App\Middleware\CsrfMiddleware;
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h5 class="mb-0">My Children</h5>
    <button class="btn btn-primary btn-sm" onclick="showAddChildModal()"><i class="bi bi-plus me-1"></i>Add Child</button>
</div>

<div class="row g-3">
    <?php if (empty($children)): ?>
    <div class="col-12">
        <div class="stat-card text-center py-5">
            <i class="bi bi-person-plus" style="font-size:4rem;color:#ddd;"></i>
            <h5 class="text-muted mt-3">No children registered yet</h5>
            <p class="text-muted">Add your first child to start booking appointments.</p>
            <button class="btn btn-primary" onclick="showAddChildModal()">Add Child</button>
        </div>
    </div>
    <?php else: ?>
        <?php foreach ($children as $child): ?>
        <div class="col-md-6">
            <div class="stat-card">
                <div class="d-flex align-items-start gap-3">
                    <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                         style="width:60px;height:60px;background:<?= $child['gender'] === 'MALE' ? 'rgba(0,123,255,.1)' : 'rgba(255,107,154,.1)' ?>;">
                        <i class="bi bi-person-fill" style="color:<?= $child['gender'] === 'MALE' ? '#007bff' : '#FF6B9A' ?>;font-size:1.5rem;"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h6 class="mb-1"><?= htmlspecialchars($child['first_name'] . ' ' . $child['last_name']) ?></h6>
                        <div class="row text-muted small">
                            <div class="col-6"><strong>DOB:</strong> <?= date('M j, Y', strtotime($child['date_of_birth'])) ?></div>
                            <div class="col-6"><strong>Age:</strong> <?= (int)((time()-strtotime($child['date_of_birth']))/31557600) ?> years</div>
                            <div class="col-6"><strong>Gender:</strong> <?= $child['gender'] ?></div>
                            <div class="col-6"><strong>Blood:</strong> <?= $child['blood_type'] ?: 'Unknown' ?></div>
                        </div>
                        <?php if ($child['allergies']): ?>
                            <div class="mt-1"><small class="text-danger"><i class="bi bi-exclamation-triangle me-1"></i>Allergies: <?= htmlspecialchars($child['allergies']) ?></small></div>
                        <?php endif; ?>
                        <div class="mt-2 d-flex gap-2">
                            <button class="btn btn-sm btn-outline-primary" onclick="editChild(<?= $child['id'] ?>, <?= htmlspecialchars(json_encode($child), ENT_QUOTES) ?>)">
                                <i class="bi bi-pencil me-1"></i>Edit
                            </button>
                            <a href="/parent/dashboard" class="btn btn-sm btn-outline-primary"><i class="bi bi-file-medical me-1"></i>Records</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Add Child Modal -->
<div class="modal fade" id="addChildModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="childModalTitle">Add Child</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <form id="childForm">
                    <input type="hidden" name="child_id" id="childId">
                    <div class="row mb-3">
                        <div class="col-6"><label class="form-label">First Name</label><input type="text" name="first_name" id="childFirstName" class="form-control" required></div>
                        <div class="col-6"><label class="form-label">Last Name</label><input type="text" name="last_name" id="childLastName" class="form-control" required></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-6"><label class="form-label">Date of Birth</label><input type="date" name="date_of_birth" id="childDob" class="form-control" required></div>
                        <div class="col-6"><label class="form-label">Gender</label>
                            <select name="gender" id="childGender" class="form-select" required><option value="MALE">Male</option><option value="FEMALE">Female</option><option value="OTHER">Other</option></select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-4"><label class="form-label">Blood Type</label>
                            <select name="blood_type" id="childBloodType" class="form-select"><option value="">Unknown</option><option>A+</option><option>A-</option><option>B+</option><option>B-</option><option>AB+</option><option>AB-</option><option>O+</option><option>O-</option></select>
                        </div>
                        <div class="col-4"><label class="form-label">Height (cm)</label><input type="number" name="height" id="childHeight" class="form-control" step="0.1"></div>
                        <div class="col-4"><label class="form-label">Weight (kg)</label><input type="number" name="weight" id="childWeight" class="form-control" step="0.1"></div>
                    </div>
                    <div class="mb-3"><label class="form-label">Allergies</label><textarea name="allergies" id="childAllergies" class="form-control" rows="2"></textarea></div>
                    <div class="mb-3"><label class="form-label">Medical Conditions</label><textarea name="medical_conditions" id="childMedical" class="form-control" rows="2"></textarea></div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" id="childSubmitBtn" onclick="submitChild()">Add Child</button>
            </div>
        </div>
    </div>
</div>

<?php ob_start(); ?>
<script>
let editingChildId = null;

function showAddChildModal() {
    editingChildId = null;
    document.getElementById('childModalTitle').textContent = 'Add Child';
    document.getElementById('childSubmitBtn').textContent = 'Add Child';
    document.getElementById('childForm').reset();
    new bootstrap.Modal(document.getElementById('addChildModal')).show();
}

function editChild(id, data) {
    editingChildId = id;
    document.getElementById('childModalTitle').textContent = 'Edit Child';
    document.getElementById('childSubmitBtn').textContent = 'Save Changes';
    document.getElementById('childFirstName').value = data.first_name || '';
    document.getElementById('childLastName').value = data.last_name || '';
    document.getElementById('childDob').value = data.date_of_birth || '';
    document.getElementById('childGender').value = data.gender || 'MALE';
    document.getElementById('childBloodType').value = data.blood_type || '';
    document.getElementById('childHeight').value = data.height || '';
    document.getElementById('childWeight').value = data.weight || '';
    document.getElementById('childAllergies').value = data.allergies || '';
    document.getElementById('childMedical').value = data.medical_conditions || '';
    new bootstrap.Modal(document.getElementById('addChildModal')).show();
}

async function submitChild() {
    const form = new FormData(document.getElementById('childForm'));
    let url = '/parent/children';
    if (editingChildId) url = `/parent/children/${editingChildId}/update`;
    const result = await apiRequest(url, 'POST', form);
    if (result.success) {
        showToast(result.message, 'success');
        setTimeout(() => location.reload(), 800);
    }
}
</script>
<?php $extraScripts = ob_get_clean(); ?>
