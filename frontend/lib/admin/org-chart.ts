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

export function buildOrgChartIndex(people: AdminOrgChartPerson[]): OrgChartIndex {
  const byId = new Map<string, OrgChartNode>();
  const reports = new Map<string, OrgChartNode[]>();

  for (const person of people) {
    byId.set(person.id, { ...person, external: false });
  }

  for (const person of people) {
    if (person.manager_id && byId.has(person.manager_id)) {
      const list = reports.get(person.manager_id) ?? [];
      list.push(byId.get(person.id)!);
      reports.set(person.manager_id, list);
      continue;
    }

    if (!person.manager_name && !person.manager_email) {
      continue;
    }

    const extId = externalManagerId(person.manager_name ?? person.manager_email ?? "", person.manager_email);
    if (!byId.has(extId)) {
      byId.set(extId, {
        id: extId,
        name: person.manager_name ?? person.manager_email ?? "Manager",
        email: person.manager_email ?? "",
        job_title: null,
        manager_id: null,
        manager_name: null,
        manager_email: null,
        direct_report_count: 0,
        external: true,
      });
    }
    const list = reports.get(extId) ?? [];
    list.push(byId.get(person.id)!);
    reports.set(extId, list);
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
  if (!person || person.external) {
    return null;
  }
  if (person.manager_id && index.byId.has(person.manager_id)) {
    const manager = index.byId.get(person.manager_id) ?? null;
    return manager?.id === person.id ? null : manager;
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
    .filter((node) => `${node.name} ${node.email} ${node.job_title ?? ""}`.toLowerCase().includes(needle))
    .slice(0, 12);
}
