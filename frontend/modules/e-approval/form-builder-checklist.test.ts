import { describe, expect, it } from "vitest";

import { buildFormPublishChecklist } from "@/modules/e-approval/form-builder-checklist";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";

function field(name: string, type: string, label?: string, options?: Record<string, unknown>): EApprovalFormFieldInput {
  return { name, type, label: label ?? name, step_order: 1, options: options ?? null };
}

describe("form publish checklist compose", () => {
  it("blocks publish when stepped mode has fewer than two steps", () => {
    const items = buildFormPublishChecklist({
      formName: "Test",
      fields: [field("section_a", "section", "Only"), field("title", "text")],
      steps: [{ type: "user", approverId: "u1", step_order: 1 }],
      requireWorkflow: true,
      composeSettings: {
        mode: "stepped",
        stepSource: "sections",
        showProgress: true,
        validateOnNext: true,
        allowBack: true,
        includeReviewStep: false,
      },
    });

    expect(items.some((item) => item.level === "error" && item.message.includes("two sections"))).toBe(true);
  });
});

describe("form publish checklist workflow health", () => {
  it("warns when near-miss if/else thresholds differ", () => {
    const items = buildFormPublishChecklist({
      formName: "Test",
      fields: [field("amount", "number", "Amount")],
      steps: [
        {
          type: "user",
          approverId: "a",
          when: [{ field: "amount", operator: "lte", value: "5000" }],
          step_order: 1,
        },
        {
          type: "user",
          approverId: "b",
          when: [{ field: "amount", operator: "gt", value: "500" }],
          step_order: 2,
        },
      ],
      requireWorkflow: true,
    });

    expect(items.some((item) => item.message.includes("thresholds differ"))).toBe(true);
  });

  it("warns when all steps are conditional", () => {
    const items = buildFormPublishChecklist({
      formName: "Test",
      fields: [field("amount", "number")],
      steps: [
        {
          type: "user",
          approverId: "a",
          when: [{ field: "amount", operator: "lte", value: "1" }],
          step_order: 1,
        },
      ],
      requireWorkflow: true,
    });

    expect(items.some((item) => item.message.includes("always-on"))).toBe(true);
  });

  it("warns when field_map has unmapped choices and no default", () => {
    const items = buildFormPublishChecklist({
      formName: "Test",
      fields: [
        field("dept", "select", "Dept", {
          choices: [
            { value: "it", label: "IT" },
            { value: "hr", label: "HR" },
          ],
        }),
      ],
      steps: [
        {
          type: "field_map",
          source_field: "dept",
          mappings: { it: "user-1" },
          step_order: 1,
        },
      ],
      requireWorkflow: true,
    });

    expect(items.some((item) => item.message.includes("unmapped") && item.message.includes("default"))).toBe(true);
  });

  it("warns when manager step has no fallback", () => {
    const items = buildFormPublishChecklist({
      formName: "Test",
      fields: [field("title", "text")],
      steps: [{ type: "manager", step_order: 1 }],
      requireWorkflow: true,
    });

    expect(items.some((item) => item.message.includes("fallback approver"))).toBe(true);
  });
});
