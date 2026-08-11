"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Clock, Layers, Mail, UserRoundPlus, Webhook } from "lucide-react";

import { TicketingAssignmentRulesEditor } from "@/components/ticketing/ticketing-assignment-rules-editor";
import { TicketingCategoriesEditor } from "@/components/ticketing/ticketing-categories-editor";
import { TicketingPageHeader } from "@/components/ticketing/ticketing-page-header";
import { slugifyTicketingCategory } from "@/components/ticketing/ticketing-utils";
import { SettingsPageSkeleton } from "@/components/ui/page-skeletons";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  fetchTicketingAssignableUsers,
  fetchTicketingSettings,
  sendTicketingSettingsTestEmail,
  sendTicketingSettingsTestWebhook,
  updateTicketingSettings,
} from "@/lib/api/modules/ticketing-api";
import { getErrorMessage } from "@/lib/api/error";
import type { TicketingAssignmentRule, TicketingCategoryOption } from "@/modules/ticketing/types";
import { permissions } from "@/lib/rbac/permissions";
import { useNotificationStore } from "@/stores/notification-store";

function optionsFromSettings(data: {
  category_options?: TicketingCategoryOption[];
  categories?: string[];
}): TicketingCategoryOption[] {
  if (data.category_options && data.category_options.length > 0) {
    return data.category_options.map((item) => ({
      id: item.id,
      label: item.label || item.id,
      sla_response_minutes: item.sla_response_minutes ?? null,
      sla_escalation_minutes: item.sla_escalation_minutes ?? null,
    }));
  }
  return (data.categories ?? []).map((id) => ({
    id,
    label: id.replace(/_/g, " "),
    sla_response_minutes: null,
    sla_escalation_minutes: null,
  }));
}

function normalizeOptions(rows: TicketingCategoryOption[]): TicketingCategoryOption[] {
  const seen = new Set<string>();
  const next: TicketingCategoryOption[] = [];
  for (const row of rows) {
    const id = slugifyTicketingCategory(row.id || row.label);
    const label = row.label.trim() || id.replace(/_/g, " ");
    if (!id || seen.has(id)) continue;
    seen.add(id);
    next.push({
      id,
      label,
      sla_response_minutes: row.sla_response_minutes ?? null,
      sla_escalation_minutes: row.sla_escalation_minutes ?? null,
    });
  }
  return next;
}

