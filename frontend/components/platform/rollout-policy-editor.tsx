"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ArrowDown, ArrowUp, Eye, EyeOff, Trash2 } from "lucide-react";
import Link from "next/link";
import { useEffect, useMemo, useState } from "react";

import { FormInput } from "@/components/forms/form-input";
import { EmailNotificationPolicyEditor } from "@/components/rollout/email-notification-policy-editor";
import { GateApprovalChainEditor } from "@/components/rollout/gate-approval-chain-editor";
import {
  normalizeEmailNotificationPolicies,
  type EmailNotificationPolicies,
} from "@/lib/rollout/email-notification-policies";
import { Button, buttonVariants } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { sanitizeGateApprovalChain } from "@/lib/rollout/gate-approval-chain-roles";
import { getErrorMessage } from "@/lib/api/error";
import {
  platformListRolloutCustomPhases,
  platformPublishRolloutPolicy,
  platformUpdateRolloutPolicy,
  type PlatformRolloutCustomPhase,
  type PlatformRolloutPolicyBundle,
} from "@/lib/api/modules/platform-api";
import { useNotificationStore } from "@/stores/notification-store";

type TimelinePhase = {
  phase_key: string;
  label: string;
  owner_role?: string | null;
  anchor: string;
  working_day_start: number;
  working_day_end: number;
  gate?: string | null;
  counts_toward_sla?: boolean;
  is_custom?: boolean;
  catalog_phase_id?: string | null;
  sort_order?: number;
};

const templateLabels: Record<string, string> = {
  bts: "BTS",
  rtb: "RTB",
  colocation: "Colocation",
};

type Props = {
  policy: PlatformRolloutPolicyBundle;
};

