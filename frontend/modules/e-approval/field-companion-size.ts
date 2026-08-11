/** Stored in checkbox companion map as a string (JSON or empty). */
export type CompanionSizeValue = {
  w?: string;
  h?: string;
  na?: boolean;
};

export function parseCompanionSizeValue(raw: string): CompanionSizeValue {
  const trimmed = raw.trim();
  if (trimmed === "") {
    return {};
  }

  if (trimmed.toLowerCase() === "na") {
    return { na: true };
  }

  if (trimmed.startsWith("{")) {
    try {
      const decoded = JSON.parse(trimmed) as unknown;
      if (!decoded || typeof decoded !== "object" || Array.isArray(decoded)) {
        return {};
      }
      const record = decoded as Record<string, unknown>;
      if (record.na === true) {
        return { na: true };
      }
      const w = record.w == null ? "" : String(record.w).trim();
      const h = record.h == null ? "" : String(record.h).trim();
      const out: CompanionSizeValue = {};
      if (w !== "") {
        out.w = w;
      }
      if (h !== "") {
        out.h = h;
      }

      return out;
    } catch {
      return {};
    }
  }

  const parts = trimmed.split(/[x×|]/i).map((p) => p.trim());
  if (parts.length >= 2) {
    const out: CompanionSizeValue = {};
    if (parts[0]) {
      out.w = parts[0];
    }
    if (parts[1]) {
      out.h = parts[1];
    }

    return out;
  }

  return {};
}

export function serializeCompanionSizeValue(value: CompanionSizeValue): string {
  if (value.na) {
    return JSON.stringify({ na: true });
  }
  const w = (value.w ?? "").trim();
  const h = (value.h ?? "").trim();
  if (w === "" && h === "") {
    return "";
  }

  return JSON.stringify({ ...(w !== "" ? { w } : {}), ...(h !== "" ? { h } : {}) });
}

export function companionSizeIsComplete(raw: string): boolean {
  const value = parseCompanionSizeValue(raw);
  if (value.na) {
    return true;
  }

  return (value.w ?? "").trim() !== "" && (value.h ?? "").trim() !== "";
}

export function formatCompanionSizeDisplay(raw: string): string {
  const value = parseCompanionSizeValue(raw);
  if (value.na) {
    return "NA";
  }
  const w = (value.w ?? "").trim();
  const h = (value.h ?? "").trim();
  if (w === "" && h === "") {
    return "";
  }

  return `${w || "—"} × ${h || "—"}`;
}

export function companionSizeHasPartial(raw: string): boolean {
  const value = parseCompanionSizeValue(raw);
  if (value.na) {
    return false;
  }
  const w = (value.w ?? "").trim();
  const h = (value.h ?? "").trim();

  return (w !== "" && h === "") || (w === "" && h !== "");
}
