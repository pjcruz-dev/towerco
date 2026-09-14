/**
 * Request for Payment print layout — ATC/ADIC paper structure, INFRA SUITE colors.
 */

export type RfpPrintPageOptions = {
  size?: string;
  marginMm?: number;
  orientation?: "portrait" | "landscape";
};

/** Labels shown on the ATC paper form + INFRA SUITE RFP template aliases. */
export const RFP_COST_PRINT_ROWS: Array<{ printLabel: string; matchLabels: string[] }> = [
  { printLabel: "SAQ-Site Survey", matchLabels: ["SAQ-Site Survey"] },
  { printLabel: "SAQ-Permitting", matchLabels: ["SAQ-Permitting"] },
  { printLabel: "SAQ-Soil Testing", matchLabels: ["SAQ-Soil Testing"] },
  { printLabel: "CME-Materials", matchLabels: ["CME-Materials"] },
  { printLabel: "CME-Labor", matchLabels: ["CME-Labor"] },
  {
    printLabel: "CME-Delivery & Handling",
    matchLabels: ["CME-Delivery & Handling", "Logistics", "Logistics / Hauling"],
  },
  { printLabel: "Various Department", matchLabels: ["Various Department"] },
  { printLabel: "Finance and Accounting", matchLabels: ["Finance and Accounting"] },
  { printLabel: "Others, pls specify", matchLabels: ["Others, pls specify", "Others"] },
];

function escapeHtml(value: string): string {
  return value
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#39;");
}

type ParsedCostRow = {
  project_site_no: string;
  ref_no: string;
  or_no: string;
};

function parseCostApplicationDisplay(display: string): Map<string, ParsedCostRow> {
  const out = new Map<string, ParsedCostRow>();
  const lines = display
    .split("\n")
    .map((line) => line.trim())
    .filter(Boolean);

  for (const line of lines) {
    const dashIdx = line.indexOf(" — ");
    const rowLabel = (dashIdx >= 0 ? line.slice(0, dashIdx) : line).trim();
    const rest = dashIdx >= 0 ? line.slice(dashIdx + 3).trim() : "";
    const cells: ParsedCostRow = { project_site_no: "", ref_no: "", or_no: "" };

    if (rest) {
      for (const token of rest.split(";").map((part) => part.trim()).filter(Boolean)) {
        const splitIdx = token.indexOf(": ");
        if (splitIdx < 0) continue;
        const label = token.slice(0, splitIdx).trim().toLowerCase();
        const value = token.slice(splitIdx + 2).trim();
        if (label.includes("project") || label.includes("site")) cells.project_site_no = value;
        else if (label.startsWith("ref")) cells.ref_no = value;
        else if (label.startsWith("or")) cells.or_no = value;
      }
    }

    out.set(rowLabel.toLowerCase(), cells);
  }

  return out;
}

/** Paper-structure cost application table (INFRA SUITE slate colors via CSS). */
export function renderRfpCostApplicationPrintHtml(displayValue: string): string {
  const selected = parseCostApplicationDisplay(displayValue);
  const rows = RFP_COST_PRINT_ROWS.map((row) => {
    let cells: ParsedCostRow | undefined;
    for (const match of row.matchLabels) {
      cells = selected.get(match.toLowerCase());
      if (cells) break;
    }
    const checked = Boolean(cells);
    const mark = checked ? "☑" : "☐";
    const isOthers = /others/i.test(row.printLabel);
    return `    <tr class="${isOthers ? "ea-rfp-cost-row--others" : ""}">
      <td class="ea-rfp-cost-check">${mark}</td>
      <td class="ea-rfp-cost-label">${escapeHtml(row.printLabel)}</td>
      <td class="ea-rfp-cost-cell">${escapeHtml(cells?.project_site_no ?? "")}</td>
      <td class="ea-rfp-cost-cell">${escapeHtml(cells?.ref_no ?? "")}</td>
      <td class="ea-rfp-cost-cell">${escapeHtml(cells?.or_no ?? "")}</td>
    </tr>`;
  });

  return `<table class="ea-rfp-cost">
  <thead>
    <tr>
      <th style="width:22px"></th>
      <th class="ea-rfp-cost-app">Cost Application</th>
      <th>Project Site No</th>
      <th>Ref No</th>
      <th>OR No.</th>
    </tr>
  </thead>
  <tbody>
${rows.join("\n")}
  </tbody>
</table>`;
}

