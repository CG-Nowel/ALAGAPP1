/* ============================================
   AlagApp Clinic - Doctor Dashboard Scripts
   Handles patient management, vaccinations, modals
   ============================================ */

// Global Variables
var currentPatient = null;
var currentPatientForVaccine = null;

// ---- Utility Functions ----
function escapeHtml(str) {
    if (!str) return '';
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
}

function showNotification(message, type) {
    type = type || 'success';
    var notification = document.getElementById('notification');
    if (!notification) return;
    notification.textContent = message;
    notification.className = 'notification ' + type + ' show';
    notification.classList.remove('hidden');
    setTimeout(function() {
        notification.classList.remove('show');
    }, 3000);
}

// ---- Mobile Sidebar Toggle ----
function toggleSidebar() {
    var sidebar = document.getElementById('sidebar');
    var overlay = document.getElementById('sidebarOverlay');
    if (sidebar) {
        sidebar.classList.toggle('open');
    }
    if (overlay) {
        overlay.classList.toggle('hidden');
    }
}

// ---- Navigation ----
function showSection(sectionName) {
    document.querySelectorAll('.section-content').forEach(function(section) {
        section.classList.add('hidden');
    });

    // Close sidebar on mobile
    var sidebar = document.getElementById('sidebar');
    var overlay = document.getElementById('sidebarOverlay');
    if (sidebar && sidebar.classList.contains('open')) {
        sidebar.classList.remove('open');
        if (overlay) overlay.classList.add('hidden');
    }

    var targetSection = document.getElementById(sectionName + '-section');
    if (targetSection) {
        targetSection.classList.remove('hidden');
        targetSection.classList.add('fade-in');
        setActiveNav(sectionName);
        if (sectionName === 'dashboard') {
            setTimeout(initializeCharts, 100);
        }
    }
}

function setActiveNav(sectionName) {
    document.querySelectorAll('.nav-item').forEach(function(item) {
        item.classList.remove('active', 'bg-white/20');
    });
    var activeNav = document.querySelector('.nav-item[data-section="' + sectionName + '"]');
    if (activeNav) {
        activeNav.classList.add('active', 'bg-white/20');
    }
}

// ---- Modal Management ----
function closeModal(modalId) {
    var el = document.getElementById(modalId);
    if (el) el.classList.add('hidden');
}

function closeAllModals() {
    document.querySelectorAll('.modal-container').forEach(function(modal) {
        modal.classList.add('hidden');
    });
}

// ---- Notification System ----
function showNotification(message, type, duration) {
    type = type || 'success';
    duration = duration || 4000;
    var notification = document.getElementById('notification');
    if (!notification) return;

    notification.textContent = message;
    notification.className = 'notification ' + type;
    notification.classList.add('show');

    setTimeout(function() {
        notification.classList.remove('show');
    }, duration);
}

// ---- Initialization ----
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.nav-item[data-section]').forEach(function(navItem) {
        navItem.addEventListener('click', function(e) {
            e.preventDefault();
            var sectionName = this.getAttribute('data-section');
            showSection(sectionName);
        });
    });

    setActiveNav('dashboard');
    var adminDateEl = document.getElementById('administration_date');
    if (adminDateEl) {
        adminDateEl.value = new Date().toISOString().split('T')[0];
    }
    initializeCharts();
});

// ---- Chart Initialization ----
function initializeCharts() {
    var chartData = window.APPOINTMENT_CHART_DATA || { dates: [], counts: [] };
    var vaccinationChartData = window.VACCINATION_CHART_DATA || { months: [], vaccination_counts: [] };

    var appointmentsCtx = document.getElementById('appointmentsChart');
    if (appointmentsCtx && chartData.dates && chartData.dates.length > 0 && typeof Chart !== 'undefined') {
        new Chart(appointmentsCtx.getContext('2d'), {
            type: 'line',
            data: {
                labels: chartData.dates,
                datasets: [{
                    label: 'Daily Appointments',
                    data: chartData.counts,
                    borderColor: '#ff7aa3',
                    backgroundColor: 'rgba(255, 107, 154, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: true, position: 'top' } },
                scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
            }
        });
    }

    var vaccinationsCtx = document.getElementById('vaccinationsChart');
    if (vaccinationsCtx && vaccinationChartData.months && vaccinationChartData.months.length > 0 && typeof Chart !== 'undefined') {
        new Chart(vaccinationsCtx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: vaccinationChartData.months,
                datasets: [{
                    label: 'Vaccinations Administered',
                    data: vaccinationChartData.vaccination_counts,
                    backgroundColor: '#4F46E5',
                    borderColor: '#3730A3',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: true, position: 'top' } },
                scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
            }
        });
    }
}

// ---- Patient Management ----
function selectPatient(patientId, element) {
    document.querySelectorAll('.patient-item').forEach(function(item) {
        item.classList.remove('bg-primary/10', 'border-primary', 'selected');
    });
    if (element) {
        element.classList.add('bg-primary/10', 'border-primary', 'selected');
    }
    currentPatient = patientId;
    loadPatientDetails(patientId);
    loadPatientVaccineNeeds(patientId);
    loadPatientVaccinationHistory(patientId);
}

function loadPatientDetails(patientId) {
    var patientSummary = document.getElementById('patientSummary');
    if (!patientSummary) return;

    patientSummary.innerHTML =
        '<div class="text-center">' +
        '<div class="loading-spinner mx-auto mb-2"></div>' +
        '<div class="text-sm text-gray-500">Loading patient details...</div>' +
        '</div>';

    var formData = new FormData();
    formData.append('action', 'get_patient_details');
    formData.append('patient_id', patientId);
    formData.append('ajax', 'true');
    formData.append('csrf_token', window.CSRF_TOKEN || '');

    fetch('', { method: 'POST', body: formData })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                displayPatientDetails(data.patient);
            } else {
                patientSummary.innerHTML = '<p class="text-red-500">' + (data.message || 'Error') + '</p>';
            }
        })
        .catch(function(error) {
            console.error('Error:', error);
            patientSummary.innerHTML = '<p class="text-red-500">Failed to load patient details</p>';
        });
}

