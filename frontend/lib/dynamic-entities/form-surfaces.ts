/** Which asset tab a field group is drawn on. Missing means Details. */
export const OVERVIEW_SURFACE = "overview";

export function groupSurface(group: { surface?: string | null }): string {
  const value = (group.surface ?? "").trim().toLowerCase();
  return value === "" ? OVERVIEW_SURFACE : value;
}

export function groupsForSurface<T extends { surface?: string | null }>(groups: T[], surface: string): T[] {
  return groups.filter((group) => groupSurface(group) === surface);
}

export function fieldsForSurface<T extends { form_group_id?: string | null; view_group_id?: string | null }>(
  fields: T[],
  groups: Array<{ id: string; surface?: string | null }>,
  surface: string,
): T[] {
  const ids = new Set(groupsForSurface(groups, surface).map((group) => group.id));
  return fields.filter((field) => {
    const groupId = field.form_group_id ?? field.view_group_id ?? null;
    if (!groupId) {
      return surface === OVERVIEW_SURFACE;
    }
    return ids.has(groupId);
  });
}
