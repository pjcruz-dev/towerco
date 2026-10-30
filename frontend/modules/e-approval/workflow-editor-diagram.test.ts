import { describe, expect, it } from "vitest";

import type { EApprovalWorkflowStepInput } from "@/modules/e-approval/types";
import {
  buildWorkflowEditorDiagram,
  removeWorkflowVisualBandAt,
  reorderWorkflowVisualBands,
} from "@/modules/e-approval/workflow-editor-diagram";

function step(
  partial: Partial<EApprovalWorkflowStepInput> & { approverId?: string; step_order: number },
): EApprovalWorkflowStepInput {
  return {
    type: "user",
    approverId: partial.approverId ?? "u1",
    ...partial,
  };
}

const amountField = [{ type: "number", name: "non_po", label: "Non-PO" }];

describe("buildWorkflowEditorDiagram", () => {
  it("builds parallel bands side-by-side", () => {
    const steps = [
      step({ id: "a", approverId: "a", step_order: 1 }),
      step({ id: "b1", approverId: "b1", step_order: 2, parallel_mode: "any" }),
      step({ id: "b2", approverId: "b2", step_order: 2, parallel_mode: "any" }),
    ];

    const bands = buildWorkflowEditorDiagram(steps, amountField, {
      titleForStep: (s) => s.approverId ?? "?",
    });

    expect(bands).toHaveLength(2);
    expect(bands[1].variant).toBe("parallel");
    expect(bands[1].nodes).toHaveLength(2);
    expect(bands[1].bandLabel).toMatch(/Any one/i);
  });

  it("groups If/Else as one exclusive side-by-side band", () => {
    const steps = [
      step({
        id: "low",
        approverId: "a",
        step_order: 1,
        when: [{ field: "non_po", operator: "lte", value: "5000" }],
      }),
      step({
        id: "high",
        approverId: "b",
        step_order: 2,
        when: [{ field: "non_po", operator: "gt", value: "5000" }],
      }),
      step({ id: "merge", approverId: "c", step_order: 3 }),
    ];

    const bands = buildWorkflowEditorDiagram(steps, amountField, {
      titleForStep: (s) => s.approverId ?? "?",
    });

    expect(bands).toHaveLength(2);
    expect(bands[0].variant).toBe("exclusive");
    expect(bands[0].nodes).toHaveLength(2);
    expect(bands[0].bandLabel).toMatch(/If \/ Else/i);
    expect(bands[0].nodes[0].caseLabel).toMatch(/5000/);
    expect(bands[0].nodes[1].caseLabel).toMatch(/5000/);
    expect(bands[1].variant).toBe("single");
  });

  it("groups threshold ladder as one exclusive band", () => {
    const steps = [
      step({
        id: "l",
        approverId: "a",
        step_order: 1,
        when: [{ field: "non_po", operator: "lte", value: "5000" }],
      }),
      step({
        id: "m",
        approverId: "b",
        step_order: 2,
        when: [
          { field: "non_po", operator: "gt", value: "5000" },
          { field: "non_po", operator: "lte", value: "20000" },
        ],
      }),
      step({
        id: "h",
        approverId: "c",
        step_order: 3,
        when: [{ field: "non_po", operator: "gt", value: "20000" }],
      }),
    ];

    const bands = buildWorkflowEditorDiagram(steps, amountField, {
      titleForStep: (s) => s.approverId ?? "?",
    });

    expect(bands).toHaveLength(1);
    expect(bands[0].variant).toBe("exclusive");
    expect(bands[0].nodes).toHaveLength(3);
    expect(bands[0].bandLabel).toMatch(/Threshold ladder/i);
  });

  it("removes an exclusive visual band together", () => {
    const steps = [
      step({
        id: "low",
        approverId: "a",
        step_order: 1,
        when: [{ field: "non_po", operator: "lte", value: "5000" }],
      }),
      step({
        id: "high",
        approverId: "b",
        step_order: 2,
        when: [{ field: "non_po", operator: "gt", value: "5000" }],
      }),
      step({ id: "merge", approverId: "c", step_order: 3 }),
    ];
    const bands = buildWorkflowEditorDiagram(steps, amountField, {
      titleForStep: (s) => s.approverId ?? "?",
    });
    const next = removeWorkflowVisualBandAt(steps, bands, 0);
    expect(next.map((s) => s.approverId)).toEqual(["c"]);
  });

  it("shows two fixed approvers on one subsidiary case as first to approve", () => {
    const steps = [
      step({
        id: "atc-1",
        approverId: "christian",
        step_order: 2,
        parallel_mode: "any",
        when: [{ field: "subsidiary", operator: "equals", value: "ATC" }],
      }),
      step({
        id: "atc-2",
        approverId: "second",
        step_order: 2,
        parallel_mode: "any",
        when: [{ field: "subsidiary", operator: "equals", value: "ATC" }],
      }),
      step({
        id: "adic",
        approverId: "norelyn",
        step_order: 3,
        when: [{ field: "subsidiary", operator: "equals", value: "ADIC" }],
      }),
    ];
    const fields = [{ type: "select", name: "subsidiary", label: "Subsidiary" }];
    const bands = buildWorkflowEditorDiagram(steps, fields, {
      titleForStep: (row) => row.approverId ?? "?",
    });

    expect(bands).toHaveLength(1);
    expect(bands[0].nodes).toHaveLength(2);
    expect(bands[0].nodes[0].title).toBe("christian or second");
    expect(bands[0].nodes[0].subtitle).toBe("First to approve");
    expect(bands[0].memberIndexes).toEqual([0, 1, 2]);

    const withLead = [
      step({ id: "lead", approverId: "lead", step_order: 1 }),
      ...steps,
    ];
    const leadBands = buildWorkflowEditorDiagram(withLead, fields, {
      titleForStep: (row) => row.approverId ?? "?",
    });
    const moved = reorderWorkflowVisualBands(withLead, leadBands, 1, 0);
    expect(moved[0].approverId).toBe("christian");
    expect(moved[0].step_order).toBe(moved[1].step_order);
    expect(moved[2].approverId).toBe("norelyn");
    expect(moved[2].step_order).not.toBe(moved[0].step_order);
    expect(moved[3].approverId).toBe("lead");
  });

  it("flags incomplete steps with a warning", () => {
    const steps = [step({ id: "x", approverId: "", step_order: 1 })];
    const bands = buildWorkflowEditorDiagram(steps, [], {
      titleForStep: () => "Unset",
    });
    expect(bands[0].nodes[0].warning).toMatch(/Needs approver/i);
  });
});
