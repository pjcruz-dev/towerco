import { describe, expect, it } from "vitest";

import {
  buildEApprovalDocumentDesignPreviewPayload,
  defaultEApprovalDocumentDesignCss,
  defaultEApprovalDocumentDesignHtml,
  documentDesignEmbedsGrids,
  documentDesignPreviewRecommendations,
  hasCustomPrintDocumentDesign,
  printableDesignFields,
  renderEApprovalPrintTemplateHtml,
  shouldAppendPrintAttachments,
} from "@/lib/e-approval/e-approval-print-template-render";
import type { EApprovalPrintPayload } from "@/modules/e-approval/types";

function samplePayload(overrides: Partial<EApprovalPrintPayload> = {}): EApprovalPrintPayload {
  return {
    document_no: "EA-100",
    form_name: "CRF Request",
    status: "in_progress",
    requestor: "Ada Admin",
    created_at: "2026-09-04T01:00:00Z",
    brand_logo_url: null,
    fields: [
      { key: "subsidiary", label: "Subsidiary", value: "ATC", field_type: "select" },
      { key: "notes", label: "Notes", value: "<script>x</script>", field_type: "textarea" },
    ],
    approvals: [],
    attachments: [],
    template: {},
    show_approval_trail: true,
    ...overrides,
  };
}

describe("renderEApprovalPrintTemplateHtml", () => {
  it("merges field and system tokens and escapes HTML text", () => {
    const html = renderEApprovalPrintTemplateHtml(
      "<p>{{system.document_no}} — {{field.subsidiary}} — {{field.notes}}</p>",
      samplePayload(),
    );
    expect(html).toContain("EA-100");
    expect(html).toContain("ATC");
    expect(html).toContain("&lt;script&gt;x&lt;/script&gt;");
    expect(html).not.toContain("<script>");
  });

  it("returns empty for blank template", () => {
    expect(renderEApprovalPrintTemplateHtml("  ", samplePayload())).toBe("");
  });

  it("renders dynamic form_body with scalar fields and grid tables", () => {
    const html = renderEApprovalPrintTemplateHtml("{{system.form_body}}", samplePayload({
      fields: [
        { key: "subsidiary", label: "Subsidiary", value: "ATC", field_type: "select" },
        {
          key: "expense_lines",
          label: "Credit card expenses",
          value: "Sample Credit card expenses",
          field_type: "grid",
        },
      ],
      grids: [
        {
          key: "expense_lines",
          label: "Credit card expenses",
          columns: ["Date", "Merchant", "Amount"],
          rows: [["2026-09-01", "Cafe", "120.00"]],
        },
      ],
    }));

    expect(html).toContain("Request details");
    expect(html).toContain("Subsidiary");
    expect(html).toContain("ATC");
    expect(html).toContain("ea-print-table");
    expect(html).toContain("Credit card expenses");
    expect(html).toContain("Merchant");
    expect(html).toContain("Cafe");
    expect(html).not.toContain("Sample Credit card expenses");
  });

  it("moves Total* fields below grids and adds numeric column footers", () => {
    const html = renderEApprovalPrintTemplateHtml("{{system.form_body}}", samplePayload({
      fields: [
        { key: "subsidiary", label: "Subsidiary", value: "ATC", field_type: "select" },
        { key: "total_personal", label: "Total personal", value: "7000", field_type: "currency" },
        { key: "total_official", label: "Total official", value: "8000", field_type: "currency" },
        { key: "total_expenses", label: "Total expenses", value: "15000", field_type: "currency" },
      ],
      grids: [
        {
          key: "expense_lines",
          label: "Credit card expenses",
          columns: ["Date", "Personal", "Official", "Total"],
          rows: [
            ["2026-09-01", "3,000.00", "4,000.00", "7,000.00"],
            ["2026-09-02", "4,000.00", "4,000.00", "8,000.00"],
          ],
        },
      ],
    }));

    const requestIdx = html.indexOf("Request details");
    const gridIdx = html.indexOf("Credit card expenses");
    const totalsIdx = html.indexOf("ea-form-totals-section");
    expect(requestIdx).toBeGreaterThan(-1);
    expect(gridIdx).toBeGreaterThan(requestIdx);
    expect(totalsIdx).toBeGreaterThan(gridIdx);
    expect(html.indexOf("Total personal")).toBeGreaterThan(totalsIdx);
    expect(html).toContain("ea-print-table-totals");
    expect(html).toContain("<strong>7,000</strong>");
    expect(html).toContain("<strong>8,000</strong>");
    expect(html).toContain("<strong>15,000</strong>");
    expect(html).toContain("Total expenses");
    // Request details should not list Total personal above the grid.
    const requestBlock = html.slice(requestIdx, gridIdx);
    expect(requestBlock).not.toContain("Total personal");
  });

  it("renders a single grid via {{grid.*}} without escaping table HTML", () => {
    const html = renderEApprovalPrintTemplateHtml("{{grid.expense_lines}}", samplePayload({
      grids: [
        {
          key: "expense_lines",
          label: "Expenses",
          columns: ["Item", "Qty"],
          rows: [["Laptop", "1"]],
        },
      ],
    }));
    expect(html).toContain("<table");
    expect(html).toContain("Laptop");
    expect(html).not.toContain("&lt;table");
  });
});

