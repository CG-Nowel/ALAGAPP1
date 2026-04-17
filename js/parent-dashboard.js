/* ============================================
   AlagApp Clinic - Parent Dashboard Scripts
   Handles appointment calendar, modals, medical records
   ============================================ */

// Global state
var parentAppointmentCalendar = null;
var currentDoctorId = null;
var appointmentsCache = [];

// ---- Initialization ----
document.addEventListener('DOMContentLoaded', function() {
    var doctorSelect = document.getElementById('doctorSelect');
    var modalDoctorSelect = document.getElementById('modalAppointmentDoctor');

    if (doctorSelect) {
        doctorSelect.addEventListener('change', function() {
            currentDoctorId = this.value;
            if (modalDoctorSelect) modalDoctorSelect.value = currentDoctorId;
            if (currentDoctorId) {
                loadDoctorAvailability(currentDoctorId);
            } else if (parentAppointmentCalendar) {
                parentAppointmentCalendar.setUnavailableDates([]);
                parentAppointmentCalendar.setWorkingDays([]);
                parentAppointmentCalendar.setAppointments([]);
            }
        });
    }

    var modalDoctor = document.getElementById('modalAppointmentDoctor');
    if (modalDoctor) {
        modalDoctor.addEventListener('change', function() {
            var id = this.value;
            if (id) {
                var mainSelect = document.getElementById('doctorSelect');
                if (mainSelect) mainSelect.value = id;
                currentDoctorId = id;
                loadDoctorAvailability(id);
            }
        });
    }

    loadParentAppointments();

    // Initialize calendar immediately so dates are clickable without selecting a doctor
    if (!parentAppointmentCalendar && document.getElementById('appointmentCalendar')) {
        parentAppointmentCalendar = new AppointmentCalendar('appointmentCalendar', {
            onDateSelect: handleDateSelect,
            unavailableDates: [],
            workingDays: ['MONDAY','TUESDAY','WEDNESDAY','THURSDAY','FRIDAY','SATURDAY'],
            appointments: [],
            userType: 'parent',
            doctorId: null,
            onMonthChange: function(m, y) {
                if (currentDoctorId) {
                    fetch('get_availability.php?doctor_id=' + encodeURIComponent(currentDoctorId) + '&month=' + m + '&year=' + y, { credentials: 'same-origin' })
                        .then(function(res) { return res.text(); })
                        .then(function(t) {
                            try {
                                var r = JSON.parse(t);
                                if (r.success) {
                                    appointmentsCache = r.appointments || [];
                                    parentAppointmentCalendar.setUnavailableDates(r.unavailable_dates || []);
                                    parentAppointmentCalendar.setWorkingDays(r.working_days || []);
                                    parentAppointmentCalendar.setAppointments(appointmentsCache);
                                }
                            } catch (err) {
                                console.error('Invalid JSON on month change:', t);
                            }
                        })
                        .catch(function(err) { console.error('Error loading availability:', err); });
                }
            }
        });
    }

    // Initialize first section as active
    var initialNav = document.querySelector('a[href="#dashboard"]');
    if (initialNav) {
        initialNav.classList.add('bg-white/20');
    }
    showSection('dashboard');
});

