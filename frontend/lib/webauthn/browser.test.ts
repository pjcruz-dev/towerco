import { describe, expect, it } from "vitest";

import { base64UrlToBuffer, bufferToBase64Url } from "@/lib/webauthn/browser";

describe("webauthn browser helpers", () => {
  it("round-trips base64url binary", () => {
    const original = new TextEncoder().encode("toweros-passkey").buffer;
    const encoded = bufferToBase64Url(original);
    const decoded = new Uint8Array(base64UrlToBuffer(encoded));
    expect(new TextDecoder().decode(decoded)).toBe("toweros-passkey");
  });
});