function displayPatientDetails(patient) {
    var patientSummary = document.getElementById('patientSummary');
    if (!patientSummary) return;

    var age = patient.age_years > 0
        ? patient.age_years + ' years'
        : patient.age_months + ' months';

    patientSummary.innerHTML =
        '<div class="text-left space-y-4">' +
        '<div><h4 class="font-semibold text-gray-800 mb-2">Patient Information</h4>' +
        '<div class="grid grid-cols-2 gap-2 text-sm">' +
        '<div class="text-gray-600">Name:</div><div class="font-medium">' + escapeHtml(patient.first_name) + ' ' + escapeHtml(patient.last_name) + '</div>' +
        '<div class="text-gray-600">DOB:</div><div class="font-medium">' + new Date(patient.date_of_birth).toLocaleDateString() + '</div>' +
        '<div class="text-gray-600">Age:</div><div class="font-medium">' + age + '</div>' +
        '<div class="text-gray-600">Gender:</div><div class="font-medium">' + escapeHtml(patient.gender || 'N/A') + '</div>' +
        '</div></div></div>';
}

function escapeHtml(unsafe) {
    if (unsafe === null || unsafe === undefined) return '';
    return String(unsafe)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

// ---- Vaccination History ----
function loadPatientVaccinationHistory(patientId) {
    var formData = new FormData();
    formData.append('action', 'get_patient_vaccination_records');
    formData.append('patient_id', patientId);
    formData.append('ajax', 'true');
    formData.append('csrf_token', window.CSRF_TOKEN || '');

    fetch('', { method: 'POST', body: formData })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                renderPatientVaccinationHistory(data.data);
            }
        })
        .catch(function(error) { console.error('Error:', error); });
}

function renderPatientVaccinationHistory(records) {
    var container = document.getElementById('patientVaccinationHistory');
    if (!container) return;

    if (!records || records.length === 0) {
        container.innerHTML = '<p class="text-gray-500 text-center py-4">No vaccination history</p>';
        return;
    }

    var html = '<div class="space-y-3">';
    records.forEach(function(record) {
        html +=
            '<div class="p-3 border border-gray-200 rounded-lg bg-white hover:shadow-md transition-shadow">' +
            '<div class="flex justify-between items-start mb-2"><div>' +
            '<div class="font-semibold text-gray-800 text-sm">' + escapeHtml(record.vaccine_name) + '</div>' +
            '<div class="text-xs text-gray-600">' + new Date(record.administration_date).toLocaleDateString() + '</div>' +
            '</div>' +
            '<span class="px-2 py-1 text-xs font-semibold rounded bg-green-100 text-green-800">Dose #' + escapeHtml(record.dose_number) + '</span>' +
            '</div>' +
            (record.lot_number ? '<div class="text-xs text-gray-600 mb-2">Lot: ' + escapeHtml(record.lot_number) + '</div>' : '') +
            (record.notes ? '<div class="text-xs text-gray-600 mb-2"><strong>Notes:</strong> ' + escapeHtml(record.notes) + '</div>' : '') +
            '<div class="flex gap-2 mt-3">' +
            '<button onclick="editVaccinationModal(' + record.id + ')" class="flex-1 px-2 py-1 text-xs bg-indigo-100 text-indigo-700 rounded hover:bg-indigo-200 transition-colors">Edit</button>' +
            '<button onclick="deleteVaccinationFromRecord(' + record.id + ')" class="flex-1 px-2 py-1 text-xs bg-red-100 text-red-700 rounded hover:bg-red-200 transition-colors">Delete</button>' +
            '</div></div>';
    });
    html += '</div>';
    container.innerHTML = html;
}

// ---- Vaccine Needs ----
function openVaccineNeedModal() {
    if (!currentPatient) {
        showNotification('Please select a patient first', 'error');
        return;
    }
    currentPatientForVaccine = currentPatient;

    var form = document.getElementById('vaccineNeedForm');
    if (form) form.reset();
    var needId = document.getElementById('vaccine_need_id');
    if (needId) needId.value = '';
    var patId = document.getElementById('vaccine_need_patient_id');
    if (patId) patId.value = currentPatient;

    var selectedPatient = document.querySelector('.patient-item.selected');
    if (selectedPatient) {
        var patientName = selectedPatient.querySelector('.font-semibold');
        var display = document.getElementById('vaccine_patient_display');
        if (patientName && display) display.textContent = patientName.textContent;
    }

    loadRecommendedVaccines(currentPatient);
    loadVaccineNeedsForModal(currentPatient);

    var modal = document.getElementById('vaccineNeedModal');
    if (modal) modal.classList.remove('hidden');
}

function loadRecommendedVaccines(patientId) {
    var formData = new FormData();
    formData.append('action', 'get_recommended_vaccines');
    formData.append('patient_id', patientId);
    formData.append('csrf_token', window.CSRF_TOKEN || '');

    fetch('', { method: 'POST', body: formData })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                var ageMonths = data.age_months;
                var ageDisplay = '';
                if (ageMonths < 12) {
                    ageDisplay = ageMonths + ' months';
                } else {
                    var years = Math.floor(ageMonths / 12);
                    var months = ageMonths % 12;
                    ageDisplay = years + ' year' + (years > 1 ? 's' : '') + ' ' + months + ' month' + (months !== 1 ? 's' : '');
                }
                var ageEl = document.getElementById('vaccine_age_display');
                if (ageEl) ageEl.textContent = ageDisplay;

                var vaccinesList = document.getElementById('recommendedVaccinesList');
                if (vaccinesList) {
                    if (data.data && data.data.length > 0) {
                        var html = '<ul class="list-disc list-inside space-y-1">';
                        data.data.forEach(function(vaccine) {
                            html += '<li>' + escapeHtml(vaccine.vaccine_name) + ' (' + escapeHtml(vaccine.disease_protected) + ')</li>';
                        });
                        html += '</ul>';
                        vaccinesList.innerHTML = html;
                    } else {
                        vaccinesList.innerHTML = '<p class="text-gray-700">No vaccines recommended for this age</p>';
                    }
                }
            }
        })
        .catch(function(error) { console.error('Error:', error); });
}

