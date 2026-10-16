"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useEffect, useState } from "react";

import { EApprovalPageHeader } from "@/components/e-approval/e-approval-page-header";
import { EApprovalSectionCard } from "@/components/e-approval/e-approval-section-card";
import { EApprovalTenantSubsidiaryLogosPanel } from "@/components/e-approval/e-approval-tenant-subsidiary-logos-panel";
import { EApprovalUserGuidesSettingsCard } from "@/components/help/e-approval-user-guides-settings-card";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { normalizeSiteSearchScope, type EApprovalSiteSearchScope } from "@/lib/api/modules/e-approval-api";
import {
  fetchEApprovalSettings,
  sendEApprovalSettingsTestWebhook,
  updateEApprovalSettings,
} from "@/lib/api/modules/e-approval-api";
import { getErrorMessage } from "@/lib/api/error";
import { notify } from "@/lib/ui/notify";
import { permissions } from "@/lib/rbac/permissions";
type SettingsForm = {
  sla_reminder_minutes: number;
  sla_escalation_minutes: number;
  sla_use_working_days: boolean;
  liquidation_requires_parent: boolean;
  liquidation_overspend_mode: "block" | "warn";
  liquidation_max_overspend_percent: number;
  liquidation_due_days: number;
  liquidation_remind_days: number;
  block_new_cash_advance: boolean;
  vat_rate_percent: number;
  vat_amount_includes_vat: boolean;
  per_diem_max_per_day: string;
  hotel_accommodation_max: string;
  site_search_scope: EApprovalSiteSearchScope;
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
  liquidation_due_days: 0,
  liquidation_remind_days: 0,
  block_new_cash_advance: false,
  vat_rate_percent: 12,
  vat_amount_includes_vat: true,
  per_diem_max_per_day: "",
  hotel_accommodation_max: "",
  site_search_scope: "request",
  notify_external_on_received: false,
  notify_external_on_approved: false,
  notify_external_on_rejected: false,
  notify_external_on_returned: false,
  teams_webhook_url: "",
  notify_teams_on_external_submit: false,
};

