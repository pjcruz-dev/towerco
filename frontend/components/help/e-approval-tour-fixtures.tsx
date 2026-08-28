"use client";

import Link from "next/link";
import { useSearchParams } from "next/navigation";
import { ArrowRight, FileStack, FileText, Inbox, User } from "lucide-react";

import { EApprovalStatusBadge } from "@/components/e-approval/e-approval-status-badge";
import { Button } from "@/components/ui/button";
import {
  E_APPROVAL_TOUR_SAMPLE_COMPOSE_PATH,
  E_APPROVAL_TOUR_SAMPLE_DETAIL_PATH,
  isEApprovalTourActive,
} from "@/lib/help/e-approval-tour-fixtures";
import {
  E_APPROVAL_TOUR_SAMPLE_DOCUMENT_NO,
  E_APPROVAL_TOUR_SAMPLE_FORM_NAME,
  eApprovalTourSampleListRows,
  eApprovalTourSampleRequestor,
} from "@/lib/help/e-approval-tour-sample-data";
import { buildTourSearchParams } from "@/lib/help/e-approval-live-tour";

function tourQuerySuffix(searchParams: URLSearchParams, stepHint?: number): string {
  const step = stepHint ?? Number.parseInt(searchParams.get("tourStep") ?? "0", 10);
  const params = buildTourSearchParams("e-approval", Number.isFinite(step) ? step : 0, {
    id: "_",
    path: "/",
    target: "_",
    title: "",
    body: "",
  }, searchParams);
  const qs = params.toString();
  return qs ? `?${qs}` : "";
}

export function EApprovalTourSampleNotice() {
  return (
    <p className="rounded-lg border border-dashed border-sky-300 bg-sky-50/80 px-3 py-2 text-xs text-sky-950 dark:border-sky-800 dark:bg-sky-950/40 dark:text-sky-100">
      Sample UI for this tour only — nothing is saved. After you finish or skip, lists return to empty.
    </p>
  );
}

/** Overview queue rows while the live tour is active (ephemeral). */
export function EApprovalTourOverviewQueueFixtures({ variant }: { variant: "awaiting" | "attention" }) {
  const searchParams = useSearchParams();
  if (!isEApprovalTourActive(searchParams)) {
    return null;
  }

  const href = `${E_APPROVAL_TOUR_SAMPLE_DETAIL_PATH}${tourQuerySuffix(new URLSearchParams(searchParams.toString()))}`;

  if (variant === "awaiting") {
    return (
      <ul className="divide-y divide-border rounded-lg border border-border border-dashed border-sky-300/80 dark:border-sky-800">
        <li>
          <Link
            href={href}
            className="flex items-start justify-between gap-3 px-3 py-2.5 transition-colors hover:bg-muted/30"
            onClick={(event) => event.preventDefault()}
          >
            <div className="min-w-0 space-y-0.5">
              <p className="truncate text-sm font-medium text-foreground">
                {E_APPROVAL_TOUR_SAMPLE_DOCUMENT_NO}
                <span className="ml-2 font-normal text-muted-foreground">{E_APPROVAL_TOUR_SAMPLE_FORM_NAME}</span>
              </p>
              <p className="text-xs text-muted-foreground">
                Pending · Step 1 · {eApprovalTourSampleRequestor.name}
              </p>
            </div>
          </Link>
        </li>
      </ul>
    );
  }

  return (
    <ul className="divide-y divide-border rounded-lg border border-border border-dashed border-sky-300/80 dark:border-sky-800">
      <li>
        <Link
          href={href}
          className="flex items-start justify-between gap-3 px-3 py-2.5 transition-colors hover:bg-muted/30"
          onClick={(event) => event.preventDefault()}
        >
          <div className="min-w-0 space-y-0.5">
            <p className="truncate text-sm font-medium text-foreground">
              DA-DRAFT-0092
              <span className="ml-2 font-normal text-muted-foreground">{E_APPROVAL_TOUR_SAMPLE_FORM_NAME}</span>
            </p>
            <p className="text-xs text-muted-foreground">
              Draft · {eApprovalTourSampleRequestor.name} · Needs your attention
            </p>
          </div>
        </Link>
      </li>
    </ul>
  );
}

