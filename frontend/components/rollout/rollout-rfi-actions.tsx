"use client";

import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useEffect, useState } from "react";

import { FormInput } from "@/components/forms/form-input";
import { AcronymLabel } from "@/components/help/acronym-label";
import { AcronymText } from "@/components/help/acronym-text";
import { Button } from "@/components/ui/button";
import { getErrorMessage } from "@/lib/api/error";
import { recordRolloutRfi } from "@/lib/api/modules/rollout-api";
import { isBuildReadinessComplete, isRfiReady } from "@/lib/rollout/phase-gate-readiness";
import type { RolloutDetail } from "@/modules/rollout/types";
import { useNotificationStore } from "@/stores/notification-store";

type Props = {
  rolloutId: string;
  detail: RolloutDetail | undefined;
  canManage: boolean;
};

/**
 * P7 — Record RFI (★ site ready). Closes delivery SLA and passes Construction gate.
 */
export function RolloutRfiActions({ rolloutId, detail, canManage }: Props) {
  const queryClient = useQueryClient();
  const push = useNotificationStore((state) => state.push);
  const [rfiDate, setRfiDate] = useState("");

  useEffect(() => {
    setRfiDate(detail?.actual_rfi_date ?? "");
  }, [detail?.actual_rfi_date]);

  const mutation = useMutation({
    mutationFn: () => recordRolloutRfi(rolloutId, rfiDate),
    onSuccess: (data) => {
      queryClient.invalidateQueries({ queryKey: ["project-one", "rollouts", "detail", rolloutId] });
      queryClient.invalidateQueries({ queryKey: ["project-one", "rollouts"] });
      queryClient.invalidateQueries({ queryKey: ["project-one", "dashboard"] });
      const variance = data.sla_variance_working_days;
      push({
        level: "success",
        title: "★ Site ready — RFI recorded",
        message:
          variance !== null && variance !== undefined
            ? `Delivery complete · SLA variance ${variance > 0 ? "+" : ""}${variance} WD`
            : "Site marked ready. Proceed to Site License / Handover.",
      });
    },
    onError: (error) =>
      push({ level: "error", title: "Could not record RFI", message: getErrorMessage(error) }),
  });

  if (!canManage || !detail?.tssr_approved_date) return null;

  const isCompleted = detail.status === "completed" || Boolean(detail.actual_rfi_date);
  const rfiReady = isRfiReady(detail);
  const p6Done = isBuildReadinessComplete(detail);

  return (
    <div className="rounded-xl border border-border bg-card p-4 shadow-sm">
      <h2 className="text-base font-medium text-foreground">
        Record <AcronymLabel term="RFI / RFTI">RFI</AcronymLabel> ★ site ready
      </h2>
      <p className="mt-1 text-sm text-muted-foreground">
        <AcronymText
          text={`Actual RFI date marks the site ready, passes the Construction (RFI Certificate) gate, and computes SLA variance vs the ${detail.sla_working_days} working-day target.`}
        />
      </p>

      {!isCompleted && !p6Done ? (
        <p className="mt-2 text-xs text-amber-800 dark:text-amber-200">
          Complete build readiness (Pre-con → Permitting → SKOM) before recording RFI.
        </p>
      ) : null}

      {isCompleted && detail.actual_rfi_date ? (
        <div className="mt-3 space-y-1 text-sm">
          <p>
            <AcronymLabel term="RFI / RFTI">Actual RFI</AcronymLabel>:{" "}
            <span className="font-medium">{detail.actual_rfi_date}</span>
            <span className="ml-2 text-xs text-green-700 dark:text-green-300">★ Site ready</span>
          </p>
          {detail.sla_variance_working_days !== null && detail.sla_variance_working_days !== undefined ? (
            <p>
              <AcronymLabel term="SLA">SLA</AcronymLabel> variance:{" "}
              <span
                className={
                  detail.sla_variance_working_days > 0
                    ? "font-medium text-red-600 dark:text-red-400"
                    : "font-medium text-green-700 dark:text-green-300"
                }
              >
                {detail.sla_variance_working_days > 0 ? "+" : ""}
                {detail.sla_variance_working_days} working days
              </span>
            </p>
          ) : null}
        </div>
      ) : (
        <form
          className="mt-4 flex flex-wrap items-end gap-3"
          onSubmit={(e) => {
            e.preventDefault();
            if (!rfiDate || !rfiReady) return;
            mutation.mutate();
          }}
        >
          <div className="min-w-[200px]">
            <FormInput
              label={<AcronymLabel term="RFI / RFTI">Actual RFI date</AcronymLabel>}
              date
              value={rfiDate}
              onChange={(e) => setRfiDate(e.target.value)}
              required
              disabled={!rfiReady}
            />
          </div>
          <Button type="submit" size="sm" disabled={mutation.isPending || !rfiDate || !rfiReady}>
            {mutation.isPending ? "Saving…" : "Record RFI"}
          </Button>
        </form>
      )}
    </div>
  );
}
