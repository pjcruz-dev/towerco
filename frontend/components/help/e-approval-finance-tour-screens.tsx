"use client";

import { Suspense, useCallback, useEffect, useMemo, useState } from "react";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { FileDown, FileText, GitBranch, MessageSquare, Paperclip, Zap } from "lucide-react";

import {
  AttachmentPreviewGallery,
  type AttachmentGalleryItem,
} from "@/components/attachments/attachment-preview-gallery";
import { EApprovalApprovalSignatureField, EApprovalSignatureConsentDialog } from "@/components/e-approval/e-approval-approval-signature-field";
import { EApprovalApprovalTrail } from "@/components/e-approval/e-approval-approval-trail";
import { EApprovalGenericFormPrintView } from "@/components/e-approval/e-approval-generic-form-print-view";
import { EApprovalSectionCard } from "@/components/e-approval/e-approval-section-card";
import { EApprovalStatusBadge } from "@/components/e-approval/e-approval-status-badge";
import { EApprovalWaitingOnPanel } from "@/components/e-approval/e-approval-waiting-on-panel";
import {
  EApprovalWorkflowStepShow,
  buildWorkflowStepShowItems,
} from "@/components/e-approval/e-approval-workflow-step-show";
import { EApprovalWorkflowPathDiagram } from "@/components/e-approval/e-approval-workflow-path-diagram";
import { EApprovalTourSampleAttachmentPages } from "@/components/help/e-approval-tour-sample-attachment-pages";
import { EApprovalTourSampleNotice } from "@/components/help/e-approval-tour-fixtures";
import { LiveProductTourHost } from "@/components/help/live-product-tour-host";
import { Badge } from "@/components/ui/badge";
import { Button, buttonVariants } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Textarea } from "@/components/ui/textarea";
import { usePermission } from "@/hooks/use-permission";
import { buildTourSearchParams } from "@/lib/help/e-approval-live-tour";
import {
  E_APPROVAL_FINANCE_TOUR_ID,
  financeTourDetailPath,
  financeTourPrintPath,
  isEApprovalFinanceTourActive,
} from "@/lib/help/e-approval-finance-tour";
import {
  eApprovalFinanceTourRequestor,
  eApprovalFinanceTourSample,
  formatTourSampleTimestamp,
  type EApprovalFinanceTourFamily,
  type FinanceTourMoneyRow,
  type FinanceTourSample,
} from "@/lib/help/e-approval-finance-tour-sample-data";
import { permissions } from "@/lib/rbac/permissions";
import { cn } from "@/lib/utils";

function isMoneyColumn(label: string): boolean {
  const normalized = label.trim().toLowerCase();
  return normalized === "vat" || normalized.endsWith("amount");
}