export function TicketingSettingsPageClient() {
  const queryClient = useQueryClient();
  const push = useNotificationStore((s) => s.push);
  const [itSupportEmail, setItSupportEmail] = useState("");
  const [notifyItOnCreate, setNotifyItOnCreate] = useState(true);
  const [notifyItOnReopen, setNotifyItOnReopen] = useState(true);
  const [notifyRequestorOnResolve, setNotifyRequestorOnResolve] = useState(true);
  const [notifyAssigneeOnAssign, setNotifyAssigneeOnAssign] = useState(true);
  const [slaEnabled, setSlaEnabled] = useState(true);
  const [slaResponseMinutes, setSlaResponseMinutes] = useState("480");
  const [slaEscalationMinutes, setSlaEscalationMinutes] = useState("1440");
  const [categoryRows, setCategoryRows] = useState<TicketingCategoryOption[]>([]);
  const [assignmentRules, setAssignmentRules] = useState<TicketingAssignmentRule[]>([]);
  const [teamsWebhookUrl, setTeamsWebhookUrl] = useState("");
  const [notifyTeamsOnCreate, setNotifyTeamsOnCreate] = useState(false);
  const [notifyTeamsOnSlaReminder, setNotifyTeamsOnSlaReminder] = useState(true);
  const [notifyTeamsOnSlaEscalation, setNotifyTeamsOnSlaEscalation] = useState(true);

  const settingsQuery = useQuery({
    queryKey: ["ticketing", "settings"],
    queryFn: fetchTicketingSettings,
  });

  const assignableUsersQuery = useQuery({
    queryKey: ["ticketing", "assignable-users"],
    queryFn: fetchTicketingAssignableUsers,
    staleTime: 300_000,
  });

  useEffect(() => {
    if (settingsQuery.data) {
      setItSupportEmail(settingsQuery.data.it_support_email ?? "");
      setNotifyItOnCreate(settingsQuery.data.notify_it_on_create ?? true);
      setNotifyItOnReopen(settingsQuery.data.notify_it_on_reopen ?? true);
      setNotifyRequestorOnResolve(settingsQuery.data.notify_requestor_on_resolve ?? true);
      setNotifyAssigneeOnAssign(settingsQuery.data.notify_assignee_on_assign ?? true);
      setSlaEnabled(settingsQuery.data.sla_enabled ?? true);
      setSlaResponseMinutes(String(settingsQuery.data.sla_response_minutes ?? 480));
      setSlaEscalationMinutes(String(settingsQuery.data.sla_escalation_minutes ?? 1440));
      setCategoryRows(optionsFromSettings(settingsQuery.data));
      setAssignmentRules(settingsQuery.data.assignment_rules ?? []);
      setTeamsWebhookUrl(settingsQuery.data.teams_webhook_url ?? "");
      setNotifyTeamsOnCreate(settingsQuery.data.notify_teams_on_create ?? false);
      setNotifyTeamsOnSlaReminder(settingsQuery.data.notify_teams_on_sla_reminder ?? true);
      setNotifyTeamsOnSlaEscalation(settingsQuery.data.notify_teams_on_sla_escalation ?? true);
    }
  }, [settingsQuery.data]);

  const saveMutation = useMutation({
    mutationFn: () => {
      const categories = normalizeOptions(categoryRows);
      if (categories.length === 0) {
        throw new Error("Add at least one category before saving.");
      }
      return updateTicketingSettings({
        it_support_email: itSupportEmail.trim(),
        notify_it_on_create: notifyItOnCreate,
        notify_it_on_reopen: notifyItOnReopen,
        notify_requestor_on_resolve: notifyRequestorOnResolve,
        notify_assignee_on_assign: notifyAssigneeOnAssign,
        sla_enabled: slaEnabled,
        sla_response_minutes: Number(slaResponseMinutes),
        sla_escalation_minutes: Number(slaEscalationMinutes),
        categories,
        assignment_rules: assignmentRules,
        teams_webhook_url: teamsWebhookUrl.trim(),
        notify_teams_on_create: notifyTeamsOnCreate,
        notify_teams_on_sla_reminder: notifyTeamsOnSlaReminder,
        notify_teams_on_sla_escalation: notifyTeamsOnSlaEscalation,
      });
    },
    onSuccess: (data) => {
      setCategoryRows(optionsFromSettings(data));
      setAssignmentRules(data.assignment_rules ?? []);
      queryClient.invalidateQueries({ queryKey: ["ticketing", "settings"] });
      queryClient.invalidateQueries({ queryKey: ["ticketing", "metadata"] });
      push({ level: "success", title: "Settings saved" });
    },
    onError: (error) => push({ level: "error", title: "Save failed", message: getErrorMessage(error) }),
  });

  const testEmailMutation = useMutation({
    mutationFn: sendTicketingSettingsTestEmail,
    onSuccess: (result) => {
      push({ level: "success", title: "Test email sent", message: result.message });
    },
    onError: (error) => push({ level: "error", title: "Test email failed", message: getErrorMessage(error) }),
  });

  const testWebhookMutation = useMutation({
    mutationFn: sendTicketingSettingsTestWebhook,
    onSuccess: (result) => {
      push({ level: "success", title: "Test webhook sent", message: result.message });
    },
    onError: (error) => push({ level: "error", title: "Test webhook failed", message: getErrorMessage(error) }),
  });

  const applyPackMutation = useMutation({
    mutationFn: (packId: string) => updateTicketingSettings({ apply_category_pack: packId }),
    onSuccess: (data) => {
      setCategoryRows(optionsFromSettings(data));
      queryClient.invalidateQueries({ queryKey: ["ticketing", "settings"] });
      queryClient.invalidateQueries({ queryKey: ["ticketing", "metadata"] });
      push({ level: "success", title: "Category pack applied" });
    },
    onError: (error) => push({ level: "error", title: "Could not apply pack", message: getErrorMessage(error) }),
  });

  const mailerReady = settingsQuery.data?.notifications_mailer_ready ?? false;
  const categoryPacks = settingsQuery.data?.category_packs ?? [];

  return (
    <PermissionGate requiredPermissions={[permissions.ticketingSettingsManage]}>
      <div className="space-y-6">
        <TicketingPageHeader
          eyebrow={
            <Link href="/ticketing" className="hover:text-primary">
              Ticketing
            </Link>
          }
          title="Ticketing settings"
          description="Configure categories, per-category SLA, auto-assign rules, IT notifications, and Teams webhooks."
          actions={
            <Button size="sm" onClick={() => saveMutation.mutate()} disabled={saveMutation.isPending}>
              {saveMutation.isPending ? "Saving…" : "Save settings"}
            </Button>
          }
        />

        {settingsQuery.isLoading ? <SettingsPageSkeleton /> : null}

        {settingsQuery.isError ? (
          <p className="text-sm text-destructive">Could not load module settings.</p>
        ) : null}

        {!settingsQuery.isLoading && !settingsQuery.isError ? (
          <div className="space-y-6">
            <section className="rounded-xl border border-border bg-card p-5 shadow-sm">
              <div className="flex items-start gap-3">
                <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                  <Layers className="h-4 w-4" />
                </div>
                <div className="min-w-0 flex-1">
                  <TicketingCategoriesEditor
                    rows={categoryRows}
                    packs={categoryPacks}
                    applyingPack={applyPackMutation.isPending}
                    onChange={setCategoryRows}
                    onApplyPack={(packId) => applyPackMutation.mutate(packId)}
                  />
                </div>
              </div>
            </section>

            <section className="rounded-xl border border-border bg-card p-5 shadow-sm">
              <div className="flex items-start gap-3">
                <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                  <UserRoundPlus className="h-4 w-4" />
                </div>
                <div className="min-w-0 flex-1">
                  <TicketingAssignmentRulesEditor
                    rules={assignmentRules}
                    categories={categoryRows}
                    users={assignableUsersQuery.data ?? []}
                    onChange={setAssignmentRules}
                  />
                </div>
              </div>
            </section>

            <section className="rounded-xl border border-border bg-card p-5 shadow-sm">
              <div className="flex items-start gap-3">
                <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-amber-500/10 text-amber-700 dark:text-amber-400">
                  <Clock className="h-4 w-4" />
                </div>
                <div className="min-w-0 flex-1 space-y-4">
                  <div>
                    <h2 className="text-sm font-medium text-foreground">SLA & escalation</h2>
                    <p className="mt-1 text-xs text-muted-foreground">
                      Tenant defaults below apply when a category has no override. Windows are still scaled by
                      priority (urgent fastest, low slowest). Scheduler runs `ticketing:sla-run` every 5 minutes.
                    </p>
                  </div>
                  <label className="flex items-center gap-3 text-sm text-foreground">
                    <Checkbox
                      checked={slaEnabled}
                      onCheckedChange={(v) => setSlaEnabled(v === true)}
                    />
                    Enable SLA tracking and automated reminders
                  </label>
                  <div className="grid gap-4 sm:grid-cols-2">
                    <div className="space-y-2">
                      <Label htmlFor="sla-response">Response reminder (minutes)</Label>
                      <Input
                        id="sla-response"
                        type="number"
                        min={1}
                        value={slaResponseMinutes}
                        onChange={(e) => setSlaResponseMinutes(e.target.value)}
                      />
                    </div>
                    <div className="space-y-2">
                      <Label htmlFor="sla-escalation">Escalation (minutes)</Label>
                      <Input
                        id="sla-escalation"
                        type="number"
                        min={1}
                        value={slaEscalationMinutes}
                        onChange={(e) => setSlaEscalationMinutes(e.target.value)}
                      />
                    </div>
                  </div>
                </div>
              </div>
            </section>

            <section className="rounded-xl border border-border bg-card p-5 shadow-sm">
              <div className="flex items-start gap-3">
                <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                  <Mail className="h-4 w-4" />
                </div>
                <div className="min-w-0 flex-1 space-y-4">
                  <div>
                    <h2 className="text-sm font-medium text-foreground">IT group mailbox</h2>
                    <p className="mt-1 text-xs text-muted-foreground">
                      Comma-separated addresses notified when tickets are created or reopened.
                    </p>
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="it-support-email">IT support email</Label>
                    <Input
                      id="it-support-email"
                      value={itSupportEmail}
                      onChange={(e) => setItSupportEmail(e.target.value)}
                      placeholder="it-support@company.com, noc@company.com"
                    />
                  </div>
                  <p className="text-xs text-muted-foreground">
                    Mailer: {settingsQuery.data?.notifications_mailer ?? "unknown"}
                    {!mailerReady ? " (log only — configure SMTP/SES in API environment)" : ""}
                  </p>
                  <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    disabled={testEmailMutation.isPending || !mailerReady}
                    onClick={() => testEmailMutation.mutate()}
                  >
                    {testEmailMutation.isPending ? "Sending…" : "Send test email to me"}
                  </Button>
                </div>
              </div>
            </section>

            <section className="rounded-xl border border-border bg-card p-5 shadow-sm">
              <div className="space-y-4">
                <div>
                  <h2 className="text-sm font-medium text-foreground">Email notification toggles</h2>
                  <p className="mt-1 text-xs text-muted-foreground">
                    Control which events send email from Ticketing.
                  </p>
                </div>
                <div className="space-y-3">
                  <label className="flex items-center gap-3 text-sm text-foreground">
                    <Checkbox checked={notifyItOnCreate} onCheckedChange={(v) => setNotifyItOnCreate(v === true)} />
                    Email IT group when a ticket is created
                  </label>
                  <label className="flex items-center gap-3 text-sm text-foreground">
                    <Checkbox checked={notifyItOnReopen} onCheckedChange={(v) => setNotifyItOnReopen(v === true)} />
                    Email IT group when a requester reopens a ticket
                  </label>
                  <label className="flex items-center gap-3 text-sm text-foreground">
                    <Checkbox checked={notifyRequestorOnResolve} onCheckedChange={(v) => setNotifyRequestorOnResolve(v === true)} />
                    Email requester when a ticket is resolved
                  </label>
                  <label className="flex items-center gap-3 text-sm text-foreground">
                    <Checkbox checked={notifyAssigneeOnAssign} onCheckedChange={(v) => setNotifyAssigneeOnAssign(v === true)} />
                    Email assignee when a ticket is assigned to them
                  </label>
                </div>
              </div>
            </section>

            <section className="rounded-xl border border-border bg-card p-5 shadow-sm">
              <div className="flex items-start gap-3">
                <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                  <Webhook className="h-4 w-4" />
                </div>
                <div className="min-w-0 flex-1 space-y-4">
                  <div>
                    <h2 className="text-sm font-medium text-foreground">Teams / webhook</h2>
                    <p className="mt-1 text-xs text-muted-foreground">
                      Optional Microsoft Teams incoming webhook URL for operational alerts.
                    </p>
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="teams-webhook">Webhook URL</Label>
                    <Input
                      id="teams-webhook"
                      value={teamsWebhookUrl}
                      onChange={(e) => setTeamsWebhookUrl(e.target.value)}
                      placeholder="https://prod-XX.westus.logic.azure.com/workflows/... (Power Automate Workflows URL)"
                    />
                  </div>
                  <div className="space-y-3">
                    <label className="flex items-center gap-3 text-sm text-foreground">
                      <Checkbox checked={notifyTeamsOnCreate} onCheckedChange={(v) => setNotifyTeamsOnCreate(v === true)} />
                      Post when a ticket is created
                    </label>
                    <label className="flex items-center gap-3 text-sm text-foreground">
                      <Checkbox checked={notifyTeamsOnSlaReminder} onCheckedChange={(v) => setNotifyTeamsOnSlaReminder(v === true)} />
                      Post on SLA reminder
                    </label>
                    <label className="flex items-center gap-3 text-sm text-foreground">
                      <Checkbox checked={notifyTeamsOnSlaEscalation} onCheckedChange={(v) => setNotifyTeamsOnSlaEscalation(v === true)} />
                      Post on SLA escalation
                    </label>
                  </div>
                  <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    disabled={testWebhookMutation.isPending || !teamsWebhookUrl.trim()}
                    onClick={() => testWebhookMutation.mutate()}
                  >
                    {testWebhookMutation.isPending ? "Sending…" : "Send test webhook"}
                  </Button>
                </div>
              </div>
            </section>
          </div>
        ) : null}
      </div>
    </PermissionGate>
  );
}
