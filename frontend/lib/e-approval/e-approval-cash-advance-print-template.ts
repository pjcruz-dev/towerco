/**
 * Cash advance print layout. Landscape A4 so the forecast lines stay on the page.
 */

export type CashAdvancePrintPageOptions = {
  size?: string;
  marginMm?: number;
  orientation?: "portrait" | "landscape";
};

export function defaultCashAdvanceDocumentDesignHtml(): string {
  return `<div class="eapproval-printable ea-ca-doc">
  <header class="ea-ca-header">
    <div class="ea-ca-brand">
      <div class="ea-form-logo">{{system.subsidiary_logo}}</div>
      <div class="ea-ca-brand-text">
        <p class="ea-ca-kicker">Finance</p>
        <h1 class="ea-ca-title">Cash advance</h1>
      </div>
    </div>
    <dl class="ea-ca-docmeta">
      <div><dt>Document</dt><dd>{{system.document_no}}</dd></div>
      <div><dt>Status</dt><dd>{{system.status}}</dd></div>
      <div><dt>Submitted</dt><dd>{{system.submitted_at}}</dd></div>
    </dl>
  </header>

  <section class="ea-ca-facts" aria-label="Request">
    <div><span>Requestor</span><strong>{{system.requestor}}</strong></div>
    <div><span>Subsidiary</span><strong>{{field.subsidiary}}</strong></div>
    <div><span>Funds needed by</span><strong>{{field.needed_by}}</strong></div>
  </section>

  <section class="ea-ca-purpose">
    <span>Purpose</span>
    <p>{{field.purpose}}</p>
  </section>

  {{grid.expense_lines}}

  <section class="ea-ca-notes">
    <span>Notes</span>
    <p>{{field.notes}}</p>
  </section>
</div>`;
}

/** Liquidation uses the cash-advance page. Actual lines replace the forecast, and the settlement sits under the table. */
export function defaultLiquidationDocumentDesignHtml(): string {
  return `<div class="eapproval-printable ea-ca-doc ea-lq-doc">
  <header class="ea-ca-header">
    <div class="ea-ca-brand">
      <div class="ea-form-logo">{{system.subsidiary_logo}}</div>
      <div class="ea-ca-brand-text">
        <p class="ea-ca-kicker">Finance</p>
        <h1 class="ea-ca-title">Liquidation</h1>
      </div>
    </div>
    <dl class="ea-ca-docmeta">
      <div><dt>Document</dt><dd>{{system.document_no}}</dd></div>
      <div><dt>Status</dt><dd>{{system.status}}</dd></div>
      <div><dt>Submitted</dt><dd>{{system.submitted_at}}</dd></div>
    </dl>
  </header>

  <section class="ea-ca-facts" aria-label="Request">
    <div><span>Requestor</span><strong>{{system.requestor}}</strong></div>
    <div><span>Subsidiary</span><strong>{{field.subsidiary}}</strong></div>
    <div><span>Cash advance</span><strong>{{field.cash_advance_document_no}}</strong></div>
    <div><span>Liquidation date</span><strong>{{field.liquidation_date}}</strong></div>
  </section>

  <section class="ea-ca-purpose">
    <span>Purpose</span>
    <p>{{field.purpose}}</p>
  </section>

  {{grid.expense_lines}}

  <section class="ea-ca-facts ea-lq-settlement" aria-label="Settlement">
    <div><span>Cash released</span><strong>{{field.cash_advance_amount}}</strong></div>
    <div><span>Settlement</span><strong>{{field.settlement_summary}}</strong></div>
  </section>

  <section class="ea-ca-notes">
    <span>Notes</span>
    <p>{{field.notes}}</p>
  </section>
</div>`;
}

