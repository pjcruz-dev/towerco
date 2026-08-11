"use client";

import { useMutation, useQueryClient } from "@tanstack/react-query";
import Link from "next/link";
import { useEffect, useState } from "react";

import { FormInput } from "@/components/forms/form-input";
import { Button, buttonVariants } from "@/components/ui/button";
import { getErrorMessage } from "@/lib/api/error";
import { recordRolloutSiteLicense } from "@/lib/api/modules/rollout-api";
import {
  isCloseOutComplete,
  isHandoverPassed,
  isHandoverReady,
  isRfiRecorded,
  isSiteLicensePassed,
  isSiteLicenseReady,
} from "@/lib/rollout/phase-gate-readiness";
import type { RolloutDetail } from "@/modules/rollout/types";
import { cn } from "@/lib/utils";
import { useNotificationStore } from "@/stores/notification-store";

type Props = {
  rolloutId: string;
  detail: RolloutDetail;
  phaseKey: string;
  canManage: boolean;
};

function stepSuffix(passed: boolean, ready: boolean, isCurrent: boolean): string {
  if (passed) {
    return " ✓";
  }
  if (isCurrent) {
    return ready ? " — ready" : " — blocked";
  }
  return ready ? " — ready next" : " — pending";
}

/**
 * P8 — Close-out: milestones → Site License → Handover to Operations.
 */
export function RolloutCloseOutPanel({ rolloutId, detail, phaseKey, canManage }: Props) {
  const queryClient = useQueryClient();
  const push = useNotificationStore((state) => state.push);
  const [licenseDate, setLicenseDate] = useState("");
  const [remarks, setRemarks] = useState("");

  const rfiDone = isRfiRecorded(detail);
  const licensePassed = isSiteLicensePassed(detail);
  const licenseReady = isSiteLicenseReady(detail);
  const handoverPassed = isHandoverPassed(detail);
  const handoverReady = isHandoverReady(detail);
  const complete = isCloseOutComplete(detail);
  const summary = detail.milestone_cycles_summary;

  useEffect(() => {
    setLicenseDate(detail.site_license_executed_date ?? "");
    setRemarks(detail.site_license_remarks ?? "");
  }, [detail.site_license_executed_date, detail.site_license_remarks]);

  const mutation = useMutation({
    mutationFn: () => recordRolloutSiteLicense(rolloutId, licenseDate, remarks || null),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["project-one", "rollouts", "detail", rolloutId] });
      queryClient.invalidateQueries({ queryKey: ["project-one", "rollouts"] });
      push({
        level: "success",
        title: "Site license recorded",
        message: "Site License gate completed. Request Handover to Operations next.",
      });
    },
    onError: (error) =>
      push({ level: "error", title: "Could not record site license", message: getErrorMessage(error) }),
  });

  const title =
    phaseKey === "handover_operations" ? "Handover to Operations" : "Site License Processing";

  return (
    <div className="space-y-3">
      {!rfiDone ? (
        <div className="rounded-lg border border-amber-200 bg-amber-50/80 px-3 py-2 text-sm text-amber-950 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-100">
          Complete <span className="font-medium">RFI</span> (★ site ready) before close-out.
        </div>
      ) : null}

      {complete ? (
        <div className="rounded-lg border border-green-200 bg-green-50/80 px-3 py-2 text-sm text-green-950 dark:border-green-900 dark:bg-green-950/30 dark:text-green-100">
          Close-out complete — site licensed and handed over to Operations.
        </div>
      ) : null}

      <div className="rounded-xl border border-border bg-card p-4 shadow-sm">
        <h3 className="text-sm font-medium text-foreground">{title}</h3>
        <p className="mt-1 text-sm text-muted-foreground">
          Post–RFI close-out (outside the 115 WD delivery SLA): finish project milestones, secure the site
          license, then hand over to Operations.
        </p>

        {summary ? (
          <p className="mt-2 text-xs text-muted-foreground">
            Milestone cycles: {summary.progress_pct}% · {summary.on_track} on track
            {summary.overdue > 0 ? ` · ${summary.overdue} overdue` : ""}
          </p>
        ) : null}

        <ul className="mt-3 list-inside list-disc text-xs text-muted-foreground">
          <li>RFI ★ site ready{rfiDone ? " ✓" : ""}</li>
          <li>
            Project milestones
            {detail.project_id ? " — open project to complete" : " — link a project if needed"}
          </li>
          <li>
            Site License
            {stepSuffix(licensePassed, licenseReady, phaseKey === "site_license")}
          </li>
          <li>
            Handover to Operations
            {stepSuffix(handoverPassed, handoverReady, phaseKey === "handover_operations")}
          </li>
        </ul>

        <div className="mt-4 flex flex-wrap gap-2">
          {!rfiDone ? (
            <Link
              href={`/project-one/rollouts/${rolloutId}?phase=construction`}
              className={cn(buttonVariants({ size: "sm", variant: "outline" }))}
            >
              Open Construction
            </Link>
          ) : null}
          {detail.project_id ? (
            <Link
              href={`/project-one/projects/${detail.project_id}`}
              className={cn(buttonVariants({ size: "sm", variant: "outline" }))}
            >
              Project milestones
            </Link>
          ) : null}
          {licensePassed && !handoverPassed ? (
            <p className="self-center text-xs text-muted-foreground">
              Use <span className="font-medium text-foreground">Request</span> on the Handover gate column.
            </p>
          ) : null}
        </div>
      </div>

      {canManage && rfiDone && phaseKey === "site_license" ? (
        <div className="rounded-xl border border-border bg-card p-4 shadow-sm">
          <h4 className="text-sm font-medium text-foreground">Record site license executed</h4>
          <p className="mt-1 text-xs text-muted-foreground">
            Saves the executed date and passes the Site License gate (or use formal approval on the timeline).
          </p>
          {licensePassed && detail.site_license_executed_date ? (
            <p className="mt-3 text-sm">
              Executed: <span className="font-medium">{detail.site_license_executed_date}</span>
              {detail.site_license_remarks ? (
                <span className="mt-1 block text-xs text-muted-foreground">{detail.site_license_remarks}</span>
              ) : null}
            </p>
          ) : (
            <form
              className="mt-4 space-y-3"
              onSubmit={(e) => {
                e.preventDefault();
                if (!licenseDate || !licenseReady) return;
                mutation.mutate();
              }}
            >
              <div className="flex flex-wrap items-end gap-3">
                <div className="min-w-[200px]">
                  <FormInput
                    label="Site license executed date"
                    date
                    value={licenseDate}
                    onChange={(e) => setLicenseDate(e.target.value)}
                    required
                    disabled={!licenseReady}
                  />
                </div>
                <Button type="submit" size="sm" disabled={mutation.isPending || !licenseDate || !licenseReady}>
                  {mutation.isPending ? "Saving…" : "Complete Site License"}
                </Button>
              </div>
              <FormInput
                label="Remarks (optional)"
                value={remarks}
                onChange={(e) => setRemarks(e.target.value)}
                disabled={!licenseReady}
              />
            </form>
          )}
        </div>
      ) : null}
    </div>
  );
}
