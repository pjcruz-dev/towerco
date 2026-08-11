"use client";

import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useEffect, useMemo, useState } from "react";

import { AcronymLabel } from "@/components/help/acronym-label";
import { MilestonePhaseLabel } from "@/components/help/milestone-phase-label";
import { Button } from "@/components/ui/button";
import { getErrorMessage } from "@/lib/api/error";
import { patchRolloutPlaybookDayOverrides } from "@/lib/api/modules/rollout-api";
import type { RolloutPlaybookPhaseTemplate, RolloutPlaybookStatus } from "@/modules/rollout/types";
import { useNotificationStore } from "@/stores/notification-store";

type Props = {
  status: RolloutPlaybookStatus | undefined;
  canConfigure: boolean;
};

const templateLabels: Record<string, string> = {
  bts: "BTS",
  rtb: "RTB",
  colocation: "Colocation",
};

export function RolloutPlaybookOverrides({ status, canConfigure }: Props) {
  const queryClient = useQueryClient();
  const push = useNotificationStore((state) => state.push);

  const timelineTemplates = status?.timeline_templates;
  const templateKeys = useMemo(
    () =>
      Object.keys(timelineTemplates ?? {}).filter((key) => (timelineTemplates?.[key]?.length ?? 0) > 0),
    [timelineTemplates],
  );

  const [activeTemplate, setActiveTemplate] = useState(templateKeys[0] ?? "bts");
  const [overrides, setOverrides] = useState<Record<string, Record<string, { working_day_end?: number }>>>({});

  useEffect(() => {
    setOverrides(status?.day_overrides ?? {});
  }, [status?.day_overrides]);

  useEffect(() => {
    if (templateKeys.length === 0) {
      return;
    }

    setActiveTemplate((current) => (templateKeys.includes(current) ? current : templateKeys[0]!));
  }, [templateKeys]);

  const mutation = useMutation({
    mutationFn: () => patchRolloutPlaybookDayOverrides(overrides),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["project-one", "rollout-playbook"] });
      push({ level: "success", title: "Day overrides saved", message: "New rollouts will use updated phase day counts." });
    },
    onError: (error) =>
      push({ level: "error", title: "Could not save overrides", message: getErrorMessage(error) }),
  });

  if (templateKeys.length === 0) {
    return (
      <p className="text-sm text-muted-foreground">Timeline templates are not available for this playbook assignment.</p>
    );
  }

  const phases = timelineTemplates?.[activeTemplate] ?? [];

  const setPhaseEnd = (phaseKey: string, value: string) => {
    const parsed = value.trim() === "" ? undefined : Number(value);
    setOverrides((prev) => {
      const templateOverrides = { ...(prev[activeTemplate] ?? {}) };
      if (parsed === undefined || Number.isNaN(parsed)) {
        delete templateOverrides[phaseKey];
      } else {
        templateOverrides[phaseKey] = { working_day_end: parsed };
      }
      const next = { ...prev, [activeTemplate]: templateOverrides };
      if (Object.keys(templateOverrides).length === 0) {
        delete next[activeTemplate];
      }
      return next;
    });
  };

  return (
    <div className="space-y-4 rounded-xl border border-border bg-card p-4 shadow-sm">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 className="text-base font-medium text-foreground">Phase day overrides</h2>
          <p className="mt-1 text-sm text-muted-foreground">
            Adjust working-day end counts per phase. Structure and phase list are fixed by the assigned playbook.
          </p>
        </div>
        {canConfigure ? (
          <Button size="sm" disabled={mutation.isPending} onClick={() => mutation.mutate()}>
            {mutation.isPending ? "Saving…" : "Save overrides"}
          </Button>
        ) : null}
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
            {status?.delivery_periods?.[key]?.working_days ? (
              <>
                {" "}
                · {status.delivery_periods[key].working_days} wd <AcronymLabel term="SLA" />
              </>
            ) : null}
          </button>
        ))}
      </div>

      <div className="overflow-x-auto">
        <table className="w-full min-w-[520px] text-left text-sm">
          <thead className="border-b border-border text-muted-foreground">
            <tr>
              <th className="py-2 pr-4 font-medium">Phase</th>
              <th className="py-2 pr-4 font-medium">Default end (wd)</th>
              <th className="py-2 font-medium">Override end (wd)</th>
            </tr>
          </thead>
          <tbody>
            {phases.map((phase: RolloutPlaybookPhaseTemplate) => {
              const overrideValue = overrides[activeTemplate]?.[phase.phase_key]?.working_day_end;
              return (
                <tr key={phase.phase_key} className="border-b border-border last:border-0">
                  <td className="py-2.5 pr-4">
                    <div className="font-medium text-foreground">
                      <MilestonePhaseLabel phaseKey={phase.phase_key} label={phase.label} />
                    </div>
                    <div className="text-xs text-muted-foreground">{phase.phase_key}</div>
                  </td>
                  <td className="py-2.5 pr-4 text-muted-foreground">{phase.working_day_end}</td>
                  <td className="py-2.5">
                    {canConfigure ? (
                      <input
                        type="number"
                        min={phase.working_day_start}
                        className="h-8 w-24 rounded-md border border-input bg-background px-2 text-sm"
                        placeholder={String(phase.working_day_end)}
                        value={overrideValue ?? ""}
                        onChange={(e) => setPhaseEnd(phase.phase_key, e.target.value)}
                      />
                    ) : (
                      <span>{overrideValue ?? "—"}</span>
                    )}
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>
    </div>
  );
}