export function defaultRequestForPaymentDocumentDesignHtml(): string {
  return `<div class="eapproval-printable ea-rfp-doc">
  <header class="ea-rfp-header">
    <div class="ea-rfp-brand">
      <div class="ea-form-logo">{{system.subsidiary_logo}}</div>
      <div class="ea-rfp-brand-meta">
        <span>{{field.subsidiary}}</span>
        <span aria-hidden="true">·</span>
        <span>{{field.department}}</span>
      </div>
    </div>
    <div class="ea-rfp-title-wrap">
      <p class="ea-rfp-kicker">Finance · E-Forms</p>
      <h1 class="ea-rfp-title">Request for payment</h1>
    </div>
    <div class="ea-rfp-docmeta">
      <div class="ea-rfp-docmeta-row"><span>Doc No.</span><strong>{{system.document_no}}</strong></div>
      <div class="ea-rfp-docmeta-row"><span>Amount</span><strong>{{field.payment_amount}} {{field.currency}}</strong></div>
      <div class="ea-rfp-docmeta-row"><span>PO / Non-PO</span><strong>{{field.non_po}}</strong></div>
    </div>
  </header>

  <div class="ea-rfp-body">
    <section class="ea-rfp-left">
      <div class="ea-rfp-payee-box">
        <div class="ea-rfp-payee-label">Payee</div>
        <div class="ea-rfp-payee-value">{{field.payee}}</div>
      </div>
      <div class="ea-rfp-field-row">
        <div class="ea-rfp-field-label">VAT Registration No.</div>
        <div class="ea-rfp-field-value">{{field.vat_registration_no}}</div>
      </div>
      <div class="ea-rfp-field-row">
        <div class="ea-rfp-field-label">Contact Person</div>
        <div class="ea-rfp-field-value">{{field.contact_person}}</div>
      </div>
      <div class="ea-rfp-field-row">
        <div class="ea-rfp-field-label">Tel No.</div>
        <div class="ea-rfp-field-value">{{field.tel_no}}</div>
      </div>
      <div class="ea-rfp-field-row">
        <div class="ea-rfp-field-label">Service / Travel Period</div>
        <div class="ea-rfp-field-value">{{field.service_period}}</div>
      </div>
      <div class="ea-rfp-field-row">
        <div class="ea-rfp-field-label">Passenger</div>
        <div class="ea-rfp-field-value">{{field.passenger}}</div>
      </div>
      <div class="ea-rfp-field-row">
        <div class="ea-rfp-field-label">Location / Site</div>
        <div class="ea-rfp-field-value">{{field.location}}</div>
      </div>
      <div class="ea-rfp-payment-for">
        <div class="ea-rfp-payment-for-label">Payment for</div>
        <div class="ea-rfp-payment-for-value">{{field.payment_purpose}}</div>
      </div>
    </section>

    <section class="ea-rfp-right">
      <table class="ea-rfp-bank">
        <thead>
          <tr><th colspan="2">Bank details</th></tr>
        </thead>
        <tbody>
          <tr>
            <th scope="row">Name of Bank</th>
            <td>{{field.bank_name}}</td>
          </tr>
          <tr>
            <th scope="row">Bank Account Name</th>
            <td>{{field.bank_account_name}}</td>
          </tr>
          <tr>
            <th scope="row">Bank Account No.</th>
            <td>{{field.bank_account_no}}</td>
          </tr>
        </tbody>
      </table>

      {{field.cost_application}}
    </section>
  </div>

  <footer class="ea-rfp-footer">
    <div class="ea-rfp-footer-note">100% full payment upon submission of service invoice.</div>
    <div class="ea-rfp-footer-meta">
      <span>Requestor: {{system.requestor}}</span>
      <span>Submitted: {{system.submitted_at}}</span>
    </div>
  </footer>
</div>`;
}

