import { describe, expect, it } from "vitest";

import { formTemplateGroupId, groupFormTemplates } from "./form-template-groups";
import type { EApprovalFormTemplate } from "./types";

function template(id: string, category: string, name = id): EApprovalFormTemplate {
  return {
    id,
    name,
    description: "",
    category,
    field_count: 1,
    step_count: 1,
    source: "system",
  };
}

describe("groupFormTemplates", () => {
  it("groups known system templates in a stable order", () => {
    const groups = groupFormTemplates([
      template("leave_request", "hr", "Leave request"),
      template("cash_advance", "finance", "Cash advance"),
      template("purchase_order", "procurement", "Purchase order"),
      template("site_document_review", "documents", "Site document review"),
    ]);

    expect(groups.map((group) => group.id)).toEqual(["finance", "procurement", "hr", "documents"]);
    expect(groups[0]?.templates.map((item) => item.id)).toEqual(["cash_advance"]);
    expect(groups[1]?.templates.map((item) => item.id)).toEqual(["purchase_order"]);
  });

  it("puts CAPEX with procurement even when the API category is finance", () => {
    expect(formTemplateGroupId({ id: "purchase_request", category: "finance" })).toBe("procurement");
  });

  it("falls back to Other for unknown tenant templates", () => {
    const groups = groupFormTemplates([template("custom_site_access", "general", "Site access")]);
    expect(groups).toHaveLength(1);
    expect(groups[0]?.id).toBe("general");
    expect(groups[0]?.label).toBe("Other");
  });
});
