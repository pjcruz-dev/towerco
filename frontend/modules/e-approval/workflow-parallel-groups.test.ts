import { describe, expect, it } from "vitest";

import {
  compactWorkflowStepOrdersPreservingTies,
  detectParallelApprovalGroups,
  insertParallelApprovalSteps,
  removeParallelApprovalGroup,
  setParallelGroupMode,
} from "@/modules/e-approval/workflow-parallel-groups";
import { toWorkflowEditorSegments } from "@/modules/e-approval/workflow-branch-groups";

describe("workflow parallel groups", () => {
  it("inserts siblings that share one step_order", () => {
    const next = insertParallelApprovalSteps(
      [{ type: "user", approverId: "first", step_order: 1 }],
      { approverIds: ["legal", "finance"] },
    );

    expect(next).toHaveLength(3);
    expect(next[1].step_order).toBe(2);
    expect(next[2].step_order).toBe(2);
    expect(detectParallelApprovalGroups(next)).toEqual([
      expect.objectContaining({ stepOrder: 2, memberIndexes: [1, 2], mode: "all", quorum: 2 }),
    ]);
  });

  it("inserts any-mode and n_of_m parallel groups", () => {
    const anyGroup = insertParallelApprovalSteps([], {
      approverIds: ["a", "b"],
      mode: "any",
    });
    expect(anyGroup[0].parallel_mode).toBe("any");
    expect(detectParallelApprovalGroups(anyGroup)[0]).toEqual(
      expect.objectContaining({ mode: "any", quorum: 1 }),
    );

    const quorumGroup = insertParallelApprovalSteps([], {
      approverIds: ["a", "b", "c"],
      mode: "n_of_m",
      quorum: 2,
    });
    expect(quorumGroup.every((step) => step.parallel_mode === "n_of_m" && step.parallel_quorum === 2)).toBe(
      true,
    );
    expect(detectParallelApprovalGroups(quorumGroup)[0]).toEqual(
      expect.objectContaining({ mode: "n_of_m", quorum: 2 }),
    );
  });

  it("updates completion mode across all members", () => {
    const steps = insertParallelApprovalSteps([], { approverIds: ["a", "b", "c"] });
    const group = detectParallelApprovalGroups(steps)[0];
    const next = setParallelGroupMode(steps, group, "n_of_m", 2);
    expect(next.every((step) => step.parallel_mode === "n_of_m" && step.parallel_quorum === 2)).toBe(true);
  });

  it("preserves ties when compacting orders", () => {
    const compacted = compactWorkflowStepOrdersPreservingTies([
      { type: "user", approverId: "a", step_order: 5 },
      { type: "user", approverId: "b", step_order: 5 },
      { type: "user", approverId: "c", step_order: 9 },
    ]);

    expect(compacted.map((step) => step.step_order)).toEqual([1, 1, 2]);
  });

  it("renders parallel segments in the workflow editor model", () => {
    const steps = insertParallelApprovalSteps([], { approverIds: ["legal", "finance"] });
    expect(toWorkflowEditorSegments(steps)).toEqual([
      {
        type: "parallel",
        group: expect.objectContaining({ stepOrder: 1, memberIndexes: [0, 1] }),
      },
    ]);
  });

  it("removes an entire parallel group", () => {
    const steps = insertParallelApprovalSteps(
      [{ type: "user", approverId: "tail", step_order: 1 }],
      { approverIds: ["a", "b"] },
      0,
    );
    const group = detectParallelApprovalGroups(steps)[0];
    const next = removeParallelApprovalGroup(steps, group);
    expect(next.map((step) => step.approverId)).toEqual(["tail"]);
    expect(next[0].step_order).toBe(1);
  });
});
