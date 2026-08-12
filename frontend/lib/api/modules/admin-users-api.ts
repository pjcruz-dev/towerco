import { apiClient } from "@/lib/api/client";
import type { PaginatedEnvelope, PaginatedMeta } from "@/lib/api/paginated";
import { normalizeAuthSession } from "@/modules/identity/auth-normalizer";
import type { AuthSession } from "@/types/auth";

export type AdminUserStatusFilter = "all" | "active" | "inactive";
export type AdminUserLastActiveFilter = "all" | "7d" | "30d" | "90d" | "never";
export type AdminUserMfaFilter = "all" | "enrolled" | "not_enrolled";

export type AdminUserActivityEntry = {
  id: string;
  event: string;
  label: string;
  risk_level: string;
  ip_address: string | null;
  context: Record<string, unknown> | null;
  created_at: string | null;
};

export type AdminUserRow = {
  id: string;
  name: string;
  email: string;
  is_active: boolean;
  deactivated_at: string | null;
  roles: string[];
  permissions: string[];
  can_impersonate?: boolean;
  created_at: string | null;
  updated_at: string | null;
  last_active_at: string | null;
  auth_methods: string[];
  mfa_enrolled: boolean;
  mfa_required: boolean;
};

export type AdminUserCreatePayload = {
  name: string;
  email: string;
  password?: string;
  roles?: string[];
};

export type AdminUserUpdatePayload = {
  name?: string;
  email?: string;
  password?: string;
  roles?: string[];
};

export type AdminUserCreateResponse = {
  id: string;
  name: string;
  email: string;
  roles: string[];
  generated_password: string | null;
};

export type AdminUserImportResult = {
  created: number;
  skipped: number;
  errors: string[];
};

export type AdminUserBulkActionResult = {
  processed: number;
  skipped: number;
  errors: Array<{ user_id: string; message: string }>;
};

export type AdminUserBulkResetPasswordResult = AdminUserBulkActionResult & {
  passwords: Array<{
    user_id: string;
    email: string;
    name: string;
    temporary_password: string;
  }>;
};

export type AdminUserSeatUsage = {
  seat_used: number;
  seat_limit: number;
  seats_available: number;
  viewer_seats_used: number;
  paid_seats_full: boolean;
  active_users: number;
};

export type AdminUserIdsResult = {
  ids: string[];
  total: number;
  truncated: boolean;
};

export type AdminUserListFilterParams = {
  search?: string;
  status?: AdminUserStatusFilter;
  last_active?: AdminUserLastActiveFilter;
  mfa?: AdminUserMfaFilter;
  role?: string;
  sort?: string;
};

const BULK_USER_IDS_CHUNK = 500;

function listFilterParams(params: AdminUserListFilterParams) {
  return {
    search: params.search,
    status: params.status && params.status !== "all" ? params.status : undefined,
    last_active: params.last_active && params.last_active !== "all" ? params.last_active : undefined,
    mfa: params.mfa && params.mfa !== "all" ? params.mfa : undefined,
    role: params.role && params.role !== "all" ? params.role : undefined,
    sort: params.sort,
  };
}

function chunkIds(ids: string[], size = BULK_USER_IDS_CHUNK): string[][] {
  const chunks: string[][] = [];
  for (let i = 0; i < ids.length; i += size) {
    chunks.push(ids.slice(i, i + size));
  }
  return chunks;
}

async function runBulkInChunks(
  userIds: string[],
  run: (chunk: string[]) => Promise<AdminUserBulkActionResult>,
): Promise<AdminUserBulkActionResult> {
  let processed = 0;
  let skipped = 0;
  const errors: AdminUserBulkActionResult["errors"] = [];

  for (const chunk of chunkIds(userIds)) {
    const result = await run(chunk);
    processed += result.processed;
    skipped += result.skipped;
    errors.push(...result.errors);
  }

  return { processed, skipped, errors };
}

export async function fetchAdminUsersIndex(params: {
  page?: number;
  per_page?: number;
  search?: string;
  status?: AdminUserStatusFilter;
  last_active?: AdminUserLastActiveFilter;
  mfa?: AdminUserMfaFilter;
  role?: string;
  sort?: string;
}): Promise<PaginatedEnvelope<AdminUserRow>> {
  const response = await apiClient.get<{ data: AdminUserRow[]; meta: PaginatedMeta }>("/admin/users", {
    params: {
      page: params.page,
      per_page: params.per_page,
      ...listFilterParams(params),
    },
  });

  return { data: response.data.data, meta: response.data.meta };
}

export async function fetchAdminUsersIds(params: AdminUserListFilterParams): Promise<AdminUserIdsResult> {
  const response = await apiClient.get<{ data: AdminUserIdsResult }>("/admin/users/ids", {
    params: listFilterParams(params),
  });
  return response.data.data;
}

export async function fetchAdminUsersSeatUsage(): Promise<AdminUserSeatUsage> {
  const response = await apiClient.get<{ data: AdminUserSeatUsage }>("/admin/users/seat-usage");
  return response.data.data;
}