function LinesTable({
  sample,
  dataHelp,
}: {
  sample: FinanceTourSample;
  dataHelp: string;
}) {
  return (
    <div data-help={dataHelp} className="overflow-x-auto rounded-xl border border-border bg-card shadow-sm">
      <div className="border-b border-border px-4 py-3">
        <h2 className="text-base font-medium text-foreground">{sample.linesTitle}</h2>
      </div>
      <table className="w-full min-w-[720px] border-collapse text-left text-sm">
        <thead className="border-b border-border bg-muted/40 text-xs font-medium text-muted-foreground">
          <tr>
            {sample.columns.map((column) => (
              <th
                key={column}
                scope="col"
                className={cn("px-3 py-2", isMoneyColumn(column) && "text-right")}
              >
                {column}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {sample.rows.map((row, rowIndex) => (
            <tr key={`${sample.documentNo}-${rowIndex}`} className="border-b border-border last:border-0">
              {row.map((cell, cellIndex) => (
                <td
                  key={`${columnKey(sample.columns, cellIndex)}-${rowIndex}`}
                  className={cn(
                    "px-3 py-2 text-foreground",
                    isMoneyColumn(sample.columns[cellIndex] ?? "") && "text-right tabular-nums",
                  )}
                >
                  {cell}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

function columnKey(columns: string[], index: number): string {
  return columns[index] ?? String(index);
}

function TotalsGrid({ rows, dataHelp }: { rows: FinanceTourMoneyRow[]; dataHelp: string }) {
  return (
    <dl data-help={dataHelp} className="grid gap-3 rounded-xl border border-border bg-card p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-4">
      {rows.map((row) => (
        <div key={row.label}>
          <dt className="text-xs font-medium text-muted-foreground">{row.label}</dt>
          <dd className="mt-0.5 text-sm font-medium tabular-nums text-foreground">{row.value}</dd>
        </div>
      ))}
    </dl>
  );
}

function FileList({ sample, dataHelp }: { sample: FinanceTourSample; dataHelp?: string }) {
  return (
    <div
      data-help={dataHelp}
      className="space-y-3 rounded-xl border border-dashed border-border bg-card p-4 shadow-sm"
    >
      <div className="flex items-center gap-2 text-sm font-medium text-foreground">
        <Paperclip className="h-4 w-4 text-muted-foreground" aria-hidden />
        {sample.fileLabel}
      </div>
      <ul className="space-y-2">
        {sample.files.map((fileName) => (
          <li
            key={fileName}
            className="flex items-center gap-2 rounded-lg border border-border bg-muted/20 px-3 py-2 text-sm"
          >
            <FileText className="h-4 w-4 shrink-0 text-muted-foreground" aria-hidden />
            <span className="truncate text-foreground">{fileName}</span>
          </li>
        ))}
      </ul>
      <p className="text-xs text-muted-foreground">Sample file only — not uploaded.</p>
    </div>
  );
}

export function FinanceTourComposeScreen({ family }: { family: EApprovalFinanceTourFamily }) {
  const sample = eApprovalFinanceTourSample(family);

  return (
    <>
      <header className="space-y-1">
        <h1 className="text-[20px] font-semibold leading-7 tracking-tight text-foreground">{sample.formName}</h1>
        <p className="text-sm text-muted-foreground">{sample.composeLead}</p>
      </header>

      {sample.parent ? (
        <div data-help="ea-compose-parent" className="space-y-3 rounded-xl border border-border bg-card p-4 shadow-sm">
          <label className="block space-y-1.5 text-sm">
            <span className="font-medium text-foreground">Cash advance to liquidate</span>
            <Input disabled defaultValue={sample.parent.summary} />
          </label>
          <label className="block space-y-1.5 text-sm">
            <span className="font-medium text-foreground">Cash advance document no.</span>
            <Input disabled defaultValue={sample.parent.documentNo} />
          </label>
          <p className="text-xs text-muted-foreground">
            Open balance {sample.parent.openBalance}. Filled when you select a released cash advance.
          </p>
        </div>
      ) : null}

      <div data-help="ea-compose-fields" className="space-y-4 rounded-xl border border-border bg-card p-4 shadow-sm">
        <div className="grid gap-4 sm:grid-cols-2">
          {sample.facts.map((fact) => (
            <label key={fact.label} className="block space-y-1.5 text-sm">
              <span className="font-medium text-foreground">{fact.label}</span>
              <Input disabled defaultValue={fact.value} />
            </label>
          ))}
        </div>
        <p className="text-xs text-muted-foreground">Approval chain: {sample.chainSummary}</p>
      </div>

      <LinesTable sample={sample} dataHelp="ea-compose-lines" />

      {sample.settlement ? (
        <div data-help="ea-compose-settlement">
          <TotalsGrid rows={sample.settlement} dataHelp="ea-compose-settlement-figures" />
          <p className="mt-2 text-xs text-muted-foreground">
            Positive settlement pays the employee. Negative settlement is a surrender.
          </p>
        </div>
      ) : null}

      <TotalsGrid rows={sample.totals} dataHelp="ea-compose-totals" />

      <FileList sample={sample} dataHelp="ea-compose-upload" />

      <label className="block space-y-1.5 text-sm">
        <span className="font-medium text-foreground">Notes</span>
        <Textarea disabled rows={2} defaultValue={sample.notes} />
      </label>

      <div className="flex flex-wrap items-center justify-end gap-2 border-t border-border pt-4">
        <span data-help="ea-compose-save-draft" className="inline-flex">
          <Button type="button" size="sm" variant="outline" disabled>
            Save draft
          </Button>
        </span>
        <span data-help="ea-compose-submit" className="inline-flex">
          <Button type="button" size="sm" disabled>
            Submit request
          </Button>
        </span>
      </div>
    </>
  );
}

function FinanceTourDetailScreen({ family }: { family: EApprovalFinanceTourFamily }) {
  const sample = eApprovalFinanceTourSample(family);
  const router = useRouter();
  const searchParams = useSearchParams();
  const canApprove = usePermission([permissions.eApprovalApprove]);
  const canCreate = usePermission([permissions.eApprovalSubmissionsCreate]);
  const showDecideTab = canApprove || canCreate;
  const rawTab = searchParams.get("tab") ?? "request";
  const tab = rawTab === "decide" && !showDecideTab ? "request" : rawTab;
  const printPath = financeTourPrintPath(family);

  const [approvalSignature, setApprovalSignature] = useState<string | null>(null);
  const [signatureConsentOpen, setSignatureConsentOpen] = useState(false);
  const [approvalSignatureError, setApprovalSignatureError] = useState<string | null>(null);
  const [decisionRemarks, setDecisionRemarks] = useState("");

  const attachmentItems = useMemo((): AttachmentGalleryItem[] => {
    return sample.files.map((fileName, index) => ({
      id: `${sample.documentNo}-att-${index + 1}`,
      fileName,
      title: fileName,
      subtitle: sample.fileLabel,
    }));
  }, [sample.documentNo, sample.fileLabel, sample.files]);
  const noopFetchBlob = useCallback(async () => new Blob(), []);

  const printHref = `${printPath}?${buildTourSearchParams(
    E_APPROVAL_FINANCE_TOUR_ID,
    Number.parseInt(searchParams.get("tourStep") ?? "0", 10) || 0,
    { id: "_", path: "/", target: "_", title: "", body: "" },
    searchParams,
  ).toString()}`;

  const workflowPath = (
    <div data-help="ea-detail-workflow-path" className="rounded-xl border border-border bg-card p-4 shadow-sm">
      <div className="flex items-start gap-2">
        <GitBranch className="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground" aria-hidden />
        <div>
          <h2 className="text-base font-medium">Workflow path</h2>
          <p className="mt-1 text-xs text-muted-foreground">{sample.chainSummary}</p>
        </div>
      </div>
      <div className="mt-4">
        <EApprovalWorkflowPathDiagram preview={sample.workflowPreview} compactDetails />
      </div>
    </div>
  );

  return (
    <div className="space-y-4">
      <header data-help="ea-detail-header" className="flex flex-wrap items-start justify-between gap-4">
        <div className="min-w-0 space-y-1">
          <p className="font-mono text-sm text-muted-foreground">{sample.documentNo}</p>
          <h1 className="text-[20px] font-semibold leading-7 tracking-tight text-foreground">{sample.formName}</h1>
        </div>
        <EApprovalStatusBadge status="pending" kind="submission" />
      </header>

      <div data-help="ea-detail-summary" className="rounded-xl border border-border bg-card px-4 py-3 shadow-sm">
        <dl className="grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
          <div>
            <dt className="text-xs font-medium text-muted-foreground">Form</dt>
            <dd className="mt-0.5 font-medium">{sample.formName}</dd>
          </div>
          <div>
            <dt className="text-xs font-medium text-muted-foreground">Requestor</dt>
            <dd className="mt-0.5">{eApprovalFinanceTourRequestor.name}</dd>
          </div>
          <div>
            <dt className="text-xs font-medium text-muted-foreground">Subsidiary</dt>
            <dd className="mt-0.5">{sample.subsidiary}</dd>
          </div>
          <div>
            <dt className="text-xs font-medium text-muted-foreground">Submitted</dt>
            <dd className="mt-0.5">{formatTourSampleTimestamp(sample.submittedAt)}</dd>
          </div>
        </dl>
        <div className="mt-4 border-t border-border pt-3">
          <p className="mb-2 text-xs font-medium text-muted-foreground">Workflow step</p>
          <EApprovalWorkflowStepShow
            variant="compact"
            steps={buildWorkflowStepShowItems({
              currentStep: sample.listRow.current_step,
              stepCount: sample.listRow.step_count,
              status: "pending",
              workflowSteps: sample.listRow.workflow_steps,
            })}
          />
        </div>
        <div className="mt-3 flex flex-wrap gap-2 border-t border-border pt-3">
          <Link
            href={printHref}
            data-help="ea-detail-print"
            data-tour-nav={printPath}
            className={cn(buttonVariants({ size: "sm", variant: "outline" }), "gap-1.5")}
          >
            <FileDown className="h-3.5 w-3.5" />
            Print / PDF
          </Link>
        </div>
      </div>

      <Tabs
        value={tab}
        onValueChange={(value) => {
          const params = new URLSearchParams(searchParams.toString());
          params.set("tab", value);
          router.replace(`${financeTourDetailPath(family)}?${params.toString()}`);
        }}
        className="gap-4"
      >
        <TabsList data-help="ea-detail-tabs" variant="line" className="w-full justify-start gap-1 overflow-x-auto">
          <TabsTrigger value="request" className="gap-1.5">
            <FileText className="h-3.5 w-3.5" />
            Request
          </TabsTrigger>
          <TabsTrigger value="approvals" className="gap-1.5">
            <GitBranch className="h-3.5 w-3.5" />
            Approvals
          </TabsTrigger>
          <TabsTrigger value="activity" className="gap-1.5">
            <MessageSquare className="h-3.5 w-3.5" />
            Activity
            <Badge variant="outline" className="ml-1 h-5 min-w-5 px-1 text-[10px]">
              {sample.comments.length}
            </Badge>
          </TabsTrigger>
          {showDecideTab ? (
            <TabsTrigger value="decide" className="gap-1.5" data-help="ea-detail-decide-tab">
              <Zap className="h-3.5 w-3.5" />
              Decide
              {canApprove ? (
                <Badge variant="secondary" className="ml-1 h-5 min-w-5 px-1 text-[10px]">
                  1
                </Badge>
              ) : null}
            </TabsTrigger>
          ) : null}
        </TabsList>

        <TabsContent value="request" className="mt-4 space-y-4">
          <div data-help="ea-detail-fields" className="rounded-xl border border-border bg-card p-4 shadow-sm">
            <h2 className="text-base font-medium text-foreground">{sample.formName}</h2>
            <dl className="mt-4 grid gap-3 sm:grid-cols-2">
              {sample.facts.map((fact) => (
                <div key={fact.label}>
                  <dt className="text-xs font-medium text-muted-foreground">{fact.label}</dt>
                  <dd className="mt-1 text-sm text-foreground">{fact.value}</dd>
                </div>
              ))}
            </dl>
          </div>

          {sample.parent ? (
            <EApprovalSectionCard dataHelp="ea-detail-parent" title="Cash advance" description={sample.relatedBody}>
              <p className="font-mono text-sm text-foreground">{sample.parent.documentNo}</p>
              <p className="mt-1 text-sm text-muted-foreground">Open balance {sample.parent.openBalance}</p>
            </EApprovalSectionCard>
          ) : (
            <EApprovalSectionCard dataHelp="ea-detail-related" title={sample.relatedTitle} description={sample.relatedBody}>
              <p className="text-sm text-muted-foreground">
                Requestor email: <span className="text-foreground">{eApprovalFinanceTourRequestor.email}</span>
              </p>
            </EApprovalSectionCard>
          )}

          <LinesTable sample={sample} dataHelp="ea-detail-lines" />
          {sample.settlement ? <TotalsGrid rows={sample.settlement} dataHelp="ea-detail-settlement" /> : null}
          <TotalsGrid rows={sample.totals} dataHelp="ea-detail-totals" />

          <div data-help="ea-detail-attachments">
            <AttachmentPreviewGallery
              title={sample.fileLabel}
              hint="Click or drag on a PDF or image to mark a spot. Each person has their own color. Type @ to notify someone on this request."
              items={attachmentItems}
              fetchBlob={noopFetchBlob}
              onDownload={() => undefined}
              openPreviewDisabled
              openPreviewLabel="Open with approval footer"
            />
          </div>

          <p className="text-sm text-muted-foreground">{sample.notes}</p>
        </TabsContent>

        <TabsContent value="approvals" className="mt-4 space-y-4">
          {workflowPath}
          <EApprovalWaitingOnPanel
            approvals={sample.approvals}
            currentStep={1}
            submissionStatus="pending"
            viewerPendingApprovalId={canApprove ? sample.approvals[0]?.id ?? null : null}
          />
          <EApprovalApprovalTrail
            approvals={sample.approvals}
            currentStep={1}
            submissionStatus="pending"
            defaultOpen
          />
        </TabsContent>

        <TabsContent value="activity" className="mt-4 space-y-4">
          <EApprovalSectionCard title="Comments" description="Discussion on this request.">
            <ul className="space-y-3">
              {sample.comments.map((comment) => (
                <li key={comment.id} className="rounded-lg border border-border bg-muted/20 px-3 py-2.5 text-sm">
                  <div className="flex flex-wrap items-center justify-between gap-2">
                    <p className="font-medium text-foreground">{comment.user_name}</p>
                    <p className="text-xs text-muted-foreground">
                      {comment.created_at ? formatTourSampleTimestamp(comment.created_at) : null}
                    </p>
                  </div>
                  <p className="mt-1.5 text-muted-foreground">{comment.message}</p>
                </li>
              ))}
            </ul>
            <div data-help="ea-detail-comment" className="mt-4 flex flex-col gap-2 border-t border-border pt-4 sm:flex-row">
              <Input disabled className="flex-1" placeholder="Write a comment. Use @ to notify someone on this request." />
              <Button type="button" size="sm" disabled>
                Post
              </Button>
            </div>
          </EApprovalSectionCard>
        </TabsContent>

        {showDecideTab ? (
          <TabsContent value="decide" className="mt-4 space-y-4">
            {canCreate ? (
              <EApprovalSectionCard
                dataHelp="ea-detail-requestor-actions"
                title="Requestor actions"
                description="While pending, cancel the request or remind whoever is waiting. Sample only — actions do not save."
              >
                <div className="mt-3 space-y-2">
                  <div className="flex flex-wrap gap-2">
                    <span data-help="ea-detail-cancel" className="inline-flex">
                      <Button size="sm" variant="outline" type="button" disabled>
                        Cancel request
                      </Button>
                    </span>
                    <span data-help="ea-detail-follow-up" className="inline-flex">
                      <Button size="sm" variant="secondary" type="button" disabled>
                        Send follow-up to approver
                      </Button>
                    </span>
                  </div>
                  <p className="text-xs text-muted-foreground">
                    Follow-up emails and notifies everyone still waiting: pending approvers, or reviewers who have not acknowledged yet. A cooldown may apply.
                  </p>
                </div>
              </EApprovalSectionCard>
            ) : null}

            {canApprove ? (
              <EApprovalSectionCard
                dataHelp="ea-decide-panel"
                title="Your decision"
                description="Sign to approve. Remarks are optional for approve, required for reject or revision. A review step is Review instead. Sample only — actions do not save."
              >
                <div className="mt-4 space-y-4">
                  <EApprovalApprovalSignatureField
                    value={approvalSignature}
                    onChange={setApprovalSignature}
                    disabled={false}
                    error={approvalSignatureError}
                    onErrorChange={setApprovalSignatureError}
                  />
                  <div data-help="ea-decide-remarks" className="space-y-1.5 border-t border-border pt-4">
                    <Label htmlFor={`tour-finance-remarks-${family}`}>Remarks</Label>
                    <Textarea
                      id={`tour-finance-remarks-${family}`}
                      rows={3}
                      value={decisionRemarks}
                      onChange={(event) => setDecisionRemarks(event.target.value)}
                      placeholder="Optional for approve · required for reject or revision (min 5 characters)"
                    />
                  </div>
                  <div data-help="ea-decide-actions" className="mt-2 flex flex-wrap gap-2 border-t border-border pt-4">
                    <span data-help="ea-decide-approve" className="inline-flex">
                      <Button
                        type="button"
                        size="sm"
                        onClick={() => {
                          setApprovalSignatureError(null);
                          setSignatureConsentOpen(true);
                        }}
                      >
                        Approve
                      </Button>
                    </span>
                    <span data-help="ea-decide-reject" className="inline-flex">
                      <Button type="button" size="sm" variant="destructive" disabled>
                        Reject
                      </Button>
                    </span>
                    <span data-help="ea-decide-revision" className="inline-flex">
                      <Button type="button" size="sm" variant="outline" disabled>
                        Request revision
                      </Button>
                    </span>
                    <p className="w-full text-xs text-muted-foreground">
                      Sample only — actions do not save. When the current step is a review, this panel is
                      Review and does not approve or reject.
                    </p>
                  </div>
                  <EApprovalSignatureConsentDialog
                    open={signatureConsentOpen}
                    onOpenChange={setSignatureConsentOpen}
                    onConfirm={() => {
                      setSignatureConsentOpen(false);
                      setApprovalSignatureError("Sample only — Approve does not save.");
                    }}
                  />
                </div>
              </EApprovalSectionCard>
            ) : null}
          </TabsContent>
        ) : null}
      </Tabs>
    </div>
  );
}

function FinanceTourPrintScreen({ family }: { family: EApprovalFinanceTourFamily }) {
  const sample = eApprovalFinanceTourSample(family);
  const searchParams = useSearchParams();
  const detailPath = financeTourDetailPath(family);
  const backParams = buildTourSearchParams(
    E_APPROVAL_FINANCE_TOUR_ID,
    Number.parseInt(searchParams.get("tourStep") ?? "0", 10) || 0,
    { id: "_", path: "/", target: "_", title: "", body: "" },
    searchParams,
  );

  return (
    <div className="space-y-0">
      <div className="space-y-3 border-b border-border bg-background px-1 pb-3 print:hidden">
        <EApprovalTourSampleNotice />
        <div
          data-help="ea-print-sample"
          className="rounded-xl border border-border bg-card px-4 py-3 shadow-sm"
        >
          <p className="text-sm font-medium text-foreground">Printed copy</p>
          <p className="mt-1 text-xs text-muted-foreground">
            {sample.formName} sample in the live finance layout. Scroll the lines — the tour stays open. Nothing is saved.
          </p>
          <div className="mt-3 flex flex-wrap items-center justify-between gap-2">
            <Link href={`${detailPath}?${backParams.toString()}`} className="text-sm text-primary hover:underline">
              ← Back to submission
            </Link>
            <Button type="button" size="sm" variant="outline" disabled>
              Print / Save as PDF
            </Button>
          </div>
        </div>
      </div>
      <EApprovalGenericFormPrintView data={sample.print} fieldsDataHelp="ea-print-doc-fields" />
      <div data-help="ea-print-attachments" className="print:break-before-page">
        <EApprovalTourSampleAttachmentPages data={sample.print} />
      </div>
    </div>
  );
}

function TourGate({
  exitPath,
  children,
}: {
  exitPath: string;
  children: React.ReactNode;
}) {
  const router = useRouter();
  const searchParams = useSearchParams();
  const tourActive = isEApprovalFinanceTourActive(searchParams);

  useEffect(() => {
    if (!tourActive) router.replace(exitPath);
  }, [exitPath, router, tourActive]);

  if (!tourActive) {
    return <p className="text-sm text-muted-foreground">Redirecting…</p>;
  }

  return (
    <>
      <LiveProductTourHost />
      {children}
    </>
  );
}

export function EApprovalFinanceTourComposePageClient({ family }: { family: EApprovalFinanceTourFamily }) {
  return (
    <Suspense fallback={null}>
      <TourGate exitPath="/e-approval/submissions/new">
        <div className="mx-auto w-full max-w-6xl space-y-4">
          <EApprovalTourSampleNotice />
          <FinanceTourComposeScreen family={family} />
        </div>
      </TourGate>
    </Suspense>
  );
}

export function EApprovalFinanceTourDetailPageClient({ family }: { family: EApprovalFinanceTourFamily }) {
  return (
    <Suspense fallback={null}>
      <TourGate exitPath="/e-approval/submissions">
        <div className="space-y-4">
          <EApprovalTourSampleNotice />
          <FinanceTourDetailScreen family={family} />
        </div>
      </TourGate>
    </Suspense>
  );
}

export function EApprovalFinanceTourPrintPageClient({ family }: { family: EApprovalFinanceTourFamily }) {
  return (
    <Suspense fallback={null}>
      <TourGate exitPath="/e-approval/submissions">
        <FinanceTourPrintScreen family={family} />
      </TourGate>
    </Suspense>
  );
}
