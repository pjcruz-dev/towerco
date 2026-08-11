/**
 * Returns whether a nav href should appear active for the current pathname.
 * Module dashboards (e.g. /project-one) use exact match so sibling routes stay distinct.
 */
export function isNavActive(pathname: string, href: string, exact = false): boolean {
  const normalizedPath = pathname.replace(/\/$/, "") || "/";
  const normalizedHref = href.replace(/\/$/, "") || "/";

  if (exact) {
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
