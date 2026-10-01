import { describe, expect, it } from "vitest";

import { resolveAssistantRouteContext } from "@/lib/assistant/route-context";

describe("resolveAssistantRouteContext", () => {
  it("maps e-approval routes", () => {
    const ctx = resolveAssistantRouteContext("/e-approval/submissions/new");
    expect(ctx.moduleKey).toBe("e_approval");
    expect(ctx.suggestedQuestions.length).toBeGreaterThan(0);
  });

  it("falls back to core suggestions", () => {
    const ctx = resolveAssistantRouteContext("/settings");
    expect(ctx.moduleKey).toBe("core");
    expect(ctx.pagePath).toBe("/settings");
  });

  it("falls back to core suggestions for unknown routes", () => {
    const ctx = resolveAssistantRouteContext("/notifications");
    expect(ctx.moduleKey).toBe("core");
    expect(ctx.pagePath).toBe("/notifications");
  });

  it("maps document register routes", () => {
    const ctx = resolveAssistantRouteContext("/documents/controlled");
    expect(ctx.moduleKey).toBe("document_register");
    expect(ctx.suggestedQuestions.length).toBeGreaterThan(0);
  });
});
