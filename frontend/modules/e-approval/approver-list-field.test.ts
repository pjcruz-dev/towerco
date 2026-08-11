import { describe, expect, it } from "vitest";

import {
  encodeApproverListValue,
  parseApproverListValue,
  toggleApproverListId,
} from "@/modules/e-approval/approver-list-field";

describe("approver list field", () => {
  it("parses json, csv, and single ids", () => {
    expect(parseApproverListValue('["a","b"]')).toEqual(["a", "b"]);
    expect(parseApproverListValue("a, b")).toEqual(["a", "b"]);
    expect(parseApproverListValue("solo")).toEqual(["solo"]);
  });

  it("encodes and toggles selections", () => {
    expect(encodeApproverListValue(["b", "a", "a"])).toBe('["b","a"]');
    expect(toggleApproverListId('["a"]', "b")).toBe('["a","b"]');
    expect(toggleApproverListId('["a","b"]', "a")).toBe('["b"]');
  });
});
