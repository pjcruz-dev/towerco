/** Metacoresoft-style printable HTML token merge. */

export type DynPrintTemplateContext = {
  system: Record<string, string>;
  record: Record<string, unknown>;
  /** Nested maps for {{record.relation.field}} */
  linked?: Record<string, Record<string, unknown>>;
  /** Rows for {{item.*}} table expansion */
  items?: Array<Record<string, unknown>>;
  /** {{theme.accent}} etc. */
  theme?: Record<string, string>;
};

function formatScalar(value: unknown): string {
  if (value === null || value === undefined || value === "") return "";
  if (typeof value === "number" && Number.isFinite(value)) {
    return value.toLocaleString(undefined, { maximumFractionDigits: 2 });
  }
  if (typeof value === "boolean") return value ? "Yes" : "No";
  if (typeof value === "object") {
    try {
      return JSON.stringify(value);
    } catch {
      return "";
    }
  }
  return String(value);
}

function isTruthy(value: unknown): boolean {
  if (value === null || value === undefined) return false;
  if (typeof value === "boolean") return value;
  if (typeof value === "number") return value !== 0 && !Number.isNaN(value);
  if (typeof value === "string") return value.trim() !== "";
  if (Array.isArray(value)) return value.length > 0;
  if (typeof value === "object") return Object.keys(value as object).length > 0;
  return Boolean(value);
}

function lookupRaw(path: string, ctx: DynPrintTemplateContext): unknown {
  const parts = path.split(".").filter(Boolean);
  if (parts.length === 0) return undefined;

  if (parts[0] === "system") {
    const key = parts.slice(1).join(".") || parts[1] || "";
    return ctx.system[key] ?? ctx.system[parts[1] ?? ""] ?? "";
  }

  if (parts[0] === "theme") {
    return ctx.theme?.[parts.slice(1).join(".")] ?? ctx.theme?.[parts[1] ?? ""] ?? "";
  }

  if (parts[0] === "item") {
    const row = ctx.items?.[0] ?? {};
    return row[parts.slice(1).join(".")] ?? row[parts[1] ?? ""];
  }

  if (parts[0] === "record") {
    const rest = parts.slice(1);
    if (rest.length === 1) {
      return ctx.record[rest[0]];
    }
    if (rest.length >= 2) {
      const linked = ctx.linked?.[rest[0]];
      if (linked) {
        return linked[rest.slice(1).join(".")] ?? linked[rest[1]];
      }
      if (rest.length === 2 && rest[1] === "title") {
        return ctx.record[rest[0]];
      }
      // Nested value on record itself (rare)
      let cur: unknown = ctx.record[rest[0]];
      for (let i = 1; i < rest.length; i++) {
        if (cur && typeof cur === "object" && !Array.isArray(cur)) {
          cur = (cur as Record<string, unknown>)[rest[i]!];
        } else {
          return undefined;
        }
      }
      return cur;
    }
  }

  return undefined;
}

function lookupPath(path: string, ctx: DynPrintTemplateContext): string {
  const parts = path.split(".").filter(Boolean);
  // Logo / logo path = URL only (templates use <img src="{{system.company_logo}}">).
  if (parts[0] === "system") {
    const key = parts.slice(1).join(".") || parts[1] || "";
    if (key === "company_logo" || key === "company_logo_path") {
      const url = ctx.system.company_logo || ctx.system.company_logo_path || "";
      if (!url || url.startsWith("<")) return url.startsWith("<") ? "" : url;
      return url;
    }
  }
  // Relationship fields: show linked title when {{record.supplier_id}} is used alone.
  if (parts[0] === "record" && parts.length === 2) {
    const linked = ctx.linked?.[parts[1]!];
    if (linked && (linked.title != null || linked.name != null)) {
      const label = linked.title ?? linked.name;
      if (label !== "" && label != null) return formatScalar(label);
    }
  }
  return formatScalar(lookupRaw(path, ctx));
}