export function RolloutPolicyEditor({ policy }: Props) {
  const queryClient = useQueryClient();
  const notify = useNotificationStore((state) => state.push);
  const isDraft = policy.status === "draft";

  const [name, setName] = useState(policy.name);
  const [changelog, setChangelog] = useState(policy.changelog ?? "");
  const [timelineTemplates, setTimelineTemplates] = useState<Record<string, TimelinePhase[]>>(
    normalizeTemplates(policy.timeline_templates),
  );
  const [hiddenPhases, setHiddenPhases] = useState<Record<string, string[]>>(policy.hidden_phases ?? {});
  const [gatePolicies, setGatePolicies] = useState(policy.gate_approval_policies ?? {});
  const [emailPolicies, setEmailPolicies] = useState<EmailNotificationPolicies>(() =>
    normalizeEmailNotificationPolicies(policy.email_notification_policies),
  );
  const [deliveryPeriods, setDeliveryPeriods] = useState(policy.delivery_periods ?? {});

  useEffect(() => {
    setName(policy.name);
    setChangelog(policy.changelog ?? "");
    setTimelineTemplates(normalizeTemplates(policy.timeline_templates));
    setHiddenPhases(policy.hidden_phases ?? {});
    setGatePolicies(policy.gate_approval_policies ?? {});
    setEmailPolicies(normalizeEmailNotificationPolicies(policy.email_notification_policies));
    setDeliveryPeriods(policy.delivery_periods ?? {});
  }, [policy]);

  const templateKeys = useMemo(
    () => Object.keys(timelineTemplates).filter((key) => timelineTemplates[key]?.length),
    [timelineTemplates],
  );
  const [activeTemplate, setActiveTemplate] = useState(templateKeys[0] ?? "bts");
  const [selectedCatalogPhaseId, setSelectedCatalogPhaseId] = useState("");

  const catalogQuery = useQuery({
    queryKey: ["platform", "rollout-custom-phases", activeTemplate],
    queryFn: () => platformListRolloutCustomPhases(activeTemplate),
    retry: 1,
  });

  useEffect(() => {
    if (templateKeys.length > 0 && !templateKeys.includes(activeTemplate)) {
      setActiveTemplate(templateKeys[0]);
    }
  }, [activeTemplate, templateKeys]);

  const saveMutation = useMutation({
    mutationFn: () =>
      platformUpdateRolloutPolicy(policy.id, {
        name,
        changelog,
        timeline_templates: timelineTemplates,
        hidden_phases: hiddenPhases,
        gate_approval_policies: gatePolicies,
        email_notification_policies: emailPolicies,
        delivery_periods: deliveryPeriods,
      }),
    onSuccess: (updated) => {
      queryClient.setQueryData(["platform", "rollout-policy", policy.id], updated);
      void queryClient.invalidateQueries({ queryKey: ["platform", "rollout-policies"] });
      notify({ level: "success", title: "Policy saved", message: "Draft rollout policy updated." });
    },
    onError: (error) =>
      notify({ level: "error", title: "Could not save policy", message: getErrorMessage(error) }),
  });

  const publishMutation = useMutation({
    mutationFn: () => platformPublishRolloutPolicy(policy.id),
    onSuccess: (updated) => {
      queryClient.setQueryData(["platform", "rollout-policy", policy.id], updated);
      void queryClient.invalidateQueries({ queryKey: ["platform", "rollout-policies"] });
      notify({
        level: "success",
        title: "Policy published",
        message: `${updated.code} is ready for tenant assignment.`,
      });
    },
    onError: (error) =>
      notify({ level: "error", title: "Publish failed", message: getErrorMessage(error) }),
  });

  const phases = timelineTemplates[activeTemplate] ?? [];
  const hiddenSet = new Set(hiddenPhases[activeTemplate] ?? []);
  const slaSummary = policy.sla_summary?.[activeTemplate];
  const gateRows = Object.entries(gatePolicies[activeTemplate] ?? {});
  const catalogPhases = catalogQuery.data ?? [];
  const existingPhaseKeys = new Set(phases.map((phase) => phase.phase_key));
  const availableCatalogPhases = catalogPhases.filter((phase) => !existingPhaseKeys.has(phase.phase_key));

  const computedSlaTotal = useMemo(() => {
    return phases.reduce((total, phase) => {
      if (phase.anchor !== "tssr_approved") return total;
      if (phase.counts_toward_sla === false) return total;
      return total + Math.max(0, phase.working_day_end - phase.working_day_start + 1);
    }, 0);
  }, [phases]);

  const slaTarget = deliveryPeriods[activeTemplate]?.working_days ?? slaSummary?.sla_working_days ?? 0;
  const slaValid = slaTarget === 0 || computedSlaTotal === 0 || computedSlaTotal === slaTarget;

  const movePhase = (index: number, direction: -1 | 1) => {
    const target = index + direction;
    if (target < 0 || target >= phases.length) return;
    setTimelineTemplates((prev) => {
      const list = [...(prev[activeTemplate] ?? [])];
      [list[index], list[target]] = [list[target], list[index]];
      return { ...prev, [activeTemplate]: list };
    });
  };

  const updatePhase = (index: number, patch: Partial<TimelinePhase>) => {
    setTimelineTemplates((prev) => {
      const list = [...(prev[activeTemplate] ?? [])];
      list[index] = { ...list[index], ...patch };
      return { ...prev, [activeTemplate]: list };
    });
  };

  const toggleHidden = (phaseKey: string) => {
    setHiddenPhases((prev) => {
      const current = new Set(prev[activeTemplate] ?? []);
      if (current.has(phaseKey)) {
        current.delete(phaseKey);
      } else {
        current.add(phaseKey);
      }
      return { ...prev, [activeTemplate]: Array.from(current) };
    });
  };

  const addCatalogPhase = (catalogPhase: PlatformRolloutCustomPhase) => {
    const row: TimelinePhase = {
      phase_key: catalogPhase.phase_key,
      label: catalogPhase.label,
      owner_role: catalogPhase.owner_role ?? null,
      anchor: catalogPhase.default_anchor,
      working_day_start: catalogPhase.default_working_day_start,
      working_day_end: catalogPhase.default_working_day_end,
      gate: catalogPhase.default_gate ?? null,
      counts_toward_sla: catalogPhase.counts_toward_sla,
      is_custom: true,
      catalog_phase_id: catalogPhase.id,
    };

    setTimelineTemplates((prev) => ({
      ...prev,
      [activeTemplate]: [...(prev[activeTemplate] ?? []), row],
    }));

    if (catalogPhase.default_gate) {
      setGatePolicies((prev) => ({
        ...prev,
        [activeTemplate]: {
          ...prev[activeTemplate],
          [catalogPhase.phase_key]: {
            enabled: false,
            chain: catalogPhase.owner_role ? [catalogPhase.owner_role, "pmo"] : ["pmo"],
          },
        },
      }));
    }

    setSelectedCatalogPhaseId("");
  };

  const removeCustomPhase = (index: number) => {
    const phase = phases[index];
    if (!phase?.is_custom) return;

    setTimelineTemplates((prev) => ({
      ...prev,
      [activeTemplate]: (prev[activeTemplate] ?? []).filter((_, i) => i !== index),
    }));

    setGatePolicies((prev) => {
      const next = { ...(prev[activeTemplate] ?? {}) };
      delete next[phase.phase_key];
      return { ...prev, [activeTemplate]: next };
    });
  };

  return (
    <div className="flex flex-col gap-8">
      <header className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <div className="flex flex-wrap items-center gap-2">
            <h1 className="text-2xl font-semibold tracking-tight text-foreground">{policy.name}</h1>
            <span
              className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${
                policy.status === "published"
                  ? "bg-emerald-100 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-100"
                  : "bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-100"
              }`}
            >
              {policy.status}
            </span>
          </div>
          <p className="mt-2 text-sm text-muted-foreground">
            <span className="font-mono text-foreground">{policy.code}</span>
            {" · "}
            Playbook v{policy.playbook_version ?? "—"}
            {policy.published_at ? ` · Published ${new Date(policy.published_at).toLocaleString()}` : ""}
          </p>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <Link href="/platform/playbooks" className={buttonVariants({ variant: "outline" })}>
            Back to playbooks
          </Link>
          {isDraft ? (
            <>
              <Button type="button" variant="outline" disabled={saveMutation.isPending} onClick={() => saveMutation.mutate()}>
                {saveMutation.isPending ? "Saving…" : "Save draft"}
              </Button>
              <Button
                type="button"
                disabled={publishMutation.isPending || saveMutation.isPending}
                onClick={() => {
                  saveMutation.mutate(undefined, {
                    onSuccess: () => publishMutation.mutate(),
                  });
                }}
              >
                {publishMutation.isPending ? "Publishing…" : "Publish policy"}
              </Button>
            </>
          ) : null}
        </div>
      </header>

      <div className="grid gap-4 lg:grid-cols-3">
        <div className="rounded-xl border border-border bg-card p-4 shadow-sm lg:col-span-2">
          <FormInput
            label="Policy name"
            value={name}
            onChange={(e) => setName(e.target.value)}
            disabled={!isDraft}
          />
          <div className="mt-4">
            <label className="text-xs font-medium text-muted-foreground">Changelog</label>
            <textarea
              className="mt-1.5 min-h-[72px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
              value={changelog}
              onChange={(e) => setChangelog(e.target.value)}
              disabled={!isDraft}
            />
          </div>
        </div>
        <div className="rounded-xl border border-border bg-card p-4 shadow-sm">
          <p className="text-xs font-medium text-muted-foreground">SLA summary ({templateLabels[activeTemplate] ?? activeTemplate})</p>
          {slaSummary ? (
            <dl className="mt-3 space-y-2 text-sm">
              <div className="flex justify-between gap-2">
                <dt className="text-muted-foreground">Target SLA</dt>
                <dd className="font-mono">{slaSummary.sla_working_days} WD</dd>
              </div>
              <div className="flex justify-between gap-2">
                <dt className="text-muted-foreground">Post–Day-1 total</dt>
                <dd className={`font-mono ${slaValid ? "text-foreground" : "text-destructive"}`}>
                  {computedSlaTotal} WD
                </dd>
              </div>
              <div className="flex justify-between gap-2">
                <dt className="text-muted-foreground">Valid</dt>
                <dd className={slaValid ? "text-emerald-600" : "text-destructive"}>{slaValid ? "Yes" : "Mismatch"}</dd>
              </div>
            </dl>
          ) : (
            <p className="mt-3 text-sm text-muted-foreground">Select a template tab below.</p>
          )}
          {isDraft ? (
            <div className="mt-4">
              <FormInput
                label="SLA working days"
                type="number"
                min={0}
                value={String(deliveryPeriods[activeTemplate]?.working_days ?? 0)}
                onChange={(e) =>
                  setDeliveryPeriods((prev) => ({
                    ...prev,
                    [activeTemplate]: {
                      ...prev[activeTemplate],
                      working_days: Number.parseInt(e.target.value, 10) || 0,
                    },
                  }))
                }
              />
            </div>
          ) : null}
        </div>
      </div>

      <section className="space-y-4 rounded-xl border border-border bg-card p-4 shadow-sm">
        <div>
          <h2 className="text-base font-medium text-foreground">Timeline phases</h2>
          <p className="mt-1 text-sm text-muted-foreground">
            Reorder phases, adjust working-day windows, or hide phases from tenant timelines. Hidden phases remain in the
            bundle but are excluded on tenant sync.
          </p>
        </div>

        <div className="flex flex-wrap items-center justify-between gap-3">
          <div className="flex flex-wrap gap-1">
            {templateKeys.map((key) => (
              <button
                key={key}
                type="button"
                onClick={() => setActiveTemplate(key)}
                className={`rounded-md px-3 py-1.5 text-xs font-medium ${
                  activeTemplate === key
                    ? "bg-primary text-primary-foreground"
                    : "bg-muted text-muted-foreground hover:text-foreground"
                }`}
              >
                {templateLabels[key] ?? key.toUpperCase()}
              </button>
            ))}
          </div>
          {isDraft ? (
            <div className="flex flex-wrap items-center gap-2">
              <select
                className="h-8 rounded-md border border-input bg-background px-2 text-xs"
                value={selectedCatalogPhaseId}
                onChange={(e) => setSelectedCatalogPhaseId(e.target.value)}
              >
                <option value="">Add custom phase…</option>
                {availableCatalogPhases.map((phase) => (
                  <option key={phase.id} value={phase.id}>
                    {phase.label}
                  </option>
                ))}
              </select>
              <Button
                type="button"
                size="sm"
                variant="outline"
                disabled={!selectedCatalogPhaseId}
                onClick={() => {
                  const phase = catalogPhases.find((item) => item.id === selectedCatalogPhaseId);
                  if (phase) addCatalogPhase(phase);
                }}
              >
                Add to timeline
              </Button>
            </div>
          ) : null}
        </div>

        <div className="overflow-x-auto">
          <table className="w-full min-w-[1040px] text-left text-sm">
            <thead className="border-b border-border text-[13px] text-muted-foreground">
              <tr>
                <th className="py-2 pr-3 font-medium">Phase</th>
                <th className="py-2 pr-3 font-medium">Anchor</th>
                <th className="py-2 pr-3 font-medium">WD start</th>
                <th className="py-2 pr-3 font-medium">WD end</th>
                <th className="py-2 pr-3 font-medium">Gate</th>
                <th className="py-2 pr-3 font-medium">SLA</th>
                <th className="py-2 pr-3 font-medium">Visible</th>
                {isDraft ? <th className="py-2 font-medium">Actions</th> : null}
              </tr>
            </thead>
            <tbody>
              {phases.map((phase, index) => {
                const isHidden = hiddenSet.has(phase.phase_key);
                return (
                  <tr key={phase.phase_key} className={`border-b border-border/60 ${isHidden ? "opacity-50" : ""}`}>
                    <td className="py-2 pr-3">
                      <p className="font-medium text-foreground">
                        {phase.label || phase.phase_key}
                        {phase.is_custom ? (
                          <span className="ml-1.5 rounded bg-sky-100 px-1.5 py-0.5 text-[10px] font-medium text-sky-900 dark:bg-sky-950 dark:text-sky-100">
                            Custom
                          </span>
                        ) : null}
                      </p>
                      <p className="font-mono text-xs text-muted-foreground">{phase.phase_key}</p>
                    </td>
                    <td className="py-2 pr-3">
                      {isDraft ? (
                        <select
                          className="h-8 rounded-md border border-input bg-background px-2 text-xs"
                          value={phase.anchor}
                          onChange={(e) => updatePhase(index, { anchor: e.target.value })}
                        >
                          <option value="endorsement">endorsement</option>
                          <option value="tssr_approved">tssr_approved</option>
                        </select>
                      ) : (
                        <span className="font-mono text-xs">{phase.anchor}</span>
                      )}
                    </td>
                    <td className="py-2 pr-3">
                      {isDraft ? (
                        <input
                          type="number"
                          className="h-8 w-16 rounded-md border border-input bg-background px-2 text-xs"
                          value={phase.working_day_start}
                          onChange={(e) =>
                            updatePhase(index, { working_day_start: Number.parseInt(e.target.value, 10) || 0 })
                          }
                        />
                      ) : (
                        phase.working_day_start
                      )}
                    </td>
                    <td className="py-2 pr-3">
                      {isDraft ? (
                        <input
                          type="number"
                          className="h-8 w-16 rounded-md border border-input bg-background px-2 text-xs"
                          value={phase.working_day_end}
                          onChange={(e) =>
                            updatePhase(index, { working_day_end: Number.parseInt(e.target.value, 10) || 0 })
                          }
                        />
                      ) : (
                        phase.working_day_end
                      )}
                    </td>
                    <td className="py-2 pr-3 font-mono text-xs">{phase.gate ?? "—"}</td>
                    <td className="py-2 pr-3">
                      {isDraft && phase.anchor === "tssr_approved" ? (
                        <Checkbox
                          className="size-4"
                          checked={phase.counts_toward_sla !== false}
                          onCheckedChange={(v) => updatePhase(index, { counts_toward_sla: v === true })}
                          title="Counts toward SLA budget"
                        />
                      ) : phase.anchor === "tssr_approved" ? (
                        phase.counts_toward_sla === false ? "No" : "Yes"
                      ) : (
                        "—"
                      )}
                    </td>
                    <td className="py-2 pr-3">
                      {isDraft ? (
                        <button
                          type="button"
                          className="rounded-md border border-border p-1.5 text-muted-foreground hover:bg-muted"
                          onClick={() => toggleHidden(phase.phase_key)}
                          title={isHidden ? "Show phase" : "Hide phase"}
                        >
                          {isHidden ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
                        </button>
                      ) : isHidden ? (
                        "Hidden"
                      ) : (
                        "Visible"
                      )}
                    </td>
                    {isDraft ? (
                      <td className="py-2">
                        <div className="flex gap-1">
                          <button
                            type="button"
                            className="rounded-md border border-border p-1 text-muted-foreground hover:bg-muted disabled:opacity-40"
                            disabled={index === 0}
                            onClick={() => movePhase(index, -1)}
                          >
                            <ArrowUp className="size-4" />
                          </button>
                          <button
                            type="button"
                            className="rounded-md border border-border p-1 text-muted-foreground hover:bg-muted disabled:opacity-40"
                            disabled={index === phases.length - 1}
                            onClick={() => movePhase(index, 1)}
                          >
                            <ArrowDown className="size-4" />
                          </button>
                          {phase.is_custom ? (
                            <button
                              type="button"
                              className="rounded-md border border-border p-1 text-destructive hover:bg-muted"
                              onClick={() => removeCustomPhase(index)}
                              title="Remove custom phase"
                            >
                              <Trash2 className="size-4" />
                            </button>
                          ) : null}
                        </div>
                      </td>
                    ) : null}
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      </section>

      {gateRows.length > 0 ? (
        <section className="space-y-4 rounded-xl border border-border bg-card p-4 shadow-sm">
          <div>
            <h2 className="text-base font-medium text-foreground">Default gate approval chains</h2>
            <p className="mt-1 text-sm text-muted-foreground">
              Platform defaults copied to tenants on assign. Tenants may override chains locally.
            </p>
          </div>

          <div className="overflow-x-auto">
            <table className="w-full min-w-[640px] text-left text-sm">
              <thead className="border-b border-border text-muted-foreground">
                <tr>
                  <th className="py-2 pr-4 font-medium">Phase</th>
                  <th className="py-2 pr-4 font-medium">Enabled</th>
                  <th className="py-2 font-medium">Approval chain (ordered roles)</th>
                </tr>
              </thead>
              <tbody>
                {gateRows.map(([phaseKey, gatePolicy]) => (
                  <tr key={phaseKey} className="border-b border-border/60">
                    <td className="py-2 pr-4 font-mono text-xs">{phaseKey}</td>
                    <td className="py-2 pr-4">
                      <Checkbox
                        className="size-4"
                        checked={gatePolicy.enabled}
                        disabled={!isDraft}
                        onCheckedChange={(v) =>
                          setGatePolicies((prev) => ({
                            ...prev,
                            [activeTemplate]: {
                              ...prev[activeTemplate],
                              [phaseKey]: { ...gatePolicy, enabled: v === true },
                            },
                          }))
                        }
                      />
                    </td>
                    <td className="py-2 max-w-md">
                      <GateApprovalChainEditor
                        chain={gatePolicy.chain}
                        disabled={!isDraft || !gatePolicy.enabled}
                        onChange={(chain) =>
                          setGatePolicies((prev) => ({
                            ...prev,
                            [activeTemplate]: {
                              ...prev[activeTemplate],
                              [phaseKey]: {
                                ...gatePolicy,
                                chain: sanitizeGateApprovalChain(chain),
                              },
                            },
                          }))
                        }
                      />
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>
      ) : null}

      <section className="space-y-4 rounded-xl border border-border bg-card p-4 shadow-sm">
        <div>
          <h2 className="text-base font-medium text-foreground">Gate approval email notifications</h2>
          <p className="mt-1 text-sm text-muted-foreground">
            Platform defaults copied to tenants on policy assign. Tenants may override per event and recipient.
          </p>
        </div>
        <EmailNotificationPolicyEditor
          value={emailPolicies}
          onChange={setEmailPolicies}
          disabled={!isDraft}
        />
      </section>
    </div>
  );
}

function normalizeTemplates(raw: Record<string, Array<Record<string, unknown>>>): Record<string, TimelinePhase[]> {
  const result: Record<string, TimelinePhase[]> = {};
  for (const [key, phases] of Object.entries(raw ?? {})) {
    if (!Array.isArray(phases)) continue;
    result[key] = phases.map((phase, index) => ({
      phase_key: String(phase.phase_key ?? ""),
      label: String(phase.label ?? phase.phase_key ?? ""),
      owner_role: phase.owner_role != null ? String(phase.owner_role) : null,
      anchor: String(phase.anchor ?? "endorsement"),
      working_day_start: Number(phase.working_day_start ?? 0),
      working_day_end: Number(phase.working_day_end ?? 0),
      gate: phase.gate != null ? String(phase.gate) : null,
      counts_toward_sla: phase.counts_toward_sla === false ? false : true,
      is_custom: Boolean(phase.is_custom),
      catalog_phase_id: phase.catalog_phase_id != null ? String(phase.catalog_phase_id) : null,
      sort_order: Number(phase.sort_order ?? index),
    }));
  }
  return result;
}
