/** Parse stored approver-list values (JSON array, CSV, or single id). */
export function parseApproverListValue(raw: string | null | undefined): string[] {
  const trimmed = (raw ?? "").trim();
  if (trimmed === "") {
    return [];
  }

  if (trimmed.startsWith("[")) {
    try {
      const decoded = JSON.parse(trimmed) as unknown;
      if (Array.isArray(decoded)) {
        return uniqueStrings(decoded.map((item) => String(item ?? "")));
      }
    } catch {
      // fall through
    }
  }

  if (trimmed.includes(",")) {
    return uniqueStrings(trimmed.split(","));
  }

  return [trimmed];
}

export function encodeApproverListValue(ids: string[]): string {
  const clean = uniqueStrings(ids);
  if (clean.length === 0) {
    return "";
  }

  return JSON.stringify(clean);
}

export function toggleApproverListId(current: string, id: string): string {
  const selected = new Set(parseApproverListValue(current));
  if (selected.has(id)) {
    selected.delete(id);
  } else {
    selected.add(id);
  }

  return encodeApproverListValue([...selected]);
}

function uniqueStrings(values: string[]): string[] {
  const seen = new Set<string>();
  const out: string[] = [];
  for (const raw of values) {
    const trimmed = raw.trim();
    if (trimmed === "" || seen.has(trimmed)) {
      continue;
    }
    seen.add(trimmed);
    out.push(trimmed);
  }

  return out;
}
