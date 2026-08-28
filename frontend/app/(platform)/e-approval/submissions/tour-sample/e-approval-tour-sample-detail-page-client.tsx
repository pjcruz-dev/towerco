"use client";

import { Suspense, useEffect, useMemo, useState } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import Link from "next/link";
import {
  FileDown,
  FileText,
  GitBranch,
  ImageIcon,
  MessageSquare,
  Paperclip,
  Zap,
} from "lucide-react";

import {
  EApprovalApprovalSignatureField,
} from "@/components/e-approval/e-approval-approval-signature-field";
import { EApprovalApprovalTrail } from "@/components/e-approval/e-approval-approval-trail";
import { EApprovalSectionCard } from "@/components/e-approval/e-approval-section-card";
import { EApprovalStatusBadge } from "@/components/e-approval/e-approval-status-badge";
import { EApprovalWaitingOnPanel } from "@/components/e-approval/e-approval-waiting-on-panel";
import { EApprovalWorkflowPathDiagram } from "@/components/e-approval/e-approval-workflow-path-diagram";
import { EApprovalTourSampleNotice } from "@/components/help/e-approval-tour-fixtures";
import { LiveProductTourHost } from "@/components/help/live-product-tour-host";
import { Badge } from "@/components/ui/badge";
import { Button, buttonVariants } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Textarea } from "@/components/ui/textarea";
import { usePermission } from "@/hooks/use-permission";
import {
  E_APPROVAL_TOUR_SAMPLE_PRINT_PATH,
  isEApprovalTourActive,
} from "@/lib/help/e-approval-tour-fixtures";
import {
  E_APPROVAL_TOUR_SAMPLE_DOCUMENT_NO,
  E_APPROVAL_TOUR_SAMPLE_FORM_NAME,
  eApprovalTourSampleApprovals,
  eApprovalTourSampleAttachments,
  eApprovalTourSampleComments,
  eApprovalTourSampleRequestor,
  eApprovalTourSampleSubmittedAt,
  eApprovalTourSampleValues,
  eApprovalTourSampleWorkflowPreview,
  formatTourSampleTimestamp,
} from "@/lib/help/e-approval-tour-sample-data";
import { buildTourSearchParams } from "@/lib/help/e-approval-live-tour";
import { permissions } from "@/lib/rbac/permissions";
import { cn } from "@/lib/utils";

function TourSampleDocumentApprovalFields() {
  const title = eApprovalTourSampleValues.find((row) => row.field_name === "title");
  const approvers = eApprovalTourSampleValues.filter((row) =>
    ["approver_1", "approver_2", "approver_3"].includes(row.field_name ?? ""),
  );

  return (
    <dl className="mt-4 grid gap-3 md:grid-cols-2">
      <div className="md:col-span-2">
        <dt className="text-xs font-medium text-muted-foreground">Title</dt>
        <dd className="mt-1 text-sm text-foreground">{title?.display_value ?? title?.value ?? "—"}</dd>
      </div>
      {approvers.map((row) => (
        <div key={row.field_id}>
          <dt className="text-xs font-medium text-muted-foreground">{row.label}</dt>
          <dd className="mt-1 text-sm text-foreground">{row.display_value ?? "—"}</dd>
          {row.display_subtitle ? (
            <p className="mt-0.5 text-xs text-muted-foreground">{row.display_subtitle}</p>
          ) : null}
        </div>
      ))}
    </dl>
  );
}

function TourSampleAttachmentsPanel() {
  return (
    <div data-help="ea-detail-attachments" className="mt-6 border-t border-border pt-4">
      <div className="mb-3 flex items-center gap-2">
        <Paperclip className="h-4 w-4 text-muted-foreground" aria-hidden />
        <h3 className="text-sm font-medium text-foreground">Attachments</h3>
        <Badge variant="outline" className="h-5 min-w-5 px-1.5 text-[10px]">
          {eApprovalTourSampleAttachments.length}
        </Badge>
      </div>
      <ul className="grid gap-2 sm:grid-cols-2">
        {eApprovalTourSampleAttachments.map((file) => {
          const isImage = /\.(png|jpe?g|gif|webp)$/i.test(file.file_name);
          return (
            <li
              key={file.id}
              className="flex items-start gap-3 rounded-lg border border-border bg-muted/20 px-3 py-2.5"
            >
              <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-background text-muted-foreground">
                {isImage ? (
                  <ImageIcon className="h-4 w-4" aria-hidden />
                ) : (
                  <FileText className="h-4 w-4" aria-hidden />
                )}
              </div>
              <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-medium text-foreground">{file.file_name}</p>
                <p className="mt-0.5 text-xs text-muted-foreground">
                  {file.metadata?.caption ?? "Supporting documents"}
                  {file.metadata?.captured_at
                    ? ` · ${formatTourSampleTimestamp(file.metadata.captured_at)}`
                    : null}
                </p>
                <Button type="button" size="sm" variant="ghost" className="mt-1 h-7 px-2 text-xs" disabled>
                  Open preview
                </Button>
              </div>
            </li>
          );
        })}
      </ul>
    </div>
  );
}

