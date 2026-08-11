import { apiClient } from "@/lib/api/client";

export type WorkspaceEnvironmentLink = {
  environment: string;
  label: string;
  hostname: string;
  login_url: string;
  /** True when the sibling tenant has Microsoft SSO enabled (central config). */
  sso_enabled?: boolean;
  sso_url?: string | null;
  /** Preferred deep-link: SSO soft-switch when available, otherwise login_url. */
  switch_url?: string;
  /** Phase 3: central ticket handoff can be minted for this sibling. */
  handoff_available?: boolean;
  is_current: boolean;
};

export type WorkspaceEnvironmentsResponse = {
  current: {
    environment: string;
    hostname: string | null;
  };
  handoff_supported?: boolean;
  environments: WorkspaceEnvironmentLink[];
};

export type WorkspaceEnvironmentHandoffResponse = {
  redeem_url: string;
  expires_at: string;
  target_environment: string;
  target_hostname: string;
};

export async function fetchWorkspaceEnvironments(): Promise<WorkspaceEnvironmentsResponse> {
  const response = await apiClient.get<{ data: WorkspaceEnvironmentsResponse }>(
    "/workspace/environments",
  );

  return response.data.data;
}

export async function mintWorkspaceEnvironmentHandoff(
  environment: string,
): Promise<WorkspaceEnvironmentHandoffResponse> {
  const response = await apiClient.post<{ data: WorkspaceEnvironmentHandoffResponse }>(
    "/workspace/environments/handoff",
    { environment },
  );

  return response.data.data;
}

export async function redeemWorkspaceEnvironmentHandoff(ticket: string): Promise<unknown> {
  const response = await apiClient.post<{ data: unknown }>(
    "/auth/environment-handoff/redeem",
    { ticket },
    { timeout: 60_000 },
  );

  return response.data.data;
}
