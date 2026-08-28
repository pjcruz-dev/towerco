/**
 * Browser WebAuthn helpers — convert API base64url options ↔ ArrayBuffer for Credentials API.
 */

export function isWebAuthnSupported(): boolean {
  return typeof window !== "undefined" && typeof window.PublicKeyCredential !== "undefined";
}

/** Passkeys require HTTPS (or localhost). Plain HTTP pages are not a secure context. */
export function isWebAuthnSecureContext(): boolean {
  return typeof window !== "undefined" && window.isSecureContext === true;
}

export async function isPlatformAuthenticatorAvailable(): Promise<boolean> {
  if (!isWebAuthnSupported()) return false;
  try {
    if (typeof PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable !== "function") {
      return true;
    }
    return await PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable();
  } catch {
    return false;
  }
}

export function base64UrlToBuffer(value: string): ArrayBuffer {
  const padded = value.replace(/-/g, "+").replace(/_/g, "/");
  const pad = padded.length % 4 === 0 ? "" : "=".repeat(4 - (padded.length % 4));
  const binary = atob(padded + pad);
  const bytes = new Uint8Array(binary.length);
  for (let i = 0; i < binary.length; i += 1) {
    bytes[i] = binary.charCodeAt(i);
  }
  return bytes.buffer;
}

export function bufferToBase64Url(buffer: ArrayBuffer): string {
  const bytes = new Uint8Array(buffer);
  let binary = "";
  for (let i = 0; i < bytes.length; i += 1) {
    binary += String.fromCharCode(bytes[i]!);
  }
  return btoa(binary).replace(/\+/g, "-").replace(/\//g, "_").replace(/=+$/g, "");
}

function decodeIdField(value: unknown): ArrayBuffer {
  if (typeof value === "string") {
    return base64UrlToBuffer(value);
  }
  throw new Error("Invalid WebAuthn binary field from server.");
}

/** Turn server publicKey (JSON) into PublicKeyCredentialCreationOptions. */
export function toCreationOptions(publicKey: Record<string, unknown>): PublicKeyCredentialCreationOptions {
  const user = publicKey.user as Record<string, unknown>;
  const exclude = Array.isArray(publicKey.excludeCredentials)
    ? (publicKey.excludeCredentials as Array<Record<string, unknown>>)
    : [];

  return {
    ...(publicKey as unknown as PublicKeyCredentialCreationOptions),
    challenge: decodeIdField(publicKey.challenge),
    user: {
      id: decodeIdField(user.id),
      name: String(user.name ?? ""),
      displayName: String(user.displayName ?? user.name ?? ""),
    },
    excludeCredentials: exclude.map((cred) => ({
      type: "public-key" as const,
      id: decodeIdField(cred.id),
      transports: Array.isArray(cred.transports)
        ? (cred.transports as AuthenticatorTransport[])
        : undefined,
    })),
  };
}

/** Turn server publicKey (JSON) into PublicKeyCredentialRequestOptions. */
export function toRequestOptions(publicKey: Record<string, unknown>): PublicKeyCredentialRequestOptions {
  const allow = Array.isArray(publicKey.allowCredentials)
    ? (publicKey.allowCredentials as Array<Record<string, unknown>>)
    : [];

  return {
    ...(publicKey as unknown as PublicKeyCredentialRequestOptions),
    challenge: decodeIdField(publicKey.challenge),
    allowCredentials: allow.map((cred) => ({
      type: "public-key" as const,
      id: decodeIdField(cred.id),
      transports: Array.isArray(cred.transports)
        ? (cred.transports as AuthenticatorTransport[])
        : undefined,
    })),
  };
}

export type SerializedPublicKeyCredential = {
  id: string;
  rawId: string;
  type: string;
  response: Record<string, unknown>;
  authenticatorAttachment?: string | null;
};

export function serializeAttestation(credential: PublicKeyCredential): SerializedPublicKeyCredential {
  const response = credential.response as AuthenticatorAttestationResponse;
  const transports =
    typeof response.getTransports === "function" ? response.getTransports() : undefined;

  return {
    id: credential.id,
    rawId: bufferToBase64Url(credential.rawId),
    type: credential.type,
    authenticatorAttachment: credential.authenticatorAttachment ?? null,
    response: {
      clientDataJSON: bufferToBase64Url(response.clientDataJSON),
      attestationObject: bufferToBase64Url(response.attestationObject),
      ...(transports ? { transports } : {}),
    },
  };
}

export function serializeAssertion(credential: PublicKeyCredential): SerializedPublicKeyCredential {
  const response = credential.response as AuthenticatorAssertionResponse;
  return {
    id: credential.id,
    rawId: bufferToBase64Url(credential.rawId),
    type: credential.type,
    authenticatorAttachment: credential.authenticatorAttachment ?? null,
    response: {
      clientDataJSON: bufferToBase64Url(response.clientDataJSON),
      authenticatorData: bufferToBase64Url(response.authenticatorData),
      signature: bufferToBase64Url(response.signature),
      ...(response.userHandle
        ? { userHandle: bufferToBase64Url(response.userHandle) }
        : {}),
    },
  };
}

export function webAuthnUserMessage(error: unknown): string {
  if (error instanceof DOMException) {
    if (error.name === "NotAllowedError") {
      return "Passkey prompt was cancelled or timed out. Try again.";
    }
    if (error.name === "InvalidStateError") {
      return "This passkey is already registered on this device.";
    }
    if (error.name === "NotSupportedError") {
      return "This browser or device does not support passkeys.";
    }
    if (error.name === "SecurityError") {
      return "Passkeys require a secure context (HTTPS or localhost).";
    }
  }
  if (typeof error === "object" && error !== null && "isAxiosError" in error) {
    const ax = error as { response?: { status?: number; data?: { message?: string } }; message?: string };
    const apiMessage = ax.response?.data?.message;
    if (apiMessage && /GET method is not supported/i.test(apiMessage)) {
      return "Passkey setup failed because the request was redirected (often HTTP→HTTPS). Open this site with https:// and try again.";
    }
    if (ax.response?.status === 301 || ax.response?.status === 302 || ax.response?.status === 307 || ax.response?.status === 308) {
      return "Passkey API redirected unexpectedly. Use the HTTPS site URL and confirm NEXT_PUBLIC_API_BASE_URL uses https://.";
    }
  }
  if (error instanceof Error && error.message) {
    return error.message;
  }
  return "Passkey operation failed.";
}
