import { describe, expect, it } from "vitest";

import { cashAdvanceBlockLinkFromFields } from "@/modules/e-approval/cash-advance-block-link";

const id = "5b70ed8e-2433-4bf6-958e-7f25329c8757";

describe("cash advance block link", () => {
  it("opens the pending liquidation when one is already in progress", () => {
    expect(
      cashAdvanceBlockLinkFromFields({
        _link_submission_id: id,
        _link_kind: "liquidation",
        _link_document_no: "LQ-14",
      }),
    ).toEqual({
      href: `/e-approval/submissions/${id}`,
      label: "Open pending liquidation LQ-14",
    });
  });

  it("opens the cash advance that is still waiting to be liquidated", () => {
    expect(
      cashAdvanceBlockLinkFromFields({
        _link_submission_id: id,
        _link_kind: "cash_advance",
        _link_document_no: "CA-9",
      })?.label,
    ).toBe("Open cash advance pending liquidation CA-9");
  });

  it("ignores a payload that is not this block", () => {
    expect(cashAdvanceBlockLinkFromFields({ form_id: "Something else failed." })).toBeNull();
    expect(
      cashAdvanceBlockLinkFromFields({
        _link_submission_id: "not-an-id",
        _link_kind: "liquidation",
      }),
    ).toBeNull();
  });
});
