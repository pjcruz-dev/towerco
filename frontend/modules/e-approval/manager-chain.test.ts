import { describe, expect, it } from "vitest";

import {
  managerChainCanvasSubtitle,
  managerChainEnabled,
  managerChainMaxLevels,
  managerChainStopTitles,
} from "@/modules/e-approval/manager-chain";

describe("manager chain step settings", () => {
  it("stays off until the step opts in", () => {
    expect(managerChainEnabled({ type: "manager" })).toBe(false);
    expect(managerChainCanvasSubtitle({ type: "manager" })).toBeNull();
  });

  it("reads a saved reporting line and describes the stop", () => {
    const step = {
      type: "manager",
      condition: {
        manager_chain: true,
        manager_chain_stop_titles: "Director",
        manager_chain_max_levels: 6,
      },
    };

    expect(managerChainEnabled(step)).toBe(true);
    expect(managerChainStopTitles(step)).toBe("Director");
    expect(managerChainMaxLevels(step)).toBe(6);
    expect(managerChainCanvasSubtitle(step)).toBe("Until Director");
  });

  it("uses the level limit when no job title is set", () => {
    const step = {
      type: "manager",
      manager_chain: true,
      manager_chain_stop_titles: "",
      manager_chain_max_levels: 3,
    };

    expect(managerChainCanvasSubtitle(step)).toBe("Up to 3 levels");
  });
});
