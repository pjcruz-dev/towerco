"use client";

import Link from "next/link";
import { useEffect, useMemo, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { GitBranch, Save, UploadCloud } from "lucide-react";

import { EApprovalPageHeader } from "@/components/e-approval/e-approval-page-header";
import { EApprovalSectionCard } from "@/components/e-approval/e-approval-section-card";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Button } from "@/components/ui/button";
import { Textarea } from "@/components/ui/textarea";
import {
  fetchEApprovalApprovalPolicy,
  publishEApprovalApprovalPolicy,
  updateEApprovalApprovalPolicyDraft,
} from "@/lib/api/modules/e-approval-api";
import { getErrorMessage } from "@/lib/api/error";
import { permissions } from "@/lib/rbac/permissions";
import type { EApprovalApprovalPolicyConfig } from "@/modules/e-approval/approval-policy-types";
import { useNotificationStore } from "@/stores/notification-store";

function formatAmount(value: number | null | undefined, currency: string): string {
  if (value === null || value === undefined) {
    return "—";
  }

  return new Intl.NumberFormat("en-PH", {
    style: "currency",
    currency,
    maximumFractionDigits: 0,
  }).format(value);
}

export function EApprovalApprovalPoliciesPageClient() {
  const queryClient = useQueryClient();
  const pushNotification = useNotificationStore((state) => state.push);
  const [draftJson, setDraftJson] = useState("");

  const policyQuery = useQuery({
    queryKey: ["e-approval", "approval-policies"],
    queryFn: fetchEApprovalApprovalPolicy,
  });

  const activeConfig = useMemo<EApprovalApprovalPolicyConfig | null>(() => {
    const snapshot = policyQuery.data;
    if (!snapshot) {
      return null;
    }

    return snapshot.draft_version?.config ?? snapshot.published_version?.config ?? snapshot.defaults;
  }, [policyQuery.data]);

  useEffect(() => {
    if (activeConfig) {
      setDraftJson(JSON.stringify(activeConfig, null, 2));
    }
  }, [activeConfig]);

  const saveMutation = useMutation({
    mutationFn: async () => {
      const parsed = JSON.parse(draftJson) as EApprovalApprovalPolicyConfig;
      return updateEApprovalApprovalPolicyDraft(parsed);
    },
    onSuccess: (data) => {
      queryClient.setQueryData(["e-approval", "approval-policies"], data);
      pushNotification({ title: "Approval policy draft saved", level: "success" });
    },
    onError: (error) => {
      pushNotification({ title: getErrorMessage(error), level: "error" });
    },
  });

  const publishMutation = useMutation({
    mutationFn: publishEApprovalApprovalPolicy,
    onSuccess: (data) => {
      queryClient.setQueryData(["e-approval", "approval-policies"], data);
      pushNotification({ title: "Approval policy published", level: "success" });
    },
    onError: (error) => {
      pushNotification({ title: getErrorMessage(error), level: "error" });
    },
  });

  const published = policyQuery.data?.published_version;
  const currency = activeConfig?.currency ?? "PHP";

  return (
    <PermissionGate requiredPermissions={[permissions.eApprovalSettingsManage]}>
      <div className="space-y-6">
        <EApprovalPageHeader
          title="Approval policies"
          description={
            <>
              <Link href="/e-approval/settings" className="text-primary hover:underline">
                E-Approval settings
              </Link>
              <span className="mx-1 text-muted-foreground">/</span>
              One tenant DOA matrix drives PR/PO approval chains at submit time. Forms with use_approval_policy enabled compile workflow steps from these rules.
            </>
          }
          actions={
            <>
              <Button
                size="sm"
                variant="outline"
                onClick={() => saveMutation.mutate()}
                disabled={saveMutation.isPending}
              >
                <Save className="mr-1.5 h-4 w-4" aria-hidden />
                {saveMutation.isPending ? "Saving…" : "Save draft"}
              </Button>
              <Button size="sm" onClick={() => publishMutation.mutate()} disabled={publishMutation.isPending}>
                <UploadCloud className="mr-1.5 h-4 w-4" aria-hidden />
                {publishMutation.isPending ? "Publishing…" : "Publish"}
              </Button>
            </>
          }
        />

        {policyQuery.isLoading ? <p className="text-sm text-muted-foreground">Loading policy…</p> : null}
        {policyQuery.isError ? <p className="text-sm text-destructive">Could not load approval policy.</p> : null}

        {published ? (
          <div className="rounded-lg border border-border bg-muted/20 px-4 py-3 text-sm text-muted-foreground">
            <span className="font-medium text-foreground">{published.label}</span>
            {published.published_at ? ` · published ${new Date(published.published_at).toLocaleString()}` : null}
            {policyQuery.data?.draft_version ? (
              <span className="ml-2 rounded-md bg-amber-500/10 px-2 py-0.5 text-xs text-amber-800 dark:text-amber-300">
                Draft pending
              </span>
            ) : null}
          </div>
        ) : null}

        {activeConfig ? (
          <>
            <EApprovalSectionCard
              title="Workflow profiles"
              description="Reusable step chains referenced by matrix rules (pr_standard, pr_capex, po_standard, po_high_value)."
            >
              <div className="grid gap-3 md:grid-cols-2">
                {Object.entries(activeConfig.workflow_profiles).map(([key, profile]) => (
                  <div key={key} className="rounded-lg border border-border bg-card p-3">
                    <div className="flex items-center gap-2 text-sm font-medium text-foreground">
                      <GitBranch className="h-4 w-4 text-primary" aria-hidden />
                      {profile.label}
                      <span className="font-mono text-xs text-muted-foreground">{key}</span>
                    </div>
                    <ol className="mt-2 space-y-1 text-sm text-muted-foreground">
                      {profile.steps.map((step, index) => (
                        <li key={`${key}-${index}`}>
                          {index + 1}. {step.type}
                          {step.approverId ? ` · ${step.approverId}` : ""}
                        </li>
                      ))}
                    </ol>
                  </div>
                ))}
              </div>
            </EApprovalSectionCard>

            <EApprovalSectionCard
              title="Policy matrix"
              description="Rules match document family, amount band, department, category, and urgency. Highest priority wins."
            >
              <div className="overflow-x-auto">
                <table className="w-full min-w-[720px] text-left text-sm">
                  <thead>
                    <tr className="border-b border-border text-xs text-muted-foreground">
                      <th className="px-2 py-2 font-medium">Priority</th>
                      <th className="px-2 py-2 font-medium">Document family</th>
                      <th className="px-2 py-2 font-medium">Amount band</th>
                      <th className="px-2 py-2 font-medium">Department</th>
                      <th className="px-2 py-2 font-medium">Urgency</th>
                      <th className="px-2 py-2 font-medium">Profile</th>
                    </tr>
                  </thead>
                  <tbody>
                    {activeConfig.rules.map((rule, index) => (
                      <tr key={`${rule.document_family}-${index}`} className="border-b border-border/70">
                        <td className="px-2 py-2">{rule.priority}</td>
                        <td className="px-2 py-2">{rule.document_family}</td>
                        <td className="px-2 py-2">
                          {formatAmount(rule.amount_min ?? null, currency)} – {formatAmount(rule.amount_max ?? null, currency)}
                        </td>
                        <td className="px-2 py-2">{rule.department ?? "Any"}</td>
                        <td className="px-2 py-2">{rule.urgency ?? "Any"}</td>
                        <td className="px-2 py-2 font-mono text-xs">{rule.workflow_profile}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </EApprovalSectionCard>

            <EApprovalSectionCard
              title="Draft configuration (JSON)"
              description="Advanced edits to profiles and rules. Publish to version the policy for new submissions."
            >
              <Textarea
                className="min-h-[360px] font-mono text-xs"
                value={draftJson}
                onChange={(event) => setDraftJson(event.target.value)}
                spellCheck={false}
              />
            </EApprovalSectionCard>
          </>
        ) : null}
      </div>
    </PermissionGate>
  );
}
