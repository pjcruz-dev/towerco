import type { LucideIcon } from "lucide-react";

import type { WorkspaceTopNavItem } from "@/lib/navigation/workspace-nav-config";

export type ModuleShellKind = "module" | "admin" | "hub";

export type ModuleShell = {
  id: string;
  kind: ModuleShellKind;
  title: string;
  /** Sidebar section label. The short `title` stays on the app switcher. */
  sectionTitle?: string;
  homeHref: string;
  prefixes: string[];
  /** Nav `module` keys that belong in this shell. */
  modules: string[];
};

/**
 * Product shells. The first URL segment picks one shell, and the sidebar
 * shows only that shell. Hub is the cross-module inbox at /dashboard.
 */
export const MODULE_SHELLS: readonly ModuleShell[] = [
  {
    id: "e_approval",
    kind: "module",
    title: "E-Forms",
    homeHref: "/e-approval",
    prefixes: ["/e-approval", "/help/e-approval"],
    modules: ["e_approval"],
  },
  {
    id: "ticketing",
    kind: "module",
    title: "Ticketing",
    homeHref: "/ticketing",
    prefixes: ["/ticketing"],
    modules: ["ticketing"],
  },
  {
    id: "document_register",
    kind: "module",
    title: "EDCS",
    sectionTitle: "Electronic Document Control System",
    homeHref: "/documents/controlled",
    prefixes: ["/documents"],
    modules: ["document_register", "documents"],
  },
  {
    id: "doc_extract",
    kind: "module",
    title: "DocExtract",
    homeHref: "/doc-extract",
    prefixes: ["/doc-extract"],
    modules: ["doc_extract"],
  },
  {
    id: "asset_management",
    kind: "module",
    title: "Assets",
    homeHref: "/assets",
    prefixes: ["/assets"],
    modules: ["asset_management"],
  },
  {
    id: "dynamic_entities",
    kind: "module",
    title: "Records",
    homeHref: "/dynamic-entities",
    prefixes: ["/dynamic-entities"],
    modules: ["dynamic_entities"],
  },
  {
    id: "administration",
    kind: "admin",
    title: "Administration",
    homeHref: "/users",
    prefixes: ["/users", "/governance", "/admin", "/settings"],
    modules: ["team_access"],
  },
];

export const HUB_SHELL: ModuleShell = {
  id: "hub",
  kind: "hub",
  title: "All apps",
  homeHref: "/dashboard",
  prefixes: [],
  modules: [],
};

export type ModuleShellLink = {
  id: string;
  kind: ModuleShellKind;
  title: string;
  homeHref: string;
  icon: LucideIcon;
};

type NavGroupLike = {
  group: string;
  items: WorkspaceTopNavItem[];
};

function pathnameOnly(href: string): string {
  const path = href.split("?")[0] ?? href;
  return path.replace(/\/$/, "") || "/";
}

export function hrefInModuleShell(href: string | undefined, shell: ModuleShell): boolean {
  if (!href || shell.kind === "hub") {
    return false;
  }
  const path = pathnameOnly(href);
  return shell.prefixes.some((prefix) => path === prefix || path.startsWith(`${prefix}/`));
}

export function resolveModuleShell(pathname: string): ModuleShell {
  const path = pathnameOnly(pathname.split("?")[0] ?? pathname);
  let best: { shell: ModuleShell; length: number } | null = null;

  for (const shell of MODULE_SHELLS) {
    for (const prefix of shell.prefixes) {
      if (path === prefix || path.startsWith(`${prefix}/`)) {
        if (!best || prefix.length > best.length) {
          best = { shell, length: prefix.length };
        }
      }
    }
  }

  return best?.shell ?? HUB_SHELL;
}

export function commandItemMatchesShell(
  item: { href: string; module?: string },
  shell: ModuleShell,
): boolean {
  if (shell.kind === "hub") {
    return true;
  }
  if (item.module && shell.modules.includes(item.module)) {
    return true;
  }
  return hrefInModuleShell(item.href, shell);
}

function childMatches(href: string, module: string | undefined, shell: ModuleShell): boolean {
  if (hrefInModuleShell(href, shell)) {
    return true;
  }
  return shell.kind !== "admin" && Boolean(module && shell.modules.includes(module));
}

/** Keep a nav item when it belongs to the shell, with children narrowed to that shell. */
export function narrowNavItem(item: WorkspaceTopNavItem, shell: ModuleShell): WorkspaceTopNavItem[] {
  if (shell.kind === "hub") {
    return [];
  }

  const moduleHit = Boolean(item.module && shell.modules.includes(item.module));
  const hrefHit = hrefInModuleShell(item.href, shell);

  if (item.items && item.items.length > 0) {
    const children = item.items.filter((sub) => childMatches(sub.href, sub.module, shell));

    if (shell.kind === "admin") {
      if (!hrefHit && children.length === 0) {
        return [];
      }
      return [{ ...item, ...(children.length > 0 ? { items: children } : { items: undefined }) }];
    }

    if (!moduleHit && !hrefHit) {
      if (item.module || children.length === 0) {
        return [];
      }
      return [{ ...item, items: children }];
    }

    const kept = item.items.filter(
      (sub) => !sub.module || shell.modules.includes(sub.module) || hrefInModuleShell(sub.href, shell),
    );
    if (kept.length === 0) {
      return hrefHit && item.href ? [{ ...item, items: undefined }] : [];
    }
    return [{ ...item, items: kept }];
  }

  if (moduleHit || hrefHit) {
    return [item];
  }
  return [];
}

