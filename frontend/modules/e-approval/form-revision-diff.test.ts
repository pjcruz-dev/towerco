import { describe, expect, it } from "vitest";

import { buildFormRevisionDiff, snapshotFromRevisionPayload } from "@/modules/e-approval/form-revision-diff";

describe("form revision diff", () => {
  it("shows email, status, and step mail changes between revisions", () => {
    const before = snapshotFromRevisionPayload({
      name: "ISO",
      fields: [{ type: "text", name: "title", label: "Title" }],
      steps: [{ type: "user", approverId: "u1", step_order: 1, label: null }],
      metadata_json: { notifications: { statuses: { approved: "" } } },
    });
    const after = snapshotFromRevisionPayload({
      name: "ISO",
      fields: [{ type: "text", name: "title", label: "Title" }],
      steps: [
        {
          type: "user",
          approverId: "u1",
          step_order: 1,
          label: "Department",
          mail: { subject: "Please review {{document_no}}" },
        },
      ],
      metadata_json: {
        notifications: {
          statuses: { approved: "Cleared" },
          events: { approved: { subject: "Cleared {{document_no}}", enabled: true } },
        },
      },
    });

    expect(before).not.toBeNull();
    expect(after).not.toBeNull();
    const diff = buildFormRevisionDiff(before!, after!);
    expect(diff.map((item) => item.label)).toEqual(
      expect.arrayContaining(["Step 1", "Status name: approved", "Email: approved"]),
    );
    expect(diff.find((item) => item.label === "Step 1")?.detail).toContain("Email changed");
    expect(diff.find((item) => item.label === "Step 1")?.detail).toContain("Department");
  });
});