function loadVaccineNeedsForModal(patientId) {
    var formData = new FormData();
    formData.append('action', 'get_vaccine_needs');
    formData.append('patient_id', patientId);
    formData.append('csrf_token', window.CSRF_TOKEN || '');

    fetch('', { method: 'POST', body: formData })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success && data.data) {
                renderVaccineNeedsForModal(data.data);
            }
        })
        .catch(function(error) { console.error('Error:', error); });
}

function renderVaccineNeedsForModal(vaccines) {
    var container = document.getElementById('vaccineNeedsModalList');
    if (!container) return;

    if (!vaccines || vaccines.length === 0) {
        container.innerHTML = '<p class="text-gray-500 text-center py-8">No vaccine needs recorded</p>';
        return;
    }

    var statusColors = {
        'RECOMMENDED': 'bg-yellow-100 text-yellow-800',
        'SCHEDULED': 'bg-blue-100 text-blue-800',
        'GIVEN': 'bg-green-100 text-green-800',
        'NOT_NEEDED': 'bg-gray-100 text-gray-800'
    };

    var html = '';
    vaccines.forEach(function(vaccine) {
        var statusColor = statusColors[vaccine.status] || 'bg-gray-100 text-gray-800';
        var dateDisplay = vaccine.recommended_date
            ? new Date(vaccine.recommended_date).toLocaleDateString()
            : 'Not set';

        html +=
            '<div class="p-4 bg-white border border-gray-200 rounded-lg">' +
            '<div class="flex justify-between items-start mb-2"><div>' +
            '<div class="font-semibold text-gray-800">' + escapeHtml(vaccine.vaccine_name) + '</div>' +
            '<div class="text-sm text-gray-600">Due: ' + dateDisplay + '</div>' +
            '</div><span class="px-2 py-1 text-xs font-semibold rounded ' + statusColor + '">' + escapeHtml(vaccine.status) + '</span></div>' +
            (vaccine.notes ? '<div class="text-sm text-gray-600 mb-2"><strong>Notes:</strong> ' + escapeHtml(vaccine.notes) + '</div>' : '') +
            '<div class="flex gap-2 mt-3">' +
            '<button onclick=\'editVaccineNeed(' + JSON.stringify(vaccine) + ')\' class="flex-1 px-3 py-1 text-sm bg-indigo-100 text-indigo-700 rounded hover:bg-indigo-200 transition-colors">Edit</button>' +
            '<button onclick="deleteVaccineNeed(' + vaccine.id + ')" class="flex-1 px-3 py-1 text-sm bg-red-100 text-red-700 rounded hover:bg-red-200 transition-colors">Delete</button>' +
            '</div></div>';
    });
    container.innerHTML = html;
}

function editVaccineNeed(vaccine) {
    var fields = {
        'vaccine_need_id': vaccine.id,
        'vaccine_name_input': vaccine.vaccine_name,
        'recommended_date_input': vaccine.recommended_date || '',
        'vaccine_status_input': vaccine.status,
        'vaccine_notes_input': vaccine.notes || ''
    };
    for (var id in fields) {
        var el = document.getElementById(id);
        if (el) el.value = fields[id];
    }
}

function handleVaccineNeedForm(event) {
    event.preventDefault();
    var form = event.target;
    var formData = new FormData(form);
    formData.append('ajax', 'true');

    showNotification('Saving vaccine need...', 'info');

    fetch('', { method: 'POST', body: formData })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                showNotification(data.message, 'success');
                form.reset();
                var needId = document.getElementById('vaccine_need_id');
                if (needId) needId.value = '';
                loadVaccineNeedsForModal(currentPatientForVaccine);
                loadPatientVaccineNeeds(currentPatientForVaccine);
            } else {
                showNotification(data.message, 'error');
            }
        })
        .catch(function(error) {
            console.error('Error:', error);
            showNotification('Failed to save vaccine need', 'error');
        });
}

function deleteVaccineNeed(vaccineNeedId) {
    if (!confirm('Are you sure you want to delete this vaccine need?')) return;

    var formData = new FormData();
    formData.append('action', 'delete_vaccine_need');
    formData.append('vaccine_need_id', vaccineNeedId);
    formData.append('csrf_token', window.CSRF_TOKEN || '');

    showNotification('Deleting vaccine need...', 'info');

    fetch('', { method: 'POST', body: formData })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                showNotification(data.message, 'success');
                loadVaccineNeedsForModal(currentPatientForVaccine);
                loadPatientVaccineNeeds(currentPatientForVaccine);
            } else {
                showNotification(data.message, 'error');
            }
        })
        .catch(function(error) {
            console.error('Error:', error);
            showNotification('Failed to delete vaccine need', 'error');
        });
}

function loadPatientVaccineNeeds(patientId) {
    var formData = new FormData();
    formData.append('action', 'get_vaccine_needs');
    formData.append('patient_id', patientId);
    formData.append('csrf_token', window.CSRF_TOKEN || '');

    fetch('', { method: 'POST', body: formData })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success && data.data) {
                renderPatientVaccineNeeds(data.data);
            }
        })
        .catch(function(error) { console.error('Error:', error); });
}

