import type { AdminOrgChartPerson } from "@/lib/api/modules/admin-users-api";

export type OrgChartNode = AdminOrgChartPerson & {
  external?: boolean;
};

export type OrgChartIndex = {
  byId: Map<string, OrgChartNode>;
  reports: Map<string, OrgChartNode[]>;
  nodes: OrgChartNode[];
};

export function externalManagerId(name: string, email?: string | null): string {
  const key = (email?.trim() || name).toLowerCase();
  return `ext:${key}`;
}

function sortNodes(list: OrgChartNode[]): OrgChartNode[] {
  return list.sort((a, b) => a.name.localeCompare(b.name, undefined, { sensitivity: "base" }));
}

function emailsMatch(left: string | null | undefined, right: string | null | undefined): boolean {
  const a = left?.trim().toLowerCase() ?? "";
  const b = right?.trim().toLowerCase() ?? "";
  return a !== "" && a === b;
}

function findChartPersonId(byId: Map<string, OrgChartNode>, email?: string | null, name?: string | null): string | null {
  const needleName = name?.trim().toLowerCase() ?? "";
  for (const node of byId.values()) {
    if (node.external) {
      continue;
    }
    if (emailsMatch(node.email, email)) {
      return node.id;
    }
  }
  if (needleName === "") {
    return null;
  }
  const named = [...byId.values()].filter(
    (node) => !node.external && node.name.trim().toLowerCase() === needleName,
  );
  return named.length === 1 ? named[0]!.id : null;
}

export function buildOrgChartIndex(people: AdminOrgChartPerson[]): OrgChartIndex {
  const byId = new Map<string, OrgChartNode>();
  const reports = new Map<string, OrgChartNode[]>();

  for (const person of people) {
    byId.set(person.id, { ...person, external: false });
  }

  const pushReport = (managerId: string, personId: string) => {
    const child = byId.get(personId);
    if (!child) {
      return;
    }
    const list = reports.get(managerId) ?? [];
    if (list.some((node) => node.id === personId)) {
      return;
    }
    list.push(child);
    reports.set(managerId, list);
  };

  for (const person of people) {
    if (person.manager_id && byId.has(person.manager_id)) {
      pushReport(person.manager_id, person.id);
      continue;
    }

    if (!person.manager_name && !person.manager_email) {
      continue;
    }

    const existingManagerId = findChartPersonId(byId, person.manager_email, person.manager_name);
    if (existingManagerId) {
      pushReport(existingManagerId, person.id);
      continue;
    }

    const managerLicensed = person.manager_licensed === true || Boolean(person.manager_license_label);
    if (!managerLicensed) {
      continue;
    }

    const extId = externalManagerId(person.manager_name ?? person.manager_email ?? "", person.manager_email);
    if (!byId.has(extId)) {
      const parentId =
        person.manager_parent_id && byId.has(person.manager_parent_id) ? person.manager_parent_id : null;
      byId.set(extId, {
        id: extId,
        name: person.manager_name ?? person.manager_email ?? "Manager",
        email: person.manager_email ?? "",
        job_title: null,
        department: person.manager_department ?? null,
        manager_id: parentId,
        manager_name: null,
        manager_email: null,
        direct_report_count: 0,
        license_label: person.manager_license_label ?? null,
        license_names: [],
        roles: [],
        photo_url: null,
        external: true,
      });
      if (parentId) {
        pushReport(parentId, extId);
      }
    } else {
      const existing = byId.get(extId);
      if (existing && !existing.department && person.manager_department) {
        existing.department = person.manager_department;
      }
    }
    pushReport(extId, person.id);
  }

  for (const [id, list] of reports) {
    reports.set(id, sortNodes(list));
    const manager = byId.get(id);
    if (manager) {
      manager.direct_report_count = list.length;
    }
  }

  return { byId, reports, nodes: sortNodes([...byId.values()]) };
}

export function resolveManager(index: OrgChartIndex, person: OrgChartNode | undefined): OrgChartNode | null {
  if (!person) {
    return null;
  }
  if (person.manager_id && index.byId.has(person.manager_id)) {
    const manager = index.byId.get(person.manager_id) ?? null;
    return manager?.id === person.id ? null : manager;
  }
  if (person.external) {
    return null;
  }
  const label = person.manager_name ?? person.manager_email;
  if (!label) {
    return null;
  }
  const manager = index.byId.get(externalManagerId(label, person.manager_email)) ?? null;
  return manager?.id === person.id ? null : manager;
}

export function orgChartRoots(index: OrgChartIndex): OrgChartNode[] {
  const childIds = new Set<string>();
  for (const list of index.reports.values()) {
    for (const child of list) {
      childIds.add(child.id);
    }
  }

  return [...index.nodes]
    .filter((node) => !childIds.has(node.id))
    .sort((a, b) => {
      if (b.direct_report_count !== a.direct_report_count) {
        return b.direct_report_count - a.direct_report_count;
      }
      return a.name.localeCompare(b.name, undefined, { sensitivity: "base" });
    });
}

