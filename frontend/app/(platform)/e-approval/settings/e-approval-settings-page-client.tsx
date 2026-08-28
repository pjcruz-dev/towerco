"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useEffect, useState } from "react";

import { EApprovalPageHeader } from "@/components/e-approval/e-approval-page-header";
import { EApprovalSectionCard } from "@/components/e-approval/e-approval-section-card";
import { EApprovalUserGuidesSettingsCard } from "@/components/help/e-approval-user-guides-settings-card";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import {
  fetchEApprovalSettings,
  sendEApprovalSettingsTestWebhook,
  updateEApprovalSettings,
} from "@/lib/api/modules/e-approval-api";
import { getErrorMessage } from "@/lib/api/error";
import { permissions } from "@/lib/rbac/permissions";
import type { EApprovalFinanceProcurementPolicy } from "@/modules/e-approval/finance-procurement-policy";

type SettingsForm = {
  sla_reminder_minutes: number;
  sla_escalation_minutes: number;
  sla_use_working_days: boolean;
  liquidation_requires_parent: boolean;
  liquidation_overspend_mode: "block" | "warn";
  liquidation_max_overspend_percent: number;
  po_overspend_mode: "block" | "warn";
  po_max_overspend_percent: number;
  notify_external_on_received: boolean;
  notify_external_on_approved: boolean;
  notify_external_on_rejected: boolean;
  notify_external_on_returned: boolean;
  teams_webhook_url: string;
  notify_teams_on_external_submit: boolean;
};

const defaultForm: SettingsForm = {
  sla_reminder_minutes: 2880,
  sla_escalation_minutes: 4320,
  sla_use_working_days: true,
  liquidation_requires_parent: true,
  liquidation_overspend_mode: "block",
  liquidation_max_overspend_percent: 0,
  po_overspend_mode: "block",
  po_max_overspend_percent: 0,
  notify_external_on_received: false,
  notify_external_on_approved: false,
  notify_external_on_rejected: false,
  notify_external_on_returned: false,
  teams_webhook_url: "",
  notify_teams_on_external_submit: false,
};

function parseSettings(data: Record<string, string | number | EApprovalFinanceProcurementPolicy | undefined>): SettingsForm {
  const nested = data.finance_procurement_policy;
  const source = typeof nested === "object" && nested !== null ? nested : data;

  return {
    sla_reminder_minutes: toInt(data.sla_reminder_minutes, 2880),
    sla_escalation_minutes: toInt(data.sla_escalation_minutes, 4320),
    sla_use_working_days: toBool(data.sla_use_working_days, true),
    liquidation_requires_parent: toBool(source.liquidation_requires_parent, true),
    liquidation_overspend_mode: source.liquidation_overspend_mode === "warn" ? "warn" : "block",
    liquidation_max_overspend_percent: toInt(source.liquidation_max_overspend_percent, 0),
    po_overspend_mode: source.po_overspend_mode === "warn" ? "warn" : "block",
    po_max_overspend_percent: toInt(source.po_max_overspend_percent, 0),
    notify_external_on_received: toBool(data.notify_external_on_received, false),
    notify_external_on_approved: toBool(data.notify_external_on_approved, false),
    notify_external_on_rejected: toBool(data.notify_external_on_rejected, false),
    notify_external_on_returned: toBool(data.notify_external_on_returned, false),
    teams_webhook_url: String(data.teams_webhook_url ?? ""),
    notify_teams_on_external_submit: toBool(data.notify_teams_on_external_submit, false),
  };
}

function toBool(value: unknown, fallback: boolean): boolean {
  if (typeof value === "boolean") return value;
  if (typeof value === "string") return value === "true";
  return fallback;
}

function toInt(value: unknown, fallback: number): number {
  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed : fallback;
}