function renderPatientVaccineNeeds(vaccines) {
    var container = document.getElementById('patientVaccineNeedsList');
    if (!container) return;

    if (!vaccines || vaccines.length === 0) {
        container.innerHTML = '<p class="text-gray-500 text-center py-4">No vaccine needs recorded</p>';
        return;
    }

    var statusColors = {
        'RECOMMENDED': 'bg-yellow-100 text-yellow-800',
        'SCHEDULED': 'bg-blue-100 text-blue-800',
        'GIVEN': 'bg-green-100 text-green-800',
        'NOT_NEEDED': 'bg-gray-100 text-gray-800'
    };

    var html = '<div class="space-y-3">';
    vaccines.forEach(function(vaccine) {
        var statusColor = statusColors[vaccine.status] || 'bg-gray-100 text-gray-800';
        var dateDisplay = vaccine.recommended_date
            ? new Date(vaccine.recommended_date).toLocaleDateString()
            : 'Not set';

        html +=
            '<div class="p-3 border border-gray-200 rounded-lg bg-white hover:shadow-md transition-shadow">' +
            '<div class="flex justify-between items-start mb-2"><div>' +
            '<div class="font-semibold text-gray-800 text-sm">' + escapeHtml(vaccine.vaccine_name) + '</div>' +
            '<div class="text-xs text-gray-600">Due: ' + dateDisplay + '</div></div>' +
            '<span class="px-2 py-1 text-xs font-semibold rounded ' + statusColor + '">' + escapeHtml(vaccine.status) + '</span></div>' +
            (vaccine.notes ? '<div class="text-xs text-gray-600 mb-2">' + escapeHtml(vaccine.notes) + '</div>' : '') +
            '</div>';
    });
    html += '</div>';
    container.innerHTML = html;
}

// ---- Vaccination Records ----
function openVaccinationModal() {
    if (!currentPatient) {
        showNotification('Please select a patient first', 'error');
        return;
    }
    var el = document.getElementById('vaccine_patient_id');
    if (el) el.value = currentPatient;
    var modal = document.getElementById('vaccinationModal');
    if (modal) modal.classList.remove('hidden');
}

function handleVaccinationForm(event) {
    event.preventDefault();
    var form = event.target;
    var formData = new FormData(form);
    formData.append('ajax', 'true');

    showNotification('Recording vaccination...', 'info');

    fetch('', { method: 'POST', body: formData })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                showNotification(data.message, 'success');
                closeModal('vaccinationModal');
                form.reset();
                var dateEl = document.getElementById('administration_date');
                if (dateEl) dateEl.value = new Date().toISOString().split('T')[0];
                if (currentPatient) loadPatientVaccinationHistory(currentPatient);
            } else {
                showNotification(data.message, 'error');
            }
        })
        .catch(function(error) {
            showNotification('Failed to record vaccination', 'error');
            console.error('Error:', error);
        });
}

function editVaccinationModal(vaccinationId) {
    var formData = new FormData();
    formData.append('action', 'get_vaccination_record');
    formData.append('vaccination_id', vaccinationId);
    formData.append('csrf_token', window.CSRF_TOKEN || '');

    fetch('', { method: 'POST', body: formData })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success && data.record) {
                showVaccinationEditModal(data.record);
            } else {
                showNotification('Failed to load vaccination record', 'error');
            }
        })
        .catch(function(error) {
            console.error('Error:', error);
            showNotification('Failed to load vaccination record', 'error');
        });
}

function showVaccinationEditModal(record) {
    var fields = {
        'edit_vaccination_id': record.id,
        'edit_vaccine_patient_id': record.patient_id,
        'edit_vaccine_name': record.vaccine_name,
        'edit_dose_number': record.dose_number,
        'edit_administration_date': record.administration_date,
        'edit_lot_number': record.lot_number || '',
        'edit_vaccination_notes': record.notes || ''
    };
    for (var id in fields) {
        var el = document.getElementById(id);
        if (el) el.value = fields[id];
    }
    var modal = document.getElementById('editVaccinationModal');
    if (modal) modal.classList.remove('hidden');
}

function handleEditVaccinationForm(event) {
    event.preventDefault();
    var form = event.target;
    var formData = new FormData(form);
    formData.append('ajax', 'true');

    showNotification('Updating vaccination record...', 'info');

    fetch('', { method: 'POST', body: formData })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                showNotification(data.message, 'success');
                closeModal('editVaccinationModal');
                if (currentPatient) loadPatientVaccinationHistory(currentPatient);
            } else {
                showNotification(data.message, 'error');
            }
        })
        .catch(function(error) {
            showNotification('Failed to update vaccination record', 'error');
            console.error('Error:', error);
        });
}

function deleteVaccinationFromRecord(vaccinationId) {
    if (!confirm('Are you sure you want to delete this vaccination record?')) return;

    var formData = new FormData();
    formData.append('action', 'delete_vaccination_record');
    formData.append('vaccination_id', vaccinationId);
    formData.append('csrf_token', window.CSRF_TOKEN || '');

    showNotification('Deleting vaccination record...', 'info');

    fetch('', { method: 'POST', body: formData })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                showNotification(data.message, 'success');
                if (currentPatient) loadPatientVaccinationHistory(currentPatient);
            } else {
                showNotification(data.message, 'error');
            }
        })
        .catch(function(error) {
            console.error('Error:', error);
            showNotification('Failed to delete vaccination record', 'error');
        });
}

// ---- Modal Openers ----
function openConsultationModal() {
    if (!currentPatient) {
        showNotification('Please select a patient first', 'error');
        return;
    }
    var selectedPatient = document.querySelector('.patient-item.selected');
    if (selectedPatient) {
        var name = selectedPatient.querySelector('.font-semibold');
        var display = document.getElementById('consultationPatientName');
        if (name && display) display.textContent = name.textContent;
    }
    var patId = document.getElementById('consultation_patient_id');
    if (patId) patId.value = currentPatient;
    var modal = document.getElementById('consultationModal');
    if (modal) modal.classList.remove('hidden');
}

function openPrescriptionModal() {
    if (!currentPatient) {
        showNotification('Please select a patient first', 'error');
        return;
    }
    var selectedPatient = document.querySelector('.patient-item.selected');
    if (selectedPatient) {
        var name = selectedPatient.querySelector('.font-semibold');
        var display = document.getElementById('prescriptionPatientName');
        if (name && display) display.textContent = name.textContent;
    }
    var patId = document.getElementById('prescription_patient_id');
    if (patId) patId.value = currentPatient;
    var modal = document.getElementById('prescriptionModal');
    if (modal) modal.classList.remove('hidden');
}