// ---- Doctor Availability ----
function loadDoctorAvailability(doctorId) {
    if (!doctorId) return;

    var month = parentAppointmentCalendar
        ? parentAppointmentCalendar.currentDate.getMonth() + 1
        : new Date().getMonth() + 1;
    var year = parentAppointmentCalendar
        ? parentAppointmentCalendar.currentDate.getFullYear()
        : new Date().getFullYear();

    fetch('get_availability.php?doctor_id=' + encodeURIComponent(doctorId) + '&month=' + month + '&year=' + year, {
        method: 'GET',
        credentials: 'same-origin'
    })
    .then(function(response) { return response.text(); })
    .then(function(text) {
        try {
            var data = JSON.parse(text);
            if (data.success) {
                appointmentsCache = data.appointments || [];
                if (parentAppointmentCalendar) {
                    parentAppointmentCalendar.options.doctorId = doctorId;
                    parentAppointmentCalendar.setUnavailableDates(data.unavailable_dates || []);
                    parentAppointmentCalendar.setWorkingDays(data.working_days || []);
                    parentAppointmentCalendar.setAppointments(appointmentsCache);
                } else {
                    parentAppointmentCalendar = new AppointmentCalendar('appointmentCalendar', {
                        onDateSelect: handleDateSelect,
                        unavailableDates: data.unavailable_dates || [],
                        workingDays: data.working_days || [],
                        appointments: appointmentsCache,
                        userType: 'parent',
                        doctorId: doctorId,
                        onMonthChange: function(m, y) {
                            fetch('get_availability.php?doctor_id=' + encodeURIComponent(doctorId) + '&month=' + m + '&year=' + y, { credentials: 'same-origin' })
                                .then(function(res) { return res.text(); })
                                .then(function(t) {
                                    try {
                                        var r = JSON.parse(t);
                                        if (r.success) {
                                            appointmentsCache = r.appointments || [];
                                            parentAppointmentCalendar.setUnavailableDates(r.unavailable_dates || []);
                                            parentAppointmentCalendar.setWorkingDays(r.working_days || []);
                                            parentAppointmentCalendar.setAppointments(appointmentsCache);
                                        } else {
                                            console.error('Availability error:', r);
                                        }
                                    } catch (err) {
                                        console.error('Invalid JSON on month change:', t);
                                    }
                                })
                                .catch(function(err) { console.error('Error loading availability:', err); });
                        }
                    });
                }
            } else {
                console.error('get_availability returned success=false:', data);
            }
        } catch (err) {
            console.error('Invalid JSON from get_availability.php:', text);
        }
    })
    .catch(function(error) { console.error('Error loading availability:', error); });
}

// ---- Appointments ----
function loadParentAppointments() {
    var month = new Date().getMonth() + 1;
    var year = new Date().getFullYear();

    fetch('get_appointments.php?month=' + month + '&year=' + year)
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                displayAppointmentsList(data.appointments || []);
                if (parentAppointmentCalendar && currentDoctorId) {
                    parentAppointmentCalendar.setAppointments(
                        appointmentsCache.length ? appointmentsCache : (data.appointments || [])
                    );
                }
            }
        })
        .catch(function(error) { console.error('Error loading appointments:', error); });
}

function displayAppointmentsList(appointments) {
    var listContainer = document.getElementById('appointmentsList');
    if (!listContainer) return;

    if (!appointments || appointments.length === 0) {
        listContainer.innerHTML =
            '<div class="no-appointments">' +
            '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:48px;height:48px;margin:0 auto;display:block;color:#9ca3af;">' +
            '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>' +
            '</svg>' +
            '<p style="text-align:center;color:#6b7280;margin-top:8px;">No appointments scheduled</p>' +
            '</div>';
        return;
    }

    var html = '';
    appointments.forEach(function(appointment) {
        var date = new Date(appointment.appointment_date);
        var formattedDate = date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        var time = appointment.appointment_time ? appointment.appointment_time.substring(0, 5) : '';
        var patientName = (appointment.patient_first_name || appointment.child_first_name || '') + ' ' +
                         (appointment.patient_last_name || appointment.child_last_name || '');

        html +=
            '<div class="appointment-item">' +
            '<div class="appointment-item-date">' + escapeHtml(formattedDate) + '</div>' +
            '<div class="appointment-item-time">' + escapeHtml(time) + '</div>' +
            '<div class="appointment-item-patient">' + escapeHtml(patientName) + '</div>' +
            '<div class="mt-2">' +
            '<span class="appointment-item-type">' + escapeHtml(appointment.type || '') + '</span>' +
            '<span class="appointment-item-status ' + (appointment.status ? appointment.status.toLowerCase() : '') + '">' + escapeHtml(appointment.status || '') + '</span>' +
            '</div>' +
            '<div class="text-sm text-gray-600 mt-2">Dr. ' + escapeHtml(appointment.doctor_first_name || '') + ' ' + escapeHtml(appointment.doctor_last_name || '') + '</div>' +
            '</div>';
    });
    listContainer.innerHTML = html;
}

