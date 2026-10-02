import { apiClient } from "@/lib/api/client";

export type ScheduledTaskRun = {
  id: string;
  ran_at: string | null;
  status: string;
  error: string | null;
};

export type ScheduledTaskRow = {
  id: string;
  number: number;
  name: string;
  description: string | null;
  command_key: string;
  schedule: string;
  schedule_display: string;
  cron_expression: string;
  execution_label: string;
  is_system: boolean;
  is_active: boolean;
  last_run_at: string | null;
  next_run_at: string | null;
  last_status: string | null;
  last_error: string | null;
  status: "active" | "paused" | "failed" | "overdue";
  recent_runs: ScheduledTaskRun[];
  created_at: string | null;
  updated_at: string | null;
};

export type ScheduledTaskMeta = {
  total: number;
  runner: {
    title: string;
    hint: string;
    command: string;
    local_command: string;
  };
  commands: Array<{
    key: string;
    name: string;
    description: string;
    default_schedule: string;
    execution_label: string;
  }>;
  schedule_presets: Array<{
    value: string;
    label: string;
    cron_expression: string;
  }>;
  timezone?: string;
};

const scheduledTaskTimeoutMs = 90_000;

export async function listScheduledTasks(): Promise<{
  rows: ScheduledTaskRow[];
  meta: ScheduledTaskMeta;
}> {
  const response = await apiClient.get<{
    data: ScheduledTaskRow[];
    meta: ScheduledTaskMeta;
  }>("/automation/scheduled-tasks", { timeout: scheduledTaskTimeoutMs });
  return {
    rows: response.data.data ?? [],
    meta: response.data.meta ?? {
      total: 0,
      runner: {
        title: "System Cron Runner",
        hint: "",
        command: "php artisan schedule:run",
        local_command: "docker exec toweros-api php artisan schedule:run",
      },
      commands: [],
      schedule_presets: [],
      timezone: undefined,
    },
  };
}

export async function createScheduledTask(payload: {
  name: string;
  description?: string | null;
  command_key: string;
  schedule: string;
  cron_expression?: string | null;
  is_active?: boolean;
}): Promise<ScheduledTaskRow> {
  const response = await apiClient.post<{ data: ScheduledTaskRow }>(
    "/automation/scheduled-tasks",
    payload,
    { timeout: scheduledTaskTimeoutMs },
  );
  return response.data.data;
}

export async function updateScheduledTask(
  id: string,
  payload: Partial<{
    name: string;
    description: string | null;
    command_key: string;
    schedule: string;
    cron_expression: string | null;
    is_active: boolean;
  }>,
): Promise<ScheduledTaskRow> {
  const response = await apiClient.patch<{ data: ScheduledTaskRow }>(
    `/automation/scheduled-tasks/${id}`,
    payload,
    { timeout: scheduledTaskTimeoutMs },
  );
  return response.data.data;
}

export async function deleteScheduledTask(id: string): Promise<void> {
  await apiClient.delete(`/automation/scheduled-tasks/${id}`, { timeout: scheduledTaskTimeoutMs });
}

export async function runScheduledTask(id: string): Promise<ScheduledTaskRow> {
  const response = await apiClient.post<{ data: ScheduledTaskRow }>(
    `/automation/scheduled-tasks/${id}/run`,
    undefined,
    { timeout: scheduledTaskTimeoutMs },
  );
  return response.data.data;
}

export async function toggleScheduledTask(id: string): Promise<ScheduledTaskRow> {
  const response = await apiClient.post<{ data: ScheduledTaskRow }>(
    `/automation/scheduled-tasks/${id}/toggle`,
    undefined,
    { timeout: scheduledTaskTimeoutMs },
  );
  return response.data.data;
}

export async function syncScheduledTasks(): Promise<{ synced: number }> {
  const response = await apiClient.post<{ data: { synced: number } }>(
    "/automation/scheduled-tasks/sync",
    undefined,
    { timeout: scheduledTaskTimeoutMs },
  );
  return response.data.data;
}