/**
 * Same paper structure as before; colors aligned to INFRA SUITE print palette
 * (#0f172a / #64748b / #f1f5f9 / #e2e8f0) instead of navy / sky blue / black.
 */
export function defaultRequestForPaymentDocumentDesignCss(
  options?: RfpPrintPageOptions,
): string {
  const size = (options?.size ?? "A4").trim() || "A4";
  const margin = Number.isFinite(options?.marginMm) ? Number(options?.marginMm) : 10;
  const orientation = options?.orientation === "landscape" ? "landscape" : "portrait";

  return `@page { size: ${size} ${orientation}; margin: ${margin}mm; }

.eapproval-printable,
.ea-rfp-doc {
  box-sizing: border-box;
  font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
  color: #0f172a;
  font-size: 11px;
  line-height: 1.35;
}

.ea-rfp-doc *,
.ea-rfp-doc *::before,
.ea-rfp-doc *::after { box-sizing: border-box; }

.ea-form-logo:empty { display: none; }
.eapproval-print-logo {
  display: block;
  max-height: 48px;
  max-width: 180px;
  object-fit: contain;
}

.ea-rfp-header {
  display: grid;
  grid-template-columns: 1.1fr 1.2fr 1fr;
  align-items: start;
  gap: 12px;
  margin-bottom: 12px;
  padding-bottom: 10px;
  border-bottom: 1px solid #e2e8f0;
}
.ea-rfp-brand { min-width: 0; }
.ea-rfp-brand-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  margin-top: 6px;
  font-size: 10px;
  color: #64748b;
}
.ea-rfp-title-wrap {
  text-align: center;
  padding-top: 2px;
}
.ea-rfp-kicker {
  margin: 0 0 4px;
  font-size: 10px;
  font-weight: 500;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: #64748b;
}
.ea-rfp-title {
  margin: 0;
  font-size: 18px;
  font-weight: 600;
  letter-spacing: -0.02em;
  color: #0f172a;
}
.ea-rfp-docmeta {
  justify-self: end;
  min-width: 168px;
  max-width: 220px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  background: #f8fafc;
  padding: 8px 10px;
  font-size: 10px;
}
.ea-rfp-docmeta-row {
  display: flex;
  justify-content: space-between;
  gap: 10px;
  padding: 3px 0;
}
.ea-rfp-docmeta-row + .ea-rfp-docmeta-row {
  border-top: 1px solid #e2e8f0;
}
.ea-rfp-docmeta-row span { color: #64748b; flex-shrink: 0; }
.ea-rfp-docmeta-row strong {
  color: #0f172a;
  font-weight: 600;
  text-align: right;
  word-break: break-word;
}

.ea-rfp-body {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  overflow: hidden;
  min-height: 440px;
  background: #fff;
}
.ea-rfp-left {
  display: flex;
  flex-direction: column;
  border-right: 1px solid #e2e8f0;
  min-width: 0;
}
.ea-rfp-right {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.ea-rfp-payee-box {
  border-bottom: 1px solid #e2e8f0;
  min-height: 68px;
  padding: 10px 12px;
  background: #f8fafc;
}
.ea-rfp-payee-label {
  font-size: 10px;
  font-weight: 600;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: #475569;
  margin-bottom: 4px;
}
.ea-rfp-payee-value {
  font-size: 13px;
  font-weight: 600;
  color: #0f172a;
  min-height: 32px;
  white-space: pre-wrap;
  word-break: break-word;
}

.ea-rfp-field-row {
  display: grid;
  grid-template-columns: 40% 1fr;
  border-bottom: 1px solid #e2e8f0;
  min-height: 30px;
}
.ea-rfp-field-label {
  padding: 6px 10px;
  font-size: 10px;
  font-weight: 500;
  color: #475569;
  border-right: 1px solid #e2e8f0;
  background: #f1f5f9;
}
.ea-rfp-field-value {
  padding: 6px 10px;
  color: #0f172a;
  word-break: break-word;
}

.ea-rfp-payment-for {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-height: 140px;
  padding: 10px 12px;
}
.ea-rfp-payment-for-label {
  font-size: 10px;
  font-weight: 600;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: #475569;
  margin-bottom: 6px;
}
.ea-rfp-payment-for-value {
  flex: 1;
  color: #0f172a;
  white-space: pre-wrap;
  word-break: break-word;
}

.ea-rfp-bank {
  width: 100%;
  border-collapse: collapse;
  table-layout: fixed;
}
.ea-rfp-bank thead th {
  background: #f1f5f9;
  color: #0f172a;
  text-align: left;
  font-size: 10px;
  font-weight: 600;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  padding: 8px 10px;
  border-bottom: 1px solid #e2e8f0;
}
.ea-rfp-bank tbody th {
  width: 38%;
  background: #f8fafc;
  color: #475569;
  font-weight: 500;
  font-size: 10px;
  text-align: left;
  padding: 7px 10px;
  border-bottom: 1px solid #e2e8f0;
  border-right: 1px solid #e2e8f0;
}
.ea-rfp-bank tbody td {
  padding: 7px 10px;
  color: #0f172a;
  border-bottom: 1px solid #e2e8f0;
  word-break: break-word;
}

.ea-rfp-cost {
  width: 100%;
  border-collapse: collapse;
  table-layout: fixed;
  flex: 1;
}
.ea-rfp-cost thead th {
  background: #f1f5f9;
  color: #0f172a;
  font-size: 9px;
  font-weight: 600;
  padding: 6px 4px;
  border-bottom: 1px solid #e2e8f0;
  border-right: 1px solid #e2e8f0;
  text-align: center;
}
.ea-rfp-cost thead th:last-child { border-right: none; }
.ea-rfp-cost thead th.ea-rfp-cost-app { text-align: left; padding-left: 8px; }
.ea-rfp-cost tbody td {
  border-bottom: 1px solid #e2e8f0;
  border-right: 1px solid #e2e8f0;
  padding: 4px;
  vertical-align: middle;
  height: 26px;
  color: #0f172a;
  word-break: break-word;
}
.ea-rfp-cost tbody tr:nth-child(even) { background: #f8fafc; }
.ea-rfp-cost tbody td:last-child { border-right: none; }
.ea-rfp-cost-check {
  width: 22px;
  text-align: center;
  font-size: 12px;
  line-height: 1;
  color: #475569;
}
.ea-rfp-cost-label {
  font-size: 10px;
  padding-left: 6px !important;
}
.ea-rfp-cost-cell { font-size: 10px; }
.ea-rfp-cost-row--others .ea-rfp-cost-label { font-style: italic; color: #64748b; }

.ea-rfp-footer {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 12px;
  margin-top: 10px;
  padding-top: 8px;
  border-top: 1px solid #e2e8f0;
  font-size: 10px;
  color: #64748b;
}
.ea-rfp-footer-note { font-weight: 500; color: #475569; }
.ea-rfp-footer-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 8px 14px;
  justify-content: flex-end;
  text-align: right;
}

@media print {
  .ea-rfp-doc { color: #0f172a; }
  .ea-rfp-docmeta,
  .ea-rfp-payee-box,
  .ea-rfp-field-label,
  .ea-rfp-bank thead th,
  .ea-rfp-bank tbody th,
  .ea-rfp-cost thead th,
  .ea-rfp-cost tbody tr:nth-child(even) {
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
}`;
}

/** @deprecated Use defaultRequestForPaymentDocumentDesignCss — kept for callers expecting extras-only. */
export function requestForPaymentLayoutCssExtras(): string {
  return "";
}
