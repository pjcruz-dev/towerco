import { describe, expect, it } from "vitest";

import {
  DEFAULT_FORM_REVISION_EDITOR_SETTINGS,
  describeResubmitRoutingOutlook,
  describeResubmitToastMessage,
  describeRevisionRoutingApplied,
  mergeFormRevisionIntoMetadata,
  parseFormRevisionConfig,
} from "@/modules/e-approval/form-revision-config";

describe("form revision config", () => {
  it("defaults to restart from step 1", () => {
    expect(parseFormRevisionConfig(null)).toEqual(DEFAULT_FORM_REVISION_EDITOR_SETTINGS);
    expect(parseFormRevisionConfig({})).toEqual(DEFAULT_FORM_REVISION_EDITOR_SETTINGS);
  });

  it("parses resume routing and material fields", () => {
    const parsed = parseFormRevisionConfig({
      revision: {
        routing: "resume_returning_step",
        material_fields: ["amount", "vendor_id"],
        approver_can_force_full_restart: false,
      },
    });

    expect(parsed).toEqual({
      routing: "resume_returning_step",
      materialFields: ["amount", "vendor_id"],
      approverCanForceFullRestart: false,
    });
  });

  it("omits default revision block from metadata", () => {
    const merged = mergeFormRevisionIntoMetadata({ compose: { mode: "stepped" } }, DEFAULT_FORM_REVISION_EDITOR_SETTINGS);
    expect(merged.revision).toBeUndefined();
    expect(merged.compose).toEqual({ mode: "stepped" });
  });

  it("writes non-default revision block", () => {
    const merged = mergeFormRevisionIntoMetadata(
      {},
      {
        routing: "resume_returning_step",
        materialFields: ["amount"],
        approverCanForceFullRestart: false,
      },
    );

    expect(merged.revision).toEqual({
      routing: "resume_returning_step",
      material_fields: ["amount"],
      approver_can_force_full_restart: false,
    });
  });

  it("treats force-full-restart on as non-default", () => {
    const merged = mergeFormRevisionIntoMetadata(
      {},
      {
        routing: "restart_from_start",
        materialFields: [],
        approverCanForceFullRestart: true,
      },
    );

    expect(merged.revision).toEqual({
      routing: "restart_from_start",
      material_fields: [],
      approver_can_force_full_restart: true,
    });
  });

  it("describes resume vs restart outlook and applied notes", () => {
    expect(
      describeResubmitRoutingOutlook({
        routing: "resume_returning_step",
        returnedFromStep: 5,
        materialFieldCount: 2,
      }),
    ).toContain("resumes at step 5");

    expect(
      describeResubmitRoutingOutlook({
        routing: "restart_from_start",
      }),
    ).toContain("restart from step 1");

    expect(
      describeRevisionRoutingApplied({
        routing: "resume_returning_step",
        reason: "resume_returning_step",
        currentStep: 5,
      }),
    ).toBe("Last resubmit · Resume: Resumed at the step that requested revision · now at step 5");

    expect(
      describeRevisionRoutingApplied({
        routing: "restart_from_start",
        reason: "form_restart_setting",
        currentStep: 1,
      }),
    ).toBe("Last resubmit · Full restart: Form is set to restart from step 1 · now at step 1");

    expect(
      describeResubmitToastMessage({
        reason: "material_fields_changed",
        currentStep: 1,
      }),
    ).toContain("now at step 1");
  });
});
