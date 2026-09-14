/** Convert long department labels into short document-number codes. */

const DEPT_STOP_WORDS = new Set([
  "and",
  "of",
  "the",
  "for",
  "to",
  "a",
  "an",
  "at",
  "in",
  "on",
  "by",
  "with",
]);

/**
 * e.g. "Engineering & Design Department" → EDD, "Business Development" → BD.
 * Single-word / already-short values stay sanitized (Finance → FINANCE, QMS → QMS).
 */
export function departmentDocCodeFromLabel(raw: string): string {
  const trimmed = raw.trim();
  if (trimmed === "") return "";

  const compact = trimmed.toUpperCase().replace(/[^A-Z0-9]/g, "");
  if (compact !== "" && compact.length <= 6 && !/\s/.test(trimmed)) {
    return compact;
  }

  const words = trimmed.split(/[^A-Za-z0-9]+/).filter(Boolean);
  const significant = words.filter((word) => !DEPT_STOP_WORDS.has(word.toLowerCase()));

  if (significant.length === 0) {
    return compact;
  }

  if (significant.length === 1) {
    const one = significant[0]!.toUpperCase().replace(/[^A-Z0-9]/g, "");
    return one !== "" ? one : compact;
  }

  const initials = significant
    .map((word) => word.replace(/[^A-Za-z0-9]/g, "").charAt(0).toUpperCase())
    .join("");

  return initials !== "" ? initials : compact;
}
