import { describe, expect, it } from "vitest";

import {
  parseStepWhenLogic,
  patchStepWhen,
  patchStepWhenLogic,
  whenSummary,
} from "@/modules/e-approval/workflow-conditions";

describe("workflow when logic", () => {
  it("defaults to and and persists or", () => {
    const step = {
      type: "user",
      approverId: "u1",
      when: [
        { field: "urgent", operator: "equals" as const, value: "yes" },
        { field: "amount", operator: "gt" as const, value: "5000" },
      ],
    };

    expect(parseStepWhenLogic(step)).toBe("and");
    const next = patchStepWhenLogic(step, "or");
    expect(next.when_logic).toBe("or");
    expect(next.condition?.when_logic).toBe("or");
    expect(parseStepWhenLogic(next)).toBe("or");
  });

  it("summarizes with OR joiner", () => {
    const step = patchStepWhen(
      { type: "user", approverId: "u1" },
      [
        { field: "urgent", operator: "equals", value: "yes" },
        { field: "amount", operator: "gt", value: "5000" },
      ],
      "or",
    );

    expect(whenSummary(step, [
      { type: "text", name: "urgent", label: "Urgent" },
      { type: "number", name: "amount", label: "Amount" },
    ])).toContain(" OR ");
  });
});
