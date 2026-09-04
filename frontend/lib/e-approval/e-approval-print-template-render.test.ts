import { describe, expect, it } from "vitest";

import {
  buildEApprovalDocumentDesignPreviewPayload,
  defaultEApprovalDocumentDesignCss,
  defaultEApprovalDocumentDesignHtml,
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
      { key: "subsidiary", label: "Subsidiary", value: "ATC" },
      { key: "notes", label: "Notes", value: "<script>x</script>" },
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
});

describe("print design helpers", () => {
  it("detects custom document design", () => {
    expect(hasCustomPrintDocumentDesign({ template_html: "<p>Hi</p>" })).toBe(true);
    expect(hasCustomPrintDocumentDesign({ template_html: "   " })).toBe(false);
    expect(hasCustomPrintDocumentDesign({})).toBe(false);
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

  it("builds letterhead and field rows from form fields", () => {
    const html = defaultEApprovalDocumentDesignHtml("Leave Request", [
      { name: "subsidiary", label: "Subsidiary", type: "select" },
      { name: "reason", label: "Reason", type: "textarea" },
      { name: "heading", label: "Ignore", type: "section" },
    ]);
    expect(html).toContain("ea-form-doc");
    expect(html).toContain("{{system.form_name}}");
    expect(html).toContain("{{system.document_no}}");
    expect(html).toContain("{{system.subsidiary_logo}}");
    expect(html).toContain("{{field.subsidiary}}");
    expect(html).toContain("{{field.reason}}");
    expect(html).toContain("ea-form-row--wide");
    expect(html).not.toContain("ea-form-kicker");
    expect(html).not.toContain("{{field.heading}}");
    expect(html).not.toContain("ea-form-signoff");
    expect(html).not.toContain("See approval history");

    const rendered = renderEApprovalPrintTemplateHtml(html, samplePayload({
      form_name: "Leave Request",
      fields: [
        { key: "subsidiary", label: "Subsidiary", value: "ATC" },
        { key: "reason", label: "Reason", value: "Medical" },
      ],
    }));
    expect(rendered).toContain("Leave Request");
    expect(rendered).toContain("ATC");
    expect(rendered).toContain("Medical");
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

  it("includes print CSS with form-style classes", () => {
    const css = defaultEApprovalDocumentDesignCss();
    expect(css).toContain("@page");
    expect(css).toContain(".ea-form-letterhead");
    expect(css).toContain(".ea-form-grid");
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
});
