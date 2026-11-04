import { describe, expect, it } from "vitest";

import type { EApprovalWorkflowStepInput } from "@/modules/e-approval/types";
import {
  groupWorkflowStepsIntoBands,
  insertWorkflowStepAt,
  insertWorkflowStepsAt,
  moveWorkflowStepBand,
  removeWorkflowBandAt,
  reorderWorkflowStepBands,
} from "@/modules/e-approval/workflow-band-reorder";

function step(
  partial: Partial<EApprovalWorkflowStepInput> & { approverId: string; step_order: number },
): EApprovalWorkflowStepInput {
  return {
    type: "user",
    ...partial,
  };
}

describe("workflow-band-reorder", () => {
  it("groups parallel siblings into one band", () => {
    const steps = [
      step({ id: "a", approverId: "a", step_order: 1 }),
      step({ id: "b1", approverId: "b1", step_order: 2, parallel_mode: "any" }),
      step({ id: "b2", approverId: "b2", step_order: 2, parallel_mode: "any" }),
      step({ id: "c", approverId: "c", step_order: 3 }),
    ];

    const bands = groupWorkflowStepsIntoBands(steps);
    expect(bands).toHaveLength(3);
    expect(bands[1].memberIndexes).toEqual([1, 2]);
    expect(bands[1].bandLabel).toMatch(/Any one/i);
  });

  it("reorders bands and keeps parallel ties on the same order", () => {
    const steps = [
      step({ id: "a", approverId: "a", step_order: 1 }),
      step({ id: "b1", approverId: "b1", step_order: 2, parallel_mode: "all" }),
      step({ id: "b2", approverId: "b2", step_order: 2, parallel_mode: "all" }),
      step({ id: "c", approverId: "c", step_order: 3 }),
    ];

    // Move band 0 (A) after parallel band → A becomes last
    const next = reorderWorkflowStepBands(steps, 0, 2);
    expect(next.map((s) => s.approverId)).toEqual(["b1", "b2", "c", "a"]);
    expect(next.map((s) => s.step_order)).toEqual([1, 1, 2, 3]);
  });

  it("moves a band up one position", () => {
    const steps = [
      step({ id: "a", approverId: "a", step_order: 1 }),
      step({ id: "b", approverId: "b", step_order: 2 }),
      step({ id: "c", approverId: "c", step_order: 3 }),
    ];

    const next = moveWorkflowStepBand(steps, 2, "up");
    expect(next.map((s) => s.approverId)).toEqual(["a", "c", "b"]);
    expect(next.map((s) => s.step_order)).toEqual([1, 2, 3]);
  });

  it("is a no-op when indexes are out of range", () => {
    const steps = [
      step({ id: "a", approverId: "a", step_order: 1 }),
      step({ id: "b", approverId: "b", step_order: 2 }),
    ];
    expect(reorderWorkflowStepBands(steps, 0, 5)).toBe(steps);
    expect(moveWorkflowStepBand(steps, 0, "up")).toBe(steps);
  });

  it("inserts a step between bands instead of appending to the end order", () => {
    const steps = [
      step({ id: "a", approverId: "a", step_order: 1 }),
      step({ id: "b", approverId: "b", step_order: 2 }),
      step({ id: "c", approverId: "c", step_order: 3 }),
    ];

    const next = insertWorkflowStepAt(
      steps,
      { type: "user", approverId: "x", step_order: 99 },
      1,
    );

    expect(next.map((s) => s.approverId)).toEqual(["a", "x", "b", "c"]);
    expect(next.map((s) => s.step_order)).toEqual([1, 2, 3, 4]);
  });

  it("removes a parallel band together", () => {
    const steps = [
      step({ id: "a", approverId: "a", step_order: 1 }),
      step({ id: "b1", approverId: "b1", step_order: 2, parallel_mode: "all" }),
      step({ id: "b2", approverId: "b2", step_order: 2, parallel_mode: "all" }),
      step({ id: "c", approverId: "c", step_order: 3 }),
    ];

    const next = removeWorkflowBandAt(steps, 1);
    expect(next.map((s) => s.approverId)).toEqual(["a", "c"]);
    expect(next.map((s) => s.step_order)).toEqual([1, 2]);
  });

  it("inserts a parallel band and a later ladder without merging them into the source", () => {
    const steps = [
      step({ id: "a", approverId: "a", step_order: 1 }),
      step({ id: "p1", approverId: "p1", step_order: 2, parallel_mode: "any" }),
      step({ id: "p2", approverId: "p2", step_order: 2, parallel_mode: "any" }),
      step({ id: "low", approverId: "low", step_order: 3 }),
      step({ id: "high", approverId: "high", step_order: 4 }),
    ];

    const parallelCopy = insertWorkflowStepsAt(
      steps,
      [
        step({ approverId: "p1", step_order: 2, parallel_mode: "any" }),
        step({ approverId: "p2", step_order: 2, parallel_mode: "any" }),
      ],
      3,
    );
    expect(parallelCopy.map((row) => row.approverId)).toEqual(["a", "p1", "p2", "p1", "p2", "low", "high"]);
    expect(parallelCopy.map((row) => row.step_order)).toEqual([1, 2, 2, 3, 3, 4, 5]);

    const ladderCopy = insertWorkflowStepsAt(
      parallelCopy,
      [
        step({ approverId: "low", step_order: 4 }),
        step({ approverId: "high", step_order: 5 }),
      ],
      7,
    );
    expect(ladderCopy.map((row) => row.step_order)).toEqual([1, 2, 2, 3, 3, 4, 5, 6, 7]);
  });
});