function openMedicalRecordModal() {
    if (!currentPatient) {
        showNotification('Please select a patient first', 'error');
        return;
    }
    var selectedPatient = document.querySelector('.patient-item.selected');
    if (selectedPatient) {
        var name = selectedPatient.querySelector('.font-semibold');
        var display = document.getElementById('medicalRecordPatientName');
        if (name && display) display.textContent = name.textContent;
    }
    var patId = document.getElementById('medical_record_patient_id');
    if (patId) patId.value = currentPatient;
    var modal = document.getElementById('medicalRecordModal');
    if (modal) modal.classList.remove('hidden');
}

// ---- Form Handlers ----
function handleConsultationForm(event) {
    event.preventDefault();
    var form = event.target;
    var formData = new FormData(form);
    formData.append('ajax', 'true');

    showNotification('Saving consultation notes...', 'info');

    fetch('', { method: 'POST', body: formData })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                showNotification(data.message, 'success');
                closeModal('consultationModal');
                form.reset();
            } else {
                showNotification(data.message, 'error');
            }
        })
        .catch(function(error) {
            showNotification('Failed to save consultation notes', 'error');
            console.error('Error:', error);
        });
}

function handlePrescriptionForm(event) {
    event.preventDefault();
    var form = event.target;
    var formData = new FormData(form);
    formData.append('ajax', 'true');

    showNotification('Saving prescription...', 'info');

    fetch('', { method: 'POST', body: formData })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                showNotification(data.message, 'success');
                closeModal('prescriptionModal');

                // Offer to print prescription
                var printData = {
                    patient_name: document.getElementById('prescriptionPatientName') ? document.getElementById('prescriptionPatientName').textContent : '',
                    medication: formData.get('medication_name') || '',
                    dosage: formData.get('dosage') || '',
                    frequency: formData.get('frequency') || '',
                    duration: formData.get('duration') || '',
                    instructions: formData.get('instructions') || '',
                    date: new Date().toLocaleDateString()
                };

                var action = prompt('Prescription saved! Enter:\n1 = Print\n2 = Download PDF\n(Cancel to skip)', '2');
                if (action === '1') {
                    printPrescription(printData);
                } else if (action === '2') {
                    downloadPrescriptionPDF(printData);
                }

                form.reset();
            } else {
                showNotification(data.message, 'error');
            }
        })
        .catch(function(error) {
            showNotification('Failed to save prescription', 'error');
            console.error('Error:', error);
        });
}

function printPrescription(data) {
    var printWindow = window.open('', '_blank', 'width=600,height=700');
    if (!printWindow) {
        showNotification('Please allow popups to print prescriptions', 'error');
        return;
    }
    var html = '<!DOCTYPE html><html><head><title>Prescription</title>' +
        '<style>body{font-family:Arial,sans-serif;padding:40px;max-width:600px;margin:0 auto;}' +
        'h1{text-align:center;color:#1a365d;border-bottom:2px solid #1a365d;padding-bottom:10px;}' +
        '.clinic-name{text-align:center;font-size:24px;font-weight:bold;color:#2d3748;}' +
        '.rx-symbol{font-size:32px;font-weight:bold;color:#2d3748;margin:20px 0;}' +
        '.field{margin:10px 0;}.field-label{font-weight:bold;color:#4a5568;}.field-value{margin-left:10px;}' +
        '.footer{margin-top:40px;border-top:1px solid #ccc;padding-top:20px;text-align:center;color:#718096;font-size:12px;}' +
        '.signature{margin-top:60px;border-top:1px solid #000;width:200px;text-align:center;padding-top:5px;}' +
        '@media print{body{padding:20px;}}</style></head><body>' +
        '<div class="clinic-name">AlagApp Pediatric Clinic</div>' +
        '<h1>Prescription</h1>' +
        '<div class="field"><span class="field-label">Date:</span><span class="field-value">' + escapeHtml(data.date) + '</span></div>' +
        '<div class="field"><span class="field-label">Patient:</span><span class="field-value">' + escapeHtml(data.patient_name) + '</span></div>' +
        '<div class="rx-symbol">Rx</div>' +
        '<div class="field"><span class="field-label">Medication:</span><span class="field-value">' + escapeHtml(data.medication) + '</span></div>' +
        '<div class="field"><span class="field-label">Dosage:</span><span class="field-value">' + escapeHtml(data.dosage) + '</span></div>' +
        '<div class="field"><span class="field-label">Frequency:</span><span class="field-value">' + escapeHtml(data.frequency) + '</span></div>' +
        '<div class="field"><span class="field-label">Duration:</span><span class="field-value">' + escapeHtml(data.duration) + '</span></div>' +
        (data.instructions ? '<div class="field"><span class="field-label">Instructions:</span><span class="field-value">' + escapeHtml(data.instructions) + '</span></div>' : '') +
        '<div class="signature">Physician Signature</div>' +
        '<div class="footer">This prescription was generated by AlagApp Pediatric Clinic Management System</div>' +
        '</body></html>';
    printWindow.document.write(html);
    printWindow.document.close();
    printWindow.focus();
    printWindow.print();
}

function downloadPrescriptionPDF(data) {
    // Dynamically load jsPDF if not already loaded
    if (typeof window.jspdf === 'undefined') {
        var script = document.createElement('script');
        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js';
        script.onload = function() { generatePrescriptionPDF(data); };
        script.onerror = function() {
            showNotification('Failed to load PDF library. Using print instead.', 'error');
            printPrescription(data);
        };
        document.head.appendChild(script);
    } else {
        generatePrescriptionPDF(data);
    }
}