/** Reimbursement is the same page as liquidation. Lines are the actual spend. There is no forecast and no cash advance. */
export function defaultReimbursementDocumentDesignHtml(): string {
  return `<div class="eapproval-printable ea-ca-doc ea-re-doc">
  <header class="ea-ca-header">
    <div class="ea-ca-brand">
      <div class="ea-form-logo">{{system.subsidiary_logo}}</div>
      <div class="ea-ca-brand-text">
        <p class="ea-ca-kicker">Finance</p>
        <h1 class="ea-ca-title">Reimbursement</h1>
      </div>
    </div>
    <dl class="ea-ca-docmeta">
      <div><dt>Document</dt><dd>{{system.document_no}}</dd></div>
      <div><dt>Status</dt><dd>{{system.status}}</dd></div>
      <div><dt>Submitted</dt><dd>{{system.submitted_at}}</dd></div>
    </dl>
  </header>

  <section class="ea-ca-facts" aria-label="Request">
    <div><span>Requestor</span><strong>{{system.requestor}}</strong></div>
    <div><span>Subsidiary</span><strong>{{field.subsidiary}}</strong></div>
  </section>

  <section class="ea-ca-purpose">
    <span>Purpose</span>
    <p>{{field.purpose}}</p>
  </section>

  {{grid.expense_lines}}

  <section class="ea-ca-notes">
    <span>Notes</span>
    <p>{{field.notes}}</p>
  </section>
</div>`;
}

