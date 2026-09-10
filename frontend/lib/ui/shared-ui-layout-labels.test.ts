import { describe, expect, it } from "vitest";

import { labelSharedUiLayoutKey } from "@/lib/ui/shared-ui-layout-labels";

describe("labelSharedUiLayoutKey", () => {
  it("maps known dashboard layout keys", () => {
    expect(labelSharedUiLayoutKey("dashboard-layout.toweros.workspace.dashboard.layout")).toBe(
      "Home · Dashboard",
    );
    expect(labelSharedUiLayoutKey("dashboard-layout.toweros.ticketing.tickets.layout")).toBe(
      "Ticketing · Tickets",
    );
  });

  it("labels form workspace and module-list keys", () => {
    expect(
      labelSharedUiLayoutKey("dashboard-layout.toweros.e-approval.workspace.layout.leave-request"),
    ).toBe("E-Forms · Workspace (leave-request)");
    expect(labelSharedUiLayoutKey("module-list.ticketing.tickets.columns")).toBe(
      "Column layouts · ticketing.tickets.columns",
    );
  });
});
