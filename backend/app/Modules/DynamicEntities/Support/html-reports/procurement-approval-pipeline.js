(function() {
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
        const today = new Date();
        const firstDayOfMonth = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().split('T')[0];
        const lastDayOfMonth = new Date(today.getFullYear(), today.getMonth() + 1, 0).toISOString().split('T')[0];

        $('#filter-date-from').val(firstDayOfMonth);
        $('#filter-date-to').val(lastDayOfMonth);
        $('.print-date').text(new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }));

        bindEvents();
        await loadAllApprovalData();
    }

    function bindEvents() {
        const $ = window.jQuery;

        $('#btn-refresh').off('click').on('click', async function() {
            const icon = $(this).find('i');
            icon.addClass('fa-spin');
            await loadAllApprovalData();
            icon.removeClass('fa-spin');
        });

        $('#filter-subsidiary, #filter-department, #filter-doc-type, #filter-level, #filter-sla, #filter-date-from, #filter-date-to').off('change').on('change', function() {
            renderDashboard();
        });

        $('#search-input').off('input').on('input', function() {
            renderDashboard();
        });

        $('#dash-tabs button').off('click').on('click', function() {
            $('#dash-tabs button').removeClass('active');
            $(this).addClass('active');
            activeFilterTab = $(this).data('tab-type');
            renderDashboard();
        });

        $('.kpi-card').off('click').on('click', function() {
            const stageFilter = $(this).data('stage-filter');
            if (stageFilter) {
                $('#filter-level').val(stageFilter);
                $('.kpi-card').removeClass('active-kpi');
                $(this).addClass('active-kpi');
                renderDashboard();
            }
        });

        $('#btn-export-csv').off('click').on('click', function() {
            exportCurrentQueueToCSV();
        });
    }

    function sqlLiteral(value) {
        return String(value ?? '').replace(/'/g, "''");
    }

    async function loadAllApprovalData() {
        const $ = window.jQuery;
        $('#approvalTableBody').html('<tr><td colspan="10" class="text-center py-5 text-muted"><i class="fas fa-spinner fa-spin me-2 text-primary"></i> Fetching live procurement records across all stages...</td></tr>');

        try {
            const dateFrom = sqlLiteral($('#filter-date-from').val());
            const dateTo = sqlLiteral($('#filter-date-to').val());
            const departmentId = sqlLiteral($('#filter-department').val());
            const deptFilterClause = departmentId !== 'ALL' ? `AND d.id = '${departmentId}'` : '';

            const sqlPR = `
                SELECT pr.id, pr.requisition_number AS doc_number, 'Purchase Requisition' AS doc_type_name, 'PR' AS doc_category,
                    pr.approval_status, pr.approval_level, pr.scm_status, pr.requisition_date AS doc_date, pr.submitted_at,
                    pr.memo AS description, pr.total_amount AS amount, pr.subsidiary_id, pr.tower_site_id, pr.procurement_type,
                    pr.department_id, d.department_name, ts.site_code, ts.site_name, ts.mno_anchor_site_id, v.vendor_name,
                    sd.staff_name AS requester_name
                FROM dat_purchase_requisitions pr
                LEFT JOIN dat_tower_sites ts ON pr.tower_site_id = ts.id
                LEFT JOIN dat_vendors v ON pr.vendor_id = v.id
                LEFT JOIN dat_staff_directory sd ON pr.requestor_id = sd.id
                LEFT JOIN dat_departments d ON pr.department_id = d.id
                WHERE (pr.is_deleted = 0 OR pr.is_deleted IS NULL)
                  AND pr.requisition_date BETWEEN '${dateFrom}' AND '${dateTo}'
                  ${deptFilterClause}
                  AND (
                      pr.approval_status LIKE '%Pending%'
                      OR pr.approval_status = 'Draft'
                      OR (pr.approval_status = 'Approved' AND pr.scm_status IN ('Awaiting SCM Verification', 'Direct Award — Pending SCM Head', 'Award Recommendation — Pending SCM Head', 'Bidding / Canvassing'))
                  )`;

            const sqlPO = `
                SELECT po.id, po.po_number AS doc_number, 'Purchase Order' AS doc_type_name, 'PO' AS doc_category,
                    po.approval_status, po.approval_level, po.vendor_confirmation_status AS scm_status, po.po_date AS doc_date,
                    po.submitted_at, po.memo AS description, po.total_amount AS amount, po.subsidiary_id, po.tower_site_id,
                    po.procurement_type, po.department_id, d.department_name, ts.site_code, ts.site_name, ts.mno_anchor_site_id,
                    v.vendor_name, '' AS requester_name
                FROM dat_purchase_orders po
                LEFT JOIN dat_tower_sites ts ON po.tower_site_id = ts.id
                LEFT JOIN dat_vendors v ON po.vendor_id = v.id
                LEFT JOIN dat_departments d ON po.department_id = d.id
                WHERE (po.is_deleted = 0 OR po.is_deleted IS NULL)
                  AND po.po_date BETWEEN '${dateFrom}' AND '${dateTo}'
                  ${deptFilterClause}
                  AND (
                      po.approval_status LIKE '%Pending%'
                      OR po.approval_status = 'Draft'
                      OR (po.approval_status = 'Approved' AND po.vendor_confirmation_status = 'Sent to Vendor')
                  )`;

            const sqlRFQ = `
                SELECT rfq.id, rfq.rfq_number AS doc_number, 'Bid Evaluation (RFQ)' AS doc_type_name, 'RFQ' AS doc_category,
                    rfq.rfq_status AS approval_status, 'SCM' AS approval_level, rfq.rfq_status AS scm_status, rfq.rfq_date AS doc_date,
                    rfq.award_submitted_at AS submitted_at, rfq.memo AS description, COALESCE(rfq.awarded_amount, rfq.budget_total, 0) AS amount,
                    rfq.subsidiary_id, 0 AS tower_site_id, rfq.procurement_type, rfq.proponent_department_id AS department_id,
                    d.department_name, '' AS site_code, '' AS site_name, '' AS mno_anchor_site_id, v.vendor_name, sd.staff_name AS requester_name
                FROM dat_request_for_quotes rfq
                LEFT JOIN dat_vendors v ON rfq.recommended_vendor_id = v.id
                LEFT JOIN dat_staff_directory sd ON rfq.employee_id = sd.id
                LEFT JOIN dat_departments d ON rfq.proponent_department_id = d.id
                WHERE (rfq.is_deleted = 0 OR rfq.is_deleted IS NULL)
                  AND rfq.rfq_date BETWEEN '${dateFrom}' AND '${dateTo}'
                  ${deptFilterClause}
                  AND rfq.rfq_status IN ('Award Recommendation — Pending SCM Head', 'Pending Bidder List (VMO)', 'Technical Evaluation', 'Commercial Evaluation', 'BAFO Requested')`;

            const sqlNTP = `
                SELECT ntp.id, ntp.ntp_number AS doc_number, 'Notice to Proceed' AS doc_type_name, 'NTP' AS doc_category,
                    ntp.ntp_status AS approval_status, 'SCM' AS approval_level, ntp.ntp_status AS scm_status, ntp.ntp_date AS doc_date,
                    ntp.submitted_at, ntp.scope_of_work AS description, COALESCE(ntp.estimated_amount, 0) AS amount, ntp.subsidiary_id,
                    ntp.tower_site_id, ntp.procurement_type, COALESCE(pr.department_id, po.department_id) AS department_id, d.department_name,
                    ts.site_code, ts.site_name, ts.mno_anchor_site_id, v.vendor_name, '' AS requester_name
                FROM dat_notices_to_proceed ntp
                LEFT JOIN dat_tower_sites ts ON ntp.tower_site_id = ts.id
                LEFT JOIN dat_vendors v ON ntp.vendor_id = v.id
                LEFT JOIN dat_purchase_requisitions pr ON ntp.requisition_id = pr.id
                LEFT JOIN dat_purchase_orders po ON ntp.purchase_order_id = po.id
                LEFT JOIN dat_departments d ON COALESCE(pr.department_id, po.department_id) = d.id
                WHERE (ntp.is_deleted = 0 OR ntp.is_deleted IS NULL)
                  AND ntp.ntp_date BETWEEN '${dateFrom}' AND '${dateTo}'
                  ${deptFilterClause}
                  AND ntp.ntp_status IN ('Pending SCM Head Approval', 'Draft')`;

            const sqlMB = `
                SELECT mb.id, mb.mb_number AS doc_number, 'Milestone Billing' AS doc_type_name, 'MB' AS doc_category,
                    mb.mb_status AS approval_status, 'SCM' AS approval_level, mb.mb_status AS scm_status, mb.billing_received_date AS doc_date,
                    mb.validated_at AS submitted_at, mb.milestone_description AS description, COALESCE(mb.billing_amount, mb.milestone_amount, 0) AS amount,
                    mb.subsidiary_id, mb.tower_site_id, mb.procurement_type, po.department_id AS department_id, d.department_name,
                    ts.site_code, ts.site_name, ts.mno_anchor_site_id, v.vendor_name, '' AS requester_name
                FROM dat_milestone_billings mb
                LEFT JOIN dat_tower_sites ts ON mb.tower_site_id = ts.id
                LEFT JOIN dat_vendors v ON mb.vendor_id = v.id
                LEFT JOIN dat_purchase_orders po ON mb.purchase_order_id = po.id
                LEFT JOIN dat_departments d ON po.department_id = d.id
                WHERE (mb.is_deleted = 0 OR mb.is_deleted IS NULL)
                  AND mb.billing_received_date BETWEEN '${dateFrom}' AND '${dateTo}'
                  ${deptFilterClause}
                  AND mb.mb_status IN ('Pending Validation', 'Validated', 'Billing Submitted', 'SCM Verified', 'Endorsed to Finance')`;

            const sqlAPV = `
                SELECT pt.id, pt.purchase_number AS doc_number, 'Vendor Bill (APV)' AS doc_type_name, 'APV' AS doc_category,
                    pt.approval_status, pt.approval_level, pt.status AS scm_status, pt.purchase_date AS doc_date, pt.submitted_at,
                    pt.memo AS description, pt.total_amount_due AS amount, pt.subsidiary_id, pt.tower_site_id, '' AS procurement_type,
                    pt.department_id, d.department_name, ts.site_code, ts.site_name, ts.mno_anchor_site_id, v.vendor_name, '' AS requester_name
                FROM dat_purchase_transactions pt
                LEFT JOIN dat_tower_sites ts ON pt.tower_site_id = ts.id
                LEFT JOIN dat_vendors v ON pt.vendor_id = v.id
                LEFT JOIN dat_departments d ON pt.department_id = d.id
                WHERE (pt.is_deleted = 0 OR pt.is_deleted IS NULL)
                  AND pt.purchase_date BETWEEN '${dateFrom}' AND '${dateTo}'
                  ${deptFilterClause}
                  AND pt.transaction_type IN ('Accounts Payable Voucher', 'Vendor Bill (APV)', 'Retention Billing')
                  AND (pt.approval_status LIKE '%Pending%' OR pt.approval_status = 'Draft')`;

            const sqlCDV = `
                SELECT pr.id, pr.payment_number AS doc_number, 'Payment Voucher (CDV)' AS doc_type_name, 'CDV' AS doc_category,
                    pr.approval_status, pr.approval_level, pr.status AS scm_status, pr.payment_date AS doc_date, pr.submitted_at,
                    pr.description, pr.amount, pr.subsidiary_id, pr.tower_site_id, '' AS procurement_type, pr.department_id,
                    d.department_name, ts.site_code, ts.site_name, ts.mno_anchor_site_id, v.vendor_name, '' AS requester_name
                FROM dat_payments_and_receipts pr
                LEFT JOIN dat_tower_sites ts ON pr.tower_site_id = ts.id
                LEFT JOIN dat_vendors v ON pr.vendor_id = v.id
                LEFT JOIN dat_departments d ON pr.department_id = d.id
                WHERE (pr.is_deleted = 0 OR pr.is_deleted IS NULL)
                  AND pr.payment_date BETWEEN '${dateFrom}' AND '${dateTo}'
                  ${deptFilterClause}
                  AND pr.type IN ('Payment Voucher', 'Payment Voucher (CDV)', 'Write Check', 'Vendor Prepayment')
                  AND (pr.approval_status LIKE '%Pending%' OR pr.approval_status = 'Draft')`;

            const [prs, pos, rfqs, ntps, mbs, apvs, cdvs] = await Promise.all([
                window.AppFramework.ExecuteSQL(sqlPR, 'Fetch pending PRs').catch(() => []),
                window.AppFramework.ExecuteSQL(sqlPO, 'Fetch pending POs').catch(() => []),
                window.AppFramework.ExecuteSQL(sqlRFQ, 'Fetch pending RFQs').catch(() => []),
                window.AppFramework.ExecuteSQL(sqlNTP, 'Fetch pending NTPs').catch(() => []),
                window.AppFramework.ExecuteSQL(sqlMB, 'Fetch pending MBs').catch(() => []),
                window.AppFramework.ExecuteSQL(sqlAPV, 'Fetch pending APVs').catch(() => []),
                window.AppFramework.ExecuteSQL(sqlCDV, 'Fetch pending CDVs').catch(() => [])
            ]);

            const combined = [...(prs || []), ...(pos || []), ...(rfqs || []), ...(ntps || []), ...(mbs || []), ...(apvs || []), ...(cdvs || [])];
            const now = new Date();
            masterData = combined.map(item => {
                const dateStr = item.submitted_at || item.doc_date || '2026-09-20';
                const createdDate = new Date(dateStr);
                const diffTime = Math.abs(now - createdDate);
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) || 1;

                let normalizedLevel = 'L1';
                let levelLabel = 'Level 1: Dept Head Review';
                let authorityRole = 'ATC Department Head';
                let badgeClass = 'badge-level-1';
                const statusStr = (item.approval_status || '').toLowerCase();
                const scmStr = (item.scm_status || '').toLowerCase();
                const numLvl = parseFloat(item.approval_level) || 1;

                if (item.doc_category === 'PR') {
                    if (statusStr.includes('level 2')) {
                        normalizedLevel = 'L2'; levelLabel = 'Level 2: CFO Budget Review'; authorityRole = 'ATC Chief Financial Officer'; badgeClass = 'badge-level-2';
                    } else if (statusStr.includes('level 3')) {
                        normalizedLevel = 'L3'; levelLabel = 'Level 3: President Sign-off'; authorityRole = 'ATC President'; badgeClass = 'badge-level-3';
                    } else if (scmStr.includes('awaiting scm') || scmStr.includes('verification')) {
                        normalizedLevel = 'SCM'; levelLabel = 'SCM: Attachment Verification'; authorityRole = 'ATC Supply Chain Management'; badgeClass = 'badge-scm';
                    } else if (scmStr.includes('direct award')) {
                        normalizedLevel = 'SCM'; levelLabel = 'SCM: Direct Award Approval'; authorityRole = 'ATC SCM Head (Director, SCM)'; badgeClass = 'badge-scm';
                    } else if (scmStr.includes('bidding')) {
                        normalizedLevel = 'SCM'; levelLabel = 'SCM: Bidding / Canvassing'; authorityRole = 'ATC Procurement Officer'; badgeClass = 'badge-scm';
                    }
                } else if (item.doc_category === 'PO') {
                    const amt = parseFloat(item.amount) || 0;
                    if (statusStr.includes('level 2')) {
                        normalizedLevel = 'L2'; levelLabel = 'Level 2: CFO Budget Clearance'; authorityRole = 'ATC Chief Financial Officer'; badgeClass = 'badge-level-2';
                    } else if (statusStr.includes('level 3')) {
                        normalizedLevel = 'L3';
                        if (amt > 500000) {
                            levelLabel = 'Level 3: CEO Approval (> ₱500K)'; authorityRole = 'ATC Chief Executive Officer';
                        } else {
                            levelLabel = 'Level 3: President Approval (≤ ₱500K)'; authorityRole = 'ATC President';
                        }
                        badgeClass = 'badge-level-3';
                    } else if (scmStr === 'sent to vendor') {
                        normalizedLevel = 'SCM'; levelLabel = 'Vendor Confirmation Pending'; authorityRole = 'ATC Procurement / Vendor'; badgeClass = 'badge-scm';
                    } else {
                        normalizedLevel = 'L1'; levelLabel = 'Level 1: SCM Director Endorsement'; authorityRole = 'ATC SCM Head (Director, SCM)'; badgeClass = 'badge-level-1';
                    }
                } else if (item.doc_category === 'RFQ') {
                    normalizedLevel = 'SCM'; badgeClass = 'badge-scm';
                    if (statusStr.includes('award recommendation')) {
                        levelLabel = 'SCM: Award Approval'; authorityRole = 'ATC SCM Head (Director, SCM)';
                    } else if (statusStr.includes('technical')) {
                        levelLabel = 'Proponent Technical Evaluation'; authorityRole = 'Requesting Proponent Head';
                    } else {
                        levelLabel = 'SCM Commercial Bidding'; authorityRole = 'ATC Procurement Officer';
                    }
                } else if (item.doc_category === 'NTP') {
                    normalizedLevel = 'SCM'; levelLabel = 'NTP: SCM Head Approval'; authorityRole = 'ATC SCM Head (Director, SCM)'; badgeClass = 'badge-scm';
                } else if (item.doc_category === 'MB') {
                    normalizedLevel = 'SCM'; badgeClass = 'badge-scm';
                    if (statusStr.includes('validation')) {
                        levelLabel = 'Proponent Milestone Validation'; authorityRole = 'Proponent (SAQ / Ops)';
                    } else {
                        levelLabel = 'SCM Billing Verification'; authorityRole = 'ATC SCM Head';
                    }
                } else if (item.doc_category === 'APV') {
                    normalizedLevel = 'PAY'; levelLabel = 'APV: Finance Head Approval'; authorityRole = 'ATC Finance Head'; badgeClass = 'badge-payables';
                } else if (item.doc_category === 'CDV') {
                    normalizedLevel = 'PAY'; badgeClass = 'badge-payables';
                    if (numLvl === 2 || statusStr.includes('level 2')) {
                        levelLabel = 'CDV: CFO Check Approval (L2)'; authorityRole = 'ATC Chief Financial Officer';
                    } else {
                        levelLabel = 'CDV: Finance Head Approval (L1)'; authorityRole = 'ATC Finance Head';
                    }
                }

                let slaCategory = 'ONTRACK';
                let slaBadge = 'sla-ontrack';
                let slaText = diffDays + 'd (On Track)';
                if (diffDays > 7) {
                    slaCategory = 'CRITICAL'; slaBadge = 'sla-overdue'; slaText = diffDays + 'd (Overdue)';
                } else if (diffDays >= 3) {
                    slaCategory = 'WARNING'; slaBadge = 'sla-warning'; slaText = diffDays + 'd (Attention)';
                }

                return Object.assign({}, item, {
                    age_days: diffDays,
                    normalized_level: normalizedLevel,
                    level_label: levelLabel,
                    authority_role: authorityRole,
                    badge_class: badgeClass,
                    sla_category: slaCategory,
                    sla_badge: slaBadge,
                    sla_text: slaText
                });
            });

            renderDashboard();
        } catch (err) {
            console.error('Failed to load procurement approval data:', err);
            $('#approvalTableBody').html('<tr><td colspan="10" class="text-center py-4 text-danger"><i class="fas fa-exclamation-triangle me-2"></i> Error loading pipeline data: ' + (err && err.message ? err.message : err) + '</td></tr>');
        }
    }

    function money(amount) {
        return '₱' + (parseFloat(amount) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function renderDashboard() {
        const $ = window.jQuery;
        const subFilter = $('#filter-subsidiary').val();
        const docFilter = $('#filter-doc-type').val();
        const lvlFilter = $('#filter-level').val();
        const slaFilter = $('#filter-sla').val();
        const deptFilter = $('#filter-department').val();
        const dateFrom = $('#filter-date-from').val();
        const dateTo = $('#filter-date-to').val();
        const searchVal = ($('#search-input').val() || '').toLowerCase().trim();

        let tabAll = 0, tabPR = 0, tabPO = 0, tabSCM = 0, tabPAY = 0, tabOverdue = 0;
        masterData.forEach(d => {
            tabAll++;
            if (d.doc_category === 'PR') tabPR++;
            if (d.doc_category === 'PO') tabPO++;
            if (['RFQ', 'NTP', 'MB'].includes(d.doc_category) || d.normalized_level === 'SCM') tabSCM++;
            if (['APV', 'CDV'].includes(d.doc_category) || d.normalized_level === 'PAY') tabPAY++;
            if (d.sla_category === 'CRITICAL') tabOverdue++;
        });
        $('#tab-cnt-all').text(tabAll);
        $('#tab-cnt-pr').text(tabPR);
        $('#tab-cnt-po').text(tabPO);
        $('#tab-cnt-scm').text(tabSCM);
        $('#tab-cnt-pay').text(tabPAY);
        $('#tab-cnt-overdue').text(tabOverdue);

        const filtered = masterData.filter(item => {
            if (subFilter !== 'ALL' && String(item.subsidiary_id) !== subFilter) return false;
            if (docFilter !== 'ALL' && item.doc_category !== docFilter) return false;
            if (lvlFilter !== 'ALL' && item.normalized_level !== lvlFilter) return false;
            if (slaFilter !== 'ALL' && item.sla_category !== slaFilter) return false;
            if (deptFilter !== 'ALL' && String(item.department_id) !== deptFilter) return false;
            const itemDate = item.doc_date || item.submitted_at;
            if (itemDate && (itemDate < dateFrom || itemDate > dateTo)) return false;
            if (activeFilterTab === 'PR' && item.doc_category !== 'PR') return false;
            if (activeFilterTab === 'PO' && item.doc_category !== 'PO') return false;
            if (activeFilterTab === 'SCM' && !(['RFQ', 'NTP', 'MB'].includes(item.doc_category) || item.normalized_level === 'SCM')) return false;
            if (activeFilterTab === 'PAY' && !(['APV', 'CDV'].includes(item.doc_category) || item.normalized_level === 'PAY')) return false;
            if (activeFilterTab === 'OVERDUE' && item.sla_category !== 'CRITICAL') return false;
            if (searchVal) {
                const searchStr = [item.doc_number, item.doc_type_name, item.description, item.site_code, item.site_name, item.mno_anchor_site_id, item.vendor_name, item.requester_name, item.authority_role, item.department_name].join(' ').toLowerCase();
                if (!searchStr.includes(searchVal)) return false;
            }
            return true;
        });

        let l1Count = 0, l1Val = 0, l2Count = 0, l2Val = 0, l3Count = 0, l3Val = 0, l3Pres = 0, l3Ceo = 0;
        let scmCount = 0, scmVal = 0, totalCount = 0, totalVal = 0, critCount = 0;
        filtered.forEach(item => {
            const amt = parseFloat(item.amount) || 0;
            totalCount++;
            totalVal += amt;
            if (item.sla_category === 'CRITICAL') critCount++;
            if (item.normalized_level === 'L1') { l1Count++; l1Val += amt; }
            else if (item.normalized_level === 'L2') { l2Count++; l2Val += amt; }
            else if (item.normalized_level === 'L3') {
                l3Count++; l3Val += amt;
                if (amt > 500000) l3Ceo++; else l3Pres++;
            } else if (item.normalized_level === 'SCM') { scmCount++; scmVal += amt; }
        });

        $('#stat-l1-count').text(l1Count);
        $('#stat-l1-val').text(money(l1Val));
        $('#stat-l2-count').text(l2Count);
        $('#stat-l2-val').text(money(l2Val));
        $('#stat-l3-count').text(l3Count);
        $('#stat-l3-val').text(money(l3Val));
        $('#stat-l3-pres').text(l3Pres);
        $('#stat-l3-ceo').text(l3Ceo);
        $('#stat-scm-count').text(scmCount);
        $('#stat-scm-val').text(money(scmVal));
        $('#stat-total-count').text(totalCount);
        $('#stat-total-val').text(money(totalVal));
        $('#stat-critical-count').text(critCount + ' Overdue');

        const tbody = $('#approvalTableBody');
        tbody.empty();
        if (filtered.length === 0) {
            tbody.html('<tr><td colspan="10" class="text-center py-5 text-muted"><i class="fas fa-check-circle text-success fs-3 d-block mb-2"></i>No pending approvals found matching the current filters.</td></tr>');
            $('#table-grand-total').text('₱0.00');
            $('#table-grand-count').text('0 items');
            $('#table-showing-text').text('Showing 0 items');
            updateCharts(filtered);
            return;
        }

        filtered.forEach(row => {
            const amt = parseFloat(row.amount) || 0;
            const partyName = row.vendor_name || row.requester_name || 'ATC Procurement';
            const siteDisplay = row.mno_anchor_site_id
                ? '<strong>' + row.mno_anchor_site_id + '</strong><div class="text-muted" style="font-size:0.75rem;">' + (row.site_code || '') + '</div>'
                : '<span class="text-muted">— Head Office —</span>';
            let entityParam = 'purchase_requisitions';
            if (row.doc_category === 'PO') entityParam = 'purchase_orders';
            else if (row.doc_category === 'RFQ') entityParam = 'request_for_quotes';
            else if (row.doc_category === 'NTP') entityParam = 'notices_to_proceed';
            else if (row.doc_category === 'MB') entityParam = 'milestone_validation___billing';
            else if (row.doc_category === 'APV') entityParam = 'purchase_transactions';
            else if (row.doc_category === 'CDV') entityParam = 'payments_and_receipts';
            const docLink = '/dynamic-entities/' + entityParam;
            const description = row.description || '<span class="text-muted">No remarks</span>';
            tbody.append(
                '<tr><td><a href="' + docLink + '" class="fw-bold text-decoration-none text-primary d-block">' + (row.doc_number || ('#ID-' + row.id)) + '</a><small class="text-muted" style="font-size:0.75rem;">' + (row.doc_date || '') + '</small></td>' +
                '<td><span class="badge bg-light text-dark border">' + (row.doc_type_name || '') + '</span></td>' +
                '<td><span class="badge ' + row.badge_class + ' text-wrap text-start py-1 px-2 d-inline-block" style="max-width: 170px;">' + row.level_label + '</span></td>' +
                '<td><div class="text-truncate" style="max-width: 240px;" title="' + String(row.description || '').replace(/"/g, '&quot;') + '">' + description + '</div></td>' +
                '<td>' + siteDisplay + '</td>' +
                '<td><div class="fw-semibold text-truncate" style="max-width: 180px;">' + partyName + '</div><small class="text-muted" style="font-size:0.75rem;">' + (row.procurement_type || 'Capex') + '</small></td>' +
                '<td class="text-end fw-bold text-dark pe-3">' + money(amt) + '</td>' +
                '<td class="text-center"><span class="badge ' + row.sla_badge + ' py-1 px-2">' + row.sla_text + '</span></td>' +
                '<td><div class="fw-bold text-dark small"><i class="fas fa-user-clock me-1 text-muted"></i>' + row.authority_role + '</div><small class="text-muted" style="font-size:0.75rem;">' + (row.department_name || '') + '</small></td>' +
                '<td class="text-center no-print"><a href="' + docLink + '" class="btn btn-sm btn-outline-primary py-0 px-2" title="Review & Act"><i class="fas fa-arrow-right"></i> Review</a></td></tr>'
            );
        });

        $('#table-grand-total').text(money(totalVal));
        $('#table-grand-count').text(filtered.length + ' pending items');
        $('#table-showing-text').text('Showing ' + filtered.length + ' of ' + masterData.length + ' total pending items');
        updateCharts(filtered);
    }

    function updateCharts(data) {
        if (!window.Chart) return;
        const levels = ['Level 1 Gate', 'Level 2 Gate', 'Level 3 Gate', 'SCM Sourcing', 'Payables'];
        const values = {
            'Requisitions': [0, 0, 0, 0, 0],
            'Purchase Orders': [0, 0, 0, 0, 0],
            'SCM Sourcing': [0, 0, 0, 0, 0],
            'Payables': [0, 0, 0, 0, 0]
        };
        let countOverdue = 0, countWarning = 0, countOntrack = 0;
        data.forEach(item => {
            const amt = parseFloat(item.amount) || 0;
            let lvlIdx = 0;
            if (item.normalized_level === 'L2') lvlIdx = 1;
            else if (item.normalized_level === 'L3') lvlIdx = 2;
            else if (item.normalized_level === 'SCM') lvlIdx = 3;
            else if (item.normalized_level === 'PAY') lvlIdx = 4;
            if (item.doc_category === 'PR') values['Requisitions'][lvlIdx] += amt;
            else if (item.doc_category === 'PO') values['Purchase Orders'][lvlIdx] += amt;
            else if (['RFQ', 'NTP', 'MB'].includes(item.doc_category)) values['SCM Sourcing'][lvlIdx] += amt;
            else values['Payables'][lvlIdx] += amt;
            if (item.sla_category === 'CRITICAL') countOverdue++;
            else if (item.sla_category === 'WARNING') countWarning++;
            else countOntrack++;
        });

        const ctxBar = document.getElementById('chartApprovalLevels');
        if (ctxBar) {
            if (charts.bar) charts.bar.destroy();
            charts.bar = new Chart(ctxBar, {
                type: 'bar',
                data: {
                    labels: levels,
                    datasets: [
                        { label: 'Requisitions', data: values['Requisitions'], backgroundColor: '#3b82f6' },
                        { label: 'Purchase Orders', data: values['Purchase Orders'], backgroundColor: '#8b5cf6' },
                        { label: 'SCM Sourcing', data: values['SCM Sourcing'], backgroundColor: '#f59e0b' },
                        { label: 'Payables & Checks', data: values['Payables'], backgroundColor: '#10b981' }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: { stacked: true, grid: { display: false } },
                        y: { stacked: true, ticks: { callback: function(val) { return '₱' + (val >= 1000000 ? (val / 1000000).toFixed(1) + 'M' : (val / 1000).toFixed(0) + 'K'); } } }
                    },
                    plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } }
                }
            });
        }

        const ctxPie = document.getElementById('chartSlaAgeing');
        if (ctxPie) {
            if (charts.pie) charts.pie.destroy();
            charts.pie = new Chart(ctxPie, {
                type: 'doughnut',
                data: {
                    labels: ['Overdue (> 7 Days)', 'Attention (3 - 7 Days)', 'Normal (< 3 Days)'],
                    datasets: [{ data: [countOverdue, countWarning, countOntrack], backgroundColor: ['#ef4444', '#f59e0b', '#0284c7'], borderWidth: 2, borderColor: '#ffffff' }]
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } }, cutout: '65%' }
            });
        }
    }

    function exportCurrentQueueToCSV() {
        const table = document.getElementById('approvalTable');
        if (!table) return;
        const csv = [];
        table.querySelectorAll('tr').forEach(row => {
            const cols = [];
            const cells = row.querySelectorAll('th, td');
            for (let i = 0; i < cells.length - 1; i++) {
                let text = cells[i].innerText.replace(/(\r\n|\n|\r)/gm, ' ').replace(/\s+/g, ' ').trim();
                text = text.replace(/"/g, '""');
                cols.push('"' + text + '"');
            }
            if (cols.length) csv.push(cols.join(','));
        });
        const blob = new Blob(['\ufeff' + csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = 'Procurement_Approval_Pipeline_' + new Date().toISOString().split('T')[0] + '.csv';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        setTimeout(() => URL.revokeObjectURL(url), 1000);
    }

    waitForFrameworkAndDOM(initDashboard);
})();
