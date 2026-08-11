/** Parse daily hunting log "candidates identified" — digits only; empty uses candidate list length. */
export function parseCandidatesIdentifiedCount(
  raw: string,
  candidateCount: number,
): { value: number | undefined; error?: string } {
  const trimmed = raw.trim();

  if (!trimmed) {
    return candidateCount > 0 ? { value: candidateCount } : { value: undefined };
  }

  if (/^\d+$/.test(trimmed)) {
    return { value: Number.parseInt(trimmed, 10) };
  }

  const match = trimmed.match(/\d+/);
  if (match) {
    return { value: Number.parseInt(match[0], 10) };
  }

  return {
    value: undefined,
    error: "Enter how many candidates were identified (numbers only, e.g. 3).",
  };
}
