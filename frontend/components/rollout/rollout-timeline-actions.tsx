"use client";

import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useEffect, useMemo, useState } from "react";

import { FormInput } from "@/components/forms/form-input";
import { AcronymLabel } from "@/components/help/acronym-label";
import { AcronymText } from "@/components/help/acronym-text";
import { Button } from "@/components/ui/button";
import { getErrorMessage } from "@/lib/api/error";
import { setRolloutDeliveryPeriodStart } from "@/lib/api/modules/rollout-api";
import {
  isDayOneReady,
  isDayOneSet,
  isMocColPassed,
  isPreAssessmentPassed,
  isTssrCreationPassed,
  isTssrCreationReady,
} from "@/lib/rollout/phase-gate-readiness";
import type { RolloutDetail } from "@/modules/rollout/types";
import { useNotificationStore } from "@/stores/notification-store";

type Props = {
  rolloutId: string;
  detail: RolloutDetail | undefined;
  canManage: boolean;
};

type TriggerKind = "bts" | "rtb" | "colocation";

/**
 * P5 — Delivery period start (Day-1). For BTS this is TSSR MNO approval date.
 */
export function RolloutTimelineActions({ rolloutId, detail, canManage }: Props) {
  const queryClient = useQueryClient();
  const push = useNotificationStore((state) => state.push);

  const triggerKind = useMemo<TriggerKind>(() => {
    if (detail?.project_type === "rtb") return "rtb";
    if (detail?.project_type === "colocation" || detail?.project_type === "colo") return "colocation";
    return "bts";
  }, [detail?.project_type]);

  const [inputDate, setInputDate] = useState("");

  useEffect(() => {
    if (!detail) return;
    if (triggerKind === "rtb") {
      setInputDate(detail.doa_execution_date ?? "");
      return;
    }
    if (triggerKind === "colocation") {
      setInputDate(detail.site_license_executed_date ?? "");
      return;
    }
    setInputDate(detail.tssr_approved_date ?? "");
  }, [detail, triggerKind]);

  const mutation = useMutation({
    mutationFn: () => {
      const body =
        triggerKind === "rtb"
          ? { doa_execution_date: inputDate }
          : triggerKind === "colocation"
            ? { site_license_executed_date: inputDate }
            : { tssr_approved_date: inputDate };

      return setRolloutDeliveryPeriodStart(rolloutId, body);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["project-one", "rollouts", "detail", rolloutId] });
      queryClient.invalidateQueries({ queryKey: ["project-one", "rollouts"] });
      queryClient.invalidateQueries({ queryKey: ["project-one", "dashboard"] });
      push({
        level: "success",
        title: "Day-1 recorded",
        message: "TSSR MNO gate completed. Post–Day-1 SLA targets recalculated.",
      });
    },
    onError: (error) =>
      push({ level: "error", title: "Could not set Day-1", message: getErrorMessage(error) }),
  });

  if (!canManage || !detail || detail.is_batch) return null;

  const dayOneReady = isDayOneReady(detail);
  const dayOneSet = isDayOneSet(detail);

  const copy = {
    bts: {
      title: (
        <>
          Day-1 — <AcronymLabel term="BTS" /> (<AcronymLabel term="TSSR" /> MNO approval)
        </>
      ),
      help: "After TSSR create/review passes Engineering, record the TSSR approved date to start the 115 working-day delivery SLA.",
      label: <AcronymLabel term="TSSR">TSSR approved date</AcronymLabel>,
    },
    rtb: {
      title: (
        <>
          Day-1 — <AcronymLabel term="RTB" /> (DOA + 15 WD)
        </>
      ),
      help: "DOA execution date plus 15 working days defines delivery period start (85 WD SLA).",
      label: "DOA execution date",
    },
    colocation: {
      title: "Day-1 — Colocation (site license)",
      help: "Site license execution date starts the 30 working-day colocation SLA.",
      label: "Site license executed date",
    },
  }[triggerKind];

  return (
    <div className="rounded-xl border border-border bg-card p-4 shadow-sm">
      <h2 className="text-base font-medium text-foreground">{copy.title}</h2>
      <p className="mt-1 text-sm text-muted-foreground">
        <AcronymText text={copy.help} />
      </p>

      {triggerKind === "bts" ? (
        <ul className="mt-2 list-inside list-disc text-xs text-muted-foreground">
          <li>Pre-assessment + MOC/COL complete {isTssrCreationReady(detail) ? "✓" : ""}</li>
          <li>
            TSSR create/review (Engineering){" "}
            {isTssrCreationPassed(detail) ? "✓" : "— request gate on timeline"}
          </li>
          <li>Record Day-1 / TSSR MNO approval {dayOneSet ? "✓" : ""}</li>
        </ul>
      ) : null}

      {!dayOneReady && !dayOneSet ? (
        <p className="mt-2 text-xs text-amber-800 dark:text-amber-200">
          {!isPreAssessmentPassed(detail)
            ? "Complete Pre-assessment first."
            : !isMocColPassed(detail)
              ? "Complete MOC/COL first."
              : !isTssrCreationPassed(detail)
                ? "Pass TSSR create/review (Engineering) on the timeline before Day-1."
                : "Prerequisites incomplete."}
        </p>
      ) : null}

      {detail.tssr_approved_date ? (
        <p className="mt-2 text-xs text-muted-foreground">
          Delivery period start: <span className="font-medium text-foreground">{detail.tssr_approved_date}</span>
          {detail.target_rfi_working_date ? (
            <>
              {" "}
              · <AcronymLabel term="RFI / RFTI">Target RFI</AcronymLabel> {detail.target_rfi_working_date}
            </>
          ) : null}
        </p>
      ) : null}
      <form
        className="mt-4 flex flex-wrap items-end gap-3"
        onSubmit={(e) => {
          e.preventDefault();
          if (!inputDate || !dayOneReady) return;
          mutation.mutate();
        }}
      >
        <div className="min-w-[200px]">
          <FormInput
            label={copy.label}
            date
            value={inputDate}
            onChange={(e) => setInputDate(e.target.value)}
            required
            disabled={!dayOneReady && !dayOneSet}
          />
        </div>
        <Button
          type="submit"
          size="sm"
          disabled={mutation.isPending || !inputDate || (!dayOneReady && !dayOneSet)}
        >
          {mutation.isPending ? "Saving…" : detail.tssr_approved_date ? "Update Day-1" : "Record Day-1"}
        </Button>
      </form>
    </div>
  );
}
