import { describe, expect, it } from "vitest";

import { parseProductReleaseImport } from "@/content/release-notes";

describe("parseProductReleaseImport", () => {
  it("loads a pasted note", () => {
    const release = parseProductReleaseImport(
      JSON.stringify({
        version: "v1.0.7",
        title: "Revised",
        summary: "Shorter.",
        sections: [{ heading: "People", body: "Actions menu." }],
      }),
    );

    expect(release?.version).toBe("v1.0.7");
    expect(release?.sections[0]?.heading).toBe("People");
  });

  it("unwraps a release object", () => {
    const release = parseProductReleaseImport(
      JSON.stringify({
        release: {
          version: "v1.0.8",
          title: "Wrapped",
          summary: "",
          sections: [],
        },
      }),
    );

    expect(release?.title).toBe("Wrapped");
  });

  it("rejects text that is not a note", () => {
    expect(parseProductReleaseImport("not json")).toBeNull();
    expect(parseProductReleaseImport(JSON.stringify({ summary: "missing version" }))).toBeNull();
  });
});
