import { describe, expect, it } from "vitest";

import {
  E_APPROVAL_FIELD_API_KEY_MAX_LENGTH,
  suggestApiKeyFromLabel,
} from "@/modules/e-approval/field-api-key";

describe("suggestApiKeyFromLabel", () => {
  it("truncates long labels to the database limit", () => {
    const label =
      "C2. If the site is Rolling or Mountains, Does it require Cut and Fill Slope Protection Extra Detail";
    const key = suggestApiKeyFromLabel(label, new Set());
    expect(key.length).toBeLessThanOrEqual(E_APPROVAL_FIELD_API_KEY_MAX_LENGTH);
    expect(key).toMatch(/^[a-z0-9_]+$/);
  });

  it("keeps uniqueness suffixes within the limit", () => {
    const label = "A".repeat(120);
    const taken = new Set([suggestApiKeyFromLabel(label, new Set())]);
    const second = suggestApiKeyFromLabel(label, taken);
    expect(second.length).toBeLessThanOrEqual(E_APPROVAL_FIELD_API_KEY_MAX_LENGTH);
    expect(taken.has(second)).toBe(false);
  });
});
