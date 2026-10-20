"use client";

import Link from "next/link";
import { useSearchParams } from "next/navigation";
import { ArrowRight, FileStack, FileText, Inbox, User } from "lucide-react";

import { EApprovalStatusBadge } from "@/components/e-approval/e-approval-status-badge";
import {
  EApprovalWorkflowStepShow,
  buildWorkflowStepShowItems,
} from "@/components/e-approval/e-approval-workflow-step-show";
import { Button } from "@/components/ui/button";
import { usePermission } from "@/hooks/use-permission";
import { buildTourSearchParams, resolveLiveTour } from "@/lib/help/e-approval-live-tour";
import {
  E_APPROVAL_FINANCE_TOUR_ID,
  financeFamilyForStepId,
  financeTourComposePath,
  financeTourDetailPath,
  isEApprovalFinanceTourActive,
} from "@/lib/help/e-approval-finance-tour";
import {
  eApprovalFinanceTourRequestor,
  eApprovalFinanceTourSample,
  eApprovalFinanceTourSampleList,
  type EApprovalFinanceTourFamily,
} from "@/lib/help/e-approval-finance-tour-sample-data";
import { permissions } from "@/lib/rbac/permissions";

function financeTourQuerySuffix(searchParams: URLSearchParams): string {
  const step = Number.parseInt(searchParams.get("tourStep") ?? "0", 10);
  const params = buildTourSearchParams(
    E_APPROVAL_FINANCE_TOUR_ID,
    Number.isFinite(step) ? step : 0,
    { id: "_", path: "/", target: "_", title: "", body: "" },
    searchParams,
  );
  const qs = params.toString();
  return qs ? `?${qs}` : "";
}

function SampleNotice() {
  return (
    <p className="rounded-lg border border-dashed border-border bg-muted/40 px-3 py-2 text-xs text-muted-foreground">
      Sample UI for this tour only — nothing is saved. After you finish or skip, lists return to empty.
    </p>
  );
}

export function useEApprovalFinanceTourFamily(): EApprovalFinanceTourFamily {
  const searchParams = useSearchParams();
  const canApprove = usePermission([permissions.eApprovalApprove]);
  const canCreate = usePermission([permissions.eApprovalSubmissionsCreate]);
  if (!isEApprovalFinanceTourActive(searchParams)) return "cash_advance";
  const tour = resolveLiveTour(E_APPROVAL_FINANCE_TOUR_ID, { canApprove, canCreate });
  const index = Number.parseInt(searchParams.get("tourStep") ?? "0", 10);
  const step = tour?.steps[Number.isFinite(index) ? index : 0];
  return financeFamilyForStepId(step?.id);
}

export function EApprovalFinanceTourOverviewFixtures({ variant }: { variant: "awaiting" | "attention" }) {
  const searchParams = useSearchParams();
  const sample = eApprovalFinanceTourSample("cash_advance");
  const href = `${financeTourDetailPath("cash_advance")}${financeTourQuerySuffix(new URLSearchParams(searchParams.toString()))}`;

  if (variant === "attention") {
    return (
      <ul className="divide-y divide-border rounded-lg border border-dashed border-border">
        <li>
          <Link
            href={href}
            className="flex items-start justify-between gap-3 px-3 py-2.5 transition-colors hover:bg-muted/30"
            onClick={(event) => event.preventDefault()}
          >
            <div className="min-w-0 space-y-0.5">
              <p className="truncate text-sm font-medium text-foreground">
                ATC-CA-D-0017
                <span className="ml-2 font-normal text-muted-foreground">Cash advance</span>
              </p>
              <p className="text-xs text-muted-foreground">
                Draft · {eApprovalFinanceTourRequestor.name} · Forecast lines still open
              </p>
            </div>
          </Link>
        </li>
      </ul>
    );
  }

  return (
    <ul className="divide-y divide-border rounded-lg border border-dashed border-border">
      <li>
        <Link
          href={href}
          className="flex items-start justify-between gap-3 px-3 py-2.5 transition-colors hover:bg-muted/30"
          onClick={(event) => event.preventDefault()}
        >
          <div className="min-w-0 space-y-0.5">
            <p className="truncate text-sm font-medium text-foreground">
              {sample.documentNo}
              <span className="ml-2 font-normal text-muted-foreground">{sample.formName}</span>
            </p>
            <p className="text-xs text-muted-foreground">
              Pending · Department Head · {eApprovalFinanceTourRequestor.name}
            </p>
          </div>
        </Link>
      </li>
    </ul>
  );
}