function TourSampleDetailInner() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const tourActive = isEApprovalTourActive(searchParams);
  const canApprove = usePermission([permissions.eApprovalApprove]);
  const canCreate = usePermission([permissions.eApprovalSubmissionsCreate]);
  const showDecideTab = canApprove || canCreate;
  const rawTab = searchParams.get("tab") ?? "request";
  const tab = rawTab === "decide" && !showDecideTab ? "request" : rawTab;

  const [approvalSignature, setApprovalSignature] = useState<string | null>(null);
  const [signatureConsentAccepted, setSignatureConsentAccepted] = useState(false);
  const [approvalSignatureError, setApprovalSignatureError] = useState<string | null>(null);
  const [decisionRemarks, setDecisionRemarks] = useState("");

  useEffect(() => {
    if (!tourActive) {
      router.replace("/e-approval/submissions");
    }
  }, [router, tourActive]);

  const commentsBadgeCount = eApprovalTourSampleComments.length;

  const workflowPath = useMemo(
    () => (
      <div data-help="ea-detail-workflow-path" className="rounded-xl border border-border bg-card p-4 shadow-sm">
        <div className="flex flex-wrap items-start justify-between gap-2">
          <div className="flex items-start gap-2">
            <GitBranch className="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground" aria-hidden />
            <div>
              <h2 className="text-base font-medium">Workflow path</h2>
              <p className="mt-1 text-xs text-muted-foreground">
                Who runs for this request, what is parallel, and where the approval stands. Hover an
                approver name to view their signature.
              </p>
            </div>
          </div>
        </div>
        <div className="mt-4 space-y-3">
          <EApprovalWorkflowPathDiagram preview={eApprovalTourSampleWorkflowPreview} compactDetails />
        </div>
      </div>
    ),
    [],
  );

  if (!tourActive) {
    return <p className="text-sm text-muted-foreground">Redirecting…</p>;
  }

  return (
    <div className="space-y-4">
      <LiveProductTourHost />
      <EApprovalTourSampleNotice />

      <header data-help="ea-detail-header" className="flex flex-wrap items-start justify-between gap-4">
        <div className="min-w-0 space-y-1">
          <p className="font-mono text-sm text-muted-foreground">{E_APPROVAL_TOUR_SAMPLE_DOCUMENT_NO}</p>
          <h1 className="text-2xl font-semibold text-foreground">{E_APPROVAL_TOUR_SAMPLE_FORM_NAME}</h1>
        </div>
        <EApprovalStatusBadge status="pending" kind="submission" />
      </header>

      <div data-help="ea-detail-summary" className="rounded-xl border border-border bg-card px-4 py-3 shadow-sm">
        <dl className="grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
          <div>
            <dt className="text-xs font-medium text-muted-foreground">Form</dt>
            <dd className="mt-0.5 font-medium">{E_APPROVAL_TOUR_SAMPLE_FORM_NAME}</dd>
          </div>
          <div>
            <dt className="text-xs font-medium text-muted-foreground">Requestor</dt>
            <dd className="mt-0.5">{eApprovalTourSampleRequestor.name}</dd>
          </div>
          <div>
            <dt className="text-xs font-medium text-muted-foreground">Workflow step</dt>
            <dd className="mt-0.5">Step 1</dd>
          </div>
          <div>
            <dt className="text-xs font-medium text-muted-foreground">Submitted</dt>
            <dd className="mt-0.5">{formatTourSampleTimestamp(eApprovalTourSampleSubmittedAt)}</dd>
          </div>
          <div>
            <dt className="text-xs font-medium text-muted-foreground">Form version at submit</dt>
            <dd className="mt-0.5">v3</dd>
          </div>
        </dl>
        <div className="mt-3 flex flex-wrap gap-2 border-t border-border pt-3">
          <Link
            href={`${E_APPROVAL_TOUR_SAMPLE_PRINT_PATH}?${buildTourSearchParams(
              "e-approval",
              Number.parseInt(searchParams.get("tourStep") ?? "0", 10) || 0,
              {
                id: "_",
                path: "/",
                target: "_",
                title: "",
                body: "",
              },
              searchParams,
            ).toString()}`}
            data-help="ea-detail-print"
            data-tour-nav={E_APPROVAL_TOUR_SAMPLE_PRINT_PATH}
            className={cn(buttonVariants({ size: "sm", variant: "outline" }), "gap-1.5")}
          >
            <FileDown className="h-3.5 w-3.5" />
            Print / PDF
          </Link>
        </div>
      </div>

      <Tabs value={tab} className="gap-4">
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
              {commentsBadgeCount}
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
          <EApprovalSectionCard
            title="Document Approval fields"
            description="Title, Approver 1–3, and Attachments only — same as the live Document Approval form."
          >
            <TourSampleDocumentApprovalFields />
            <TourSampleAttachmentsPanel />
          </EApprovalSectionCard>

          <EApprovalSectionCard title="Related & links" description="Linked submissions, documents, and tickets.">
            <p className="mb-4 text-sm text-muted-foreground">
              Requestor email:{" "}
              <span className="text-foreground">{eApprovalTourSampleRequestor.email}</span>
            </p>
            <p className="text-sm text-muted-foreground">No related submissions.</p>
            <div className="mt-4 space-y-2 border-t border-border pt-4">
              <h3 className="text-sm font-medium">Linked documents</h3>
              <p className="text-sm text-muted-foreground">No linked documents.</p>
            </div>
          </EApprovalSectionCard>
        </TabsContent>

        <TabsContent value="approvals" className="mt-4 space-y-4">
          {workflowPath}
          <EApprovalWaitingOnPanel
            approvals={eApprovalTourSampleApprovals}
            currentStep={1}
            submissionStatus="pending"
            viewerPendingApprovalId={canApprove ? "tour-appr-1" : null}
          />
          <EApprovalApprovalTrail
            approvals={eApprovalTourSampleApprovals}
            currentStep={1}
            submissionStatus="pending"
            defaultOpen
          />
        </TabsContent>

        <TabsContent value="activity" className="mt-4 space-y-4">
          <EApprovalSectionCard title="Comments" description="Discussion on this request.">
            <ul className="space-y-3">
              {eApprovalTourSampleComments.map((comment) => (
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
              <Input disabled className="flex-1" placeholder="Write a comment…" />
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
                description="While pending, cancel the request or remind waiting approvers. Sample only — actions do not save."
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
                    Follow-up emails and notifies every pending approver on the current step
                    (including parallel peers). A cooldown may apply between reminders.
                  </p>
                </div>
              </EApprovalSectionCard>
            ) : null}

            {canApprove ? (
            <EApprovalSectionCard
              dataHelp="ea-decide-panel"
              title="Your decision"
              description="Sign to approve. Remarks are optional for approve, required for reject or revision. Sample only — actions do not save."
            >
              <div className="mt-4 space-y-4">
                <EApprovalApprovalSignatureField
                  value={approvalSignature}
                  onChange={setApprovalSignature}
                  consentAccepted={signatureConsentAccepted}
                  onConsentChange={setSignatureConsentAccepted}
                  disabled={false}
                  error={approvalSignatureError}
                  onErrorChange={setApprovalSignatureError}
                />

                <div data-help="ea-decide-remarks" className="space-y-1.5 border-t border-border pt-4">
                  <Label htmlFor="tour-decision-remarks">Remarks</Label>
                  <Textarea
                    id="tour-decision-remarks"
                    rows={3}
                    value={decisionRemarks}
                    onChange={(event) => setDecisionRemarks(event.target.value)}
                    placeholder="Optional for approve · required for reject or revision (min 5 characters)"
                  />
                </div>

                <div
                  data-help="ea-decide-actions"
                  className="mt-2 flex flex-wrap gap-2 border-t border-border pt-4"
                >
                  <span data-help="ea-decide-approve" className="inline-flex">
                    <Button type="button" size="sm" disabled>
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
                  <p className="w-full text-xs text-muted-foreground">Sample only — actions do not save.</p>
                </div>
              </div>
            </EApprovalSectionCard>
            ) : null}
          </TabsContent>
        ) : null}
      </Tabs>
    </div>
  );
}

export function EApprovalTourSampleDetailPageClient() {
  return (
    <Suspense fallback={null}>
      <TourSampleDetailInner />
    </Suspense>
  );
}