export async function exportAdminUsersCsv(params: {
  search?: string;
  status?: AdminUserStatusFilter;
}): Promise<Blob> {
  const response = await apiClient.get<Blob>("/admin/users/export", {
    params: {
      search: params.search,
      status: params.status && params.status !== "all" ? params.status : undefined,
    },
    responseType: "blob",
  });
  return response.data;
}

export async function createAdminUser(payload: AdminUserCreatePayload): Promise<AdminUserCreateResponse> {
  const response = await apiClient.post<{ data: AdminUserCreateResponse }>("/admin/users", payload);
  return response.data.data;
}

export async function updateAdminUser(
  userId: string,
  payload: AdminUserUpdatePayload,
): Promise<{ id: string; name: string; email: string; roles: string[] }> {
  const response = await apiClient.patch<{ data: { id: string; name: string; email: string; roles: string[] } }>(
    `/admin/users/${userId}`,
    payload,
  );
  return response.data.data;
}

export async function deactivateAdminUser(userId: string): Promise<void> {
  await apiClient.post(`/admin/users/${userId}/deactivate`);
}

export async function bulkDeactivateAdminUsers(userIds: string[]): Promise<AdminUserBulkActionResult> {
  return runBulkInChunks(userIds, async (chunk) => {
    const response = await apiClient.post<{ data: AdminUserBulkActionResult }>("/admin/users/bulk-deactivate", {
      user_ids: chunk,
    });
    return response.data.data;
  });
}

export async function bulkAssignRoleAdminUsers(
  userIds: string[],
  role: string,
): Promise<AdminUserBulkActionResult> {
  return bulkAssignRolesAdminUsers(userIds, [role]);
}

export async function bulkAssignRolesAdminUsers(
  userIds: string[],
  roles: string[],
): Promise<AdminUserBulkActionResult> {
  return runBulkInChunks(userIds, async (chunk) => {
    const response = await apiClient.post<{ data: AdminUserBulkActionResult }>("/admin/users/bulk-assign-role", {
      user_ids: chunk,
      roles,
      mode: "add",
    });
    return response.data.data;
  });
}

export async function bulkResetPasswordAdminUsers(
  userIds: string[],
  options?: { password?: string; revoke_sessions?: boolean },
): Promise<AdminUserBulkResetPasswordResult> {
  let processed = 0;
  let skipped = 0;
  const errors: AdminUserBulkResetPasswordResult["errors"] = [];
  const passwords: AdminUserBulkResetPasswordResult["passwords"] = [];

  for (const chunk of chunkIds(userIds)) {
    const response = await apiClient.post<{ data: AdminUserBulkResetPasswordResult }>(
      "/admin/users/bulk-reset-password",
      {
        user_ids: chunk,
        password: options?.password,
        revoke_sessions: options?.revoke_sessions ?? true,
      },
    );
    const result = response.data.data;
    processed += result.processed;
    skipped += result.skipped;
    errors.push(...result.errors);
    passwords.push(...result.passwords);
  }

  return { processed, skipped, errors, passwords };
}

export async function reactivateAdminUser(userId: string): Promise<void> {
  await apiClient.post(`/admin/users/${userId}/reactivate`);
}

export async function deleteAdminUser(userId: string): Promise<void> {
  await apiClient.delete(`/admin/users/${userId}`);
}

export async function fetchAdminUserActivity(
  userId: string,
  limit = 50,
): Promise<AdminUserActivityEntry[]> {
  const response = await apiClient.get<{ data: AdminUserActivityEntry[] }>(
    `/admin/users/${userId}/activity`,
    { params: { limit } },
  );
  return response.data.data;
}

export async function revokeAdminUserSessions(userId: string): Promise<void> {
  await apiClient.post(`/admin/users/${userId}/revoke-sessions`);
}

export async function importAdminUsers(file: File): Promise<AdminUserImportResult> {
  const form = new FormData();
  form.append("file", file);

  const response = await apiClient.post<{ data: AdminUserImportResult }>("/admin/users/import", form, {
    headers: { "Content-Type": "multipart/form-data" },
  });

  return response.data.data;
}

export async function impersonateAdminUser(userId: string, reason: string): Promise<AuthSession> {
  const response = await apiClient.post<{ data: unknown }>(`/admin/users/${userId}/impersonate`, {
    reason,
  });
  return normalizeAuthSession(response.data.data);
}

export async function stopImpersonation(): Promise<void> {
  await apiClient.post("/auth/impersonation/stop");
}

/** Sample rows for Team & Access CSV import. Role names must exist; use commas for multiple roles. */
export const ADMIN_USERS_IMPORT_TEMPLATE_CSV = [
  "email,name,role",
  "jane.doe@example.com,Jane Doe,viewer",
  "approver@example.com,E Approval Approver,e_approval_approver",
  "viewer@example.com,E Approval Viewer,e_approval_viewer",
  "requestor@example.com,E Approval Requestor,e_approval_requestor",
  'multi.role@example.com,Multi Role User,"e_approval_approver,e_approval_requestor,e_approval_viewer"',
].join("\n") + "\n";
