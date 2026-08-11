import { describe, expect, it } from "vitest";

import {
  groupSavedAttachmentsByField,
  pendingAttachmentsNotYetSaved,
} from "@/modules/e-approval/draft-attachments";

describe("groupSavedAttachmentsByField", () => {
  it("groups attachments by field name", () => {
    expect(
      groupSavedAttachmentsByField([
        { id: "a1", field_name: "file_upload_5", file_name: "report.pdf" },
        { id: "a2", field_name: "file_upload_5", file_name: "appendix.pdf" },
        { id: "a3", field_name: null, file_name: "ignored.pdf" },
      ]),
    ).toEqual({
      file_upload_5: [
        { id: "a1", file_name: "report.pdf" },
        { id: "a2", file_name: "appendix.pdf" },
      ],
    });
  });
});

describe("pendingAttachmentsNotYetSaved", () => {
  it("skips files already saved on the draft", () => {
    const file = new File(["x"], "report.pdf", { type: "application/pdf" });
    const other = new File(["y"], "new.pdf", { type: "application/pdf" });

    expect(
      pendingAttachmentsNotYetSaved(
        { file_upload_5: [file, other] },
        [{ field_name: "file_upload_5", file_name: "report.pdf" }],
      ),
    ).toEqual({ file_upload_5: [other] });
  });

  it("returns empty when every pending file is already saved", () => {
    const file = new File(["x"], "report.pdf", { type: "application/pdf" });

    expect(
      pendingAttachmentsNotYetSaved(
        { file_upload_5: [file] },
        [{ field_name: "file_upload_5", file_name: "report.pdf" }],
      ),
    ).toEqual({});
  });
});
