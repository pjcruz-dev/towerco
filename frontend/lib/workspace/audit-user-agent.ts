/**
 * Parse browser User-Agent for audit trail.
 * Shows browser / OS / phone-tablet-desktop — only what the UA reports (not IMEI/model SKU when stripped).
 */

export type AuditDeviceInfo = {
  formFactor: "Phone" | "Tablet" | "Desktop" | "Bot" | "Unknown";
  device: string | null;
  os: string | null;
  osVersion: string | null;
  browser: string | null;
  browserVersion: string | null;
  /** Short table label */
  summary: string;
  /** Multi-line detail lines */
  details: string[];
  raw: string;
};

export function parseAuditUserAgent(userAgent: string | null | undefined): AuditDeviceInfo | null {
  const raw = (userAgent ?? "").trim();
  if (!raw) {
    return null;
  }

  const formFactor = detectFormFactor(raw);
  const device = detectDevice(raw, formFactor);
  const { name: os, version: osVersion } = detectOs(raw);
  const { name: browser, version: browserVersion } = detectBrowser(raw);

  const summaryParts: string[] = [];
  if (formFactor === "Phone" || formFactor === "Tablet") {
    summaryParts.push(device || formFactor);
  } else if (formFactor === "Bot") {
    summaryParts.push("Bot");
  }
  if (browser) {
    summaryParts.push(browserVersion ? `${browser} ${browserVersion}` : browser);
  }
  if (os) {
    summaryParts.push(osVersion ? `${os} ${osVersion}` : os);
  }

  const details: string[] = [];
  details.push(`Type: ${formFactor}`);
  if (device) details.push(`Device: ${device}`);
  if (os) details.push(`OS: ${osVersion ? `${os} ${osVersion}` : os}`);
  if (browser) details.push(`Browser: ${browserVersion ? `${browser} ${browserVersion}` : browser}`);

  return {
    formFactor,
    device,
    os,
    osVersion,
    browser,
    browserVersion,
    summary: summaryParts.length > 0 ? summaryParts.join(" · ") : truncateUa(raw),
    details,
    raw,
  };
}

export function formatAuditUserAgent(userAgent: string | null | undefined): string {
  return parseAuditUserAgent(userAgent)?.summary ?? "—";
}

export function auditUserAgentTitle(userAgent: string | null | undefined): string {
  const info = parseAuditUserAgent(userAgent);
  if (!info) {
    return "No device / browser string recorded";
  }
  return [info.summary, info.raw].filter(Boolean).join("\n");
}

function detectFormFactor(ua: string): AuditDeviceInfo["formFactor"] {
  if (/bot|crawler|spider|slurp|facebookexternalhit|preview/i.test(ua)) {
    return "Bot";
  }
  if (/iPad|Tablet|PlayBook|Silk/i.test(ua) || (/Android/i.test(ua) && !/Mobile/i.test(ua))) {
    return "Tablet";
  }
  if (/Mobile|iPhone|iPod|Android.*Mobile|webOS|BlackBerry|IEMobile|Opera Mini|Windows Phone/i.test(ua)) {
    return "Phone";
  }
  return "Desktop";
}

function detectDevice(ua: string, formFactor: AuditDeviceInfo["formFactor"]): string | null {
  if (/iPhone/i.test(ua)) return "iPhone";
  if (/iPad/i.test(ua)) return "iPad";
  if (/iPod/i.test(ua)) return "iPod";

  // Android: "... Linux; Android 14; Pixel 8 Build/..." or "SM-S918B"
  const androidDevice = ua.match(/Android [^;]*;\s*([^;)]+?)(?:\s+Build\/|[;/])/i);
  if (androidDevice?.[1]) {
    const token = androidDevice[1].trim();
    if (token && !/^wv$/i.test(token) && !/^[a-z]{2}[-_][a-z]{2}$/i.test(token)) {
      return cleanAndroidModel(token);
    }
  }

  if (/Android/i.test(ua)) {
    return formFactor === "Tablet" ? "Android tablet" : "Android phone";
  }

  if (formFactor === "Desktop") {
    if (/Windows NT/i.test(ua)) return "Windows PC";
    if (/Macintosh|Mac OS X/i.test(ua)) return "Mac";
    if (/CrOS/i.test(ua)) return "Chromebook";
    if (/Linux/i.test(ua)) return "Linux PC";
  }

  return null;
}

