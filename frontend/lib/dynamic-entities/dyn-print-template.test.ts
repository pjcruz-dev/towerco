import { describe, expect, it } from "vitest";

import { expandPrintFieldChips, renderDynPrintTemplate } from "@/lib/dynamic-entities/dyn-print-template";

describe("print field chips", () => {
  it("turns a chip back into its token and then the record value", () => {
    const html =
      '<p><span class="mp-var" contenteditable="false" data-mp-token="{{system.company_name}}" data-mp-label="COMPANY NAME">COMPANY NAME</span></p>';

    const expanded = expandPrintFieldChips(html);
    expect(expanded).toContain("{{system.company_name}}");
    expect(expanded).not.toContain("COMPANY NAME");

    const rendered = renderDynPrintTemplate(html, {
      system: { company_name: "Alliance Towers" },
      record: {},
    });
    expect(rendered).toContain("Alliance Towers");
    expect(rendered).not.toContain("data-mp-token");
  });
});