function generatePrescriptionPDF(data) {
    var jsPDF = window.jspdf.jsPDF;
    var doc = new jsPDF();
    var pageWidth = doc.internal.pageSize.getWidth();
    var y = 20;

    // Header
    doc.setFontSize(20);
    doc.setFont('helvetica', 'bold');
    doc.text('AlagApp Pediatric Clinic', pageWidth / 2, y, { align: 'center' });
    y += 10;

    doc.setFontSize(14);
    doc.setFont('helvetica', 'normal');
    doc.text('PRESCRIPTION', pageWidth / 2, y, { align: 'center' });
    y += 5;

    // Line
    doc.setLineWidth(0.5);
    doc.line(20, y, pageWidth - 20, y);
    y += 12;

    // Fields
    doc.setFontSize(11);
    var fields = [
        ['Date', data.date],
        ['Patient', data.patient_name],
    ];
    fields.forEach(function(f) {
        doc.setFont('helvetica', 'bold');
        doc.text(f[0] + ':', 20, y);
        doc.setFont('helvetica', 'normal');
        doc.text(f[1] || '', 55, y);
        y += 8;
    });

    y += 5;

    // Rx symbol
    doc.setFontSize(24);
    doc.setFont('helvetica', 'bold');
    doc.text('Rx', 20, y);
    y += 12;

    // Medication details
    doc.setFontSize(11);
    var rxFields = [
        ['Medication', data.medication],
        ['Dosage', data.dosage],
        ['Frequency', data.frequency],
        ['Duration', data.duration],
        ['Instructions', data.instructions]
    ];
    rxFields.forEach(function(f) {
        if (f[1]) {
            doc.setFont('helvetica', 'bold');
            doc.text(f[0] + ':', 25, y);
            doc.setFont('helvetica', 'normal');
            var lines = doc.splitTextToSize(f[1], pageWidth - 80);
            doc.text(lines, 65, y);
            y += 8 * lines.length;
        }
    });

    y += 20;

    // Signature line
    doc.line(20, y, 80, y);
    y += 5;
    doc.setFontSize(10);
    doc.text('Physician Signature', 20, y);

    y += 15;
    // Footer
    doc.setFontSize(8);
    doc.setFont('helvetica', 'italic');
    doc.text('Generated by AlagApp Pediatric Clinic Management System', pageWidth / 2, doc.internal.pageSize.getHeight() - 10, { align: 'center' });

    // Download
    var filename = 'prescription_' + (data.patient_name || 'patient').replace(/[^a-zA-Z0-9]/g, '_') + '_' + new Date().toISOString().slice(0, 10) + '.pdf';
    doc.save(filename);
    showNotification('Prescription PDF downloaded!', 'success');
}

function handleMedicalRecordForm(event) {
    event.preventDefault();
    var form = event.target;
    var formData = new FormData(form);
    formData.append('ajax', 'true');

    showNotification('Saving medical record...', 'info');

    fetch('', { method: 'POST', body: formData })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                showNotification(data.message, 'success');
                closeModal('medicalRecordModal');
                form.reset();
            } else {
                showNotification(data.message, 'error');
            }
        })
        .catch(function(error) {
            showNotification('Failed to save medical record', 'error');
            console.error('Error:', error);
        });
}

// ---- Appointment Actions ----
function approveAppointment(appointmentId) {
    var formData = new FormData();
    formData.append('csrf_token', window.CSRF_TOKEN || '');
    formData.append('action', 'approve_appointment');
    formData.append('appointment_id', appointmentId);
    formData.append('ajax', 'true');

    showNotification('Approving appointment...', 'info');

    fetch('', { method: 'POST', body: formData })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                showNotification(data.message, 'success');
                setTimeout(function() { location.reload(); }, 1500);
            } else {
                showNotification(data.message, 'error');
            }
        })
        .catch(function(error) {
            showNotification('Failed to approve appointment', 'error');
            console.error('Error:', error);
        });
}

function rejectAppointment(appointmentId) {
    var reason = prompt('Enter reason for rejection:');
    if (!reason) return;

    var formData = new FormData();
    formData.append('csrf_token', window.CSRF_TOKEN || '');
    formData.append('action', 'reject_appointment');
    formData.append('appointment_id', appointmentId);
    formData.append('reason', reason);
    formData.append('ajax', 'true');

    showNotification('Rejecting appointment...', 'info');

    fetch('', { method: 'POST', body: formData })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                showNotification(data.message, 'success');
                setTimeout(function() { location.reload(); }, 1500);
            } else {
                showNotification(data.message, 'error');
            }
        })
        .catch(function(error) {
            showNotification('Failed to reject appointment', 'error');
            console.error('Error:', error);
        });
}

function completeAppointment(appointmentId) {
    var formData = new FormData();
    formData.append('csrf_token', window.CSRF_TOKEN || '');
    formData.append('action', 'complete_appointment');
    formData.append('appointment_id', appointmentId);
    formData.append('ajax', 'true');

    showNotification('Completing appointment...', 'info');

    fetch('', { method: 'POST', body: formData })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                showNotification(data.message, 'success');
                setTimeout(function() { location.reload(); }, 1500);
            } else {
                showNotification(data.message, 'error');
            }
        })
        .catch(function(error) {
            showNotification('Failed to complete appointment', 'error');
            console.error('Error:', error);
        });
}

function filterAppointments() {
    var filter = document.getElementById('appointmentFilter');
    if (!filter) return;

    var value = filter.value.toLowerCase();
    var today = new Date();
    today.setHours(0, 0, 0, 0);
    var todayStr = today.getFullYear() + '-' + String(today.getMonth() + 1).padStart(2, '0') + '-' + String(today.getDate()).padStart(2, '0');

    var rows = document.querySelectorAll('#appointments-section tbody tr');
    rows.forEach(function(row) {
        if (value === 'all') {
            row.style.display = '';
            return;
        }

        var dateCell = row.querySelector('td:nth-child(2)');
        var statusCell = row.querySelector('td:nth-child(5)');
        var rowDate = dateCell ? dateCell.textContent.trim() : '';
        var rowStatus = statusCell ? statusCell.textContent.trim().toLowerCase() : '';

        var show = false;
        if (value === 'today') {
            var parsedDate = new Date(rowDate);
            parsedDate.setHours(0, 0, 0, 0);
            show = parsedDate.getTime() === today.getTime();
        } else if (value === 'upcoming') {
            var parsedUpcoming = new Date(rowDate);
            parsedUpcoming.setHours(0, 0, 0, 0);
            show = parsedUpcoming >= today && rowStatus !== 'completed' && rowStatus !== 'cancelled';
        } else if (value === 'pending') {
            show = rowStatus.indexOf('pending') !== -1;
        } else if (value === 'completed') {
            show = rowStatus.indexOf('completed') !== -1 || rowStatus.indexOf('complete') !== -1;
        }

        row.style.display = show ? '' : 'none';
    });
}