describe("print design helpers", () => {
  it("detects custom document design", () => {
    expect(hasCustomPrintDocumentDesign({ template_html: "<p>Hi</p>" })).toBe(true);
    expect(hasCustomPrintDocumentDesign({ template_html: "   " })).toBe(false);
    expect(hasCustomPrintDocumentDesign({})).toBe(false);
  });

  it("detects when design embeds grids", () => {
    expect(documentDesignEmbedsGrids("{{system.form_body}}")).toBe(true);
    expect(documentDesignEmbedsGrids("{{system.form_grids}}")).toBe(true);
    expect(documentDesignEmbedsGrids("{{grid.expense_lines}}")).toBe(true);
    expect(documentDesignEmbedsGrids("{{field.notes}}")).toBe(false);
  });

  it("defaults append attachments to true", () => {
    expect(shouldAppendPrintAttachments({})).toBe(true);
    expect(shouldAppendPrintAttachments({ footer: { appendAttachments: true } })).toBe(true);
    expect(shouldAppendPrintAttachments({ footer: { appendAttachments: false } })).toBe(false);
  });
});

describe("form-style starter layout", () => {
  it("filters non-body field types", () => {
    const printable = printableDesignFields([
      { name: "subsidiary", label: "Subsidiary", type: "select" },
      { name: "sec", label: "Section", type: "section" },
      { name: "notes", label: "Notes", type: "textarea" },
    ]);
    expect(printable.map((f) => f.name)).toEqual(["subsidiary", "notes"]);
  });

  it("builds letterhead with dynamic form_body token", () => {
    const html = defaultEApprovalDocumentDesignHtml("Leave Request", [
      { name: "subsidiary", label: "Subsidiary", type: "select" },
      { name: "reason", label: "Reason", type: "textarea" },
      { name: "heading", label: "Ignore", type: "section" },
    ]);
    expect(html).toContain("ea-form-doc");
    expect(html).toContain("{{system.form_name}}");
    expect(html).toContain("{{system.document_no}}");
    expect(html).toContain("{{system.subsidiary_logo}}");
    expect(html).toContain("{{system.form_body}}");
    expect(html).not.toContain("{{field.subsidiary}}");
    expect(html).not.toContain("ea-form-kicker");
    expect(html).not.toContain("ea-form-signoff");

    const rendered = renderEApprovalPrintTemplateHtml(html, samplePayload({
      form_name: "Leave Request",
      fields: [
        { key: "subsidiary", label: "Subsidiary", value: "ATC", field_type: "select" },
        { key: "reason", label: "Reason", value: "Medical", field_type: "textarea" },
      ],
    }));
    expect(rendered).toContain("Leave Request");
    expect(rendered).toContain("ATC");
    expect(rendered).toContain("Medical");
    expect(rendered).toContain("Request details");
  });

  it("strips legacy static sign-off boxes when rendering", () => {
    const html = `<div>Body</div>
<footer class="ea-form-signoff">
  <p>Approval signatures are stamped automatically</p>
  <div class="ea-form-sign-box">See approval history</div>
</footer>`;
    const rendered = renderEApprovalPrintTemplateHtml(html, samplePayload());
    expect(rendered).toContain("Body");
    expect(rendered).not.toContain("ea-form-signoff");
    expect(rendered).not.toContain("See approval history");
  });

  it("resolves subsidiary_logo from dynamic map and falls back to company logo", () => {
    const withAtc = renderEApprovalPrintTemplateHtml("{{system.subsidiary_logo}}", samplePayload({
      brand_logo_url: "/api/v1/e-approval/forms/x/logo",
      subsidiary_logos: {
        ATC: "/api/v1/e-approval/forms/x/subsidiary-logos/ATC",
        ADIC: "/api/v1/e-approval/forms/x/subsidiary-logos/ADIC",
      },
      fields: [{ key: "subsidiary", label: "Subsidiary", value: "ATC" }],
      template: { subsidiary_logo_field: "subsidiary" },
    }));
    expect(withAtc).toContain("subsidiary-logos/ATC");
    expect(withAtc).not.toContain("subsidiary-logos/ADIC");

    const withAdic = renderEApprovalPrintTemplateHtml("{{system.subsidiary_logo}}", samplePayload({
      brand_logo_url: "/api/v1/e-approval/forms/x/logo",
      subsidiary_logos: {
        ATC: "/api/v1/e-approval/forms/x/subsidiary-logos/ATC",
        ADIC: "/api/v1/e-approval/forms/x/subsidiary-logos/ADIC",
      },
      fields: [{ key: "subsidiary", label: "Subsidiary", value: "adic" }],
      template: { subsidiary_logo_field: "subsidiary" },
    }));
    expect(withAdic).toContain("subsidiary-logos/ADIC");

    const fallback = renderEApprovalPrintTemplateHtml("{{system.subsidiary_logo}}", samplePayload({
      brand_logo_url: "/api/v1/e-approval/forms/x/logo",
      subsidiary_logos: {},
      fields: [{ key: "subsidiary", label: "Subsidiary", value: "OTHER" }],
      template: { subsidiary_logo_field: "subsidiary" },
    }));
    expect(fallback).toContain("/api/v1/e-approval/forms/x/logo");
  });

  it("preview payload injects subsidiary sample when logos exist without field", () => {
    const payload = buildEApprovalDocumentDesignPreviewPayload(
      [{ name: "purpose", label: "Purpose", type: "textarea" }],
      "Cash advance",
      {
        subsidiary_logos: {
          ATC: "/api/v1/e-approval/forms/x/subsidiary-logos/ATC",
        },
      },
    );
    expect(payload.fields.some((f) => f.key === "subsidiary" && f.value === "ATC")).toBe(true);
    const html = renderEApprovalPrintTemplateHtml("{{system.subsidiary_logo}}", payload);
    expect(html).toContain("subsidiary-logos/ATC");
  });

  it("preview payload builds sample grids for grid fields", () => {
    const payload = buildEApprovalDocumentDesignPreviewPayload(
      [
        { name: "purpose", label: "Purpose", type: "textarea" },
        {
          name: "expense_lines",
          label: "Credit card expenses",
          type: "grid",
          grid_columns: ["Date", "Merchant", "Amount"],
        },
      ],
      "Credit Card Expense Report",
    );
    expect(payload.grids?.[0]?.key).toBe("expense_lines");
    expect(payload.grids?.[0]?.columns).toEqual(["Date", "Merchant", "Amount"]);
    expect(payload.grids?.[0]?.rows.length).toBeGreaterThan(0);
    expect(payload.fields.some((f) => f.key === "expense_lines")).toBe(false);

    const html = renderEApprovalPrintTemplateHtml(
      defaultEApprovalDocumentDesignHtml(),
      payload,
    );
    expect(html).toContain("Credit card expenses");
    expect(html).toContain("Merchant");
    expect(html).toContain("ea-print-table");
  });

  it("includes print CSS with form-style and table classes", () => {
    const css = defaultEApprovalDocumentDesignCss();
    expect(css).toContain("@page");
    expect(css).toContain(".ea-form-letterhead");
    expect(css).toContain(".ea-form-grid");
    expect(css).toContain(".ea-print-table");
  });
});

describe("documentDesignPreviewRecommendations", () => {
  it("suggests starter layout when empty", () => {
    const tips = documentDesignPreviewRecommendations("", "", 3);
    expect(tips[0]).toMatch(/starter layout/i);
  });

  it("asks for fields when form has none", () => {
    const tips = documentDesignPreviewRecommendations("", "", 0);
    expect(tips[0]).toMatch(/Add fields/i);
  });

  it("suggests migrating legacy per-field tokens to form_body", () => {
    const tips = documentDesignPreviewRecommendations(
      "<div>{{field.notes}}</div>",
      "@page { size: A4; }",
      2,
    );
    expect(tips.some((tip) => /form_body/i.test(tip))).toBe(true);
  });
});
