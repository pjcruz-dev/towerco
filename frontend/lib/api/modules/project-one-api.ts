import { apiClient } from "@/lib/api/client";
import type { PaginatedEnvelope, PaginatedMeta } from "@/lib/api/paginated";
import type {
  CreateProjectInput,
  ProjectDetail,
  ProjectListRow,
  ProjectOneApprovalListRow,
  ProjectOneDashboardResponse,
  ProjectOneMapPin,
  UpdateProjectInput,
} from "@/modules/project-one/types";
import type { RolloutListRow } from "@/modules/rollout/types";

export type CreateProjectApprovalInput = {
  approval_type: string;
  title: string;
  requester: string;
  sla_risk: "low" | "medium" | "high";
  project_id?: string;
  rollout_program_id?: string;
  attachment_file_ids?: string[];
};

export type ResolveProjectApprovalInput = {
  status: "approved" | "rejected";
  resolution_notes?: string;
};

export type FetchProjectOneDashboardOptions = {
  /** Lazy-load map pins (recommended). Omit for fastest KPI-first paint. */
  includeMap?: boolean;
};

export async function fetchProjectOneDashboard(
  options: FetchProjectOneDashboardOptions = {},
): Promise<ProjectOneDashboardResponse> {
  const base = process.env.NEXT_PUBLIC_PROJECT_ONE_DASHBOARD_PATH ?? "/project-one/dashboard";
  const path = options.includeMap ? `${base}?include=map` : base;

  const response = await apiClient.get<{ data: ProjectOneDashboardResponse }>(path);

  return response.data.data;
}

/** Load dashboard map pins after KPIs (see docs/modules/project-one-performance.md). */
export async function fetchProjectOneDashboardMap(): Promise<ProjectOneMapPin[]> {
  const response = await apiClient.get<{ data: { map_pins: ProjectOneMapPin[] } }>(
    "/project-one/dashboard/map",
  );

  return response.data.data.map_pins ?? [];
}

export async function fetchProjectOneProjectsIndex(params?: {
  page?: number;
  per_page?: number;
  search?: string;
  site_id?: string;
  sort?: string;
}): Promise<PaginatedEnvelope<ProjectListRow>> {
  const response = await apiClient.get<{ data: ProjectListRow[]; meta: PaginatedMeta }>("/project-one/projects", {
    params,
  });

  return { data: response.data.data, meta: response.data.meta };
}

/** Alias used by procurement / e-approval link fields. */
export const fetchProjectsIndex = fetchProjectOneProjectsIndex;

export async function fetchProjectOneProject(id: string): Promise<ProjectDetail> {
  const response = await apiClient.get<{ data: ProjectDetail }>(`/project-one/projects/${id}`);

  return response.data.data;
}

export async function createProjectOneProject(body: CreateProjectInput): Promise<ProjectDetail> {
  const response = await apiClient.post<{ data: ProjectDetail }>("/project-one/projects", body);

  return response.data.data;
}

export async function updateProjectOneProject(id: string, body: UpdateProjectInput): Promise<ProjectDetail> {
  const response = await apiClient.patch<{ data: ProjectDetail }>(`/project-one/projects/${id}`, body);

  return response.data.data;
}

export async function fetchProjectOneApprovalsIndex(params?: {
  page?: number;
  per_page?: number;
  search?: string;
  status?: "pending" | "approved" | "rejected" | "all";
  sort?: string;
}): Promise<PaginatedEnvelope<ProjectOneApprovalListRow>> {
  const response = await apiClient.get<{ data: ProjectOneApprovalListRow[]; meta: PaginatedMeta }>(
    "/project-one/approvals",
    { params },
  );

  return { data: response.data.data, meta: response.data.meta };
}

export async function createProjectApproval(
  body: CreateProjectApprovalInput,
): Promise<ProjectOneApprovalListRow> {
  const response = await apiClient.post<{ data: ProjectOneApprovalListRow }>("/project-one/approvals", body);

  return response.data.data;
}

export async function resolveProjectApproval(
  id: string,
  body: ResolveProjectApprovalInput,
): Promise<{ id: string; status: string }> {
  const response = await apiClient.patch<{ data: { id: string; status: string } }>(
    `/project-one/approvals/${id}`,
    body,
  );

  return response.data.data;
}

export async function updateProjectMilestoneStatus(
  milestoneId: string,
  status: "pending" | "in_progress" | "completed" | "overdue",
): Promise<{ id: string; status: string }> {
  const response = await apiClient.patch<{ data: { id: string; status: string } }>(
    `/project-one/milestones/${milestoneId}`,
    { status },
  );

  return response.data.data;
}

export async function fetchRolloutsIndex(params?: {
  page?: number;
  per_page?: number;
  search?: string;
}): Promise<PaginatedEnvelope<RolloutListRow>> {
  const response = await apiClient.get<{ data: RolloutListRow[]; meta: PaginatedMeta }>(
    "/project-one/rollouts",
    { params },
  );

  return { data: response.data.data, meta: response.data.meta };
}