export function defaultCashAdvanceDocumentDesignCss(options?: CashAdvancePrintPageOptions): string {
  const size = options?.size ?? "A4";
  const margin = Math.min(40, Math.max(0, Number(options?.marginMm ?? 10) || 0));
  const orientation = options?.orientation === "portrait" ? "portrait" : "landscape";

  return `@page { size: ${size} ${orientation}; margin: ${margin}mm; }

.ea-ca-doc {
  box-sizing: border-box;
  font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
  color: #0f172a;
  font-size: 11px;
  line-height: 1.35;
}
.ea-ca-doc *,
.ea-ca-doc *::before,
.ea-ca-doc *::after { box-sizing: border-box; }

.ea-ca-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 16px;
  padding-bottom: 10px;
  border-bottom: 2px solid #0f172a;
}
.ea-ca-brand { display: flex; align-items: center; gap: 12px; min-width: 0; }
.ea-form-logo:empty { display: none; }
.eapproval-print-logo {
  display: block;
  max-height: 48px;
  max-width: 140px;
  object-fit: contain;
}
.ea-ca-kicker {
  margin: 0;
  font-size: 10px;
  font-weight: 600;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: #64748b;
}
.ea-ca-title {
  margin: 2px 0 0;
  font-size: 20px;
  font-weight: 600;
  letter-spacing: -0.02em;
  line-height: 1.15;
}
.ea-ca-docmeta {
  margin: 0;
  min-width: 210px;
  display: grid;
  gap: 2px;
}
.ea-ca-docmeta div {
  display: flex;
  justify-content: space-between;
  gap: 12px;
}
.ea-ca-docmeta dt { color: #64748b; font-weight: 500; }
.ea-ca-docmeta dd { margin: 0; font-weight: 600; text-align: right; }

.ea-ca-facts {
  display: grid;
  grid-template-columns: 1.3fr 0.7fr 1fr;
  gap: 8px;
  margin-top: 10px;
}
.ea-lq-doc .ea-ca-facts { grid-template-columns: 1.1fr 0.7fr 1fr 0.9fr; }
.ea-lq-doc .ea-lq-settlement { grid-template-columns: 1fr 1.4fr; }
.ea-re-doc .ea-ca-facts { grid-template-columns: 1.3fr 0.7fr; }
.ea-re-doc .ea-lq-settlement { grid-template-columns: 1fr; }
.ea-ca-facts div,
.ea-ca-purpose,
.ea-ca-notes {
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  padding: 6px 8px;
  min-width: 0;
}
.ea-ca-facts span,
.ea-ca-purpose span,
.ea-ca-notes span {
  display: block;
  font-size: 9px;
  font-weight: 600;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: #64748b;
}
.ea-ca-facts strong,
.ea-ca-purpose p,
.ea-ca-notes p {
  display: block;
  margin: 2px 0 0;
  font-size: 12px;
  font-weight: 600;
  color: #0f172a;
  white-space: pre-wrap;
  word-break: break-word;
}
.ea-ca-purpose,
.ea-ca-notes { margin-top: 8px; }
.ea-ca-notes p { font-weight: 500; min-height: 1.2em; }

.ea-ca-doc .ea-form-grid-section { margin-top: 12px; }
.ea-ca-doc .ea-form-section-title {
  margin: 0 0 6px;
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: #334155;
}
.ea-ca-doc .ea-print-table-wrap {
  overflow: visible;
  border: 1px solid #94a3b8;
  border-radius: 6px;
}
.ea-ca-doc .ea-print-table {
  width: 100%;
  border-collapse: collapse;
  table-layout: fixed;
  font-size: 9px;
  line-height: 1.3;
}
.ea-ca-doc .ea-print-table th,
.ea-ca-doc .ea-print-table td {
  border: 1px solid #cbd5e1;
  padding: 4px 5px;
  vertical-align: top;
  word-break: break-word;
  text-align: left;
}
.ea-ca-doc .ea-print-table thead th {
  background: #f1f5f9;
  font-size: 8px;
  font-weight: 700;
  text-align: center;
  letter-spacing: 0.01em;
}
.ea-ca-doc .ea-print-table tbody tr:nth-child(even) { background: #f8fafc; }
.ea-ca-doc .ea-print-col-date { width: 16%; }
.ea-ca-doc .ea-print-col-site { width: 12%; }
.ea-ca-doc .ea-print-col-type { width: 10%; }
.ea-ca-doc .ea-print-col-money,
.ea-ca-doc .ea-print-col-vat {
  width: 10%;
  text-align: right;
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}
.ea-ca-doc .ea-print-col-treatment { width: 10%; text-align: center; }

/* Liquidation and reimbursement have more columns than a cash advance forecast. */
.ea-lq-doc .ea-print-table,
.ea-re-doc .ea-print-table {
  font-size: 8px;
}
.ea-lq-doc .ea-print-table th,
.ea-lq-doc .ea-print-table td,
.ea-re-doc .ea-print-table th,
.ea-re-doc .ea-print-table td {
  padding: 3px 4px;
  word-break: normal;
  overflow-wrap: break-word;
}
.ea-lq-doc .ea-print-table thead th,
.ea-re-doc .ea-print-table thead th {
  font-size: 7.5px;
  line-height: 1.2;
  white-space: normal;
}
.ea-lq-doc .ea-print-col-date,
.ea-re-doc .ea-print-col-date { width: 10%; }
.ea-lq-doc .ea-print-col-site,
.ea-re-doc .ea-print-col-site { width: 7%; }
.ea-lq-doc .ea-print-col-ref,
.ea-re-doc .ea-print-col-ref { width: 5%; }
.ea-lq-doc .ea-print-col-party,
.ea-re-doc .ea-print-col-party { width: 7%; }
.ea-lq-doc .ea-print-col-note,
.ea-re-doc .ea-print-col-note { width: 8%; }
.ea-lq-doc .ea-print-col-type,
.ea-re-doc .ea-print-col-type { width: 6%; }
.ea-lq-doc .ea-print-col-qty,
.ea-re-doc .ea-print-col-qty { width: 5%; text-align: right; }
.ea-lq-doc .ea-print-col-money,
.ea-re-doc .ea-print-col-money,
.ea-lq-doc .ea-print-col-vat,
.ea-re-doc .ea-print-col-vat {
  width: 7%;
  white-space: normal;
}
.ea-lq-doc .ea-print-table td.ea-print-col-money,
.ea-re-doc .ea-print-table td.ea-print-col-money,
.ea-lq-doc .ea-print-table td.ea-print-col-vat,
.ea-re-doc .ea-print-table td.ea-print-col-vat {
  white-space: nowrap;
}
.ea-lq-doc .ea-print-col-treatment,
.ea-re-doc .ea-print-col-treatment { width: 7%; }
.ea-ca-doc .ea-print-table tfoot td {
  background: #f8fafc;
  font-weight: 700;
  font-size: 9px;
  white-space: normal;
}
.ea-ca-doc .ea-print-table tfoot .ea-print-col-money,
.ea-ca-doc .ea-print-table tfoot .ea-print-col-vat {
  white-space: normal;
}
.ea-ca-doc .ea-print-table-total-label {
  text-align: right;
  letter-spacing: 0.04em;
}
.ea-ca-doc .ea-print-table-total-value {
  text-align: right;
  white-space: nowrap;
  font-variant-numeric: tabular-nums;
}
.ea-ca-doc .ea-print-total-kicker {
  display: block;
  font-size: 7px;
  font-weight: 600;
  letter-spacing: 0.02em;
  text-transform: uppercase;
  color: #64748b;
}

@media print {
  .ea-ca-doc { color: #0f172a; }
  .ea-ca-docmeta dd,
  .ea-ca-facts strong,
  .ea-ca-doc .ea-print-table thead th,
  .ea-ca-doc .ea-print-table tbody tr:nth-child(even),
  .ea-ca-doc .ea-print-table tfoot td {
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
}`;
}
