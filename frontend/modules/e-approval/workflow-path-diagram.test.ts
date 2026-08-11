import { describe, expect, it } from "vitest";

import {
  buildWorkflowPathDiagram,
  describeParallelBandLabel,
} from "@/modules/e-approval/workflow-path-diagram";

describe("workflow-path-diagram", () => {
  it("groups parallel runs and keeps skipped on a separate rail", () => {
    const diagram = buildWorkflowPathDiagram({
      resolved_steps: [
        {
          step_order: 1,
          type: "user",
          label: "A",
          resolved_user_id: "a",
          resolved_user_name: "Admin",
          resolved_user_email: "a@test",
          warning: null,
          runtime_status: "approved",
          signature: "data:image/png;base64,abc",
        },
        {
          step_order: 2,
          type: "user",
          label: "B1",
          resolved_user_id: "b1",
          resolved_user_name: "Legal",
          resolved_user_email: "l@test",
          parallel_mode: "any",
          warning: null,
          runtime_status: "pending",
        },
        {
          step_order: 2,
          type: "user",
          label: "B2",
          resolved_user_id: "b2",
          resolved_user_name: "Finance",
          resolved_user_email: "f@test",
          parallel_mode: "any",
          warning: null,
          runtime_status: "pending",
        },
      ],
      skipped_steps: [
        {
          step_order: 3,
          type: "user",
          label: "High gate",
          path_reason: "Skipped",
        },
      ],
    });

    expect(diagram.runBands).toHaveLength(2);
    expect(diagram.runBands[0].nodes).toHaveLength(1);
    expect(diagram.runBands[0].nodes[0].signature).toBe("data:image/png;base64,abc");
    expect(diagram.runBands[1].nodes).toHaveLength(2);
    expect(diagram.runBands[1].bandLabel).toMatch(/Any one/i);
    expect(diagram.skippedBands).toHaveLength(1);
    expect(diagram.skippedBands[0].nodes[0].kind).toBe("skipped");
  });

  it("describes parallel completion modes", () => {
    expect(describeParallelBandLabel([{ parallel_mode: "all" }, { parallel_mode: "all" }])).toMatch(
      /All must approve/i,
    );
    expect(
      describeParallelBandLabel([
        { parallel_mode: "n_of_m", parallel_quorum: 2 },
        { parallel_mode: "n_of_m", parallel_quorum: 2 },
        { parallel_mode: "n_of_m", parallel_quorum: 2 },
      ]),
    ).toMatch(/2 of 3/);
  });
});