export function EApprovalFinanceTourSubmissionFixtures() {
  const searchParams = useSearchParams();
  const activeFamily = useEApprovalFinanceTourFamily();
  const qs = financeTourQuerySuffix(new URLSearchParams(searchParams.toString()));

  return (
    <div className="space-y-3">
      <SampleNotice />
      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        {eApprovalFinanceTourSampleList.map((sample) => {
          const highlighted = sample.family === activeFamily;
          const detailPath = financeTourDetailPath(sample.family);
          const href = `${detailPath}${qs}`;
          return (
            <article
              key={sample.listRow.id}
              className="group flex h-full flex-col rounded-xl border border-dashed border-border bg-card shadow-sm"
            >
              <Link href={href} className="flex flex-1 flex-col p-4 outline-none focus-visible:ring-2 focus-visible:ring-ring">
                <div className="flex items-start justify-between gap-3">
                  <div className="flex min-w-0 items-start gap-3">
                    <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                      <FileStack className="h-5 w-5" aria-hidden />
                    </div>
                    <div className="min-w-0">
                      <p className="font-mono text-sm font-medium text-foreground">{sample.documentNo}</p>
                      <p className="mt-0.5 line-clamp-1 text-sm text-muted-foreground">{sample.formName}</p>
                      <p className="mt-0.5 text-xs text-muted-foreground">{sample.subsidiary}</p>
                    </div>
                  </div>
                  <span data-help={highlighted ? "ea-submissions-status" : undefined}>
                    <EApprovalStatusBadge status={sample.listRow.status} kind="submission" />
                  </span>
                </div>
                <div className="mt-4 space-y-2">
                  <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground">
                    <span className="inline-flex items-center gap-1">
                      <User className="h-3.5 w-3.5" aria-hidden />
                      {eApprovalFinanceTourRequestor.name}
                    </span>
                  </div>
                  <div data-help={highlighted ? "ea-submissions-step-show" : undefined}>
                    <EApprovalWorkflowStepShow
                      variant="compact"
                      steps={buildWorkflowStepShowItems({
                        currentStep: sample.listRow.current_step,
                        stepCount: sample.listRow.step_count,
                        status: sample.listRow.status,
                        workflowSteps: sample.listRow.workflow_steps,
                      })}
                    />
                  </div>
                </div>
              </Link>
              <div
                data-help={highlighted ? "ea-submissions-card-actions" : undefined}
                data-tour-nav={highlighted ? detailPath : undefined}
                className="flex flex-wrap items-center gap-x-3 gap-y-1 border-t border-border bg-muted/20 px-4 py-2.5"
              >
                <Link href={href} className="text-sm font-medium text-primary hover:underline">
                  Open submission
                </Link>
              </div>
            </article>
          );
        })}
      </div>
    </div>
  );
}

