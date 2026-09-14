/**
 * Trigger a browser download for a Blob response (CSV / XLSX / PDF).
 */
export function saveBlob(blob: Blob, filename: string): void {
  const url = URL.createObjectURL(blob);
  const anchor = document.createElement("a");
  anchor.href = url;
  anchor.download = filename;
  anchor.rel = "noopener";
  document.body.appendChild(anchor);
  anchor.click();
  anchor.remove();
  URL.revokeObjectURL(url);
}

export type PrintTableColumn = {
  header: string;
  value: string;
};

/**
 * Print a clean list table without relying on pop-ups.
 * Uses a hidden iframe so Chrome/Edge do not block print (window.open + noopener
 * returns null even when a blank tab opens).
 */
export function printModuleListTable(options: {
  title: string;
  subtitle?: string;
  columns: string[];
  rows: string[][];
}): void {
  const { title, subtitle, columns, rows } = options;
  const escape = (value: string) =>
    value
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;");

  const head = columns.map((col) => `<th>${escape(col)}</th>`).join("");
  const body = rows
    .map((row) => `<tr>${row.map((cell) => `<td>${escape(cell)}</td>`).join("")}</tr>`)
    .join("");

  const html = `<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>${escape(title)}</title>
  <style>
    body { font-family: Inter, system-ui, sans-serif; color: #0f172a; margin: 24px; }
    h1 { font-size: 18px; font-weight: 600; margin: 0 0 4px; }
    p { font-size: 12px; color: #64748b; margin: 0 0 16px; }
    table { width: 100%; border-collapse: collapse; font-size: 12px; }
    th, td { border: 1px solid #e2e8f0; padding: 6px 8px; text-align: left; vertical-align: top; }
    th { background: #f8fafc; font-weight: 600; }
    @media print {
      body { margin: 0; }
      th { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
  </style>
</head>
<body>
  <h1>${escape(title)}</h1>
  ${subtitle ? `<p>${escape(subtitle)}</p>` : ""}
  <table>
    <thead><tr>${head}</tr></thead>
    <tbody>${body || `<tr><td colspan="${Math.max(columns.length, 1)}">No rows</td></tr>`}</tbody>
  </table>
</body>
</html>`;

  const iframe = document.createElement("iframe");
  iframe.setAttribute("title", "Print preview");
  iframe.setAttribute("aria-hidden", "true");
  iframe.style.cssText =
    "position:fixed;right:0;bottom:0;width:0;height:0;border:0;opacity:0;pointer-events:none;";
  document.body.appendChild(iframe);

  const win = iframe.contentWindow;
  const doc = win?.document ?? iframe.contentDocument;
  if (!win || !doc) {
    iframe.remove();
    throw new Error("Unable to open print preview in this browser.");
  }

  doc.open();
  doc.write(html);
  doc.close();

  const cleanup = () => {
    window.setTimeout(() => {
      iframe.remove();
    }, 1_000);
  };

  const triggerPrint = () => {
    try {
      win.focus();
      win.print();
    } finally {
      cleanup();
    }
  };

  // Allow layout/paint before invoking the print dialog.
  window.setTimeout(triggerPrint, 50);
}