export function moduleShellLinksFromGroups(groups: NavGroupLike[]): ModuleShellLink[] {
  const items = groups.flatMap((group) => group.items);
  const links: ModuleShellLink[] = [];

  for (const shell of MODULE_SHELLS) {
    if (shell.kind !== "module") {
      continue;
    }
    const matched = items.flatMap((item) => narrowNavItem(item, shell));
    if (matched.length === 0) {
      continue;
    }
    const primary =
      matched.find((item) => item.module && shell.modules.includes(item.module)) ?? matched[0]!;
    const homeHref = shell.homeHref;
    links.push({
      id: shell.id,
      kind: shell.kind,
      title: shell.title,
      homeHref,
      icon: primary.icon,
    });
  }

  return links;
}

export function applyModuleShellToGroups(groups: NavGroupLike[], shell: ModuleShell): NavGroupLike[] {
  if (shell.kind === "hub") {
    const items: WorkspaceTopNavItem[] = [];
    const flat = groups.flatMap((group) => group.items);
    const dashboard = flat.find((item) => item.href === "/dashboard");
    if (dashboard) {
      items.push(dashboard);
    }
    for (const link of moduleShellLinksFromGroups(groups)) {
      if (link.kind === "admin") {
        continue;
      }
      items.push({
        title: link.title,
        href: link.homeHref,
        exact: true,
        icon: link.icon,
        permissions: [],
        module: link.id,
      });
    }
    return items.length > 0 ? [{ group: "Apps", items }] : [];
  }

  const items = groups.flatMap((group) => group.items.flatMap((item) => narrowNavItem(item, shell)));
  return items.length > 0 ? [{ group: shell.sectionTitle ?? shell.title, items }] : [];
}

function collectShownHrefs(groups: NavGroupLike[]): Set<string> {
  const hrefs = new Set<string>();
  const visit = (item: { href?: string; items?: Array<{ href?: string; items?: Array<{ href?: string }> }> }) => {
    if (item.href) {
      hrefs.add(pathnameOnly(item.href));
    }
    item.items?.forEach((child) => visit(child));
  };
  groups.forEach((group) => group.items.forEach((item) => visit(item)));
  return hrefs;
}

function isProductModuleItem(item: WorkspaceTopNavItem): boolean {
  const product = MODULE_SHELLS.filter((shell) => shell.kind === "module");
  if (item.module && product.some((shell) => shell.modules.includes(item.module!))) {
    return true;
  }
  const hrefs = [item.href, ...(item.items ?? []).map((child) => child.href)].filter(
    (href): href is string => Boolean(href),
  );
  if (hrefs.length === 0) {
    return false;
  }
  return hrefs.every((href) => product.some((shell) => hrefInModuleShell(href, shell)));
}

function footerItemWithoutShown(
  item: WorkspaceTopNavItem,
  shown: Set<string>,
): WorkspaceTopNavItem | null {
  const children = (item.items ?? [])
    .filter((child) => !shown.has(pathnameOnly(child.href)))
    .map((child) => child);
  const ownHref = item.href ? pathnameOnly(item.href) : undefined;
  const ownShown = ownHref ? shown.has(ownHref) : false;

  if (children.length > 0) {
    return { ...item, ...(ownShown ? { href: undefined } : {}), items: children };
  }
  if (!ownHref || ownShown) {
    return null;
  }
  return { ...item, items: undefined };
}

/**
 * Pinned links are the nav items that are not product modules and are not
 * already rendered in the current shell. Same item never appears twice.
 */
export function shellFooterItems(
  groups: NavGroupLike[],
  shownGroups: NavGroupLike[],
): WorkspaceTopNavItem[] {
  const shown = collectShownHrefs(shownGroups);
  const shownTitles = new Set(shownGroups.flatMap((group) => group.items.map((item) => item.title)));
  return groups.flatMap((group) =>
    group.items.flatMap((item) => {
      if (
        isProductModuleItem(item) ||
        shownTitles.has(item.title) ||
        (item.href ? pathnameOnly(item.href) === HUB_SHELL.homeHref : false)
      ) {
        return [];
      }
      const kept = footerItemWithoutShown(item, shown);
      return kept ? [kept] : [];
    }),
  );
}

function footerBundleSource(items: WorkspaceTopNavItem[]): WorkspaceTopNavItem {
  let best = items[0]!;
  let bestCount = -1;
  for (const item of items) {
    const count = item.items?.length ?? (item.href ? 1 : 0);
    if (count > bestCount) {
      best = item;
      bestCount = count;
    }
  }
  return best;
}

/**
 * Several footer entries become one expandable item. A single entry stays as it is.
 * The label is the live item that contributes the most links, so a normal user
 * sees Settings rather than the nav group's name.
 */
export function compressShellFooter(items: WorkspaceTopNavItem[]): WorkspaceTopNavItem[] {
  if (items.length <= 1) {
    return items;
  }

  const source = footerBundleSource(items);
  const ordered = [source, ...items.filter((item) => item !== source)];
  const children = ordered.flatMap((item) => {
    if (item.items && item.items.length > 0) {
      return item.items.map((child) => ({ ...child, section: item.title }));
    }
    if (!item.href) {
      return [];
    }
    return [
      {
        title: item.title,
        href: item.href,
        exact: item.exact,
        permissions: item.permissions,
        module: item.module,
      },
    ];
  });
  const home = children[0]?.href;
  const icon = ordered[0]?.icon ?? items[0]?.icon;
  if (!home || !icon || children.length === 0) {
    return items;
  }

  return [
    {
      title: source.title,
      icon,
      href: home,
      permissions: [],
      items: children,
    },
  ];
}
