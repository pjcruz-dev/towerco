export function entraLicenseChipLabel(label: string | null | undefined, names: string[] | null | undefined): string | null {
  const primary = label?.trim() ?? "";
  if (primary === "") {
    return null;
  }
  const extra = Math.max(0, (names ?? []).length - 1);
  return extra > 0 ? `${primary} +${extra}` : primary;
}

export function entraLicenseSummary(names: string[] | null | undefined): string {
  const list = (names ?? []).map((name) => name.trim()).filter(Boolean);
  return list.length > 0 ? list.join(", ") : "";
}
