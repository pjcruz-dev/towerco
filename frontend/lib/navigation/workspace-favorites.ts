/**
 * Pinned workspace pages for the navbar Favorites (star) menu.
 */

export type WorkspaceFavoriteItem = {
  href: string;
  title: string;
  /** Uppercase chip: REPORT | SETUP | LIST | PAGE */
  kind: string;
};

const FAVORITES_STORAGE_KEY = "toweros.workspace.favorites";
const FAVORITES_LIMIT = 20;

export function readWorkspaceFavorites(): WorkspaceFavoriteItem[] {
  if (typeof window === "undefined") {
    return [];
  }

  try {
    const raw = window.localStorage.getItem(FAVORITES_STORAGE_KEY);
    if (!raw) return [];
    const parsed = JSON.parse(raw) as WorkspaceFavoriteItem[];
    if (!Array.isArray(parsed)) return [];
    return parsed
      .filter((row) => row && typeof row.href === "string" && typeof row.title === "string")
      .slice(0, FAVORITES_LIMIT);
  } catch {
    return [];
  }
}

function writeFavorites(items: WorkspaceFavoriteItem[]): void {
  try {
    window.localStorage.setItem(FAVORITES_STORAGE_KEY, JSON.stringify(items.slice(0, FAVORITES_LIMIT)));
  } catch {
    // Ignore quota errors.
  }
}

export function isWorkspaceFavorite(href: string): boolean {
  return readWorkspaceFavorites().some((row) => row.href === href);
}

export function toggleWorkspaceFavorite(item: WorkspaceFavoriteItem): WorkspaceFavoriteItem[] {
  const existing = readWorkspaceFavorites();
  const next = existing.some((row) => row.href === item.href)
    ? existing.filter((row) => row.href !== item.href)
    : [item, ...existing.filter((row) => row.href !== item.href)];
  writeFavorites(next);
  return next;
}

export function removeWorkspaceFavorite(href: string): WorkspaceFavoriteItem[] {
  const next = readWorkspaceFavorites().filter((row) => row.href !== href);
  writeFavorites(next);
  return next;
}

/** Metacoresoft-style category chip for recent/favorite rows. */
export function workspaceNavKindTag(input: {
  href: string;
  group?: string;
  section?: string;
  kind?: string;
  title?: string;
}): string {
  const hay = [input.section, input.group, input.kind, input.href, input.title]
    .filter(Boolean)
    .join(" ")
    .toLowerCase();

  if (/report|dashboard|kpi|analytic/.test(hay)) return "REPORT";
  if (/dynamic-entities|entity|list|sites|inventory|records/.test(hay)) return "LIST";
  if (/admin|user|role|setting|security|setup|preference|profile|billing|audit|backup/.test(hay)) {
    return "SETUP";
  }
  if (input.section) return input.section.slice(0, 10).toUpperCase();
  if (input.group) return input.group.slice(0, 10).toUpperCase();
  return "PAGE";
}
