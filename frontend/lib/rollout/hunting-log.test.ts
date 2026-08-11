import { describe, expect, it } from "vitest";

import { parseCandidatesIdentifiedCount } from "./hunting-log";

describe("parseCandidatesIdentifiedCount", () => {
  it("defaults to candidate count when empty", () => {
    expect(parseCandidatesIdentifiedCount("", 3)).toEqual({ value: 3 });
  });

  it("parses plain integers", () => {
    expect(parseCandidatesIdentifiedCount("3", 0)).toEqual({ value: 3 });
  });

  it("extracts digits from mixed text", () => {
    expect(parseCandidatesIdentifiedCount("CANDIDATE 3", 0)).toEqual({ value: 3 });
  });

  it("rejects text without digits", () => {
    expect(parseCandidatesIdentifiedCount("CANDIDATE", 0).error).toBeTruthy();
  });
});