function parseSettings(data: Record<string, string | number | undefined>): SettingsForm {
  return {
    sla_reminder_minutes: toInt(data.sla_reminder_minutes, 2880),
    sla_escalation_minutes: toInt(data.sla_escalation_minutes, 4320),
    sla_use_working_days: toBool(data.sla_use_working_days, true),
    liquidation_requires_parent: toBool(data.liquidation_requires_parent, true),
    liquidation_overspend_mode: data.liquidation_overspend_mode === "warn" ? "warn" : "block",
    liquidation_max_overspend_percent: toInt(data.liquidation_max_overspend_percent, 0),
    liquidation_due_days: toInt(data.liquidation_due_days, 0),
    liquidation_remind_days: toInt(data.liquidation_remind_days, 0),
    block_new_cash_advance: toBool(data.block_new_cash_advance, false),
    vat_rate_percent: toInt(data.vat_rate_percent, 12),
    vat_amount_includes_vat: toBool(data.vat_amount_includes_vat, true),
    per_diem_max_per_day: data.per_diem_max_per_day == null ? "" : String(data.per_diem_max_per_day),
    hotel_accommodation_max: data.hotel_accommodation_max == null ? "" : String(data.hotel_accommodation_max),
    site_search_scope: normalizeSiteSearchScope(data.site_search_scope),
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
        liquidation_due_days: form.liquidation_due_days,
        liquidation_remind_days: form.liquidation_remind_days,
        block_new_cash_advance: form.block_new_cash_advance ? "true" : "false",
        vat_rate_percent: form.vat_rate_percent,
        vat_amount_includes_vat: form.vat_amount_includes_vat ? "true" : "false",
        per_diem_max_per_day: form.per_diem_max_per_day.trim(),
        hotel_accommodation_max: form.hotel_accommodation_max.trim(),
        site_search_scope: form.site_search_scope,
        notify_external_on_received: form.notify_external_on_received ? "true" : "false",
        notify_external_on_approved: form.notify_external_on_approved ? "true" : "false",
        notify_external_on_rejected: form.notify_external_on_rejected ? "true" : "false",
        notify_external_on_returned: form.notify_external_on_returned ? "true" : "false",
        teams_webhook_url: form.teams_webhook_url.trim(),
        notify_teams_on_external_submit: form.notify_teams_on_external_submit ? "true" : "false",
      }),
    onSuccess: async () => {
      setError(null);
      notify.success("Settings saved");
      await queryClient.invalidateQueries({ queryKey: ["e-approval", "settings"] });
      await queryClient.invalidateQueries({ queryKey: ["e-approval", "settings", "public"] });
      await queryClient.invalidateQueries({ queryKey: ["e-approval", "metadata"] });
    },
    onError: (mutationError) => setError(getErrorMessage(mutationError)),
  });

  const testWebhookMutation = useMutation({
    mutationFn: sendEApprovalSettingsTestWebhook,
    onSuccess: (result) => {
      setError(null);
      notify.success("Test webhook sent", result.message);
    },
    onError: (mutationError) => {
      setError(getErrorMessage(mutationError));
    },
  });

  return (
    <PermissionGate requiredPermissions={[permissions.eApprovalSettingsManage]}>
      <div className="space-y-6">
        <EApprovalPageHeader
          title="E-Forms settings"
          description="SLA timers, cash advance and liquidation controls, external submitter notifications, and Teams webhooks."
          actions={
            <Button
              size="sm"
              type="button"
              onClick={() => saveMutation.mutate()}
              disabled={saveMutation.isPending || isLoading}
            >
              Save changes
            </Button>
          }
        />

        {isError ? <p className="text-sm text-destructive">Could not load settings.</p> : null}
        {error ? <p className="text-sm text-destructive">{error}</p> : null}
        {isLoading && !data ? <p className="text-sm text-muted-foreground">Loading settings…</p> : null}

        <EApprovalSectionCard
          title="Subsidiary logos"
          description="Tenant letterhead logos for ATC, ADIC, and other subsidiaries. Managed once here — all forms inherit them on print."
        >
          <EApprovalTenantSubsidiaryLogosPanel />
        </EApprovalSectionCard>

        <EApprovalSectionCard
          title="Approval SLA"
          description="Reminder and escalation thresholds for pending approvals. Working days skip weekends and tenant public holidays."
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
          title="Cash advance & liquidation"
          description="Deadline, VAT, and per diem. Approvers are set on each form's workflow."
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
                <Label htmlFor="liquidation_due_days">Days to liquidate</Label>
                <Input
                  id="liquidation_due_days"
                  type="number"
                  min={0}
                  value={form.liquidation_due_days}
                  onChange={(event) =>
                    setForm((current) => ({
                      ...current,
                      liquidation_due_days: toInt(event.target.value, 0),
                    }))
                  }
                />
                <p className="text-xs text-muted-foreground">0 turns the deadline off. Counted from the release date.</p>
              </div>
              <div className="space-y-2">
                <Label htmlFor="liquidation_remind_days">Remind this many days before</Label>
                <Input
                  id="liquidation_remind_days"
                  type="number"
                  min={0}
                  value={form.liquidation_remind_days}
                  onChange={(event) =>
                    setForm((current) => ({
                      ...current,
                      liquidation_remind_days: toInt(event.target.value, 0),
                    }))
                  }
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="vat_rate_percent">VAT rate (%)</Label>
                <Input
                  id="vat_rate_percent"
                  type="number"
                  min={0}
                  max={100}
                  value={form.vat_rate_percent}
                  onChange={(event) =>
                    setForm((current) => ({
                      ...current,
                      vat_rate_percent: toInt(event.target.value, 12),
                    }))
                  }
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="per_diem_max_per_day">Per diem max per day</Label>
                <Input
                  id="per_diem_max_per_day"
                  inputMode="decimal"
                  value={form.per_diem_max_per_day}
                  placeholder="No cap"
                  onChange={(event) =>
                    setForm((current) => ({ ...current, per_diem_max_per_day: event.target.value }))
                  }
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="hotel_accommodation_max">Lodging max</Label>
                <Input
                  id="hotel_accommodation_max"
                  inputMode="decimal"
                  value={form.hotel_accommodation_max}
                  placeholder="No cap"
                  onChange={(event) =>
                    setForm((current) => ({ ...current, hotel_accommodation_max: event.target.value }))
                  }
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="site_search_scope">Site ID search</Label>
                <Select
                  id="site_search_scope"
                  value={form.site_search_scope}
                  onChange={(event) =>
                    setForm((current) => ({
                      ...current,
                      site_search_scope: normalizeSiteSearchScope(event.target.value),
                    }))
                  }
                >
                  <option value="request">Match the request</option>
                  <option value="all">All subsidiaries</option>
                  <option value="atc">ATC only</option>
                  <option value="adic">ADIC only</option>
                </Select>
                <p className="text-xs text-muted-foreground">
                  From Site and To Site use this list. Match the request follows the subsidiary on the form.
                </p>
              </div>
            </div>
            <div className="flex items-center justify-between gap-4 rounded-lg border border-border px-3 py-3">
              <div>
                <Label htmlFor="vat_amount_includes_vat">Amount includes VAT</Label>
                <p className="text-xs text-muted-foreground">
                  When on, VAT is taken out of the amount. When off, VAT is added on top.
                </p>
              </div>
              <Switch
                id="vat_amount_includes_vat"
                checked={form.vat_amount_includes_vat}
                onCheckedChange={(checked) =>
                  setForm((current) => ({ ...current, vat_amount_includes_vat: checked }))
                }
              />
            </div>
            <div className="flex items-center justify-between gap-4 rounded-lg border border-border px-3 py-3">
              <div>
                <Label htmlFor="block_new_cash_advance">Block a new cash advance</Label>
                <p className="text-xs text-muted-foreground">
                  While one is unliquidated or overdue, that person cannot file another.
                </p>
              </div>
              <Switch
                id="block_new_cash_advance"
                checked={form.block_new_cash_advance}
                onCheckedChange={(checked) =>
                  setForm((current) => ({ ...current, block_new_cash_advance: checked }))
                }
              />
            </div>
          </div>
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

        <EApprovalUserGuidesSettingsCard />
      </div>
    </PermissionGate>
  );
}