function cleanAndroidModel(token: string): string {
  const cleaned = token.replace(/\s+/g, " ").trim();
  // Locale tags like "en-us" slipped through
  if (/^[a-z]{2}[-_][A-Z]{2}$/.test(cleaned)) {
    return "Android phone";
  }
  return cleaned;
}

function detectOs(ua: string): { name: string | null; version: string | null } {
  const win = ua.match(/Windows NT ([0-9.]+)/i);
  if (win) {
    return { name: "Windows", version: windowsNtLabel(win[1]) };
  }

  const android = ua.match(/Android ([0-9.]+)/i);
  if (android) {
    return { name: "Android", version: android[1] };
  }

  // iPhone OS 17_4 or CPU OS 17_4 like Mac OS X
  const ios = ua.match(/(?:iPhone OS|CPU (?:iPhone )?OS) ([0-9_]+)/i);
  if (ios || /iPhone|iPad|iPod/i.test(ua)) {
    return {
      name: /iPad/i.test(ua) ? "iPadOS" : "iOS",
      version: ios ? ios[1].replace(/_/g, ".") : null,
    };
  }

  const mac = ua.match(/Mac OS X ([0-9_]+)/i);
  if (mac) {
    return { name: "macOS", version: mac[1].replace(/_/g, ".") };
  }

  if (/CrOS/i.test(ua)) {
    return { name: "ChromeOS", version: null };
  }

  if (/Linux/i.test(ua)) {
    return { name: "Linux", version: null };
  }

  return { name: null, version: null };
}

function windowsNtLabel(nt: string): string {
  const map: Record<string, string> = {
    "10.0": "10/11",
    "6.3": "8.1",
    "6.2": "8",
    "6.1": "7",
  };
  return map[nt] ?? nt;
}

function detectBrowser(ua: string): { name: string | null; version: string | null } {
  const edge = ua.match(/Edg(?:e|A|iOS)?\/([0-9.]+)/i);
  if (edge) return { name: "Edge", version: majorVersion(edge[1]) };

  const opera = ua.match(/(?:OPR|Opera)\/([0-9.]+)/i);
  if (opera) return { name: "Opera", version: majorVersion(opera[1]) };

  const samsung = ua.match(/SamsungBrowser\/([0-9.]+)/i);
  if (samsung) return { name: "Samsung Internet", version: majorVersion(samsung[1]) };

  const firefox = ua.match(/(?:Firefox|FxiOS)\/([0-9.]+)/i);
  if (firefox) return { name: "Firefox", version: majorVersion(firefox[1]) };

  const chrome = ua.match(/(?:Chrome|CriOS)\/([0-9.]+)/i);
  if (chrome && !/Chromium/i.test(ua)) {
    return { name: "Chrome", version: majorVersion(chrome[1]) };
  }

  const safari = ua.match(/Version\/([0-9.]+).*Safari\//i);
  if (safari || (/Safari\//i.test(ua) && !/Chrome\//i.test(ua))) {
    return { name: "Safari", version: safari ? majorVersion(safari[1]) : null };
  }

  if (/MSIE |Trident\//i.test(ua)) {
    const ie = ua.match(/(?:MSIE |rv:)([0-9.]+)/i);
    return { name: "Internet Explorer", version: ie ? majorVersion(ie[1]) : null };
  }

  return { name: null, version: null };
}

function majorVersion(version: string): string {
  const parts = version.split(".");
  return parts[0] ?? version;
}

function truncateUa(ua: string): string {
  return ua.length > 64 ? `${ua.slice(0, 61)}…` : ua;
}
