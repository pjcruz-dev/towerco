import { describe, expect, it } from "vitest";

import {
  effectiveWorkflowSource,
  matchApprovalPolicyProfile,
  normalizeApproverFieldValues,
  reconcileApproverFieldValues,
  requiredApproverFieldNamesForSubmit,
  resolveApproverFieldValue,
  sanitizeApproverFieldValues,
  workflowApproverFieldNames,
} from "@/modules/e-approval/approver-field-support";
import type { EApprovalApprovalPolicyConfig } from "@/modules/e-approval/approval-policy-types";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";

const policy: EApprovalApprovalPolicyConfig = {
  currency: "PHP",
  default_profiles: {
    purchase_requisition: "pr_standard",
    purchase_order: "po_standard",
  },
  workflow_profiles: {
    pr_standard: {
      label: "PR Standard",
      steps: [
        { type: "manager", step_order: 1 },
        { type: "field", approverId: "procurement_approver", step_order: 2 },
      ],
    },
    pr_capex: {
      label: "PR CapEx",
      steps: [
        { type: "manager", step_order: 1 },
        { type: "field", approverId: "procurement_approver", step_order: 2 },
        { type: "field", approverId: "finance_approver", step_order: 3 },
      ],
    },
  },
  rules: [
    {
      priority: 100,
      document_family: "purchase_requisition",
      amount_field: "estimated_total",
      amount_min: 500_001,
      amount_max: null,
      workflow_profile: "pr_capex",
    },
    {
      priority: 50,
      document_family: "purchase_requisition",
      amount_field: "estimated_total",
      amount_min: null,
      amount_max: 500_000,
      workflow_profile: "pr_standard",
    },
  ],
};

const approverOptions = [
  { id: "user-proc", label: "Proc User (proc@example.com)" },
  { id: "user-fin", label: "Finance User (fin@example.com)" },
];

describe("approver-field-support", () => {
  it("matches standard PR policy below capex threshold", () => {
    const profile = matchApprovalPolicyProfile(
      policy,
      { form_family: "purchase_requisition", use_approval_policy: true },
      { estimated_total: "84537", department: "it", urgency: "normal" },
    );

    expect(profile).toBe("pr_standard");
    expect(workflowApproverFieldNames(policy, profile)).toEqual(["procurement_approver"]);
  });

  it("resolves approver labels to user ids", () => {
    expect(resolveApproverFieldValue("Proc User (proc@example.com)", approverOptions)).toBe("user-proc");
    expect(resolveApproverFieldValue("proc@example.com", approverOptions)).toBe("user-proc");
  });

  it("normalizes stored display labels before submit", () => {
    const fields: EApprovalFormFieldInput[] = [
      { type: "approver", name: "procurement_approver", label: "Procurement approver", step_order: 1 },
    ];

    const normalized = normalizeApproverFieldValues(
      fields,
      { procurement_approver: "Proc User (proc@example.com)" },
      approverOptions,
    );

    expect(normalized.procurement_approver).toBe("user-proc");
  });

  it("requires only policy workflow approver fields", () => {
    const fields: EApprovalFormFieldInput[] = [
      {
        type: "approver",
        name: "procurement_approver",
        label: "Procurement approver",
        step_order: 1,
        validation: { required: true },
      },
      {
        type: "approver",
        name: "finance_approver",
        label: "Finance approver",
        step_order: 2,
        validation: { required: true },
      },
    ];

    const required = requiredApproverFieldNamesForSubmit(
      fields,
      { form_family: "purchase_requisition", use_approval_policy: true },
      { estimated_total: "84537" },
      policy,
    );

    expect(required).toEqual(new Set(["procurement_approver"]));
  });

  it("skips policy approver requirements when form workflow is active", () => {
    const fields: EApprovalFormFieldInput[] = [
      {
        type: "approver",
        name: "procurement_approver",
        label: "Procurement approver",
        step_order: 1,
        validation: { required: true },
      },
      {
        type: "approver",
        name: "finance_approver",
        label: "Finance approver",
        step_order: 2,
        validation: { required: true },
      },
    ];

    expect(
      effectiveWorkflowSource({
        form_family: "purchase_requisition",
        use_approval_policy: true,
        workflow_source: "form",
      }),
    ).toBe("form");

    expect(
      requiredApproverFieldNamesForSubmit(
        fields,
        {
          form_family: "purchase_requisition",
          use_approval_policy: true,
          workflow_source: "form",
        },
        { estimated_total: "7735590" },
        policy,
      ),
    ).toBeNull();
  });

  it("uses effective_workflow_source from schema when workflow_source is unset", () => {
    expect(
      effectiveWorkflowSource({
        form_family: "purchase_requisition",
        use_approval_policy: true,
        effective_workflow_source: "form",
      }),
    ).toBe("form");
  });

  it("clears approver picks that are no longer assignable", () => {
    const fields: EApprovalFormFieldInput[] = [
      { name: "approver_1", label: "Approver 1", type: "approver", step_order: 1 },
      { name: "approver_2", label: "Approver 2", type: "approver", step_order: 2 },
    ];
    const options = [{ id: "user-admin", label: "Tenant administrator (admin@example.com)" }];

    const sanitized = sanitizeApproverFieldValues(
      fields,
      {
        approver_1: "user-admin",
        approver_2: "deleted-user-id",
      },
      options,
    );

    expect(sanitized.approver_1).toBe("user-admin");
    expect(sanitized.approver_2).toBe("");
  });
});
