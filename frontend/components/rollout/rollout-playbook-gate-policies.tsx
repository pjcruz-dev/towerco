"use client";

import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useEffect, useMemo, useState } from "react";

import { GateApprovalChainEditor } from "@/components/rollout/gate-approval-chain-editor";
import { AcronymLabel } from "@/components/help/acronym-label";
import { MilestonePhaseLabel } from "@/components/help/milestone-phase-label";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { sanitizeGateApprovalChain } from "@/lib/rollout/gate-approval-chain-roles";
import { getErrorMessage } from "@/lib/api/error";
import { patchRolloutPlaybookConfig } from "@/lib/api/modules/rollout-api";
import type { RolloutPlaybookPhaseTemplate, RolloutPlaybookStatus } from "@/modules/rollout/types";
import { useNotificationStore } from "@/stores/notification-store";

type Props = {
  status: RolloutPlaybookStatus | undefined;
  canConfigure: boolean;
};

type GatePolicy = { enabled: boolean; chain: string[] };

const templateLabels: Record<string, string> = {
  bts: "BTS",
  rtb: "RTB",
  colocation: "Colocation",
};

/** Union of saved policies with every timeline phase so any phase can be enabled here. */
function expandPoliciesWithTimeline(
  policies: Record<string, Record<string, GatePolicy>>,
  timelineTemplates: Record<string, RolloutPlaybookPhaseTemplate[]> | undefined,
): Record<string, Record<string, GatePolicy>> {
  const expanded: Record<string, Record<string, GatePolicy>> = { ...policies };

  if (!timelineTemplates) {
    return expanded;
  }

  for (const [templateKey, phases] of Object.entries(timelineTemplates)) {
    const phaseMap: Record<string, GatePolicy> = { ...(expanded[templateKey] ?? {}) };

    for (const phase of phases) {
      const existing = phaseMap[phase.phase_key];
      phaseMap[phase.phase_key] = {
        enabled: existing?.enabled ?? false,
        chain: sanitizeGateApprovalChain(existing?.chain ?? []),
      };
    }

    expanded[templateKey] = phaseMap;
  }

  return expanded;
}

