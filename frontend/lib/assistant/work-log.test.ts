import { describe, expect, it } from "vitest";

import type { AssistantActivityStep } from "@/lib/api/modules/assistant-api";
import { formatWorked, presentWorkStep, stepCountLabel, visibleWorkSteps } from "@/lib/assistant/work-log";

function step(partial: Partial<AssistantActivityStep> & Pick<AssistantActivityStep, "id" | "title">): AssistantActivityStep {
  return {
    kind: "tool",
    status: "done",
    detail: null,
    ...partial,
  };
}

describe("work log presentation", () => {
  it("formats elapsed time the way the work header reads", () => {
    expect(formatWorked(10_000)).toBe("10s");
    expect(formatWorked(4_200)).toBe("4.2s");
    expect(formatWorked(75_000)).toBe("1m 15s");
    expect(stepCountLabel(1)).toBe("1 step");
    expect(stepCountLabel(3)).toBe("3 steps");
  });

  it("turns a database query into a sentence and a count, not the statement", () => {
    const presented = presentWorkStep(
      step({
        id: "tool-0",
        title: "Ran Execute sql",
        detail: "SELECT count(*) FROM users;\n1 row.",
      }),
      "How many user we have?",
    );

    expect(presented.kind).toBe("query");
    expect(presented.title).toBe('Running database query: "How many user we have?"');
    expect(presented.result).toBe("found 1 record");
  });

  it("keeps the model's own purpose on a later query", () => {
    const presented = presentWorkStep(
      step({
        id: "tool-1",
        title: 'Running database query: "Count active users in Procurement roles"',
        detail: "1 row.",
      }),
      "How many procurement active team?",
    );

    expect(presented.title).toBe('Running database query: "Count active users in Procurement roles"');
    expect(presented.result).toBe("found 1 record");
  });

  it("keeps a short tool summary and hides a raw dump", () => {
    const summary = presentWorkStep(
      step({
        id: "tool-1",
        title: "Ran List my open tickets",
        detail: "2 rows. 2 open tickets.",
      }),
    );
    expect(summary.title).toBe("Ran List my open tickets");
    expect(summary.result).toBe("2 open tickets");

    const dump = presentWorkStep(
      step({
        id: "tool-2",
        title: "Ran Read file",
        detail: ["{", '  "users": [', "    1,", "    2,", "    3,", "    4,", "    5,", "  ]", "}"].join("\n"),
      }),
    );
    expect(dump.result).toBe("Finished.");
  });

  it("shows the model's note as the timeline prose", () => {
    const presented = presentWorkStep(
      step({
        id: "thought-1",
        kind: "thought",
        title: "Thinking",
        detail:
          "We have two roles related to procurement: 'ATC Procurement Officer' (ID: 369) and 'Procurement Officer' (ID: 254).\nI'll count the active users for each.",
      }),
    );
    expect(presented.kind).toBe("thought");
    expect(presented.result).toContain("ATC Procurement Officer");
    expect(presented.result).toContain("I'll count the active users for each.");
  });

  it("drops chrome steps and keeps the live placeholder when nothing else has started", () => {
    const steps = [
      step({ id: "question", kind: "model", title: "Reading your question", status: "done" }),
      step({ id: "plan-0", kind: "model", title: "Choosing an operation", status: "running" }),
    ];
    expect(visibleWorkSteps(steps, "", false)).toEqual([]);
    expect(visibleWorkSteps(steps, "", true).map((item) => item.title)).toEqual(["Asking the model"]);
    expect(visibleWorkSteps(steps, "Look up the active users.", true)[0]?.kind).toBe("thought");
  });

  it("hides a knowledge-base search when the model did not run a tool", () => {
    const steps = [
      step({ id: "search", kind: "search", title: "Searched approved knowledge", detail: "1 guide" }),
    ];
    expect(visibleWorkSteps(steps, "", false)).toEqual([]);
  });
});