function viewAppointmentDetails(appointmentId) {
    var formData = new FormData();
    formData.append('action', 'get_appointment_details');
    formData.append('appointment_id', appointmentId);
    formData.append('ajax', 'true');
    formData.append('csrf_token', window.CSRF_TOKEN || '');

    fetch('', { method: 'POST', body: formData })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                showNotification('Appointment loaded', 'success');
                console.log('Appointment Details:', data.appointment);
            } else {
                showNotification(data.message, 'error');
            }
        })
        .catch(function(error) {
            showNotification('Failed to load appointment details', 'error');
            console.error('Error:', error);
        });
}

// ---- Close Modals ----
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-backdrop')) {
        closeAllModals();
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeAllModals();
    }
});

// ============================================
// Doctor Calendar - Schedule & Availability
// ============================================
var doctorCalendar = null;
var doctorCalendarData = { unavailable_dates: [], appointments: [], working_days: [], appointment_counts: {} };

function initDoctorCalendar() {
    var calendarEl = document.getElementById('doctorCalendar');
    if (!calendarEl) return;
    var now = new Date();
    loadDoctorCalendarData(now.getMonth() + 1, now.getFullYear());
}


function loadDoctorCalendarData(month, year) {
    fetch('get_availability.php?doctor_id=' + encodeURIComponent(window.DOCTOR_ID) + '&month=' + month + '&year=' + year, {
        credentials: 'same-origin'
    })
    .then(function(response) { return response.text(); })
    .then(function(text) {
        try {
            var data = JSON.parse(text);
            if (data.success) {
                doctorCalendarData = {
                    unavailable_dates: data.unavailable_dates || [],
                    appointments: data.appointments || [],
                    working_days: data.working_days || [],
                    appointment_counts: data.appointment_counts || {}
                };
                renderDoctorCalendar(month, year);
            } else {
                console.error('Doctor calendar load error:', data);
                renderDoctorCalendar(month, year);
            }
        } catch (err) {
            console.error('Invalid JSON from get_availability:', text);
            renderDoctorCalendar(month, year);
        }
    })
    .catch(function(err) {
        console.error('Error loading doctor calendar data:', err);
        renderDoctorCalendar(month, year);
    });
}

function renderDoctorCalendar(month, year) {
    var container = document.getElementById('doctorCalendar');
    if (!container) return;

    var currentDate = new Date(year, month - 1, 1);
    var firstDay = new Date(year, month - 1, 1);
    var lastDay = new Date(year, month, 0);
    var prevLastDay = new Date(year, month - 1, 0);
    var firstDayIndex = firstDay.getDay();
    var nextDays = 7 - lastDay.getDay() - 1;
    if (nextDays < 0) nextDays = 6;

    var months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    var dayNames = ['SUNDAY','MONDAY','TUESDAY','WEDNESDAY','THURSDAY','FRIDAY','SATURDAY'];

    var html = '<div class="calendar-container">' +
        '<div class="calendar-header">' +
        '<button class="calendar-nav-btn" onclick="navigateDoctorCalendar(-1,' + month + ',' + year + ')"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg></button>' +
        '<h3 class="calendar-month-year">' + months[month - 1] + ' ' + year + '</h3>' +
        '<button class="calendar-nav-btn" onclick="navigateDoctorCalendar(1,' + month + ',' + year + ')"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg></button>' +
        '</div>' +
        '<div class="calendar-weekdays"><div>Sun</div><div>Mon</div><div>Tue</div><div>Wed</div><div>Thu</div><div>Fri</div><div>Sat</div></div>' +
        '<div class="calendar-days">';

    // Previous month days
    for (var x = firstDayIndex; x > 0; x--) {
        html += '<div class="calendar-day prev-month">' + (prevLastDay.getDate() - x + 1) + '</div>';
    }

    var today = new Date();
    today.setHours(0, 0, 0, 0);

    for (var day = 1; day <= lastDay.getDate(); day++) {
        var dateStr = year + '-' + String(month).padStart(2, '0') + '-' + String(day).padStart(2, '0');
        var dayOfWeek = new Date(year, month - 1, day).getDay();
        var dayName = dayNames[dayOfWeek];

        var dayClass = 'calendar-day';
        var dayData = '';
        var isUnavailable = false;
        var isPast = false;

        var currentDay = new Date(year, month - 1, day);
        if (currentDay < today) {
            dayClass += ' past-date';
            isPast = true;
        }

        if (day === today.getDate() && (month - 1) === today.getMonth() && year === today.getFullYear()) {
            dayClass += ' today';
        }

        if (doctorCalendarData.working_days.length > 0 && doctorCalendarData.working_days.indexOf(dayName) === -1) {
            dayClass += ' non-working-day';
        }

        var unavailable = null;
        for (var u = 0; u < doctorCalendarData.unavailable_dates.length; u++) {
            if (doctorCalendarData.unavailable_dates[u].date === dateStr && doctorCalendarData.unavailable_dates[u].availability_type === 'UNAVAILABLE') {
                unavailable = doctorCalendarData.unavailable_dates[u];
                break;
            }
        }
        if (unavailable) {
            isUnavailable = true;
            dayClass += ' unavailable-date';
            dayData = ' data-reason="' + escapeHtml(unavailable.reason || 'Unavailable') + '"';
        }

        var apptCount = doctorCalendarData.appointment_counts[dateStr] || 0;
        if (apptCount > 0) {
            dayClass += ' has-appointments';
            dayData += ' data-appointment-count="' + apptCount + '"';
        }

        // Doctors can click any future date to toggle availability or view details
        if (!isPast) {
            dayClass += ' clickable';
        }

        html += '<div class="' + dayClass + '" data-date="' + dateStr + '"' + dayData + ' onclick="handleDoctorDayClick(\'' + dateStr + '\',' + isPast + ',' + isUnavailable + ')">' +
            '<span class="day-number">' + day + '</span>' +
            (apptCount > 0 ? '<span class="appointment-indicator">' + apptCount + '</span>' : '') +
            (isUnavailable ? '<span class="unavailable-badge">X</span>' : '') +
            '</div>';
    }

    for (var j = 1; j <= nextDays; j++) {
        html += '<div class="calendar-day next-month">' + j + '</div>';
    }

    html += '</div></div>';
    container.innerHTML = html;

    // Store current month/year for navigation
    container.dataset.month = month;
    container.dataset.year = year;
}