/** Gallery cards for an empty Submissions list during the tour. */
export function EApprovalTourSubmissionFixtures() {
  const searchParams = useSearchParams();
  if (!isEApprovalTourActive(searchParams)) {
    return null;
  }

  const qs = tourQuerySuffix(new URLSearchParams(searchParams.toString()));
  const openHref = `${E_APPROVAL_TOUR_SAMPLE_DETAIL_PATH}${qs}`;

  return (
    <div className="space-y-3">
      <EApprovalTourSampleNotice />
      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        {eApprovalTourSampleListRows.map((row, index) => {
          const href = `${E_APPROVAL_TOUR_SAMPLE_DETAIL_PATH}${qs}`;
          const stepLabel =
            row.current_step > 0 ? `Step ${row.current_step} · Approver ${row.current_step}` : "Draft";
          return (
            <article
              key={row.id}
              className="group flex h-full flex-col rounded-xl border border-dashed border-sky-300/80 bg-card shadow-sm dark:border-sky-800"
            >
              <Link
                href={href}
                className="flex flex-1 flex-col p-4 outline-none focus-visible:ring-2 focus-visible:ring-ring"
              >
                <div className="flex items-start justify-between gap-3">
                  <div className="flex min-w-0 items-start gap-3">
                    <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                      <FileStack className="h-5 w-5" aria-hidden />
                    </div>
                    <div className="min-w-0">
                      <p className="font-mono text-sm font-medium text-foreground">{row.document_no}</p>
                      <p className="mt-0.5 line-clamp-1 text-sm text-muted-foreground">
                        {row.form_name ?? E_APPROVAL_TOUR_SAMPLE_FORM_NAME}
                      </p>
                    </div>
                  </div>
                  <span data-help={index === 0 ? "ea-submissions-status" : undefined}>
                    <EApprovalStatusBadge status={row.status} kind="submission" />
                  </span>
                </div>
                <div className="mt-4 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground">
                  <span className="inline-flex items-center gap-1">
                    <User className="h-3.5 w-3.5" aria-hidden />
                    {row.requestor?.name ?? eApprovalTourSampleRequestor.name}
                  </span>
                  <span>{stepLabel}</span>
                </div>
              </Link>
              <div
                data-help={index === 0 ? "ea-submissions-card-actions" : undefined}
                data-tour-nav={index === 0 ? E_APPROVAL_TOUR_SAMPLE_DETAIL_PATH : undefined}
                className="flex flex-wrap items-center gap-x-3 gap-y-1 border-t border-border bg-muted/20 px-4 py-2.5"
              >
                <Link href={openHref} className="text-sm font-medium text-primary hover:underline">
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

/** Table rows for an empty Submissions list during the tour (same Document Approval data as gallery). */
export function EApprovalTourSubmissionTableFixtures() {
  const searchParams = useSearchParams();
  if (!isEApprovalTourActive(searchParams)) {
    return null;
  }

  const qs = tourQuerySuffix(new URLSearchParams(searchParams.toString()));
  const openHref = `${E_APPROVAL_TOUR_SAMPLE_DETAIL_PATH}${qs}`;

  return (
    <div className="space-y-3 p-4">
      <EApprovalTourSampleNotice />
      <div className="overflow-x-auto rounded-lg border border-dashed border-sky-300/80 dark:border-sky-800">
        <table className="w-full min-w-[640px] border-collapse text-left text-sm">
          <thead className="border-b border-border bg-muted/40 text-xs font-medium text-muted-foreground">
            <tr>
              <th className="px-3 py-2.5">Document</th>
              <th className="px-3 py-2.5">Form</th>
              <th className="px-3 py-2.5">Status</th>
              <th className="px-3 py-2.5">Requestor</th>
              <th className="px-3 py-2.5">Step</th>
              <th className="px-3 py-2.5">Open</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-border">
            {eApprovalTourSampleListRows.map((row, index) => (
              <tr key={row.id} className="bg-card hover:bg-muted/20">
                <td className="px-3 py-2.5">
                  <Link
                    href={openHref}
                    className="font-mono text-xs font-medium text-foreground hover:text-primary"
                  >
                    {row.document_no}
                  </Link>
                </td>
                <td className="px-3 py-2.5 text-foreground">
                  {row.form_name ?? E_APPROVAL_TOUR_SAMPLE_FORM_NAME}
                </td>
                <td className="px-3 py-2.5">
                  <span data-help={index === 0 ? "ea-submissions-status" : undefined}>
                    <EApprovalStatusBadge status={row.status} kind="submission" />
                  </span>
                </td>
                <td className="px-3 py-2.5 text-foreground">
                  {row.requestor?.name ?? eApprovalTourSampleRequestor.name}
                </td>
                <td className="px-3 py-2.5 text-muted-foreground">{row.current_step}</td>
                <td className="px-3 py-2.5">
                  <Link
                    href={openHref}
                    data-help={index === 0 ? "ea-submissions-card-actions" : undefined}
                    data-tour-nav={index === 0 ? E_APPROVAL_TOUR_SAMPLE_DETAIL_PATH : undefined}
                    className="text-sm font-medium text-primary hover:underline"
                  >
                    View
                  </Link>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}

/** Published-form picker card when the tenant has no forms (tour only). */
export function EApprovalTourPickerFixtures() {
  const searchParams = useSearchParams();
  if (!isEApprovalTourActive(searchParams)) {
    return null;
  }

  const composeHref = `${E_APPROVAL_TOUR_SAMPLE_COMPOSE_PATH}${tourQuerySuffix(new URLSearchParams(searchParams.toString()))}`;

  return (
    <div className="space-y-3">
      <EApprovalTourSampleNotice />
      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <article
          data-help="ea-picker-form-card"
          className="group flex h-full flex-col rounded-xl border border-dashed border-sky-300/80 bg-card shadow-sm dark:border-sky-800"
        >
          <div className="flex flex-1 flex-col p-4">
            <div className="flex items-start gap-3">
              <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                <FileText className="h-5 w-5" aria-hidden />
              </div>
              <div className="min-w-0 flex-1">
                <h3 className="text-base font-medium leading-snug text-foreground">{E_APPROVAL_TOUR_SAMPLE_FORM_NAME}</h3>
                <p className="mt-1 text-xs text-muted-foreground">Title · Approver 1–3 · Attachments</p>
              </div>
            </div>
            <p className="mt-3 text-sm text-muted-foreground">
              Multi-level document approval with title, three approvers, and PDF attachments.
            </p>
          </div>
          <div className="space-y-2 border-t border-border bg-muted/20 px-4 py-3">
            <Button
              type="button"
              className="w-full gap-1.5"
              data-help="ea-picker-start"
              data-tour-nav={composeHref}
              render={<Link href={composeHref} />}
            >
              Start request
              <ArrowRight className="h-4 w-4" />
            </Button>
          </div>
        </article>
      </div>
    </div>
  );
}

/** Approvals inbox card when the queue is empty (tour only). */
export function EApprovalTourApprovalFixtures() {
  const searchParams = useSearchParams();
  if (!isEApprovalTourActive(searchParams)) {
    return null;
  }

  const href = `${E_APPROVAL_TOUR_SAMPLE_DETAIL_PATH}${tourQuerySuffix(new URLSearchParams(searchParams.toString()))}`;

  return (
    <div className="space-y-3">
      <EApprovalTourSampleNotice />
      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <article className="flex h-full flex-col rounded-xl border border-dashed border-sky-300/80 bg-card p-4 shadow-sm dark:border-sky-800">
          <div className="flex items-start gap-3">
            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
              <Inbox className="h-5 w-5" aria-hidden />
            </div>
            <div className="min-w-0">
              <p className="font-mono text-sm font-medium">{E_APPROVAL_TOUR_SAMPLE_DOCUMENT_NO}</p>
              <p className="mt-0.5 text-sm text-muted-foreground">{E_APPROVAL_TOUR_SAMPLE_FORM_NAME}</p>
              <p className="mt-2 text-xs text-muted-foreground">
                Awaiting you · Step 1 · Approver 1 · {eApprovalTourSampleRequestor.name}
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

/** Final tour step anchor on Overview (ephemeral while tour is active). */
export function EApprovalTourCompleteAnchor() {
  const searchParams = useSearchParams();
  if (!isEApprovalTourActive(searchParams)) {
    return null;
  }

  return (
    <aside
      data-help="ea-tour-complete"
      className="rounded-xl border border-emerald-200 bg-emerald-50/80 p-4 shadow-sm dark:border-emerald-900/50 dark:bg-emerald-950/30"
      aria-label="Tour complete"
    >
      <p className="text-sm font-medium text-foreground">E-Approval tour finished</p>
      <p className="mt-1 text-xs text-muted-foreground">
        You covered requestor and approver flows. Click Done to close — sample UI goes away and real empty
        states return.
      </p>
    </aside>
  );
}
