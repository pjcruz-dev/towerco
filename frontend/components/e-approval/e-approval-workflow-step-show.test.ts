import { describe, expect, it } from "vitest";

import {
  buildWorkflowStepShowItems,
  workflowPreviewToStepShowItems,
  workflowStepCompactCaption,
} from "@/components/e-approval/e-approval-workflow-step-show";

describe("rejected approval trail", () => {
  it("keeps a rejected API step rejected and names the rejector", () => {
    const steps = buildWorkflowStepShowItems({
      currentStep: 2,
      status: "rejected",
      workflowSteps: [
        { step_order: 1, state: "completed", status_label: "Approved", approver_name: "Peter Joseph Cruz" },
        { step_order: 2, state: "rejected", status_label: "Rejected", approver_name: "Denver Arquiza" },
        { step_order: 3, state: "skipped", status_label: "Not reached", approver_name: null },
      ],
    });

    expect(steps[1]?.state).toBe("rejected");
    expect(steps[1]?.statusLabel).toBe("Rejected");
    expect(steps[1]?.approverName).toBe("Denver Arquiza");
    expect(workflowStepCompactCaption(steps)).toEqual({
      progress: "Rejected · step 2 of 3",
      waiting: "Rejected · Denver Arquiza",
    });
  });

  it("does not paint a runtime rejection as pending", () => {
    const steps = workflowPreviewToStepShowItems(
      [
        { step_order: 1, runtime_status: "approved", runtime_approver: { name: "Peter Joseph Cruz" } },
        { step_order: 2, runtime_status: "rejected", runtime_approver: { name: "Denver Arquiza" } },
      ],
      "rejected",
    );

    expect(steps[1]?.state).toBe("rejected");
    expect(steps[1]?.statusLabel).toBe("Rejected");
    expect(steps.map((step) => step.state)).not.toContain("current");
  });

  it("keeps a unique key for each reviewer on the same step", () => {
    const steps = workflowPreviewToStepShowItems(
      [
        {
          step_order: 1,
          duty: "review",
          runtime_status: "acknowledged",
          resolved_user_id: "aileen",
          resolved_user_name: "Aileen Rosas",
        },
        {
          step_order: 1,
          duty: "review",
          runtime_status: "pending",
          resolved_user_id: "albino",
          resolved_user_name: "Albino Manalo Jr.",
        },
        { step_order: 2, runtime_status: "pending", resolved_user_name: "Jerico Fortuna" },
      ],
      "pending",
      [{ step_order: 3, label: "Skipped gate" }],
    );

    expect(new Set(steps.map((step) => step.key)).size).toBe(steps.length);
    expect(steps[0]?.statusLabel).toBe("Reviewed");
    expect(steps[1]?.statusLabel).toBe("Pending");
    expect(steps[2]?.statusLabel).toBe("Pending");
    expect(steps[3]?.state).toBe("skipped");
  });

  it("keeps a first-to-approve band on one step and marks the others not needed", () => {
    const steps = workflowPreviewToStepShowItems(
      [
        { step_order: 1, runtime_status: "skipped", label: "Direct manager (Entra)" },
        { step_order: 2, runtime_status: "approved", runtime_approver: { id: "caro", name: "Caro Traya" } },
        { step_order: 2, runtime_status: "invalidated", runtime_approver: { id: "anne", name: "Anne Marie Acaso" } },
        { step_order: 2, runtime_status: "invalidated", runtime_approver: { id: "arvin", name: "Arvin John Gervacio" } },
        { step_order: 3, runtime_status: "pending", runtime_approver: { id: "denver", name: "Denver Arquiza" } },
        { step_order: 4, resolved_user_name: "Peter Joseph Cruz" },
      ],
      "pending",
    );

    expect(steps.map((step) => `${step.stepOrder} ${step.approverName ?? ""} ${step.statusLabel}`)).toEqual([
      "1  Skipped",
      "2 Caro Traya Approved",
      "2 Anne Marie Acaso Not needed",
      "2 Arvin John Gervacio Not needed",
      "3 Denver Arquiza Pending",
      "4 Peter Joseph Cruz Upcoming",
    ]);
    expect(workflowStepCompactCaption(steps)).toEqual({
      progress: "Step 3 of 4",
      waiting: "Waiting on Denver Arquiza",
    });
  });
});