function navigateDoctorCalendar(direction, currentMonth, currentYear) {
    var newMonth = currentMonth + direction;
    var newYear = currentYear;
    if (newMonth < 1) { newMonth = 12; newYear--; }
    if (newMonth > 12) { newMonth = 1; newYear++; }
    loadDoctorCalendarData(newMonth, newYear);
}

function handleDoctorDayClick(dateStr, isPast, isUnavailable) {
    if (isPast) return;

    // Show day appointments
    showDayAppointments(dateStr);

    // If it's already unavailable, offer to remove it
    if (isUnavailable) {
        if (confirm('This date is marked as unavailable. Do you want to make it available again?')) {
            toggleDoctorAvailability(dateStr, false);
        }
    } else {
        // Fill the unavailability form date
        var dateInput = document.getElementById('unavailableDate');
        if (dateInput) dateInput.value = dateStr;
    }
}

function showDayAppointments(dateStr) {
    var titleEl = document.getElementById('selectedDateTitle');
    var container = document.getElementById('calendarDayAppointments');
    if (!container) return;

    var dateObj = new Date(dateStr + 'T00:00:00');
    var options = { weekday: 'long', month: 'short', day: 'numeric', year: 'numeric' };
    if (titleEl) titleEl.textContent = dateObj.toLocaleDateString('en-US', options);

    var dayAppointments = (doctorCalendarData.appointments || []).filter(function(a) {
        return (a.date || a.appointment_date) === dateStr;
    });

    if (dayAppointments.length === 0) {
        container.innerHTML =
            '<div class="text-center py-6">' +
            '<i class="fas fa-calendar-check text-gray-300 text-3xl mb-3"></i>' +
            '<p class="text-gray-500 text-sm">No appointments on this day</p>' +
            '</div>';
        return;
    }

    var html = '';
    dayAppointments.forEach(function(appt) {
        var time = (appt.time || appt.appointment_time || '');
        time = time ? time.substring(0, 5) : '';
        var patientName = (appt.patient_first_name || '') + ' ' + (appt.patient_last_name || '');
        var statusClass = '';
        switch ((appt.status || '').toUpperCase()) {
            case 'CONFIRMED': statusClass = 'bg-green-100 text-green-800'; break;
            case 'SCHEDULED': statusClass = 'bg-orange-100 text-orange-800'; break;
            case 'COMPLETED': statusClass = 'bg-blue-100 text-blue-800'; break;
            default: statusClass = 'bg-gray-100 text-gray-800';
        }

        html +=
            '<div class="p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">' +
            '<div class="flex justify-between items-start">' +
            '<div><div class="font-medium text-gray-800 text-sm">' + escapeHtml(patientName) + '</div>' +
            '<div class="text-xs text-gray-500"><i class="far fa-clock mr-1"></i>' + escapeHtml(time) + '</div></div>' +
            '<span class="px-2 py-0.5 text-xs font-medium rounded-full ' + statusClass + '">' + escapeHtml(appt.status || '') + '</span>' +
            '</div>' +
            '<div class="text-xs text-gray-500 mt-1">' + escapeHtml(appt.type || 'Consultation') + '</div>' +
            '</div>';
    });
    container.innerHTML = html;
}

function toggleDoctorAvailability(dateStr, markUnavailable, reason) {
    var formData = new FormData();
    formData.append('csrf_token', window.CSRF_TOKEN || '');
    formData.append('date', dateStr);

    if (markUnavailable) {
        formData.append('action', 'set_unavailable');
        formData.append('reason', reason || '');
        formData.append('is_all_day', '1');
    } else {
        formData.append('action', 'remove_unavailable');
    }

    fetch('manage_availability.php', { method: 'POST', body: formData, credentials: 'same-origin' })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                showNotification(data.message, 'success');
                // Reload calendar data
                var container = document.getElementById('doctorCalendar');
                var m = container ? parseInt(container.dataset.month) : (new Date().getMonth() + 1);
                var y = container ? parseInt(container.dataset.year) : new Date().getFullYear();
                loadDoctorCalendarData(m, y);
            } else {
                showNotification(data.message || 'Error updating availability', 'error');
            }
        })
        .catch(function(err) {
            showNotification('Failed to update availability', 'error');
            console.error('Error:', err);
        });
}

// Handle Set Unavailable form submission
document.addEventListener('DOMContentLoaded', function() {
    var form = document.getElementById('setUnavailableForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            var dateInput = document.getElementById('unavailableDate');
            var reasonInput = document.getElementById('unavailableReason');
            if (!dateInput || !dateInput.value) {
                showNotification('Please select a date', 'error');
                return;
            }
            toggleDoctorAvailability(dateInput.value, true, reasonInput ? reasonInput.value : '');
            if (reasonInput) reasonInput.value = '';
        });
    }
});

// Initialize doctor calendar when schedule section is shown
var origShowSection = window.showSection;
window.showSection = function(sectionName) {
    origShowSection(sectionName);
    if (sectionName === 'schedule' && !doctorCalendar) {
        doctorCalendar = true;
        initDoctorCalendar();
    }
};

// Also init on load if schedule is the default section
document.addEventListener('DOMContentLoaded', function() {
    // Pre-initialize after a small delay so charts init first
    setTimeout(function() {
        if (document.getElementById('doctorCalendar')) {
            initDoctorCalendar();
        }
    }, 500);
});