function handleDateSelect(date) {
    if (!currentDoctorId) {
        (window.showToast || window.alert)('Please select a doctor first', 'warning');
        return;
    }

    var modal = document.getElementById('bookAppointmentModal');
    if (!modal) return;
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';

    var dateInput = document.getElementById('modalAppointmentDate');
    var doctorSelect = document.getElementById('modalAppointmentDoctor');
    var childSelect = document.getElementById('modalAppointmentChild');

    if (dateInput) dateInput.value = date;
    if (doctorSelect) doctorSelect.value = currentDoctorId;

    if (childSelect && !childSelect.value && childSelect.options.length > 1) {
        childSelect.selectedIndex = 1;
    }

    updateTimeOptionsForDate(date, currentDoctorId);
}

function updateTimeOptionsForDate(date, doctorId) {
    var timeSelect = document.getElementById('modalAppointmentTime');
    if (!timeSelect) return;

    // Clear existing options and show loading
    timeSelect.innerHTML = '<option value="">Loading available times...</option>';
    timeSelect.disabled = true;

    // Collect booked times from cache
    var bookedTimes = new Set();
    var sourceAppointments = Array.isArray(appointmentsCache) && appointmentsCache.length ? appointmentsCache : [];
    var fallbackAppointments = (parentAppointmentCalendar && parentAppointmentCalendar.options && parentAppointmentCalendar.options.appointments)
        ? parentAppointmentCalendar.options.appointments : [];
    var allAppointments = sourceAppointments.length ? sourceAppointments : fallbackAppointments;

    allAppointments.forEach(function(a) {
        var aDate = a.appointment_date || a.date || '';
        var aTime = a.appointment_time || a.time || '';
        var aDoctor = a.doctor_id ? String(a.doctor_id) : (a.doctorId ? String(a.doctorId) : '');
        if (!aDate || !aTime) return;
        if (String(aDate) === String(date) && String(aDoctor) === String(doctorId)) {
            bookedTimes.add(aTime.substring(0, 5));
        }
    });

    // Fetch real available slots from the API
    fetch('available-slots.php?doctor_id=' + encodeURIComponent(doctorId) + '&date=' + encodeURIComponent(date))
        .then(function(response) { return response.json(); })
        .then(function(data) {
            timeSelect.innerHTML = '<option value="">-- Select Time --</option>';
            timeSelect.disabled = false;

            if (data.success && data.data && data.data.length > 0) {
                data.data.forEach(function(slot) {
                    var opt = document.createElement('option');
                    opt.value = slot.time;
                    opt.textContent = slot.formatted;
                    var slotHHMM = slot.time.substring(0, 5);
                    var isBooked = false;
                    bookedTimes.forEach(function(bt) {
                        if (bt.substring(0, 5) === slotHHMM) isBooked = true;
                    });
                    if (isBooked) {
                        opt.disabled = true;
                        opt.textContent += ' (Booked)';
                    }
                    timeSelect.appendChild(opt);
                });

                var available = Array.from(timeSelect.options).some(function(o) { return o.value && !o.disabled; });
                if (!available) {
                    setTimeout(function() {
                        (window.showToast || window.alert)('No available times on this date for the selected doctor. Please choose another date or doctor.', 'warning');
                    }, 10);
                }
            } else {
                timeSelect.innerHTML = '<option value="">No available times</option>';
                setTimeout(function() {
                    (window.showToast || window.alert)('No available times on this date for the selected doctor. Please choose another date or doctor.', 'warning');
                }, 10);
            }
        })
        .catch(function() {
            // Fallback to hardcoded slots if API fails
            timeSelect.innerHTML = '<option value="">-- Select Time --</option>';
            timeSelect.disabled = false;
            var fallbackSlots = [
                {time: '09:00:00', label: '09:00 AM'}, {time: '09:30:00', label: '09:30 AM'},
                {time: '10:00:00', label: '10:00 AM'}, {time: '10:30:00', label: '10:30 AM'},
                {time: '11:00:00', label: '11:00 AM'}, {time: '11:30:00', label: '11:30 AM'},
                {time: '13:00:00', label: '01:00 PM'}, {time: '13:30:00', label: '01:30 PM'},
                {time: '14:00:00', label: '02:00 PM'}, {time: '14:30:00', label: '02:30 PM'},
                {time: '15:00:00', label: '03:00 PM'}, {time: '15:30:00', label: '03:30 PM'},
                {time: '16:00:00', label: '04:00 PM'}, {time: '16:30:00', label: '04:30 PM'}
            ];
            fallbackSlots.forEach(function(slot) {
                var opt = document.createElement('option');
                opt.value = slot.time;
                opt.textContent = slot.label;
                var slotHHMM = slot.time.substring(0, 5);
                var isBooked = false;
                bookedTimes.forEach(function(bt) {
                    if (bt.substring(0, 5) === slotHHMM) isBooked = true;
                });
                opt.disabled = isBooked;
                timeSelect.appendChild(opt);
            });
        });
}

