/**
 * Returns whether a nav href should appear active for the current pathname.
 * Module dashboards (e.g. /project-one) use exact match so sibling routes stay distinct.
 *
 * Index routes like `/dynamic-entities` must never prefix-match child pages
 * (`/dynamic-entities/printables/...`), or "All entities" stays highlighted everywhere.
 */
const EXACT_ONLY_INDEX_HREFS = new Set([
  "/dashboard",
  "/dynamic-entities",
  "/ticketing",
  "/e-approval",
  "/settings",
  "/help",
]);

export function isNavActive(pathname: string, href: string, exact = false): boolean {
  const normalizedPath = pathname.replace(/\/$/, "") || "/";
  const normalizedHref = href.replace(/\/$/, "") || "/";

  const requireExact = exact || EXACT_ONLY_INDEX_HREFS.has(normalizedHref);

  if (requireExact) {
    return normalizedPath === normalizedHref;
  }

  if (normalizedPath === normalizedHref) {
    return true;
  }

  return normalizedPath.startsWith(`${normalizedHref}/`);
}

export function isAnyNavActive(pathname: string, hrefs: string[], exactFlags?: boolean[]): boolean {
  return hrefs.some((href, index) => isNavActive(pathname, href, exactFlags?.[index] ?? false));
}
