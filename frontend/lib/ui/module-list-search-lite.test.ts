import { describe, expect, it } from "vitest";

import { parseModuleListSearchLite } from "@/lib/ui/module-list-search-lite";
import {
  applyRowSelectionToSelectedIds,
  selectedIdsToRowSelection,
} from "@/lib/ui/module-list-selection";

describe("parseModuleListSearchLite", () => {
  it("extracts allowlisted tokens and residual text", () => {
    const result = parseModuleListSearchLite("status:ready invoice ACME priority:high", {
      status: ["ready", "failed", "processing"],
      priority: ["high", "low"],
    });
    expect(result.filters).toEqual({ status: "ready", priority: "high" });
    expect(result.operators).toEqual({ status: "eq", priority: "eq" });
    expect(result.search).toBe("invoice ACME");
  });

  it("treats = as equality", () => {
    const result = parseModuleListSearchLite("status=ready leftover", {
      status: ["ready"],
    });
    expect(result.filters).toEqual({ status: "ready" });
    expect(result.operators).toEqual({ status: "eq" });
    expect(result.search).toBe("leftover");
  });

  it("leaves ~ tokens in search for backend DSL", () => {
    const result = parseModuleListSearchLite('title~"network outage" status:open', {
      status: ["open"],
      title: "*",
    });
    expect(result.filters).toEqual({ status: "open" });
    expect(result.operators).toEqual({ title: "contains", status: "eq" });
    expect(result.search).toContain("title~");
    expect(result.search).toContain("network outage");
  });

  it("leaves != tokens in search for backend invert", () => {
    const result = parseModuleListSearchLite("status!=closed hello", {
      status: ["open", "closed"],
    });
    expect(result.filters).toEqual({});
    expect(result.operators).toEqual({ status: "ne" });
    expect(result.search).toContain("status!=closed");
    expect(result.search).toContain("hello");
  });

  it("leaves comparison tokens in search for backend ranges", () => {
    const result = parseModuleListSearchLite("created>=2026-01-01 leftover", {
      created: "*",
    });
    expect(result.filters).toEqual({});
    expect(result.operators).toEqual({ created: "gte" });
    expect(result.search).toContain("created>=2026-01-01");
    expect(result.search).toContain("leftover");
  });

  it("ignores unknown keys and invalid values", () => {
    const result = parseModuleListSearchLite("status:bogus foo:bar hello", {
      status: ["ready"],
    });
    expect(result.filters).toEqual({});
    expect(result.search).toBe("status:bogus foo:bar hello");
  });

  it("leaves pipe OR equality in search for backend", () => {
    const result = parseModuleListSearchLite("status:pending|approved hello", {
      status: ["pending", "approved", "rejected"],
    });
    expect(result.filters).toEqual({});
    expect(result.operators).toEqual({ status: "eq" });
    expect(result.search).toContain("status:pending|approved");
    expect(result.search).toContain("hello");
  });

  it("leaves cross-key OR intact for backend", () => {
    const result = parseModuleListSearchLite("status:open OR priority:high leftover", {
      status: ["open", "closed"],
      priority: ["high", "low"],
    });
    expect(result.filters).toEqual({});
    expect(result.operators).toEqual({});
    expect(result.search).toBe("status:open OR priority:high leftover");
  });

  it("does not treat OR inside quotes as a group separator", () => {
    const result = parseModuleListSearchLite('title~"a OR b" status:open', {
      status: ["open"],
      title: "*",
    });
    expect(result.filters).toEqual({ status: "open" });
    expect(result.search).toContain("title~");
    expect(result.search).toContain("a OR b");
  });
});

describe("module-list-selection cross-page merge", () => {
  it("keeps prior page ids when selecting a row on another page", () => {
    const current = new Set(["a", "b"]);
    let next = current;
    applyRowSelectionToSelectedIds(
      (old) => ({ ...old, c: true }),
      current,
      (value) => {
        next = value;
      },
    );
    expect([...next].sort()).toEqual(["a", "b", "c"]);
  });

  it("deselects one id without clearing others", () => {
    const current = new Set(["a", "b", "c"]);
    let next = current;
    applyRowSelectionToSelectedIds(
      (old) => ({ ...old, b: false }),
      current,
      (value) => {
        next = value;
      },
    );
    expect([...next].sort()).toEqual(["a", "c"]);
  });

  it("maps set ↔ row selection state", () => {
    expect(selectedIdsToRowSelection(new Set(["x", "y"]))).toEqual({ x: true, y: true });
  });
});