export function EApprovalSettingsPageClient() {
  const queryClient = useQueryClient();
  const [form, setForm] = useState<SettingsForm>(defaultForm);
  const [error, setError] = useState<string | null>(null);
  const [saved, setSaved] = useState(false);
  const [webhookMessage, setWebhookMessage] = useState<string | null>(null);

  const { data, isLoading, isError } = useQuery({
    queryKey: ["e-approval", "settings"],
    queryFn: fetchEApprovalSettings,
  });

  useEffect(() => {
    if (data) {
      setForm(parseSettings(data));
    }
  }, [data]);

  const saveMutation = useMutation({
    mutationFn: () =>
      updateEApprovalSettings({
        sla_reminder_minutes: form.sla_reminder_minutes,
        sla_escalation_minutes: form.sla_escalation_minutes,
        sla_use_working_days: form.sla_use_working_days ? "true" : "false",
        liquidation_requires_parent: form.liquidation_requires_parent ? "true" : "false",
        liquidation_overspend_mode: form.liquidation_overspend_mode,
        liquidation_max_overspend_percent: form.liquidation_max_overspend_percent,
        po_overspend_mode: form.po_overspend_mode,
        po_max_overspend_percent: form.po_max_overspend_percent,
        notify_external_on_received: form.notify_external_on_received ? "true" : "false",
        notify_external_on_approved: form.notify_external_on_approved ? "true" : "false",
        notify_external_on_rejected: form.notify_external_on_rejected ? "true" : "false",
        notify_external_on_returned: form.notify_external_on_returned ? "true" : "false",
        teams_webhook_url: form.teams_webhook_url.trim(),
        notify_teams_on_external_submit: form.notify_teams_on_external_submit ? "true" : "false",
      }),
    onSuccess: async () => {
      setError(null);
      setSaved(true);
      await queryClient.invalidateQueries({ queryKey: ["e-approval", "settings"] });
      await queryClient.invalidateQueries({ queryKey: ["e-approval", "metadata"] });
    },
    onError: (mutationError) => setError(getErrorMessage(mutationError)),
  });

  const testWebhookMutation = useMutation({
    mutationFn: sendEApprovalSettingsTestWebhook,
    onSuccess: (result) => {
      setWebhookMessage(result.message);
      setError(null);
    },
    onError: (mutationError) => {
      setWebhookMessage(null);
      setError(getErrorMessage(mutationError));
    },
  });

  return (
    <PermissionGate requiredPermissions={[permissions.eApprovalSettingsManage]}>
      <div className="space-y-6">
        <EApprovalPageHeader
          title="E-Approval settings"
          description="SLA timers, tenant finance controls, external submitter notifications, and Teams webhooks."
          actions={
            <Button
              size="sm"
              type="button"
              onClick={() => {
                setSaved(false);
                saveMutation.mutate();
              }}
              disabled={saveMutation.isPending || isLoading}
            >
              Save changes
            </Button>
          }
        />

        {isError ? <p className="text-sm text-destructive">Could not load settings.</p> : null}
        {error ? <p className="text-sm text-destructive">{error}</p> : null}
        {saved ? <p className="text-sm text-green-600 dark:text-green-400">Settings saved.</p> : null}
        {webhookMessage ? <p className="text-sm text-green-600 dark:text-green-400">{webhookMessage}</p> : null}
        {isLoading && !data ? <p className="text-sm text-muted-foreground">Loading settings…</p> : null}

        <EApprovalSectionCard
          title="Approval SLA"
          description="Reminder and escalation thresholds for pending approvals. Working days skip weekends and tenant public holidays (same calendar as Project-One rollout)."
        >
          <div className="grid gap-4 sm:grid-cols-2">
            <div className="space-y-1.5">
              <Label htmlFor="sla-reminder">Reminder after (minutes)</Label>
              <Input
                id="sla-reminder"
                type="number"
                min={1}
                value={form.sla_reminder_minutes}
                onChange={(event) =>
                  setForm((current) => ({
                    ...current,
                    sla_reminder_minutes: toInt(event.target.value, current.sla_reminder_minutes),
                  }))
                }
              />
              <p className="text-xs text-muted-foreground">Default 2880 = 48 hours.</p>
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="sla-escalation">Escalation after (minutes)</Label>
              <Input
                id="sla-escalation"
                type="number"
                min={1}
                value={form.sla_escalation_minutes}
                onChange={(event) =>
                  setForm((current) => ({
                    ...current,
                    sla_escalation_minutes: toInt(event.target.value, current.sla_escalation_minutes),
                  }))
                }
              />
              <p className="text-xs text-muted-foreground">Default 4320 = 72 hours.</p>
            </div>
          </div>
          <label className="mt-4 flex items-center gap-3 text-sm text-foreground">
            <Switch
              checked={form.sla_use_working_days}
              onCheckedChange={(checked) =>
                setForm((current) => ({ ...current, sla_use_working_days: checked }))
              }
            />
            <span>
              Count working days only
              <span className="mt-0.5 block text-xs text-muted-foreground">
                Recommended for production. When off, SLA uses continuous wall-clock time.
              </span>
            </span>
          </label>
        </EApprovalSectionCard>

        <EApprovalSectionCard
          title="External submitter email"
          description="Opt-in status emails to the external party who submitted a public form. Default is off — existing flows are unchanged until enabled."
        >
          <div className="space-y-3">
            {(
              [
                ["notify_external_on_received", "Email when submission is received"],
                ["notify_external_on_approved", "Email when approved (includes deliverable links when form package is enabled)"],
                ["notify_external_on_rejected", "Email when rejected"],
                ["notify_external_on_returned", "Email when returned for revision (includes revise link)"],
              ] as const
            ).map(([key, label]) => (
              <label key={key} className="flex items-center gap-3 text-sm text-foreground">
                <Checkbox
                  checked={form[key]}
                  onCheckedChange={(checked) => setForm((current) => ({ ...current, [key]: checked === true }))}
                />
                {label}
              </label>
            ))}
          </div>
        </EApprovalSectionCard>

        <EApprovalSectionCard
          title="Teams / webhook"
          description="Optional Microsoft Teams (Power Automate Workflows) webhook for external public submissions."
        >
          <div className="space-y-4">
            <div className="space-y-2">
              <Label htmlFor="ea-teams-webhook">Webhook URL</Label>
              <Input
                id="ea-teams-webhook"
                value={form.teams_webhook_url}
                onChange={(event) => setForm((current) => ({ ...current, teams_webhook_url: event.target.value }))}
                placeholder="https://prod-XX.westus.logic.azure.com/workflows/..."
              />
            </div>
            <label className="flex items-center gap-3 text-sm text-foreground">
              <Checkbox
                checked={form.notify_teams_on_external_submit}
                onCheckedChange={(checked) =>
                  setForm((current) => ({ ...current, notify_teams_on_external_submit: checked === true }))
                }
              />
              Post when an external public form is submitted
            </label>
            <Button
              type="button"
              size="sm"
              variant="outline"
              disabled={testWebhookMutation.isPending || !form.teams_webhook_url.trim()}
              onClick={() => testWebhookMutation.mutate()}
            >
              {testWebhookMutation.isPending ? "Sending…" : "Send test webhook"}
            </Button>
          </div>
        </EApprovalSectionCard>

        <EApprovalSectionCard
          title="Cash advance & liquidation"
          description="Control whether liquidation must link to an approved cash advance and how over-balance totals are handled."
        >
          <div className="space-y-5">
            <div className="flex items-center justify-between gap-4 rounded-lg border border-border px-3 py-3">
              <div>
                <Label htmlFor="liquidation_requires_parent">Require linked cash advance</Label>
                <p className="text-xs text-muted-foreground">
                  When enabled, liquidation submissions must include a parent cash advance.
                </p>
              </div>
              <Switch
                id="liquidation_requires_parent"
                checked={form.liquidation_requires_parent}
                onCheckedChange={(checked) =>
                  setForm((current) => ({ ...current, liquidation_requires_parent: checked }))
                }
              />
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
              <div className="space-y-2">
                <Label>Over-liquidation mode</Label>
                <Select
                  value={form.liquidation_overspend_mode}
                  onChange={(event) =>
                    setForm((current) => ({
                      ...current,
                      liquidation_overspend_mode: event.target.value as "block" | "warn",
                    }))
                  }
                >
                  <option value="block">Block above open balance</option>
                  <option value="warn">Warn and allow buffer</option>
                </Select>
              </div>

              <div className="space-y-2">
                <Label htmlFor="liquidation_max_overspend_percent">Max overspend percent</Label>
                <Select
                  id="liquidation_max_overspend_percent"
                  value={String(form.liquidation_max_overspend_percent)}
                  disabled={form.liquidation_overspend_mode !== "warn"}
                  onChange={(event) =>
                    setForm((current) => ({
                      ...current,
                      liquidation_max_overspend_percent: Number(event.target.value),
                    }))
                  }
                >
                  {Array.from({ length: 26 }, (_, index) => (
                    <option key={index} value={String(index)}>
                      {index}%
                    </option>
                  ))}
                </Select>
              </div>
            </div>
          </div>
        </EApprovalSectionCard>

        <EApprovalSectionCard
          title="Purchase requisition & PO"
          description="Control how purchase orders may exceed the remaining PR budget."
        >
          <div className="grid gap-4 sm:grid-cols-2">
            <div className="space-y-2">
              <Label>PO overspend mode</Label>
              <Select
                value={form.po_overspend_mode}
                onChange={(event) =>
                  setForm((current) => ({
                    ...current,
                    po_overspend_mode: event.target.value as "block" | "warn",
                  }))
                }
              >
                <option value="block">Block above open balance</option>
                <option value="warn">Warn and allow buffer</option>
              </Select>
            </div>

            <div className="space-y-2">
              <Label htmlFor="po_max_overspend_percent">Max overspend percent</Label>
              <Select
                id="po_max_overspend_percent"
                value={String(form.po_max_overspend_percent)}
                disabled={form.po_overspend_mode !== "warn"}
                onChange={(event) =>
                  setForm((current) => ({
                    ...current,
                    po_max_overspend_percent: Number(event.target.value),
                  }))
                }
              >
                {Array.from({ length: 26 }, (_, index) => (
                  <option key={index} value={String(index)}>
                    {index}%
                  </option>
                ))}
              </Select>
            </div>
          </div>
        </EApprovalSectionCard>

        <EApprovalUserGuidesSettingsCard />
      </div>
    </PermissionGate>
  );
}
