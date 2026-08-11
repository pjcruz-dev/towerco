"use client";

import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useEffect, useState } from "react";

import { FormInput } from "@/components/forms/form-input";
import { Button } from "@/components/ui/button";
import { getErrorMessage } from "@/lib/api/error";
import { patchRolloutMetadata } from "@/lib/api/modules/rollout-api";
import { isEndorsementEstablished } from "@/lib/rollout/phase-gate-readiness";
import type { RolloutDetail } from "@/modules/rollout/types";
import { useNotificationStore } from "@/stores/notification-store";

type Props = {
  rolloutId: string;
  detail: RolloutDetail | undefined;
  canManage: boolean;
};

/**
 * P1 — Endorsement & Planning / Site Tracker enrolment.
 * Setting the MNO endorsement date anchors timeline targets and passes the endorsement gate.
 */
export function RolloutEndorsementActions({ rolloutId, detail, canManage }: Props) {
  const queryClient = useQueryClient();
  const push = useNotificationStore((state) => state.push);
  const [inputDate, setInputDate] = useState("");
  const [inputRef, setInputRef] = useState("");

  useEffect(() => {
    setInputDate(detail?.endorsement_date ?? "");
    setInputRef(detail?.endorsement_ref ?? "");
  }, [detail?.endorsement_date, detail?.endorsement_ref]);

  const mutation = useMutation({
    mutationFn: () =>
      patchRolloutMetadata(rolloutId, {
        endorsement_date: inputDate.trim() || null,
        endorsement_ref: inputRef.trim() || null,
      }),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["project-one", "rollouts", "detail", rolloutId] });
      void queryClient.invalidateQueries({ queryKey: ["project-one", "rollouts"] });
      push({
        level: "success",
        title: "Endorsement complete",
        message: "Site Tracker enrolment recorded. Timeline targets recalculated — SAQ can begin site hunting.",
      });
    },
    onError: (error) =>
      push({
        level: "error",
        title: "Could not complete endorsement",
        message: getErrorMessage(error),
      }),
  });

  if (!canManage || !detail || detail.is_batch) {
    return null;
  }

  const established = isEndorsementEstablished(detail);
  const endorsementPhase = detail.timeline_phases?.find((p) => p.phase_key === "endorsement");
  const gatePassed = endorsementPhase?.gate_status === "passed";

  if (established && detail.endorsement_date) {
    return (
      <div className="rounded-xl border border-border bg-card px-4 py-3 text-sm shadow-sm">
        <div className="flex flex-wrap items-baseline justify-between gap-2">
          <p>
            <span className="font-medium text-foreground">Endorsement</span>
            <span className="ml-2 text-xs text-muted-foreground">Site Tracker enrolment</span>
          </p>
          {gatePassed ? (
            <span className="rounded-md border border-green-200 bg-green-50 px-2 py-0.5 text-xs font-medium text-green-900 dark:border-green-900 dark:bg-green-950/50 dark:text-green-100">
              Gate passed
            </span>
          ) : null}
        </div>
        <p className="mt-1 text-foreground">
          <span className="font-medium">Date:</span>{" "}
          <span className="font-mono">{detail.endorsement_date}</span>
          {detail.endorsement_ref ? (
            <>
              {" · "}
              <span className="font-medium">Ref:</span> {detail.endorsement_ref}
            </>
          ) : null}
        </p>
      </div>
    );
  }

  return (
    <div className="rounded-xl border border-amber-200 bg-amber-50/80 p-4 shadow-sm dark:border-amber-900 dark:bg-amber-950/30">
      <h2 className="text-base font-medium text-foreground">Endorsement &amp; Planning</h2>
      <p className="mt-1 text-sm text-muted-foreground">
        Complete Site Tracker enrolment before SAQ adds candidates. The endorsement date anchors working-day
        targets for all timeline phases.
      </p>
      <ul className="mt-2 list-inside list-disc text-xs text-muted-foreground">
        <li>Record MNO endorsement date (required)</li>
        <li>Optional work-package / endorsement reference</li>
        <li>Passes the Endorsement gate automatically</li>
      </ul>
      <div className="mt-3 flex flex-wrap items-end gap-3">
        <div className="min-w-[200px] flex-1">
          <FormInput
            label="Endorsement date"
            date
            value={inputDate}
            onChange={(event) => setInputDate(event.target.value)}
          />
        </div>
        <div className="min-w-[200px] flex-1">
          <FormInput
            label="Endorsement ref (optional)"
            placeholder="Work package / MNO ref"
            value={inputRef}
            onChange={(event) => setInputRef(event.target.value)}
          />
        </div>
        <Button
          type="button"
          disabled={!inputDate.trim() || mutation.isPending}
          onClick={() => mutation.mutate()}
        >
          {mutation.isPending ? "Saving…" : "Complete enrolment"}
        </Button>
      </div>
    </div>
  );
}
