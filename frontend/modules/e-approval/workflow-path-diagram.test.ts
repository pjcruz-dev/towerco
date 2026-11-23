import { describe, expect, it } from "vitest";

import {
  buildWorkflowPathDiagram,
  describeParallelBandLabel,
  trainingPathBand,
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
        { duty: "review" },
        { duty: "review" },
      ]),
    ).toBe("Reviewers · All must review");
    expect(
      describeParallelBandLabel([
        { parallel_mode: "n_of_m", parallel_quorum: 2 },
        { parallel_mode: "n_of_m", parallel_quorum: 2 },
        { parallel_mode: "n_of_m", parallel_quorum: 2 },
      ]),
    ).toMatch(/2 of 3/);
  });

  it("labels a reporting-line step with the Entra job title", () => {
    const diagram = buildWorkflowPathDiagram({
      resolved_steps: [
        {
          step_order: 1,
          type: "user",
          label: "Project Manager",
          resolved_user_id: "ramon",
          resolved_user_name: "Ramon Hernandez",
          resolved_user_email: "rhernandez@alliancetowers.com",
          role_label: "Project Manager",
          warning: null,
        },
        {
          step_order: 2,
          type: "user",
          label: "Director",
          resolved_user_id: "pilar",
          resolved_user_name: "Demetrio Pilar",
          resolved_user_email: "dpilar@alliancetowers.com",
          role_label: "Director",
          warning: null,
        },
      ],
    });

    expect(diagram.runBands.map((band) => band.nodes[0]?.title)).toEqual([
      "Ramon Hernandez",
      "Demetrio Pilar",
    ]);
    expect(diagram.runBands.map((band) => band.nodes[0]?.subtitle)).toEqual([
      "Project Manager · rhernandez@alliancetowers.com",
      "Director · dpilar@alliancetowers.com",
    ]);
  });

  it("adds a live training band after the last approval", () => {
    const band = trainingPathBand(
      [
        { id: "1", name: "Peter Joseph Cruz", email: "prcruz@alliancetowers.com", status: "invited" },
        { id: "2", name: "Denver Arquiza", email: "dearquiza@alliancetowers.com", status: "completed" },
      ],
      4,
    );

    expect(band.stepOrder).toBe(5);
    expect(band.bandLabel).toBe("Training · All must finish");
    expect(band.nodes.map((node) => node.status)).toEqual(["pending", "training_done"]);
  });
});