export function expandableOrgIds(index: OrgChartIndex): string[] {
  return [...index.reports.entries()]
    .filter(([, list]) => list.length > 0)
    .map(([id]) => id);
}

export function pickDefaultFocus(index: OrgChartIndex, currentUserId?: string | null): string | null {
  if (currentUserId && index.byId.has(currentUserId)) {
    return currentUserId;
  }

  let best: OrgChartNode | null = null;
  for (const node of index.nodes) {
    if (node.external) {
      continue;
    }
    if (!best || node.direct_report_count > best.direct_report_count) {
      best = node;
    }
  }

  return best?.id ?? index.nodes[0]?.id ?? null;
}

export function personInitials(name: string): string {
  const parts = name.trim().split(/\s+/).filter(Boolean);
  if (parts.length === 0) {
    return "?";
  }
  if (parts.length === 1) {
    return parts[0].slice(0, 2).toUpperCase();
  }
  return `${parts[0][0] ?? ""}${parts[parts.length - 1][0] ?? ""}`.toUpperCase();
}

export function filterOrgPeople(nodes: OrgChartNode[], query: string): OrgChartNode[] {
  const needle = query.trim().toLowerCase();
  if (needle === "") {
    return [];
  }

  return nodes
    .filter((node) =>
      `${node.name} ${node.email} ${node.job_title ?? ""} ${node.department ?? ""} ${node.license_label ?? ""} ${(node.roles ?? []).join(" ")}`
        .toLowerCase()
        .includes(needle),
    )
    .slice(0, 12);
}

export type OrgChartFilters = {
  department: string;
  license: string;
  role: string;
};

export function emptyOrgChartFilters(): OrgChartFilters {
  return { department: "", license: "", role: "" };
}

export function orgChartFiltersActive(filters: OrgChartFilters): boolean {
  return filters.department !== "" || filters.license !== "" || filters.role !== "";
}

export function collectOrgFilterOptions(nodes: OrgChartNode[]): {
  departments: string[];
  licenses: string[];
  roles: string[];
} {
  const departments = new Set<string>();
  const licenses = new Set<string>();
  const roles = new Set<string>();

  for (const node of nodes) {
    if (node.department?.trim()) departments.add(node.department.trim());
    if (node.license_label?.trim()) licenses.add(node.license_label.trim());
    for (const role of node.roles ?? []) {
      if (role.trim()) roles.add(role.trim());
    }
  }

  const sort = (list: string[]) =>
    list.sort((a, b) => a.localeCompare(b, undefined, { sensitivity: "base" }));

  return {
    departments: sort([...departments]),
    licenses: sort([...licenses]),
    roles: sort([...roles]),
  };
}

function nodeMatchesFilters(node: OrgChartNode, filters: OrgChartFilters): boolean {
  if (filters.department && (node.department ?? "").trim() !== filters.department) {
    return false;
  }
  if (filters.license && (node.license_label ?? "").trim() !== filters.license) {
    return false;
  }
  if (filters.role && !(node.roles ?? []).includes(filters.role)) {
    return false;
  }
  return true;
}

/** Keep matches plus ancestors so reporting lines stay connected. */
export function filterOrgChartIndex(index: OrgChartIndex, filters: OrgChartFilters): OrgChartIndex {
  if (!orgChartFiltersActive(filters)) {
    return index;
  }

  const matches = new Set(
    index.nodes.filter((node) => nodeMatchesFilters(node, filters)).map((node) => node.id),
  );
  if (matches.size === 0) {
    return { byId: new Map(), reports: new Map(), nodes: [] };
  }

  const keep = new Set(matches);
  for (const id of matches) {
    let current = index.byId.get(id);
    while (current) {
      keep.add(current.id);
      const manager = resolveManager(index, current);
      if (!manager || keep.has(manager.id)) {
        break;
      }
      current = manager;
    }
  }

  const byId = new Map<string, OrgChartNode>();
  for (const id of keep) {
    const node = index.byId.get(id);
    if (node) {
      byId.set(id, { ...node });
    }
  }

  const reports = new Map<string, OrgChartNode[]>();
  for (const [managerId, list] of index.reports) {
    if (!keep.has(managerId)) continue;
    const children = list.filter((child) => keep.has(child.id)).map((child) => byId.get(child.id)!);
    if (children.length === 0) continue;
    reports.set(managerId, sortNodes([...children]));
    const manager = byId.get(managerId);
    if (manager) {
      manager.direct_report_count = children.length;
    }
  }

  for (const node of byId.values()) {
    if (!reports.has(node.id)) {
      node.direct_report_count = 0;
    }
  }

  return { byId, reports, nodes: sortNodes([...byId.values()]) };
}
