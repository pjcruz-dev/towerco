import { apiClient } from "@/lib/api/client";

export type SidebarNavItemType =
  | "header"
  | "entity_link"
  | "internal_page"
  | "external_link"
  | "divider";

export type SidebarNavAdminItem = {
  id: string;
  parent_id: string | null;
  key: string | null;
  type: SidebarNavItemType;
  title: string;
  icon: string | null;
  href: string | null;
  entity_slug: string | null;
  permission_key: string | null;
  required_permissions: string[];
  permissions_match: "all" | "any";
  module: string | null;
  sort_order: number;
  is_system: boolean;
  is_visible: boolean;
  is_global_default: boolean;
  role_default_ids: number[];
  resolved_href: string | null;
};

export type SidebarNavRoleOption = {
  id: number;
  name: string;
};

export type SidebarNavAdminPayload = {
  items: SidebarNavAdminItem[];
  roles: SidebarNavRoleOption[];
  seeded: boolean;
};

export type WorkspaceSidebarRuntimeItem = {
  title: string;
  icon?: string | null;
  href?: string | null;
  module?: string | null;
  exact?: boolean;
  items?: WorkspaceSidebarRuntimeItem[];
};

export type WorkspaceSidebarPayload = {
  groups: Array<{ group: string; items: WorkspaceSidebarRuntimeItem[] }>;
  default_landing_href: string;
};

export async function fetchAdminSidebar(): Promise<SidebarNavAdminPayload> {
  const response = await apiClient.get<{ data: SidebarNavAdminPayload }>("/admin/sidebar");
  return response.data.data;
}

export async function createSidebarNavItem(
  payload: Partial<SidebarNavAdminItem> & { title: string; type: SidebarNavItemType },
): Promise<{ id: string }> {
  const response = await apiClient.post<{ data: { id: string } }>("/admin/sidebar/items", payload);
  return response.data.data;
}

export async function updateSidebarNavItem(
  id: string,
  payload: Partial<SidebarNavAdminItem> & { role_default_ids?: number[] },
): Promise<void> {
  await apiClient.patch(`/admin/sidebar/items/${id}`, payload);
}

export async function deleteSidebarNavItem(id: string): Promise<void> {
  await apiClient.delete(`/admin/sidebar/items/${id}`);
}

export async function reorderSidebarNavItems(payload: {
  parent_id: string | null;
  ordered_ids: string[];
}): Promise<void> {
  await apiClient.post("/admin/sidebar/reorder", payload);
}

export async function seedSidebarNav(force = false): Promise<SidebarNavAdminPayload> {
  const response = await apiClient.post<{ data: SidebarNavAdminPayload }>("/admin/sidebar/seed", {
    force,
  });
  return response.data.data;
}

export async function fetchWorkspaceSidebar(): Promise<WorkspaceSidebarPayload> {
  const response = await apiClient.get<{ data: WorkspaceSidebarPayload }>("/workspace/sidebar");
  return response.data.data;
}
