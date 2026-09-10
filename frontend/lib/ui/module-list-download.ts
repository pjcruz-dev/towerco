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
 * Open a clean print window for the current filtered list (not the live DataTable chrome).
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
    @media print { body { margin: 0; } }
  </style>
</head>
<body>
  <h1>${escape(title)}</h1>
  ${subtitle ? `<p>${escape(subtitle)}</p>` : ""}
  <table>
    <thead><tr>${head}</tr></thead>
    <tbody>${body || `<tr><td colspan="${columns.length}">No rows</td></tr>`}</tbody>
  </table>
  <script>window.onload = function () { window.focus(); window.print(); };</script>
</body>
</html>`;

  const popup = window.open("", "_blank", "noopener,noreferrer,width=960,height=720");
  if (!popup) {
    throw new Error("Pop-up blocked. Allow pop-ups to print this list.");
  }
  popup.document.open();
  popup.document.write(html);
  popup.document.close();
}
