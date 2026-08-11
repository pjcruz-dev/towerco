import { describe, expect, it } from "vitest";

import {
  hasSignatureValue,
  isImageSignature,
  isTypedSignature,
  signatureModeForValue,
} from "@/modules/e-approval/signature";

describe("signature helpers", () => {
  it("detects image and typed signatures", () => {
    expect(isImageSignature("data:image/png;base64,abc")).toBe(true);
    expect(isTypedSignature("Jane Doe")).toBe(true);
    expect(isTypedSignature("data:image/png;base64,abc")).toBe(false);
    expect(hasSignatureValue("  ")).toBe(false);
  });

  it("picks input mode from stored value", () => {
    expect(signatureModeForValue("Jane Doe")).toBe("type");
    expect(signatureModeForValue("data:image/png;base64,abc")).toBe("draw");
    expect(signatureModeForValue(null)).toBe("draw");
  });
});