export function RolloutPlaybookGatePolicies({ status, canConfigure }: Props) {
  const queryClient = useQueryClient();
  const push = useNotificationStore((state) => state.push);

  const policies = status?.gate_approval_policies;
  const timelineTemplates = status?.timeline_templates;

  const expandedPolicies = useMemo(
    () => expandPoliciesWithTimeline(policies ?? {}, timelineTemplates),
    [policies, timelineTemplates],
  );

  const templateKeys = useMemo(() => {
    const keys = new Set<string>([
      ...Object.keys(expandedPolicies),
      ...Object.keys(timelineTemplates ?? {}),
    ]);
    return Array.from(keys).filter((key) => {
      const phaseCount = timelineTemplates?.[key]?.length ?? Object.keys(expandedPolicies[key] ?? {}).length;
      return phaseCount > 0;
    });
  }, [expandedPolicies, timelineTemplates]);

  const [activeTemplate, setActiveTemplate] = useState(templateKeys[0] ?? "bts");
  const [draft, setDraft] = useState(expandedPolicies);
  const [escalationDays, setEscalationDays] = useState(String(status?.gate_approval_escalation_working_days ?? 3));

  useEffect(() => {
    setDraft(expandPoliciesWithTimeline(policies ?? {}, timelineTemplates));
  }, [policies, timelineTemplates]);

  useEffect(() => {
    setEscalationDays(String(status?.gate_approval_escalation_working_days ?? 3));
  }, [status?.gate_approval_escalation_working_days]);

  useEffect(() => {
    if (templateKeys.length === 0) {
      return;
    }

    setActiveTemplate((current) => (templateKeys.includes(current) ? current : templateKeys[0]!));
  }, [templateKeys]);

  const mutation = useMutation({
    mutationFn: () =>
      patchRolloutPlaybookConfig({
        gate_approval_policies: draft,
        gate_approval_escalation_working_days: Number.parseInt(escalationDays, 10) || 3,
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["project-one", "rollout-playbook"] });
      push({ level: "success", title: "Gate policies saved", message: "Approval chains updated for this tenant." });
    },
    onError: (error) =>
      push({ level: "error", title: "Could not save policies", message: getErrorMessage(error) }),
  });

  const phaseMeta = useMemo(() => {
    const map = new Map<string, RolloutPlaybookPhaseTemplate>();
    for (const phase of timelineTemplates?.[activeTemplate] ?? []) {
      map.set(phase.phase_key, phase);
    }
    return map;
  }, [activeTemplate, timelineTemplates]);

  const rows = useMemo(() => {
    const policyMap = draft[activeTemplate] ?? {};
    const ordered = timelineTemplates?.[activeTemplate];

    if (ordered?.length) {
      return ordered.map((phase) => [phase.phase_key, policyMap[phase.phase_key] ?? { enabled: false, chain: [] }] as const);
    }

    return Object.entries(policyMap).map(([phaseKey, policy]) => [phaseKey, policy] as const);
  }, [activeTemplate, draft, timelineTemplates]);

  const enabledCount = rows.filter(([, policy]) => policy.enabled).length;

  if (templateKeys.length === 0) {
    return null;
  }

  return (
    <div className="space-y-4 rounded-xl border border-border bg-card p-4 shadow-sm">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2 className="text-base font-medium text-foreground">Gate approval policies</h2>
          <p className="mt-1 text-sm text-muted-foreground">
            Turn on approval for any timeline phase: check <strong>Enabled</strong>, then set the order with{" "}
            <strong>SAQ → PMO → CME</strong> (core steps — same three owners as rollout metadata). Use{" "}
            <em>Show advanced step types</em> only for MNO, Engineering, or tenant escalation. Applies to this tenant
            without a new playbook version.
          </p>
          <p className="mt-1 text-xs text-muted-foreground">
            {enabledCount} of {rows.length} phases require formal approval on {templateLabels[activeTemplate] ?? activeTemplate}.
          </p>
        </div>
        {canConfigure ? (
          <Button size="sm" disabled={mutation.isPending} onClick={() => mutation.mutate()}>
            {mutation.isPending ? "Saving…" : "Save policies"}
          </Button>
        ) : null}
      </div>

      <div className="max-w-xs">
        <label className="text-xs font-medium text-muted-foreground">Escalation after (working days)</label>
        <input
          type="number"
          min={1}
          max={30}
          className="mt-1.5 h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
          value={escalationDays}
          disabled={!canConfigure}
          onChange={(e) => setEscalationDays(e.target.value)}
        />
        <p className="mt-1 text-xs text-muted-foreground">
          Pending approvers receive a reminder email after this many working days on the same step.
        </p>
      </div>

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
            {templateLabels[key] ? <AcronymLabel term={templateLabels[key]}>{templateLabels[key]}</AcronymLabel> : key.toUpperCase()}
          </button>
        ))}
      </div>

      <div className="overflow-x-auto">
        <table className="w-full min-w-[640px] text-left text-sm">
          <thead className="border-b border-border text-muted-foreground">
            <tr>
              <th className="py-2 pr-4 font-medium">Phase</th>
              <th className="py-2 pr-4 font-medium">Enabled</th>
              <th className="py-2 font-medium">Approval chain (roles)</th>
            </tr>
          </thead>
          <tbody>
            {rows.map(([phaseKey, policy]) => {
              const meta = phaseMeta.get(phaseKey);
              const displayLabel = meta?.label ?? phaseKey.replaceAll("_", " ");

              return (
              <tr
                key={phaseKey}
                className={`border-b border-border/60 ${policy.enabled ? "" : "bg-muted/20"}`}
              >
                <td className="py-2 pr-4">
                  <MilestonePhaseLabel phaseKey={phaseKey} label={displayLabel} />
                  <div className="mt-0.5 font-mono text-[10px] text-muted-foreground">{phaseKey}</div>
                  {meta?.gate ? (
                    <div className="mt-0.5 text-[10px] text-muted-foreground">Gate: {meta.gate}</div>
                  ) : null}
                </td>
                <td className="py-2 pr-4">
                  <Checkbox
                    className="size-4"
                    checked={policy.enabled}
                    disabled={!canConfigure}
                    onCheckedChange={(v) => {
                      const enabled = v === true;
                      let chain = policy.chain;
                      if (enabled && chain.length === 0) {
                        const owner = meta?.owner_role?.toLowerCase();
                        const first = owner === "cme" || owner === "cme_power" ? "cme" : "saq";
                        chain = sanitizeGateApprovalChain([first, "pmo"]);
                      }
                      setDraft((prev) => ({
                        ...prev,
                        [activeTemplate]: {
                          ...prev[activeTemplate],
                          [phaseKey]: { ...policy, enabled, chain },
                        },
                      }));
                    }}
                  />
                </td>
                <td className="py-2">
                  <GateApprovalChainEditor
                    chain={policy.chain}
                    disabled={!canConfigure || !policy.enabled}
                    onChange={(chain) =>
                      setDraft((prev) => ({
                        ...prev,
                        [activeTemplate]: {
                          ...prev[activeTemplate],
                          [phaseKey]: { ...policy, chain: sanitizeGateApprovalChain(chain) },
                        },
                      }))
                    }
                  />
                </td>
              </tr>
            );
            })}
          </tbody>
        </table>
      </div>

      <p className="text-xs text-muted-foreground">
        Each step maps to rollout owners and permissions (SAQ, PMO, CME, etc.). Assign matching SAQ / PMO / CME owners
        on each rollout so the right people receive gate requests.
      </p>
    </div>
  );
}
