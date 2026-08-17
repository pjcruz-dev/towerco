export type PermissionGroup = {
  id: string;
  label: string;
  permissions: string[];
};

const MODULE_GROUP_LABELS: Record<string, string> = {
  dashboard: "Core",
  workspace: "Core",
  tenant: "Platform",
  user: "Team & Access",
  role: "Team & Access",
  billing: "Team & Access",
  sites: "Sites",
  documents: "Documents",
  gis: "GIS",
  project_one: "PROJECT-ONE",
  tower_one: "TOWER-ONE",
  fiber_one: "FIBER-ONE",
  asset_one: "ASSET-ONE",
  e_approval: "E-Approval",
  ticketing: "Ticketing",
  procurement_one: "Procurement-One",
  finance_one: "Finance-One",
  ai_assistant: "AI Assistant",
};

const GROUP_ORDER = [
  "Core",
  "Platform",
  "Team & Access",
  "Sites",
  "Documents",
  "PROJECT-ONE",
  "TOWER-ONE",
  "FIBER-ONE",
  "ASSET-ONE",
  "E-Approval",
  "Ticketing",
  "Procurement-One",
  "Finance-One",
  "AI Assistant",
  "GIS",
  "Other",
];

function permissionGroupId(permission: string): string {
  const prefix = permission.split(":")[0]?.trim() ?? "";
  return MODULE_GROUP_LABELS[prefix] ?? "Other";
}

function permissionGroupLabel(id: string): string {
  return id;
}

export function permissionLabel(name: string): string {
  return name.replace(/_/g, " ").replace(/:/g, " · ");
}

/** Group a flat permission catalog by module prefix for picker UIs. */
export function groupPermissionsByModule(permissions: string[]): PermissionGroup[] {
  const buckets = new Map<string, string[]>();

  for (const permission of permissions) {
    const groupId = permissionGroupId(permission);
    const list = buckets.get(groupId) ?? [];
    list.push(permission);
    buckets.set(groupId, list);
  }

  return GROUP_ORDER.filter((id) => buckets.has(id))
    .map((id) => ({
      id,
      label: permissionGroupLabel(id),
      permissions: [...(buckets.get(id) ?? [])].sort((a, b) => a.localeCompare(b)),
    }))
    .concat(
      [...buckets.keys()]
        .filter((id) => !GROUP_ORDER.includes(id))
        .sort()
        .map((id) => ({
          id,
          label: permissionGroupLabel(id),
          permissions: [...(buckets.get(id) ?? [])].sort((a, b) => a.localeCompare(b)),
        })),
    );
}
