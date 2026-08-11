import { apiClient } from "@/lib/api/client";

export type AdminRoleAssignedUser = {
  id: string;
  name: string;
  email: string;
  is_active: boolean;
};

export type AdminRoleRow = {
  id: number;
  name: string;
  is_baseline: boolean;
  is_system?: boolean;
  permissions: string[];
  user_count: number;
};

export type AdminRoleDetail = AdminRoleRow & {
  users: AdminRoleAssignedUser[];
};

export type AdminRolePermissionGroup = {
  label: string;
  permissions: string[];
};

export type AdminRoleCatalog = {
  roles: AdminRoleRow[];
  permissions: string[];
  permission_groups?: Record<string, AdminRolePermissionGroup>;
  enabled_modules?: string[];
};

export type AdminRoleCreatePayload = {
  name: string;
  permissions: string[];
};

export type AdminRoleCompareResult = {
  left: AdminRoleRow;
  right: AdminRoleRow;
  only_left: string[];
  only_right: string[];
  shared: string[];
};

export async function fetchAdminRoleCatalog(): Promise<AdminRoleCatalog> {
  const response = await apiClient.get<{ data: AdminRoleCatalog }>("/admin/roles");
  return response.data.data;
}

export async function fetchAdminRole(roleId: number): Promise<AdminRoleDetail> {
  const response = await apiClient.get<{ data: AdminRoleDetail }>(`/admin/roles/${roleId}`);
  return response.data.data;
}

export async function createAdminRole(payload: AdminRoleCreatePayload): Promise<AdminRoleRow> {
  const response = await apiClient.post<{ data: AdminRoleRow }>("/admin/roles", payload);
  return response.data.data;
}

export async function cloneAdminRole(roleId: number, name: string): Promise<AdminRoleRow> {
  const response = await apiClient.post<{ data: AdminRoleRow }>(`/admin/roles/${roleId}/clone`, { name });
  return response.data.data;
}

export async function updateAdminRole(
  roleId: number,
  permissions: string[],
): Promise<AdminRoleRow> {
  const response = await apiClient.patch<{ data: AdminRoleRow }>(`/admin/roles/${roleId}`, {
    permissions,
  });
  return response.data.data;
}

export async function deleteAdminRole(roleId: number): Promise<void> {
  await apiClient.delete(`/admin/roles/${roleId}`);
}

export async function compareAdminRoles(leftId: number, rightId: number): Promise<AdminRoleCompareResult> {
  const response = await apiClient.get<{ data: AdminRoleCompareResult }>("/admin/roles/compare", {
    params: { left: leftId, right: rightId },
  });
  return response.data.data;
}

export function suggestRoleCloneName(roleName: string): string {
  const base = `${roleName.trim().toLowerCase().replace(/\s+/g, "_")}_copy`;
  return base.slice(0, 64);
}
