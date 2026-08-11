import { describe, expect, it } from "vitest";

import type {
  EApprovalWorkflowCondition,
  EApprovalWorkflowStepInput,
} from "@/modules/e-approval/types";
import {
  alignNearMissBranchThresholds,
  appendIfElseBranchSteps,
  branchGroupLabel,
  createComplementaryIfElseConditions,
  createThresholdLadderWhens,
  detectExclusiveBranchGroups,
  detectExclusiveThresholdLadders,
  detectNearMissBranchPairs,
  getExclusiveThresholdCondition,
  insertIfElseBranchSteps,
  insertThresholdLadderSteps,
  isCanonicalThresholdLadder,
  parseThresholdBand,
  toWorkflowEditorSegments,
  updateExclusiveBranchThreshold,
  updateExclusiveLadderThresholds,
} from "@/modules/e-approval/workflow-branch-groups";
import { parseStepWhen } from "@/modules/e-approval/workflow-conditions";

function stepWithWhen(
  when: EApprovalWorkflowCondition[],
  approverId = "user-a",
): EApprovalWorkflowStepInput {
  return {
    type: "user",
    approverId,
    when,
  };
}

describe("workflow branch groups", () => {
  it("builds complementary lte / gt conditions", () => {
    const { ifWhen, elseWhen } = createComplementaryIfElseConditions("requested_amount", "5000");

    expect(ifWhen).toEqual([{ field: "requested_amount", operator: "lte", value: "5000" }]);
    expect(elseWhen).toEqual([{ field: "requested_amount", operator: "gt", value: "5000" }]);
  });

  it("appends two fixed-user steps with exclusive conditions", () => {
    const next = appendIfElseBranchSteps(
      [{ type: "user", approverId: "shared", step_order: 1 }],
      {
        field: "amount",
        threshold: "1000",
        ifApproverId: "mgr-low",
        elseApproverId: "mgr-high",
      },
    );

    expect(next).toHaveLength(3);
    expect(next[1].approverId).toBe("mgr-low");
    expect(next[2].approverId).toBe("mgr-high");
    expect(parseStepWhen(next[1])).toEqual([{ field: "amount", operator: "lte", value: "1000" }]);
    expect(parseStepWhen(next[2])).toEqual([{ field: "amount", operator: "gt", value: "1000" }]);
  });

  it("detects adjacent lte/gt pairs as an exclusive branch group", () => {
    const steps = [
      stepWithWhen([{ field: "amount", operator: "lte", value: "5000" }], "a"),
      stepWithWhen([{ field: "amount", operator: "gt", value: "5000" }], "b"),
      { type: "user", approverId: "finance", step_order: 3 },
    ];

    const groups = detectExclusiveBranchGroups(steps);
    expect(groups).toHaveLength(1);
    expect(groups[0]).toMatchObject({
      startIndex: 0,
      lowIndex: 0,
      highIndex: 1,
      field: "amount",
      threshold: "5000",
      lowOperator: "lte",
      highOperator: "gt",
    });
  });

  it("detects reverse order gt then lte as the same logical branch", () => {
    const steps = [
      stepWithWhen([{ field: "amount", operator: "gt", value: "5000" }], "high"),
      stepWithWhen([{ field: "amount", operator: "lte", value: "5000" }], "low"),
    ];

    const groups = detectExclusiveBranchGroups(steps);
    expect(groups).toHaveLength(1);
    expect(groups[0].lowIndex).toBe(1);
    expect(groups[0].highIndex).toBe(0);
  });

  it("detects lt / gte complementary pairs", () => {
    const steps = [
      stepWithWhen([{ field: "non_po", operator: "lt", value: "100" }], "a"),
      stepWithWhen([{ field: "non_po", operator: "gte", value: "100" }], "b"),
    ];

    expect(detectExclusiveBranchGroups(steps)[0]).toMatchObject({
      lowOperator: "lt",
      highOperator: "gte",
      threshold: "100",
    });
  });

  it("does not pair steps with mismatched fields or thresholds", () => {
    expect(
      detectExclusiveBranchGroups([
        stepWithWhen([{ field: "amount", operator: "lte", value: "5000" }]),
        stepWithWhen([{ field: "amount", operator: "gt", value: "10000" }]),
      ]),
    ).toEqual([]);

    expect(
      detectExclusiveBranchGroups([
        stepWithWhen([{ field: "amount", operator: "lte", value: "5000" }]),
        stepWithWhen([{ field: "other", operator: "gt", value: "5000" }]),
      ]),
    ).toEqual([]);
  });

  it("detects near-miss pairs when thresholds differ (5000 vs 500)", () => {
    const steps = [
      stepWithWhen([{ field: "non_po", operator: "lte", value: "5000" }], "a"),
      stepWithWhen([{ field: "non_po", operator: "gt", value: "500" }], "b"),
    ];

    expect(detectExclusiveBranchGroups(steps)).toEqual([]);
    expect(detectNearMissBranchPairs(steps)).toEqual([
      expect.objectContaining({
        startIndex: 0,
        field: "non_po",
        lowThreshold: "5000",
        highThreshold: "500",
      }),
    ]);
  });

  it("aligns near-miss thresholds so steps group as If/Else", () => {
    const steps = [
      stepWithWhen([{ field: "non_po", operator: "lte", value: "5000" }], "a"),
      stepWithWhen([{ field: "non_po", operator: "gt", value: "500" }], "b"),
    ];
    const nearMiss = detectNearMissBranchPairs(steps)[0];
    const aligned = alignNearMissBranchThresholds(steps, nearMiss, "5000");

    expect(detectNearMissBranchPairs(aligned)).toEqual([]);
    expect(detectExclusiveBranchGroups(aligned)).toHaveLength(1);
    expect(parseStepWhen(aligned[1])).toEqual([{ field: "non_po", operator: "gt", value: "5000" }]);
  });

  it("inserts if/else branch at a given index", () => {
    const next = insertIfElseBranchSteps(
      [
        { type: "user", approverId: "first", step_order: 1 },
        { type: "user", approverId: "last", step_order: 2 },
      ],
      {
        field: "amount",
        threshold: "100",
        ifApproverId: "low",
        elseApproverId: "high",
      },
      1,
    );

    expect(next.map((step) => step.approverId)).toEqual(["first", "low", "high", "last"]);
    expect(next.map((step) => step.step_order)).toEqual([1, 2, 3, 4]);
  });

  it("does not pair multi-condition or always-run steps", () => {
    expect(getExclusiveThresholdCondition({ type: "user", approverId: "a" })).toBeNull();
    expect(
      getExclusiveThresholdCondition(
        stepWithWhen([
          { field: "amount", operator: "lte", value: "5000" },
          { field: "region", operator: "equals", value: "MY" },
        ]),
      ),
    ).toBeNull();
  });

  it("builds editor segments with a ladder then shared step", () => {
    const steps = [
      stepWithWhen([{ field: "amount", operator: "lte", value: "5000" }], "a"),
      stepWithWhen([{ field: "amount", operator: "gt", value: "5000" }], "b"),
      { type: "user", approverId: "finance" },
    ];

    expect(toWorkflowEditorSegments(steps)).toEqual([
      {
        type: "ladder",
        ladder: expect.objectContaining({
          startIndex: 0,
          endIndex: 1,
          field: "amount",
          thresholds: ["5000"],
        }),
      },
      { type: "single", index: 2 },
    ]);
  });

  it("builds multi-threshold ladder whens and inserts bands", () => {
    expect(createThresholdLadderWhens("amount", ["20000", "5000"])).toEqual([
      [{ field: "amount", operator: "lte", value: "5000" }],
      [
        { field: "amount", operator: "gt", value: "5000" },
        { field: "amount", operator: "lte", value: "20000" },
      ],
      [{ field: "amount", operator: "gt", value: "20000" }],
    ]);

    const next = insertThresholdLadderSteps(
      [],
      {
        field: "amount",
        thresholds: ["5000", "20000"],
        approverIds: ["a", "b", "c"],
      },
    );

    expect(next).toHaveLength(3);
    expect(parseStepWhen(next[1])).toEqual([
      { field: "amount", operator: "gt", value: "5000" },
      { field: "amount", operator: "lte", value: "20000" },
    ]);
    expect(detectExclusiveThresholdLadders(next)).toHaveLength(1);
    expect(toWorkflowEditorSegments(next)[0]).toMatchObject({
      type: "ladder",
      ladder: { thresholds: ["5000", "20000"], bandIndexes: [0, 1, 2] },
    });
  });

  it("parses middle bands with two conditions", () => {
    const band = parseThresholdBand(
      stepWithWhen([
        { field: "amount", operator: "gt", value: "5000" },
        { field: "amount", operator: "lte", value: "20000" },
      ]),
    );
    expect(band).toEqual({
      field: "amount",
      lower: { operator: "gt", value: "5000" },
      upper: { operator: "lte", value: "20000" },
    });
    expect(isCanonicalThresholdLadder([
      { field: "amount", lower: null, upper: { operator: "lte", value: "5000" } },
      band!,
      { field: "amount", lower: { operator: "gt", value: "20000" }, upper: null },
    ])).toBe(true);
  });

  it("formats branch labels", () => {
    const labels = branchGroupLabel(
      {
        startIndex: 0,
        lowIndex: 0,
        highIndex: 1,
        field: "requested_amount",
        threshold: "5000",
        lowOperator: "lte",
        highOperator: "gt",
      },
      "Requested amount",
    );

    expect(labels.header).toBe("If / Else — Requested amount");
    expect(labels.ifLabel).toBe("Requested amount ≤ 5000");
    expect(labels.elseLabel).toBe("Requested amount > 5000");
  });

  it("updates both If/Else sides when the shared threshold changes", () => {
    const steps = [
      stepWithWhen([{ field: "non_po", operator: "lte", value: "5000" }], "a"),
      stepWithWhen([{ field: "non_po", operator: "gt", value: "5000" }], "b"),
    ];
    const group = detectExclusiveBranchGroups(steps)[0];
    const next = updateExclusiveBranchThreshold(steps, group, "non_po", "2000");

    expect(parseStepWhen(next[0])).toEqual([{ field: "non_po", operator: "lte", value: "2000" }]);
    expect(parseStepWhen(next[1])).toEqual([{ field: "non_po", operator: "gt", value: "2000" }]);
    expect(detectExclusiveBranchGroups(next)).toHaveLength(1);
    expect(detectNearMissBranchPairs(next)).toEqual([]);
  });

  it("rewrites ladder bands when a boundary changes without ungrouping", () => {
    const steps = insertThresholdLadderSteps([], {
      field: "non_po",
      thresholds: ["5000", "20000"],
      approverIds: ["a", "b", "c"],
    });
    const ladder = detectExclusiveThresholdLadders(steps)[0];
    const next = updateExclusiveLadderThresholds(steps, ladder, "non_po", ["2000", "20000"]);

    expect(next.map((step) => step.approverId)).toEqual(["a", "b", "c"]);
    expect(parseStepWhen(next[0])).toEqual([{ field: "non_po", operator: "lte", value: "2000" }]);
    expect(parseStepWhen(next[1])).toEqual([
      { field: "non_po", operator: "gt", value: "2000" },
      { field: "non_po", operator: "lte", value: "20000" },
    ]);
    expect(parseStepWhen(next[2])).toEqual([{ field: "non_po", operator: "gt", value: "20000" }]);
    expect(detectExclusiveThresholdLadders(next)).toHaveLength(1);
  });
});