// ---- Mobile Menu ----
(function() {
    var mobileMenuButton = document.getElementById('mobileMenuButton');
    var closeSidebarBtn = document.getElementById('closeSidebar');
    var sidebar = document.getElementById('sidebar');
    var mobileOverlay = document.getElementById('mobileOverlay');

    function openMobileMenu() {
        if (sidebar) sidebar.classList.add('open');
        if (mobileOverlay) mobileOverlay.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeMobileMenu() {
        if (sidebar) sidebar.classList.remove('open');
        if (mobileOverlay) mobileOverlay.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }

    if (mobileMenuButton) mobileMenuButton.addEventListener('click', openMobileMenu);
    if (closeSidebarBtn) closeSidebarBtn.addEventListener('click', closeMobileMenu);
    if (mobileOverlay) mobileOverlay.addEventListener('click', closeMobileMenu);

    document.querySelectorAll('nav a').forEach(function(link) {
        link.addEventListener('click', function() {
            if (window.innerWidth < 768) closeMobileMenu();
        });
    });

    // Expose globally for showSection
    window._closeMobileMenu = closeMobileMenu;
})();

// ---- Section Management ----
function showSection(sectionName) {
    document.querySelectorAll('.section-content').forEach(function(section) {
        section.classList.add('hidden');
    });
    var targetSection = document.getElementById(sectionName + '-section');
    if (targetSection) {
        targetSection.classList.remove('hidden');
        var mainContent = document.querySelector('.main-content');
        if (mainContent) mainContent.scrollTop = 0;
    }
    document.querySelectorAll('nav a').forEach(function(link) {
        link.classList.remove('bg-white/20');
    });
    var activeLink = document.querySelector('a[href="#' + sectionName + '"]');
    if (activeLink) {
        activeLink.classList.add('bg-white/20');
    }
    if (window.innerWidth < 768 && window._closeMobileMenu) {
        window._closeMobileMenu();
    }
}

// ---- Modal Helpers ----
function openAddChildModal() {
    var el = document.getElementById('addChildModal');
    if (el) el.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeAddChildModal() {
    var el = document.getElementById('addChildModal');
    if (el) el.classList.add('hidden');
    document.body.style.overflow = 'auto';
}

function openBookAppointmentModal() {
    var el = document.getElementById('bookAppointmentModal');
    if (el) el.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeBookAppointmentModal() {
    var modal = document.getElementById('bookAppointmentModal');
    if (modal) modal.classList.add('hidden');
    document.body.style.overflow = 'auto';

    var form = document.getElementById('bookAppointmentForm');
    if (form) form.reset();

    var timeSelect = document.getElementById('modalAppointmentTime');
    if (timeSelect) {
        Array.from(timeSelect.options).forEach(function(opt) { opt.disabled = false; });
    }
}

function openVaccinationInfoModal() {
    var el = document.getElementById('vaccinationInfoModal');
    if (el) el.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeVaccinationInfoModal() {
    var el = document.getElementById('vaccinationInfoModal');
    if (el) el.classList.add('hidden');
    document.body.style.overflow = 'auto';
}

function viewChildVaccinations(childId) {
    showSection('vaccinations');
}

// ---- Upload Modal ----
function openUploadModal(patientId, patientName) {
    var patientIdInput = document.getElementById('upload_patient_id');
    var title = document.getElementById('uploadModalTitle');
    var info = document.getElementById('uploadModalChildInfo');
    var modal = document.getElementById('uploadFileModal');

    if (patientIdInput) patientIdInput.value = patientId;
    if (title) title.textContent = 'Upload File';
    if (info) info.textContent = 'For ' + patientName;
    if (modal) modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeUploadModal() {
    var modal = document.getElementById('uploadFileModal');
    if (modal) modal.classList.add('hidden');
    document.body.style.overflow = 'auto';
}

// ---- Medical Records ----
function showMedicalRecordsTab(tabName) {
    document.querySelectorAll('.medical-records-tab').forEach(function(tab) {
        tab.classList.remove('active', 'border-primary', 'text-primary');
        tab.classList.add('text-gray-500');
    });
    var activeTab = document.querySelector('.medical-records-tab[data-tab="' + tabName + '"]');
    if (activeTab) {
        activeTab.classList.add('active', 'border-primary', 'text-primary');
        activeTab.classList.remove('text-gray-500');
    }
    document.querySelectorAll('.medical-records-content').forEach(function(content) {
        content.classList.add('hidden');
    });
    var activeContent = document.getElementById(tabName + '-tab');
    if (activeContent) {
        activeContent.classList.remove('hidden');
    }
    loadMedicalRecordsData(tabName);
}

function loadMedicalRecordsData(tabName) {
    var childId = window.currentMedicalRecordsChildId;
    if (!childId) return;

    var contentDiv = document.getElementById(tabName + '-content');
    if (!contentDiv) return;

    contentDiv.innerHTML = '<div class="text-center py-8"><div class="spinner"></div><p class="mt-2 text-gray-600">Loading...</p></div>';

    // Use the URL set by the PHP page, falling back to current page
    var url = (window.MEDICAL_RECORDS_URL || window.location.pathname) +
              '?action=get_medical_records&child_id=' + encodeURIComponent(childId);

    fetch(url)
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (!data.success) {
                contentDiv.innerHTML = '<p class="text-center text-gray-500 py-8">Unable to load records.</p>';
                return;
            }

            var html = '';
            if (tabName === 'consultations') {
                var records = data.consultations || [];
                if (records.length === 0) {
                    html = '<p class="text-center text-gray-500 py-8">No consultation records found for this child.</p>';
                } else {
                    records.forEach(function(r) {
                        var date = r.created_at || r.record_date || '';
                        html +=
                            '<div class="bg-blue-50 p-4 rounded-lg mb-4">' +
                            '<div class="flex justify-between items-start">' +
                            '<div><h4 class="font-semibold text-blue-800">' + escapeHtml(r.diagnosis || r.record_type || 'Consultation') + '</h4>' +
                            '<p class="text-sm text-blue-700">Dr. ' + escapeHtml(r.doctor_first || '') + ' ' + escapeHtml(r.doctor_last || '') + '</p></div>' +
                            '<span class="text-xs text-blue-600">' + escapeHtml(date) + '</span></div>' +
                            '<div class="mt-2 text-sm text-blue-900">' +
                            '<p><strong>Notes:</strong> ' + escapeHtml(r.notes || r.treatment_plan || r.symptoms || '') + '</p>' +
                            (r.treatment_plan ? '<p><strong>Treatment:</strong> ' + escapeHtml(r.treatment_plan) + '</p>' : '') +
                            '</div></div>';
                    });
                }
            } else if (tabName === 'prescriptions') {
                var prescriptions = data.prescriptions || [];
                if (prescriptions.length === 0) {
                    html = '<p class="text-center text-gray-500 py-8">No prescriptions found for this child.</p>';
                } else {
                    prescriptions.forEach(function(p) {
                        html +=
                            '<div class="bg-purple-50 p-4 rounded-lg mb-4">' +
                            '<div class="flex justify-between items-start">' +
                            '<div><h4 class="font-semibold text-purple-800">' + escapeHtml(p.medication_name || 'Prescription') + '</h4>' +
                            '<p class="text-sm text-purple-700">Dr. ' + escapeHtml(p.doctor_first || '') + ' ' + escapeHtml(p.doctor_last || '') + '</p></div>' +
                            '<span class="text-xs text-purple-600">' + escapeHtml(p.prescription_date || p.created_at || '') + '</span></div>' +
                            '<div class="mt-2 text-sm text-purple-900">' +
                            '<p><strong>Dosage:</strong> ' + escapeHtml(p.dosage || '') + '</p>' +
                            '<p><strong>Frequency:</strong> ' + escapeHtml(p.frequency || '') + '</p>' +
                            '<p><strong>Duration:</strong> ' + escapeHtml(p.duration || '') + '</p>' +
                            (p.instructions ? '<p><strong>Instructions:</strong> ' + escapeHtml(p.instructions) + '</p>' : '') +
                            '</div></div>';
                    });
                }
            } else if (tabName === 'vaccinations') {
                var vaccinations = data.vaccinations || [];
                if (vaccinations.length === 0) {
                    html = '<p class="text-center text-gray-500 py-8">No vaccination records found for this child.</p>';
                } else {
                    vaccinations.forEach(function(v) {
                        html +=
                            '<div class="bg-green-50 p-4 rounded-lg mb-4">' +
                            '<div class="flex justify-between items-start">' +
                            '<div><h4 class="font-semibold text-green-800">' + escapeHtml(v.vaccine_name || 'Vaccine') + '</h4>' +
                            '<p class="text-sm text-green-700">Dose ' + escapeHtml(v.dose_number || '') + ' of ' + escapeHtml(v.total_doses || '') + '</p></div>' +
                            '<span class="text-xs text-green-600">' + escapeHtml(v.administration_date || v.created_at || '') + '</span></div>' +
                            '<div class="mt-2 text-sm text-green-900">' +
                            (v.next_due_date ? '<p><strong>Next Due:</strong> ' + escapeHtml(v.next_due_date) + '</p>' : '') +
                            (v.lot_number ? '<p><strong>Lot Number:</strong> ' + escapeHtml(v.lot_number) + '</p>' : '') +
                            (v.manufacturer ? '<p><strong>Manufacturer:</strong> ' + escapeHtml(v.manufacturer) + '</p>' : '') +
                            (v.site ? '<p><strong>Site:</strong> ' + escapeHtml(v.site) + '</p>' : '') +
                            '</div></div>';
                    });
                }
            }

            contentDiv.innerHTML = html;
        })
        .catch(function(err) {
            console.error('Error loading medical records:', err);
            contentDiv.innerHTML = '<p class="text-center text-gray-500 py-8">Failed to load records.</p>';
        });
}

function openMedicalRecords(childId, childName) {
    var title = document.getElementById('medicalRecordsTitle');
    var info = document.getElementById('medicalRecordsChildInfo');

    if (title) title.textContent = 'Medical Records';
    if (info) info.textContent = 'For ' + childName;

    window.currentMedicalRecordsChildId = childId;
    showMedicalRecordsTab('consultations');

    var modal = document.getElementById('medicalRecordsModal');
    if (modal) modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeMedicalRecordsModal() {
    var modal = document.getElementById('medicalRecordsModal');
    if (modal) modal.classList.add('hidden');
    document.body.style.overflow = 'auto';
    window.currentMedicalRecordsChildId = null;
}

// ---- HTML Escape Utility ----
function escapeHtml(unsafe) {
    if (unsafe === null || unsafe === undefined) return '';
    return String(unsafe)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

// ---- Close Modals on Backdrop Click ----
document.querySelectorAll('.modal-backdrop').forEach(function(backdrop) {
    backdrop.addEventListener('click', function(e) {
        if (e.target !== this) return;
        var modalEl = this;
        if (modalEl.id === 'addChildModal') closeAddChildModal();
        else if (modalEl.id === 'bookAppointmentModal') closeBookAppointmentModal();
        else if (modalEl.id === 'vaccinationInfoModal') closeVaccinationInfoModal();
        else if (modalEl.id === 'medicalRecordsModal') closeMedicalRecordsModal();
        else if (modalEl.id === 'uploadFileModal') closeUploadModal();
    });
});

// ---- Close Modals on Escape Key ----
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeAddChildModal();
        closeBookAppointmentModal();
        closeVaccinationInfoModal();
        closeMedicalRecordsModal();
        closeUploadModal();
    }
});

// ---- AppointmentCalendar Class ----
var AppointmentCalendar = (function() {
    function AppointmentCalendar(containerId, options) {
        this.container = document.getElementById(containerId);
        this.currentDate = new Date();
        this.selectedDate = null;
        this.options = {
            onDateSelect: options.onDateSelect || null,
            unavailableDates: options.unavailableDates || [],
            appointments: options.appointments || [],
            workingDays: options.workingDays || ['MONDAY','TUESDAY','WEDNESDAY','THURSDAY','FRIDAY'],
            userType: options.userType || 'parent',
            doctorId: options.doctorId || null,
            onMonthChange: options.onMonthChange || null
        };
        this.init();
    }

    AppointmentCalendar.prototype.init = function() {
        this.render();
        this.attachEventListeners();
    };

    AppointmentCalendar.prototype.render = function() {
        if (!this.container) return;

        var month = this.currentDate.getMonth();
        var year = this.currentDate.getFullYear();
        var firstDay = new Date(year, month, 1);
        var lastDay = new Date(year, month + 1, 0);
        var prevLastDay = new Date(year, month, 0);
        var firstDayIndex = firstDay.getDay();
        var lastDayIndex = lastDay.getDay();
        var nextDays = 7 - lastDayIndex - 1;

        var months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
        var dayNames = ['SUNDAY','MONDAY','TUESDAY','WEDNESDAY','THURSDAY','FRIDAY','SATURDAY'];

        var html = '<div class="calendar-container">' +
            '<div class="calendar-header">' +
            '<button class="calendar-nav-btn" id="prevMonth"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg></button>' +
            '<h3 class="calendar-month-year">' + months[month] + ' ' + year + '</h3>' +
            '<button class="calendar-nav-btn" id="nextMonth"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg></button>' +
            '</div>' +
            '<div class="calendar-weekdays"><div>Sun</div><div>Mon</div><div>Tue</div><div>Wed</div><div>Thu</div><div>Fri</div><div>Sat</div></div>' +
            '<div class="calendar-days">';

        // Previous month days
        for (var x = firstDayIndex; x > 0; x--) {
            html += '<div class="calendar-day prev-month">' + (prevLastDay.getDate() - x + 1) + '</div>';
        }

        // Current month days
        var today = new Date();
        today.setHours(0, 0, 0, 0);
        var self = this;

        for (var day = 1; day <= lastDay.getDate(); day++) {
            var dateStr = year + '-' + String(month + 1).padStart(2, '0') + '-' + String(day).padStart(2, '0');
            var dayOfWeek = new Date(year, month, day).getDay();
            var dayName = dayNames[dayOfWeek];

            var dayClass = 'calendar-day';
            var dayData = '';
            var isUnavailable = false;
            var isPast = false;
            var isWorkingDay = true;

            var currentDay = new Date(year, month, day);
            if (currentDay < today) {
                dayClass += ' past-date';
                isPast = true;
            }

            if (day === today.getDate() && month === today.getMonth() && year === today.getFullYear()) {
                dayClass += ' today';
            }

            if (self.options.workingDays && !self.options.workingDays.includes(dayName)) {
                isWorkingDay = false;
                dayClass += ' non-working-day';
            }

            var unavailable = (self.options.unavailableDates || []).find(function(d) { return d.date === dateStr; });
            if (unavailable) {
                isUnavailable = true;
                dayClass += ' unavailable-date';
                dayData = ' data-reason="' + (unavailable.reason || 'Unavailable') + '"';
            }

            var dayAppointments = (self.options.appointments || []).filter(function(a) {
                return String(a.appointment_date || a.date || '') === String(dateStr);
            });
            if (dayAppointments.length > 0) {
                dayClass += ' has-appointments';
                dayData += ' data-appointment-count="' + dayAppointments.length + '"';
            }

            var clickable = !isPast && isWorkingDay && !isUnavailable && self.options.onDateSelect;
            if (clickable) {
                dayClass += ' clickable';
            }

            html += '<div class="' + dayClass + '" data-date="' + dateStr + '"' + dayData + '>' +
                '<span class="day-number">' + day + '</span>' +
                (dayAppointments.length > 0 ? '<span class="appointment-indicator">' + dayAppointments.length + '</span>' : '') +
                (isUnavailable ? '<span class="unavailable-badge">X</span>' : '') +
                '</div>';
        }

        // Next month days
        for (var j = 1; j <= nextDays; j++) {
            html += '<div class="calendar-day next-month">' + j + '</div>';
        }

        html += '</div></div>';
        this.container.innerHTML = html;
    };

    AppointmentCalendar.prototype.attachEventListeners = function() {
        var self = this;

        var prevBtn = this.container.querySelector('#prevMonth');
        var nextBtn = this.container.querySelector('#nextMonth');

        if (prevBtn) prevBtn.addEventListener('click', function() { self.previousMonth(); });
        if (nextBtn) nextBtn.addEventListener('click', function() { self.nextMonth(); });

        this.container.querySelectorAll('.calendar-day.clickable').forEach(function(day) {
            day.addEventListener('click', function(e) {
                var date = e.currentTarget.dataset.date;
                if (date && self.options.onDateSelect) {
                    self.container.querySelectorAll('.calendar-day.selected').forEach(function(d) {
                        d.classList.remove('selected');
                    });
                    e.currentTarget.classList.add('selected');
                    self.selectedDate = date;
                    self.options.onDateSelect(date);
                }
            });
        });

        this.container.querySelectorAll('.calendar-day.unavailable-date').forEach(function(day) {
            day.addEventListener('mouseenter', function(e) {
                var reason = e.currentTarget.dataset.reason;
                if (reason) self.showTooltip(e.currentTarget, reason);
            });
            day.addEventListener('mouseleave', function() {
                self.hideTooltip();
            });
        });
    };

    AppointmentCalendar.prototype.showTooltip = function(element, text) {
        var existing = document.getElementById('calendar-tooltip');
        if (existing) existing.remove();

        var tooltip = document.createElement('div');
        tooltip.className = 'calendar-tooltip';
        tooltip.textContent = text;
        tooltip.id = 'calendar-tooltip';

        var rect = element.getBoundingClientRect();
        tooltip.style.top = (rect.top - 40) + 'px';
        tooltip.style.left = (rect.left + rect.width / 2) + 'px';

        document.body.appendChild(tooltip);
    };

    AppointmentCalendar.prototype.hideTooltip = function() {
        var tooltip = document.getElementById('calendar-tooltip');
        if (tooltip) tooltip.remove();
    };

    AppointmentCalendar.prototype.previousMonth = function() {
        this.currentDate.setMonth(this.currentDate.getMonth() - 1);
        this.updateCalendar();
    };

    AppointmentCalendar.prototype.nextMonth = function() {
        this.currentDate.setMonth(this.currentDate.getMonth() + 1);
        this.updateCalendar();
    };

    AppointmentCalendar.prototype.updateCalendar = function() {
        if (this.options.onMonthChange) {
            this.options.onMonthChange(this.currentDate.getMonth() + 1, this.currentDate.getFullYear());
        }
        this.render();
        this.attachEventListeners();
    };

    AppointmentCalendar.prototype.setUnavailableDates = function(dates) {
        this.options.unavailableDates = dates;
        this.render();
        this.attachEventListeners();
    };

    AppointmentCalendar.prototype.setAppointments = function(appointments) {
        this.options.appointments = appointments;
        this.render();
        this.attachEventListeners();
    };

    AppointmentCalendar.prototype.setWorkingDays = function(days) {
        this.options.workingDays = days;
        this.render();
        this.attachEventListeners();
    };

    return AppointmentCalendar;
})();