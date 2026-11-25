import { describe, expect, it } from "vitest";

import { notificationShellForPath } from "@/modules/notifications/shell";

describe("notificationShellForPath", () => {
  it("keeps EDCS notifications inside document control", () => {
    expect(notificationShellForPath("/documents/controlled")).toBe("edcs");
    expect(notificationShellForPath("/documents/controlled/register")).toBe("edcs");
  });

  it("keeps the dashboard inbox open to every module", () => {
    expect(notificationShellForPath("/dashboard")).toBeNull();
  });

  it("follows the other modules", () => {
    expect(notificationShellForPath("/e-approval")).toBe("e_approval");
    expect(notificationShellForPath("/ticketing/tickets")).toBe("ticketing");
  });
});
