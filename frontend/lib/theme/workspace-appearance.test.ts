import { describe, expect, it } from "vitest";

import { parseWorkspaceAppearance } from "@/lib/theme/workspace-appearance";

describe("parseWorkspaceAppearance", () => {
  it("returns null when the user has no saved design", () => {
    expect(parseWorkspaceAppearance(null)).toBeNull();
  });

  it("keeps a personal chrome payload", () => {
    expect(
      parseWorkspaceAppearance({
        primary_preset: "teal",
        skin: "bordered",
        layout: "collapsed",
        content: "wide",
        color_mode: "dark",
      }),
    ).toMatchObject({
      saved: true,
      primary_preset: "teal",
      skin: "bordered",
      layout: "collapsed",
      content: "wide",
      colorMode: "dark",
    });
  });
});
