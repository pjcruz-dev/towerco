(function () {
    let masterData = [];
    let activeFilterTab = 'ALL';
    let charts = {};

    function waitForFrameworkAndDOM(callback) {
        function checkApp() {
            if (window.AppFramework && !window.AppFramework._loading && window.jQuery) {
                callback();
            } else {
                setTimeout(checkApp, 100);
            }
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', checkApp);
        } else {
            checkApp();
        }
    }

    async function initDashboard() {
        const $ = window.jQuery;
        $('.print-date').text(new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }));
        bindEvents();
        await loadTickets();
    }

    function bindEvents() {
        const $ = window.jQuery;
        $('#btn-refresh').off('click').on('click', async function () {
            const icon = $(this).find('i');
            icon.addClass('fa-spin');
            await loadTickets();
            icon.removeClass('fa-spin');
        });
        $('#filter-status, #filter-priority, #filter-category, #filter-sla, #filter-date-from, #filter-date-to').off('change').on('change', function () {
            renderDashboard();
        });
        $('#search-input').off('input').on('input', function () {
            renderDashboard();
        });
        $('#dash-tabs button').off('click').on('click', function () {
            $('#dash-tabs button').removeClass('active');
            $(this).addClass('active');
            activeFilterTab = $(this).data('tab-type');
            renderDashboard();
        });
        $('.kpi-card').off('click').on('click', function () {
            const status = $(this).data('status-filter');
            $('.kpi-card').removeClass('active-kpi');
            $(this).addClass('active-kpi');
            if (status === 'UNASSIGNED' || status === 'OVERDUE') {
                $('#filter-status').val('ALL');
                activeFilterTab = status;
                $('#dash-tabs button').removeClass('active');
                $('#dash-tabs button[data-tab-type="' + status + '"]').addClass('active');
            } else {
                $('#filter-status').val(status || 'ALL');
                activeFilterTab = 'ALL';
                $('#dash-tabs button').removeClass('active');
                $('#dash-tabs button[data-tab-type="ALL"]').addClass('active');
            }
            renderDashboard();
        });
        $('#btn-export-csv').off('click').on('click', exportCurrentQueueToCSV);
    }

    function ticketNo(value) {
        const num = parseInt(value, 10) || 0;
        return 'TKT-' + String(num).padStart(5, '0');
    }

    function labelStatus(status) {
        if (status === 'in_progress') return 'In progress';
        if (status === 'open') return 'Open';
        if (status === 'resolved') return 'Resolved';
        if (status === 'closed') return 'Closed';
        return status || 'Open';
    }

    function statusBadge(status) {
        if (status === 'in_progress') return 'badge-progress';
        if (status === 'resolved') return 'badge-resolved';
        if (status === 'closed') return 'badge-closed';
        return 'badge-open';
    }

    function priorityBadge(priority) {
        if (priority === 'urgent') return 'badge-priority-urgent';
        if (priority === 'high') return 'badge-priority-high';
        if (priority === 'low') return 'badge-priority-low';
        return 'badge-priority-normal';
    }

    function isActive(status) {
        return status !== 'resolved' && status !== 'closed';
    }

    async function loadTickets() {
        const $ = window.jQuery;
        $('#ticketTableBody').html('<tr><td colspan="8" class="text-center py-5 text-muted"><i class="fas fa-spinner fa-spin me-2 text-primary"></i> Fetching tickets...</td></tr>');

        const sql = `
            SELECT
                t.id,
                t.ticket_number,
                t.title,
                t.description,
                t.status,
                t.priority,
                t.category,
                t.created_at,
                t.sla_due_at,
                t.resolved_at,
                req.name AS requester_name,
                ag.name AS assignee_name
            FROM ticketing_tickets t
            LEFT JOIN users req ON req.id = t.requester_id
            LEFT JOIN users ag ON ag.id = t.assignee_id
            ORDER BY t.created_at DESC
        `;

        try {
            const rows = await window.AppFramework.ExecuteSQL(sql, 'Fetch helpdesk tickets').catch(function () { return []; });
            const now = new Date();
            masterData = (rows || []).map(function (item) {
                const created = item.created_at ? new Date(item.created_at) : now;
                const ageDays = Math.max(1, Math.ceil(Math.abs(now - created) / 86400000));
                const due = item.sla_due_at ? new Date(item.sla_due_at) : null;
                const active = isActive(item.status);
                let slaCategory = 'ONTRACK';
                let slaBadge = 'sla-ontrack';
                let slaText = ageDays + 'd';
                if (!active) {
                    slaCategory = 'DONE';
                    slaBadge = 'sla-done';
                    slaText = labelStatus(item.status);
                } else if (due && due.getTime() < now.getTime()) {
                    slaCategory = 'CRITICAL';
                    slaBadge = 'sla-overdue';
                    slaText = 'Overdue';
                } else if (due && (due.getTime() - now.getTime()) < 86400000) {
                    slaCategory = 'WARNING';
                    slaBadge = 'sla-warning';
                    slaText = 'Due soon';
                } else if (ageDays > 7) {
                    slaCategory = 'CRITICAL';
                    slaBadge = 'sla-overdue';
                    slaText = ageDays + 'd (aged)';
                } else if (ageDays >= 3) {
                    slaCategory = 'WARNING';
                    slaBadge = 'sla-warning';
                    slaText = ageDays + 'd';
                }
                return Object.assign({}, item, {
                    ticket_label: ticketNo(item.ticket_number),
                    age_days: ageDays,
                    unassigned: active && !item.assignee_name,
                    sla_category: slaCategory,
                    sla_badge: slaBadge,
                    sla_text: slaText,
                    opened_on: item.created_at ? String(item.created_at).slice(0, 10) : ''
                });
            });
            fillCategories();
            renderDashboard();
        } catch (err) {
            $('#ticketTableBody').html('<tr><td colspan="8" class="text-center py-4 text-danger">Error loading tickets: ' + (err.message || err) + '</td></tr>');
        }
    }

    function fillCategories() {
        const $ = window.jQuery;
        const current = $('#filter-category').val() || 'ALL';
        const names = {};
        masterData.forEach(function (row) {
            if (row.category) names[row.category] = true;
        });
        const select = $('#filter-category');
        select.empty().append('<option value="ALL">All categories</option>');
        Object.keys(names).sort().forEach(function (name) {
            select.append($('<option></option>').val(name).text(name));
        });
        select.val(names[current] || current === 'ALL' ? current : 'ALL');
    }

    function renderDashboard() {
        const $ = window.jQuery;
        const statusFilter = $('#filter-status').val();
        const priorityFilter = $('#filter-priority').val();
        const categoryFilter = $('#filter-category').val();
        const slaFilter = $('#filter-sla').val();
        const dateFrom = $('#filter-date-from').val();
        const dateTo = $('#filter-date-to').val();
        const searchVal = ($('#search-input').val() || '').toLowerCase().trim();

        let tabAll = 0, tabOpen = 0, tabProgress = 0, tabUnassigned = 0, tabOverdue = 0, tabResolved = 0;
        masterData.forEach(function (row) {
            tabAll++;
            if (row.status === 'open') tabOpen++;
            if (row.status === 'in_progress') tabProgress++;
            if (row.unassigned) tabUnassigned++;
            if (row.sla_category === 'CRITICAL') tabOverdue++;
            if (row.status === 'resolved') tabResolved++;
        });
        $('#tab-cnt-all').text(tabAll);
        $('#tab-cnt-open').text(tabOpen);
        $('#tab-cnt-progress').text(tabProgress);
        $('#tab-cnt-unassigned').text(tabUnassigned);
        $('#tab-cnt-overdue').text(tabOverdue);
        $('#tab-cnt-resolved').text(tabResolved);

        const filtered = masterData.filter(function (item) {
            if (statusFilter !== 'ALL' && item.status !== statusFilter) return false;
            if (priorityFilter !== 'ALL' && item.priority !== priorityFilter) return false;
            if (categoryFilter !== 'ALL' && item.category !== categoryFilter) return false;
            if (slaFilter !== 'ALL' && item.sla_category !== slaFilter) return false;
            if (dateFrom && item.opened_on && item.opened_on < dateFrom) return false;
            if (dateTo && item.opened_on && item.opened_on > dateTo) return false;
            if (activeFilterTab === 'open' && item.status !== 'open') return false;
            if (activeFilterTab === 'in_progress' && item.status !== 'in_progress') return false;
            if (activeFilterTab === 'resolved' && item.status !== 'resolved') return false;
            if (activeFilterTab === 'UNASSIGNED' && !item.unassigned) return false;
            if (activeFilterTab === 'OVERDUE' && item.sla_category !== 'CRITICAL') return false;
            if (searchVal) {
                const haystack = [item.ticket_label, item.title, item.description, item.requester_name, item.assignee_name, item.category].join(' ').toLowerCase();
                if (haystack.indexOf(searchVal) === -1) return false;
            }
            return true;
        });

        let openCount = 0, progressCount = 0, unassignedCount = 0, overdueCount = 0;
        filtered.forEach(function (item) {
            if (item.status === 'open') openCount++;
            if (item.status === 'in_progress') progressCount++;
            if (item.unassigned) unassignedCount++;
            if (item.sla_category === 'CRITICAL') overdueCount++;
        });
        $('#stat-open-count').text(openCount);
        $('#stat-progress-count').text(progressCount);
        $('#stat-unassigned-count').text(unassignedCount);
        $('#stat-overdue-count').text(overdueCount);
        $('#stat-total-count').text(filtered.length);
        $('#table-showing-text').text('Showing ' + filtered.length + ' of ' + masterData.length + ' tickets');

        const tbody = $('#ticketTableBody');
        tbody.empty();
        if (filtered.length === 0) {
            tbody.html('<tr><td colspan="8" class="text-center py-5 text-muted"><i class="fas fa-check-circle text-success fs-3 d-block mb-2"></i>No tickets match the current filters.</td></tr>');
            updateCharts(filtered);
            return;
        }

        filtered.forEach(function (row) {
            const href = '/ticketing/tickets/' + row.id;
            const title = row.title || 'Untitled';
            tbody.append(
                '<tr>' +
                '<td><a class="fw-bold text-decoration-none text-primary d-block" href="' + href + '">' + row.ticket_label + '</a><small class="text-muted">' + (row.opened_on || '') + '</small></td>' +
                '<td><div class="text-truncate" style="max-width: 280px;" title="' + escapeAttr(title) + '">' + escapeHtml(title) + '</div><small class="text-muted">' + escapeHtml(row.category || '') + '</small></td>' +
                '<td><span class="badge ' + statusBadge(row.status) + '">' + labelStatus(row.status) + '</span></td>' +
                '<td><span class="badge ' + priorityBadge(row.priority) + '">' + escapeHtml(row.priority || 'normal') + '</span></td>' +
                '<td>' + escapeHtml(row.requester_name || '—') + '</td>' +
                '<td>' + escapeHtml(row.assignee_name || 'Unassigned') + '</td>' +
                '<td class="text-center"><span class="badge ' + row.sla_badge + '">' + escapeHtml(row.sla_text) + '</span></td>' +
                '<td class="text-center no-print"><a class="btn btn-sm btn-outline-primary py-0 px-2" href="' + href + '">Open</a></td>' +
                '</tr>'
            );
        });
        updateCharts(filtered);
    }

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function escapeAttr(value) {
        return escapeHtml(value).replace(/"/g, '&quot;');
    }

    function updateCharts(data) {
        if (!window.Chart) return;
        const statusOrder = ['open', 'in_progress', 'resolved', 'closed'];
        const statusCounts = { open: 0, in_progress: 0, resolved: 0, closed: 0 };
        let overdue = 0, warning = 0, ontrack = 0, done = 0;
        data.forEach(function (item) {
            if (statusCounts[item.status] != null) statusCounts[item.status]++;
            if (item.sla_category === 'CRITICAL') overdue++;
            else if (item.sla_category === 'WARNING') warning++;
            else if (item.sla_category === 'DONE') done++;
            else ontrack++;
        });

        const bar = document.getElementById('chartStatus');
        if (bar) {
            if (charts.bar) charts.bar.destroy();
            charts.bar = new Chart(bar, {
                type: 'bar',
                data: {
                    labels: ['Open', 'In progress', 'Resolved', 'Closed'],
                    datasets: [{
                        label: 'Tickets',
                        data: statusOrder.map(function (key) { return statusCounts[key]; }),
                        backgroundColor: ['#3b82f6', '#8b5cf6', '#10b981', '#94a3b8']
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { grid: { display: false } }, y: { beginAtZero: true, ticks: { precision: 0 } } } }
            });
        }

        const pie = document.getElementById('chartSla');
        if (pie) {
            if (charts.pie) charts.pie.destroy();
            charts.pie = new Chart(pie, {
                type: 'doughnut',
                data: {
                    labels: ['Overdue', 'Due soon', 'On track', 'Done'],
                    datasets: [{ data: [overdue, warning, ontrack, done], backgroundColor: ['#ef4444', '#f59e0b', '#0284c7', '#10b981'], borderWidth: 2, borderColor: '#ffffff' }]
                },
                options: { responsive: true, maintainAspectRatio: false, cutout: '65%', plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } } }
            });
        }
    }

    function exportCurrentQueueToCSV() {
        const table = document.getElementById('ticketTable');
        if (!table) return;
        const csv = [];
        table.querySelectorAll('tr').forEach(function (row) {
            const cols = [];
            const cells = row.querySelectorAll('th, td');
            for (let i = 0; i < cells.length - 1; i++) {
                const text = cells[i].innerText.replace(/\s+/g, ' ').trim().replace(/"/g, '""');
                cols.push('"' + text + '"');
            }
            if (cols.length) csv.push(cols.join(','));
        });
        const blob = new Blob(['\ufeff' + csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = 'Ticketing_Dashboard_' + new Date().toISOString().split('T')[0] + '.csv';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
    }

    waitForFrameworkAndDOM(initDashboard);
})();
