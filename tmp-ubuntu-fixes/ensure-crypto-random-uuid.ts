/**
 * Browsers only expose crypto.randomUUID in secure contexts (HTTPS / localhost).
 * LAN HTTP hosts like *.toweros.lan need a fallback so snacks and drafts keep working.
 */
export function ensureCryptoRandomUuid(): void {
  if (typeof globalThis === "undefined") {
    return;
  }

  const cryptoObj = globalThis.crypto as Crypto | undefined;
  if (!cryptoObj || typeof cryptoObj.randomUUID === "function") {
    return;
  }

  const fallback = (): `${string}-${string}-${string}-${string}-${string}` => {
    const bytes = new Uint8Array(16);
    if (typeof cryptoObj.getRandomValues === "function") {
      cryptoObj.getRandomValues(bytes);
    } else {
      for (let i = 0; i < bytes.length; i += 1) {
        bytes[i] = Math.floor(Math.random() * 256);
      }
    }

    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;

    const hex = Array.from(bytes, (b) => b.toString(16).padStart(2, "0")).join("");
    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}` as `${string}-${string}-${string}-${string}-${string}`;
  };

  Object.defineProperty(cryptoObj, "randomUUID", {
    value: fallback,
    configurable: true,
    writable: true,
  });
}

ensureCryptoRandomUuid();