export function EApprovalFinanceTourSubmissionTableFixtures() {
  const searchParams = useSearchParams();
  const activeFamily = useEApprovalFinanceTourFamily();
  const qs = financeTourQuerySuffix(new URLSearchParams(searchParams.toString()));

  return (
    <div className="space-y-3 p-4">
      <SampleNotice />
      <div className="overflow-x-auto rounded-lg border border-dashed border-border">
        <table className="w-full min-w-[640px] border-collapse text-left text-sm">
          <thead className="border-b border-border bg-muted/40 text-xs font-medium text-muted-foreground">
            <tr>
              <th scope="col" className="px-3 py-2">Document</th>
              <th scope="col" className="px-3 py-2">Form</th>
              <th scope="col" className="px-3 py-2">Status</th>
              <th scope="col" className="px-3 py-2">Step</th>
              <th scope="col" className="px-3 py-2">Open</th>
            </tr>
          </thead>
          <tbody>
            {eApprovalFinanceTourSampleList.map((sample) => {
              const highlighted = sample.family === activeFamily;
              const detailPath = financeTourDetailPath(sample.family);
              return (
                <tr key={sample.listRow.id} className="border-b border-border last:border-0">
                  <td className="px-3 py-2.5 font-mono text-foreground">{sample.documentNo}</td>
                  <td className="px-3 py-2.5 text-muted-foreground">{sample.formName}</td>
                  <td className="px-3 py-2.5">
                    <span data-help={highlighted ? "ea-submissions-status" : undefined}>
                      <EApprovalStatusBadge status={sample.listRow.status} kind="submission" />
                    </span>
                  </td>
                  <td className="px-3 py-2.5" data-help={highlighted ? "ea-submissions-step-show" : undefined}>
                    <EApprovalWorkflowStepShow
                      variant="compact"
                      steps={buildWorkflowStepShowItems({
                        currentStep: sample.listRow.current_step,
                        stepCount: sample.listRow.step_count,
                        status: sample.listRow.status,
                        workflowSteps: sample.listRow.workflow_steps,
                      })}
                    />
                  </td>
                  <td className="px-3 py-2.5">
                    <Link
                      href={`${detailPath}${qs}`}
                      data-help={highlighted ? "ea-submissions-card-actions" : undefined}
                      data-tour-nav={highlighted ? detailPath : undefined}
                      className="text-sm font-medium text-primary hover:underline"
                    >
                      View
                    </Link>
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

export function EApprovalFinanceTourPickerFixtures({
  onlyFamily,
}: {
  onlyFamily?: EApprovalFinanceTourFamily;
} = {}) {
  const searchParams = useSearchParams();
  const activeFamily = useEApprovalFinanceTourFamily();
  const qs = financeTourQuerySuffix(new URLSearchParams(searchParams.toString()));
  const samples = onlyFamily
    ? eApprovalFinanceTourSampleList.filter((sample) => sample.family === onlyFamily)
    : eApprovalFinanceTourSampleList;

  return (
    <div className="space-y-3">
      {onlyFamily ? null : <SampleNotice />}
      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        {samples.map((sample) => {
          const highlighted = sample.family === activeFamily;
          const composePath = financeTourComposePath(sample.family);
          return (
            <article
              key={sample.family}
              data-help={highlighted ? "ea-picker-form-card" : undefined}
              className="group flex h-full flex-col rounded-xl border border-dashed border-border bg-card shadow-sm"
            >
              <div className="flex flex-1 flex-col p-4">
                <div className="flex items-start gap-3">
                  <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                    <FileText className="h-5 w-5" aria-hidden />
                  </div>
                  <div className="min-w-0 flex-1">
                    <h3 className="text-base font-medium leading-snug text-foreground">{sample.formName}</h3>
                    <p className="mt-1 text-xs text-muted-foreground">{sample.chainSummary}</p>
                  </div>
                </div>
                <p className="mt-3 text-sm text-muted-foreground">{sample.pickerBlurb}</p>
              </div>
              <div className="space-y-2 border-t border-border bg-muted/20 px-4 py-3">
                <Button
                  type="button"
                  className="w-full gap-1.5"
                  data-help={highlighted ? "ea-picker-start" : undefined}
                  data-tour-nav={highlighted ? composePath : undefined}
                  render={<Link href={`${composePath}${qs}`} />}
                >
                  Start request
                  <ArrowRight className="h-4 w-4" />
                </Button>
              </div>
            </article>
          );
        })}
      </div>
    </div>
  );
}

export function EApprovalFinanceTourApprovalFixtures() {
  const searchParams = useSearchParams();
  const sample = eApprovalFinanceTourSample("cash_advance");
  const href = `${financeTourDetailPath("cash_advance")}${financeTourQuerySuffix(new URLSearchParams(searchParams.toString()))}`;

  return (
    <div className="space-y-3">
      <SampleNotice />
      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <article className="flex h-full flex-col rounded-xl border border-dashed border-border bg-card p-4 shadow-sm">
          <div className="flex items-start gap-3">
            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
              <Inbox className="h-5 w-5" aria-hidden />
            </div>
            <div className="min-w-0">
              <p className="font-mono text-sm font-medium">{sample.documentNo}</p>
              <p className="mt-0.5 text-sm text-muted-foreground">{sample.formName}</p>
              <p className="mt-2 text-xs text-muted-foreground">
                Awaiting you · Department Head · {eApprovalFinanceTourRequestor.name}
              </p>
            </div>
          </div>
          <Link href={href} className="mt-4 text-sm font-medium text-primary hover:underline">
            Open to decide
          </Link>
        </article>
      </div>
    </div>
  );
}

export function EApprovalFinanceTourCompleteAnchor() {
  const searchParams = useSearchParams();
  const canApprove = usePermission([permissions.eApprovalApprove]);
  const canCreate = usePermission([permissions.eApprovalSubmissionsCreate]);
  const tour = resolveLiveTour(E_APPROVAL_FINANCE_TOUR_ID, { canApprove, canCreate });
  const index = Number.parseInt(searchParams.get("tourStep") ?? "", 10);
  const step = tour?.steps[Number.isFinite(index) ? index : -1];
  if (step?.id !== "tour-complete") return null;

  return (
    <aside
      data-help="ea-tour-complete"
      className="rounded-xl border border-border bg-card p-4 shadow-sm"
      aria-label="Tour complete"
    >
      <p className="text-sm font-medium text-foreground">Finance tour finished</p>
      <p className="mt-1 text-xs text-muted-foreground">
        Cash advance, liquidation, and reimbursement are covered. Click Done to close — sample UI goes away.
      </p>
    </aside>
  );
}