/** Expand {{#if path}}…{{else}}…{{/if}} and {{#unless}} (Metacoresoft Handlebars subset). */
function expandConditionals(html: string, ctx: DynPrintTemplateContext): string {
  let out = html;
  let guard = 0;
  const ifRe =
    /\{\{\s*#if\s+([a-zA-Z0-9_.]+)\s*\}\}([\s\S]*?)(?:\{\{\s*else\s*\}\}([\s\S]*?))?\{\{\s*\/if\s*\}\}/g;
  const unlessRe =
    /\{\{\s*#unless\s+([a-zA-Z0-9_.]+)\s*\}\}([\s\S]*?)(?:\{\{\s*else\s*\}\}([\s\S]*?))?\{\{\s*\/unless\s*\}\}/g;

  // Repeat for nested blocks (innermost resolved first as outer shrinks).
  while (guard < 24) {
    guard += 1;
    let changed = false;
    const next = out
      .replace(ifRe, (_m, path: string, whenTrue: string, whenFalse = "") => {
        changed = true;
        return isTruthy(lookupRaw(path.trim(), ctx)) ? whenTrue : whenFalse;
      })
      .replace(unlessRe, (_m, path: string, whenTrue: string, whenFalse = "") => {
        changed = true;
        return !isTruthy(lookupRaw(path.trim(), ctx)) ? whenTrue : whenFalse;
      });
    out = next;
    if (!changed) break;
  }

  return out;
}

/** Expand table rows that contain {{item.*}} or [[item.*]] tokens. */
function expandItemRows(html: string, items: Array<Record<string, unknown>>): string {
  if (
    (!html.includes("{{item.") &&
      !html.includes("[[item.") &&
      !html.includes("{{loop.") &&
      !html.includes("[[loop.")) ||
    items.length === 0
  ) {
    return html;
  }

  return html.replace(/<tr\b[^>]*>[\s\S]*?<\/tr>/gi, (rowHtml) => {
    if (
      !rowHtml.includes("{{item.") &&
      !rowHtml.includes("[[item.") &&
      !rowHtml.includes("{{loop.") &&
      !rowHtml.includes("[[loop.")
    ) {
      return rowHtml;
    }
    return items
      .map((item, index) => {
        const rowCtx = { ...item, __index: index + 1, __index0: index };
        return rowHtml
          .replace(/\{\{\s*loop\.index\s*\}\}/gi, String(index + 1))
          .replace(/\{\{\s*loop\.index0\s*\}\}/gi, String(index))
          .replace(/\[\[\s*loop\.index\s*\]\]/gi, String(index + 1))
          .replace(/\[\[\s*loop\.index0\s*\]\]/gi, String(index))
          .replace(/\{\{\s*item\.([a-zA-Z0-9_.]+)\s*\}\}/g, (_m, path: string) =>
            formatScalar(rowCtx[path] ?? item[path] ?? ""),
          )
          .replace(/\[\[\s*item\.([a-zA-Z0-9_.]+)\s*\]\]/g, (_m, path: string) =>
            formatScalar(rowCtx[path] ?? item[path] ?? ""),
          );
      })
      .join("");
  });
}

/**
 * Merge {{tokens}} into HTML. Supports:
 * - {{#if path}} / {{#unless path}} / {{else}} / {{/if}}
 * - {{system.*}} / {{theme.*}} (logo = URL for src=)
 * - {{record.field}} / {{record.relation.field}}
 * - {{item.field}} / [[item.field]] (with row expansion)
 * - P[{{record.amount}}] currency-style wrapper → peso-prefixed value
 */
export function renderDynPrintTemplate(html: string, ctx: DynPrintTemplateContext): string {
  let out = ensurePrintableRoot(html);
  out = expandConditionals(out, ctx);
  out = expandItemRows(out, ctx.items ?? []);

  const logoUrl = ctx.system.company_logo || ctx.system.company_logo_path || "";
  // Attribute context first (src="{{system.company_logo}}"), then bare token → <img>.
  out = out.replace(
    /(src\s*=\s*["'])\s*\{\{\s*system\.company_logo(?:_path)?\s*\}\}\s*(["'])/gi,
    (_m, open: string, close: string) => `${open}${logoUrl}${close}`,
  );
  out = out.replace(/\{\{\s*system\.company_logo(?:_path)?\s*\}\}/gi, () => {
    if (!logoUrl || logoUrl.startsWith("<")) return logoUrl.startsWith("<") ? logoUrl : "";
    const safe = logoUrl.replace(/"/g, "&quot;");
    return `<img src="${safe}" alt="" class="company-logo" style="max-height:65px;" />`;
  });

  out = out.replace(/P\[\{\{\s*([^}]+?)\s*\}\}\]/g, (_m, path: string) => {
    const v = lookupPath(path.trim(), ctx);
    if (!v) return "";
    return `₱ ${v}`;
  });

  out = out.replace(/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/g, (_m, path: string) => lookupPath(path, ctx));
  out = out.replace(/\[\[\s*([a-zA-Z0-9_.]+)\s*\]\]/g, (_m, path: string) => lookupPath(path, ctx));

  out = out.replace(/\{\{\s*#(?:if|unless)\s+[^}]+\}\}/g, "");
  out = out.replace(/\{\{\s*\/(?:if|unless)\s*\}\}/g, "");
  out = out.replace(/\{\{\s*else\s*\}\}/g, "");
  out = out.replace(/<img\b[^>]*\bsrc\s*=\s*(["'])\s*\1[^>]*>/gi, "");

  return out;
}

/** Substitute tokens inside printable CSS (e.g. color: {{theme.accent}}). */
export function renderDynPrintCss(css: string, ctx: DynPrintTemplateContext): string {
  if (!css.trim()) return css;
  return css.replace(/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/g, (_m, path: string) => lookupPath(path, ctx));
}

/** Metacoresoft templates expect a positioned root for badges; wrap if missing. */
export function ensurePrintableRoot(html: string): string {
  const trimmed = html.trim();
  if (!trimmed) return trimmed;
  if (/class\s*=\s*["'][^"']*\bcontract-container\b/i.test(trimmed)) return trimmed;
  if (/^<div\b[^>]*\bcontract-container\b/i.test(trimmed)) return trimmed;
  return `<div class="contract-container">${trimmed}</div>`;
}

export function buildSystemDateTokens(now = new Date()): Record<string, string> {
  const nextYear = new Date(now);
  nextYear.setFullYear(now.getFullYear() + 1);
  const nextMonth = new Date(now);
  nextMonth.setMonth(now.getMonth() + 1);
  return {
    today: now.toLocaleDateString(),
    current_date: now.toLocaleDateString(),
    now: now.toLocaleString(),
    next_year: String(nextYear.getFullYear()),
    next_month: nextMonth.toLocaleDateString(undefined, { month: "long", year: "numeric" }),
  };
}

export function defaultPrintableHtml(documentTitle: string): string {
  const title = documentTitle.trim() || "DOCUMENT";
  return `<div class="printable-container">
  <table class="header-table">
    <tr>
      <td class="logo-area"><img src="{{system.company_logo}}" alt="" class="company-logo" /></td>
      <td class="company-details">
        <div class="company-name">{{system.company_name}}</div>
        <div>{{system.company_address}}</div>
        <div>{{system.company_phone}} · {{system.company_email}}</div>
        <div>TIN: {{system.company_tin}}</div>
      </td>
      <td class="document-meta">
        <div class="document-title">${title}</div>
        <div>Status: {{record.status}}</div>
        <div>Date: {{system.today}}</div>
      </td>
    </tr>
  </table>
  <div class="divider"></div>
  <h3 class="section-title">Record details</h3>
  <p>Title: {{record.title}}</p>
  <p>Add fields from the left panel (e.g. <code>{{record.field_name}}</code>).</p>
  <table class="signature-table">
    <tr>
      <td>Prepared By<br/><strong>{{system.prepared_by}}</strong></td>
      <td>Approved By<br/><strong>{{system.approved_by}}</strong></td>
    </tr>
  </table>
</div>`;
}

export function defaultPrintableCss(): string {
  return `/* Printable Styles */
@page { size: Letter; margin: 0.5in; }
.printable-container {
  width: 100%;
  max-width: 7.5in;
  margin: 0 auto;
  font-family: Inter, Helvetica, Arial, sans-serif;
  font-size: 10pt;
  color: #111;
}
.header-table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
.company-name { font-weight: 700; font-size: 12pt; color: {{theme.accent}}; }
.document-title { font-weight: 700; font-size: 14pt; text-align: right; }
.section-title {
  margin: 16px 0 8px;
  font-size: 11pt;
  border-bottom: 1px solid #ccc;
  letter-spacing: 0.02em;
}
.divider { border-top: 1px solid #ccc; margin: 12px 0; }
.signature-table { width: 100%; margin-top: 32px; border-collapse: collapse; }
.signature-table td { width: 50%; vertical-align: top; padding-top: 24px; }
.company-logo { max-height: 64px; max-width: 180px; }
`;
}
