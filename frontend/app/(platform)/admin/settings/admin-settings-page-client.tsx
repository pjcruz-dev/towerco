"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useEffect, useState } from "react";

import { PermissionGate } from "@/components/layout/permission-gate";
import { Button } from "@/components/ui/button";
import { SectionCardSkeleton } from "@/components/ui/page-skeletons";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { getErrorMessage } from "@/lib/api/error";
import {
  fetchAdminSettings,
  updateAdminSettings,
  type AdminSettingsPayload,
} from "@/lib/api/modules/admin-settings-api";
import { permissions } from "@/lib/rbac/permissions";
import { useNotificationStore } from "@/stores/notification-store";

type SettingsTab = "kpi" | "sla" | "workflows";

function stringifyJson(value: unknown): string {
  try {
    return JSON.stringify(value ?? {}, null, 2);
  } catch {
    return "{}";
  }
}

function parseJson(text: string): unknown {
  return JSON.parse(text) as unknown;
}

export function AdminSettingsPageClient() {
  const queryClient = useQueryClient();
  const notify = useNotificationStore((state) => state.push);
  const [tab, setTab] = useState<SettingsTab>("kpi");
  const [kpiText, setKpiText] = useState("{}");
  const [slaText, setSlaText] = useState("{}");
  const [workflowText, setWorkflowText] = useState("[]");

  const settingsQuery = useQuery({
    queryKey: ["admin", "settings"],
    queryFn: fetchAdminSettings,
  });

  useEffect(() => {
    if (!settingsQuery.data) return;
    setKpiText(stringifyJson(settingsQuery.data.kpi_config));
    setSlaText(stringifyJson(settingsQuery.data.sla_config));
    setWorkflowText(stringifyJson(settingsQuery.data.workflow_templates ?? []));
  }, [settingsQuery.data]);

  const saveMutation = useMutation({
    mutationFn: async () => {
      const payload: Partial<AdminSettingsPayload> = {};
      if (tab === "kpi") {
        payload.kpi_config = parseJson(kpiText) as Record<string, unknown>;
      } else if (tab === "sla") {
        payload.sla_config = parseJson(slaText) as Record<string, unknown>;
      } else {
        payload.workflow_templates = parseJson(workflowText) as Record<string, unknown>[];
      }
      return updateAdminSettings(payload);
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["admin", "settings"] });
      notify({ level: "success", title: "Settings saved", message: "Configuration updated for this tenant." });
    },
    onError: (error) => {
      notify({ level: "error", title: "Save failed", message: getErrorMessage(error) });
    },
  });

  const editor = (value: string, onChange: (next: string) => void, hint: string) => (
    <div className="space-y-2">
      <p className="text-sm text-muted-foreground">{hint}</p>
      <textarea
        value={value}
        onChange={(e) => onChange(e.target.value)}
        spellCheck={false}
        className="min-h-[320px] w-full rounded-md border border-border bg-background p-3 font-mono text-xs leading-relaxed text-foreground outline-none focus:border-ring"
      />
    </div>
  );

  return (
    <PermissionGate requiredPermissions={[permissions.tenantManage]}>
      <div className="space-y-5">
        <header>
          <h1 className="text-2xl font-semibold tracking-tight text-foreground">Admin configuration</h1>
          <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
            KPI targets, SLA policies, and workflow templates for operational governance. JSON is validated server-side
            on save.
          </p>
        </header>

        {settingsQuery.isLoading ? (
          <SectionCardSkeleton fields={6} />
        ) : settingsQuery.isError ? (
          <p className="text-sm text-destructive">Could not load admin settings.</p>
        ) : (
          <div className="rounded-xl border border-border bg-card p-4 shadow-sm">
            <Tabs value={tab} onValueChange={(value) => setTab(value as SettingsTab)}>
              <TabsList>
                <TabsTrigger value="kpi">KPI targets</TabsTrigger>
                <TabsTrigger value="sla">SLA policies</TabsTrigger>
                <TabsTrigger value="workflows">Workflow builder</TabsTrigger>
              </TabsList>

              <TabsContent value="kpi" className="mt-4 space-y-4">
                {editor(
                  kpiText,
                  setKpiText,
                  "Define KPI targets (e.g. uptime %, work-order SLA). Use the targets array schema from defaults.",
                )}
              </TabsContent>
              <TabsContent value="sla" className="mt-4 space-y-4">
                {editor(
                  slaText,
                  setSlaText,
                  "Map severity levels to response and resolution windows in minutes/hours.",
                )}
              </TabsContent>
              <TabsContent value="workflows" className="mt-4 space-y-4">
                {editor(
                  workflowText,
                  setWorkflowText,
                  "Workflow templates as a JSON array. Each entry can define steps, approvers, and module hooks.",
                )}
              </TabsContent>
            </Tabs>

            <div className="mt-4 flex justify-end border-t border-border pt-4">
              <Button disabled={saveMutation.isPending} onClick={() => saveMutation.mutate()}>
                {saveMutation.isPending ? "Saving…" : "Save section"}
              </Button>
            </div>
          </div>
        )}
      </div>
    </PermissionGate>
  );
}
