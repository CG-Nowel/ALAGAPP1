// Admin Dashboard Calendar - FullCalendar v6 Integration
document.addEventListener('DOMContentLoaded', function() {
    var adminCalendarInitialized = false;

    // Hook into section navigation
    var originalShowSection = window.showSection || function() {};
    window.showSection = function(sectionName) {
        originalShowSection.call(this, sectionName);
        
        if (sectionName === 'calendar' && !adminCalendarInitialized) {
            adminCalendarInitialized = true;
            setTimeout(initAdminCalendar, 100);
        }
    };

    function initAdminCalendar() {
        var calendarEl = document.getElementById('adminCalendar');
        if (!calendarEl) return console.error('No calendar element');

        if (typeof FullCalendar === 'undefined') {
            console.error('FullCalendar not loaded');
            return;
        }

        var appointments = window.adminCalendarAppointments || [];

        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek'
            },
            height: 600,
            dayMaxEvents: true,
            events: appointments.map(function(appt) {
                return {
                    id: 'appt-' + appt.id,
                    title: appt.patient_first_name + ' ' + appt.patient_last_name,
                    start: appt.appointment_date + 'T' + appt.appointment_time,
                    backgroundColor: getEventColor(appt.status),
                    borderColor: getEventColor(appt.status),
                    extendedProps: {
                        type: appt.type,
                        doctor: appt.doctor_first_name + ' ' + appt.doctor_last_name,
                        status: appt.status
                    }
                };
            }),
            eventDidMount: function(info) {
                info.el.style.cursor = 'pointer';
                info.el.title = info.event.extendedProps.doctor + ' - ' + info.event.extendedProps.status;
            },
            dateClick: function(info) {
                filterAppointmentsByDate(info.dateStr);
            }
        });

        calendar.render();
        window.adminCalendarInstance = calendar;
        console.log('Admin calendar initialized -', appointments.length, 'events');
    }

    function getEventColor(status) {
        const colors = {
            'SCHEDULED': '#f59e0b',
            'CONFIRMED': '#10b981',
            'IN_PROGRESS': '#f59e0b', 
            'COMPLETED': '#3b82f6',
            'CANCELLED': '#ef4444',
            'NO_SHOW': '#6b7280'
        };
        return colors[status] || '#6b7280';
    }

    function filterAppointmentsByDate(dateStr) {
        var dayAppts = (window.adminCalendarAppointments || []).filter(a => a.appointment_date === dateStr);
        var titleEl = document.getElementById('adminCalendarDateTitle');
        var container = document.getElementById('adminCalendarDayAppointments');

        if (titleEl) {
            var date = new Date(dateStr);
            titleEl.textContent = date.toLocaleDateString('en-US', {
                weekday: 'long',
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
        }

        if (!container) return;

        if (dayAppts.length === 0) {
            container.innerHTML = `
                <div class="text-center py-12">
                    <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <p class="text-gray-500 text-lg font-medium">No appointments</p>
                    <p class="text-gray-400 text-sm mt-1">No appointments scheduled for this day</p>
                </div>
            `;
            return;
        }

        var html = '<div class="space-y-4 divide-y divide-gray-200">';
        dayAppts.forEach(function(appt) {
            var time = appt.appointment_time ? appt.appointment_time.substring(0,5) : 'TBD';
            var patient = (appt.patient_first_name || '') + ' ' + (appt.patient_last_name || '');
            var doctor = 'Dr. ' + (appt.doctor_first_name || '') + ' ' + (appt.doctor_last_name || '');
            var statusBadge = getStatusBadge(appt.status);
            
            html += `
                <div class="pt-4 pb-3 hover:bg-gray-50 rounded-lg transition-colors cursor-pointer" onclick="viewAppointment(${appt.id})">
                    <div class="flex justify-between items-start mb-2">
                        <div>
                            <div class="font-semibold text-sm text-gray-900">${escapeHtml(patient)}</div>
                            <div class="text-xs text-gray-600">${doctor}</div>
                        </div>
                        ${statusBadge}
                    </div>
                    <div class="flex items-center text-xs text-gray-500">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        ${time}
                    </div>
                </div>
            `;
        });
        html += '</div>';
        container.innerHTML = html;
    }

    function getStatusBadge(status) {
        const badges = {
            'SCHEDULED': '<span class="px-2 py-1 text-xs font-medium bg-orange-100 text-orange-800 rounded-full">Scheduled</span>',
            'CONFIRMED': '<span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">Confirmed</span>',
            'IN_PROGRESS': '<span class="px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full">In Progress</span>',
            'COMPLETED': '<span class="px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded-full">Completed</span>',
            'CANCELLED': '<span class="px-2 py-1 text-xs font-medium bg-red-100 text-red-800 rounded-full">Cancelled</span>'
        };
        return badges[status] || '<span class="px-2 py-1 text-xs font-medium bg-gray-100 text-gray-800 rounded-full">Unknown</span>';
    }

    window.viewAppointment = function(id) {
        (window.showToast || window.alert)('Appointment ID: ' + id + ' - Full details view coming soon!', 'info');
    };

    window.escapeHtml = function(text) {
        var map = {
            '&': '&amp;',
            '<': '<',
            '>': '>',
            '"': '"',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    };

    // Auto-init if already on calendar tab
    if (window.location.hash === '#calendar') {
        setTimeout(initAdminCalendar, 500);
    }
});

