"use client";

import Link from "next/link";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useSearchParams } from "next/navigation";
import { useEffect, useMemo, useState } from "react";
import {
  FileDown,
  FileText,
  GitBranch,
  MessageSquare,
  Pencil,
  Zap,
} from "lucide-react";

import { EApprovalApprovalSignatureField, validateApprovalSignature, validateApprovalSignatureConsent } from "@/components/e-approval/e-approval-approval-signature-field";
import { EApprovalApprovalTrail } from "@/components/e-approval/e-approval-approval-trail";
import { EApprovalSectionCard } from "@/components/e-approval/e-approval-section-card";
import { EApprovalStatusBadge } from "@/components/e-approval/e-approval-status-badge";
import { EApprovalRelatedSubmissionsPanel } from "@/components/e-approval/e-approval-related-submissions-panel";
import {
  countStampedApprovals,
  EApprovalSubmissionAttachmentsPanel,
} from "@/components/e-approval/e-approval-submission-attachments-panel";
import { OperationalAlert } from "@/components/feedback/operational-alert";
import { RaiseTicketButton } from "@/components/ticketing/raise-ticket-button";
import { TicketingRelatedTickets } from "@/components/ticketing/ticketing-related-tickets";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Checkbox } from "@/components/ui/checkbox";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Textarea } from "@/components/ui/textarea";
import {
  cancelEApprovalSubmission,
  createEApprovalDocumentLink,
  decideEApprovalApproval,
  dcfResubmitEApprovalSubmission,
  deleteEApprovalDocumentLink,
  fetchEApprovalComments,
  fetchEApprovalSubmission,
  postEApprovalComment,
  requestEApprovalRevision,
  rerouteEApprovalApproval,
  resubmitEApprovalSubmission,
  sendEApprovalManualFollowUp,
} from "@/lib/api/modules/e-approval-api";
import {
  mapEApprovalAssignableUsersToOptions,
  useEApprovalAssignableUsers,
} from "@/hooks/use-e-approval-assignable-users";
import { getErrorMessage } from "@/lib/api/error";
import { EApprovalSubmissionFieldDisplay } from "@/components/e-approval/e-approval-submission-field-display";
import { EApprovalSubmissionFormContent } from "@/components/e-approval/e-approval-submission-form-content";
import { EApprovalSubmissionWorkflowPathPanel } from "@/components/e-approval/e-approval-submission-workflow-path-panel";
import { EApprovalWaitingOnPanel } from "@/components/e-approval/e-approval-waiting-on-panel";
import { getDuplicateApproverIds } from "@/modules/e-approval/display";
import { shouldHideSubmissionAttachmentFieldValue } from "@/modules/e-approval/submission-form-content";
import {
  describeResubmitRoutingOutlook,
  describeResubmitToastMessage,
  describeRevisionRoutingApplied,
} from "@/modules/e-approval/form-revision-config";
import { eApprovalResubmitUrl } from "@/modules/documents/controlled-document-submission-url";
import type { EApprovalDocumentLinkRow } from "@/modules/e-approval/types";
import { usePermission } from "@/hooks/use-permission";
import { permissions } from "@/lib/rbac/permissions";
import { useEApprovalPdfPreview } from "@/hooks/use-e-approval-pdf-preview";
import { useNotificationStore } from "@/stores/notification-store";

type Props = { submissionId: string };

function formatTimestamp(value: string | null | undefined): string {
  if (!value) return "—";
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? value : date.toLocaleString();
}

const SUBMISSION_TABS = new Set(["request", "approvals", "activity", "decide"]);

/** Map legacy ?tab= values and normalize to the current tab set. */
function resolveSubmissionTab(
  raw: string | null | undefined,
  options: { canDecide: boolean },
): string {
  const normalized = (raw ?? "").trim().toLowerCase();
  const mapped =
    normalized === "summary" || normalized === "content"
      ? "request"
      : normalized === "workflow"
        ? "approvals"
        : normalized === "actions"
          ? "decide"
          : normalized === "comments"
            ? "activity"
            : normalized;

  if (mapped === "decide" && !options.canDecide) {
    return "request";
  }

  if (SUBMISSION_TABS.has(mapped)) {
    return mapped;
  }

  return "request";
}

export function EApprovalSubmissionDetailPageClient({ submissionId }: Props) {
  const queryClient = useQueryClient();
  const searchParams = useSearchParams();
  const push = useNotificationStore((s) => s.push);
  const canApprove = usePermission([permissions.eApprovalApprove]);
  const canManage = usePermission([permissions.eApprovalFormsManage]);
  const canCreate = usePermission([permissions.eApprovalSubmissionsCreate]);
  const [comment, setComment] = useState("");
  const [decisionRemarks, setDecisionRemarks] = useState("");
  const [forceFullRestart, setForceFullRestart] = useState(false);
  const [approvalSignature, setApprovalSignature] = useState<string | null>(null);
  const [approvalSignatureError, setApprovalSignatureError] = useState<string | null>(null);
  const [signatureConsentAccepted, setSignatureConsentAccepted] = useState(false);
  const [rerouteUserId, setRerouteUserId] = useState("");
  const [rerouteReason, setRerouteReason] = useState("");
  const [linkTargetId, setLinkTargetId] = useState("");
  const [dcfValues, setDcfValues] = useState<Record<string, string>>({});

  const { data, isError } = useQuery({
    queryKey: ["e-approval", "submission", submissionId],
    queryFn: () => fetchEApprovalSubmission(submissionId),
  });

  const usersQuery = useEApprovalAssignableUsers(canManage);

  const commentsQuery = useQuery({
    queryKey: ["e-approval", "submission", submissionId, "comments"],
    queryFn: () => fetchEApprovalComments(submissionId),
  });

  const cancelMutation = useMutation({
    mutationFn: () => cancelEApprovalSubmission(submissionId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["e-approval", "submission", submissionId] });
      push({ level: "success", title: "Submission cancelled" });
    },
    onError: (e) => push({ level: "error", title: "Cancel failed", message: getErrorMessage(e) }),
  });

  const commentMutation = useMutation({
    mutationFn: () => postEApprovalComment(submissionId, { message: comment }),
    onSuccess: () => {
      setComment("");
      commentsQuery.refetch();
      queryClient.invalidateQueries({ queryKey: ["e-approval", "notifications"] });
      queryClient.invalidateQueries({ queryKey: ["tenant", "notifications"] });
    },
    onError: (e) => push({ level: "error", title: "Comment failed", message: getErrorMessage(e) }),
  });

  const revisionMutation = useMutation({
    mutationFn: () =>
      requestEApprovalRevision(submissionId, decisionRemarks, {
        force_full_restart: forceFullRestart,
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["e-approval", "submission", submissionId] });
      queryClient.invalidateQueries({ queryKey: ["e-approval", "submission", submissionId, "comments"] });
      push({ level: "success", title: "Returned for revision" });
      setDecisionRemarks("");
      setForceFullRestart(false);
    },
    onError: (e) => push({ level: "error", title: "Revision failed", message: getErrorMessage(e) }),
  });

  const followUpMutation = useMutation({
    mutationFn: () => sendEApprovalManualFollowUp(submissionId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["e-approval", "submission", submissionId] });
      push({ level: "success", title: "Follow-up sent" });
    },
    onError: (e) => push({ level: "error", title: "Follow-up failed", message: getErrorMessage(e) }),
  });

  useEffect(() => {
    if (!data?.values) return;
    setDcfValues(Object.fromEntries(data.values.map((v) => [v.field_name ?? v.field_id, v.value ?? ""])));
  }, [data]);

  const dcfMutation = useMutation({
    mutationFn: () => dcfResubmitEApprovalSubmission(submissionId, dcfValues),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["e-approval", "submission", submissionId] });
      push({ level: "success", title: "Document control submitted" });
    },
    onError: (e) => push({ level: "error", title: "DCF submit failed", message: getErrorMessage(e) }),
  });

  const linkMutation = useMutation({
    mutationFn: () => createEApprovalDocumentLink(submissionId, { target_submission_id: linkTargetId }),
    onSuccess: () => {
      setLinkTargetId("");
      queryClient.invalidateQueries({ queryKey: ["e-approval", "submission", submissionId] });
      push({ level: "success", title: "Link added" });
    },
    onError: (e) => push({ level: "error", title: "Link failed", message: getErrorMessage(e) }),
  });

  const pendingApproval = useMemo(() => {
    if (!data?.viewer_pending_approval_id) {
      return undefined;
    }

    return data.approvals.find((approval) => approval.id === data.viewer_pending_approval_id);
  }, [data]);

  const rerouteMutation = useMutation({
    mutationFn: () =>
      rerouteEApprovalApproval(pendingApproval!.id, { new_approver_id: rerouteUserId, reason: rerouteReason }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["e-approval"] });
      push({ level: "success", title: "Approval rerouted" });
    },
    onError: (e) => push({ level: "error", title: "Reroute failed", message: getErrorMessage(e) }),
  });

  const resubmitMutation = useMutation({
    mutationFn: () => {
      const values = Object.fromEntries((data?.values ?? []).map((v) => [v.field_name ?? v.field_id, v.value]));
      return resubmitEApprovalSubmission(submissionId, values);
    },
    onSuccess: (result) => {
      queryClient.invalidateQueries({ queryKey: ["e-approval", "submission", submissionId] });
      const applied = result.revision_routing_applied;
      const message = applied
        ? describeResubmitToastMessage({
            routing: applied.routing,
            reason: applied.reason,
            currentStep: applied.current_step,
          })
        : undefined;
      push({ level: "success", title: "Resubmitted", message });
    },
    onError: (e) => push({ level: "error", title: "Resubmit failed", message: getErrorMessage(e) }),
  });

  const decideMutation = useMutation({
    mutationFn: ({
      approvalId,
      decision,
      signature,
    }: {
      approvalId: string;
      decision: "approved" | "rejected";
      signature?: string | null;
    }) =>
      decideEApprovalApproval(approvalId, {
        decision,
        remarks: decisionRemarks.trim() || undefined,
        signature: decision === "approved" ? signature : undefined,
        signature_consent: decision === "approved" ? true : undefined,
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["e-approval"] });
      queryClient.invalidateQueries({ queryKey: ["e-approval", "me", "profile"] });
      queryClient.invalidateQueries({ queryKey: ["e-approval", "submission", submissionId, "comments"] });
      push({ level: "success", title: "Decision recorded" });
      setDecisionRemarks("");
      setApprovalSignature(null);
      setApprovalSignatureError(null);
      setSignatureConsentAccepted(false);
    },
    onError: (e) => push({ level: "error", title: "Decision failed", message: getErrorMessage(e) }),
  });

  const canEditAndResubmit =
    Boolean(canCreate) &&
    Boolean(data?.viewer_is_requestor || canManage) &&
    (data?.status === "returned" || data?.status === "rejected");
  const resubmitHref =
    canEditAndResubmit && data?.form_id ? eApprovalResubmitUrl(data.form_id, submissionId) : null;
  const showDecideTab =
    Boolean(canApprove && data?.viewer_pending_approval_id) ||
    Boolean(data?.viewer_is_requestor && data?.status === "pending") ||
    data?.status === "awaiting_dcf";
  const decideBadgeCount =
    (canApprove && data?.viewer_pending_approval_id ? 1 : 0) +
    (data?.status === "awaiting_dcf" ? 1 : 0);

  const defaultTab = useMemo(() => {
    const fromQuery = resolveSubmissionTab(searchParams.get("tab"), { canDecide: showDecideTab });
    if (searchParams.get("tab")) {
      return fromQuery;
    }
    if (
      showDecideTab &&
      ((canApprove && data?.viewer_pending_approval_id) || data?.status === "awaiting_dcf")
    ) {
      return "decide";
    }
    return "request";
  }, [
    canApprove,
    data?.viewer_pending_approval_id,
    data?.status,
    searchParams,
    showDecideTab,
  ]);

  const tabsKey = data ? `${data.id}-${pendingApproval?.id ?? "idle"}-${defaultTab}` : "loading";
  const { openPdfPreview, isGenerating: isPdfGenerating } = useEApprovalPdfPreview();
  const attachments = data?.attachments ?? [];
  const visibleValues = (data?.values ?? []).filter(
    (v) => !shouldHideSubmissionAttachmentFieldValue(v, attachments),
  );
  const attachmentFieldLabelsByName = useMemo(() => {
    const labels: Record<string, string> = {};
    for (const value of data?.values ?? []) {
      const name = value.field_name?.trim();
      if (!name) continue;
      const label = (value.label ?? value.field_name)?.trim();
      if (label) labels[name] = label;
    }
    for (const field of data?.form_fields ?? []) {
      const name = field.name?.trim();
      if (!name || labels[name]) continue;
      const label = (field.label ?? field.name)?.trim();
      if (label) labels[name] = label;
    }
    return labels;
  }, [data?.values, data?.form_fields]);
  const duplicateApproverIds = useMemo(
    () => getDuplicateApproverIds(data?.values ?? []),
    [data?.values],
  );

  const commentCount = commentsQuery.data?.length ?? 0;
  const hasWorkflowRemarkComment = (commentsQuery.data ?? []).some(
    (c) => /^Rejected:/i.test(c.message) || /^Revision requested:/i.test(c.message),
  );
  const showLegacyWorkflowRemark = Boolean(data?.revision_remarks?.trim()) && !hasWorkflowRemarkComment;
  const commentsBadgeCount = commentCount + (showLegacyWorkflowRemark ? 1 : 0);

  return (
    <PermissionGate requiredPermissions={[permissions.eApprovalSubmissionsView]}>
      <div className="space-y-6">
        {data ? (
          <header className="flex flex-wrap items-start justify-between gap-4">
            <div className="min-w-0">
              <p className="text-xs font-medium text-muted-foreground">Submission</p>
              <h1 className="text-2xl font-semibold tracking-tight text-foreground">{data.document_no}</h1>
              <p className="mt-1 text-sm text-muted-foreground">{data.form_name}</p>
            </div>
            <EApprovalStatusBadge status={data.status} kind="submission" />
          </header>
        ) : null}

        {isError ? <p className="text-sm text-destructive">Could not load submission.</p> : null}

        {data?.status === "draft" && data.viewer_is_requestor && canCreate ? (
          <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-primary/30 bg-primary/5 px-4 py-3 shadow-sm">
            <div className="min-w-0">
              <p className="text-sm font-medium text-foreground">Draft not submitted yet</p>
              <p className="mt-0.5 text-sm text-muted-foreground">
                Continue on the request form to finish fields and submit for approval.
              </p>
            </div>
            <Button
              size="sm"
              className="gap-1.5"
              render={<Link href={`/e-approval/request/${data.form_id}`} />}
            >
              <Pencil className="h-3.5 w-3.5" />
              Continue editing
            </Button>
          </div>
        ) : null}

        {canEditAndResubmit && resubmitHref ? (
          <OperationalAlert
            level="warning"
            title={data?.status === "rejected" ? "Request was rejected" : "Needs revision"}
            description={
              <>
                {data?.revision_remarks ? (
                  <p className="whitespace-pre-wrap">{data.revision_remarks}</p>
                ) : (
                  <p>An approver returned this request. Edit the form and resubmit when ready.</p>
                )}
                {data?.revision_remarks_by || data?.revision_remarks_at ? (
                  <p className="mt-2 text-xs text-muted-foreground">
                    {[data.revision_remarks_by, formatTimestamp(data.revision_remarks_at)]
                      .filter((part) => part && part !== "—")
                      .join(" · ")}
                  </p>
                ) : null}
                <p className="mt-2 text-xs text-muted-foreground">
                  {describeResubmitRoutingOutlook({
                    status: data?.status,
                    forceFullRestart: data?.force_full_restart,
                    routing: data?.revision_config?.routing,
                    returnedFromStep: data?.returned_from_step,
                    materialFieldCount: data?.revision_config?.material_fields?.length ?? 0,
                  })}
                </p>
              </>
            }
            actions={
              <div className="flex flex-wrap gap-2">
                <Button size="sm" className="gap-1.5" render={<Link href={resubmitHref} />}>
                  <Pencil className="h-3.5 w-3.5" />
                  Edit and resubmit
                </Button>
                <Button
                  size="sm"
                  variant="outline"
                  onClick={() => resubmitMutation.mutate()}
                  disabled={resubmitMutation.isPending}
                >
                  Resubmit without changes
                </Button>
                {data?.status === "returned" ? (
                  <Button
                    size="sm"
                    variant="outline"
                    onClick={() => cancelMutation.mutate()}
                    disabled={cancelMutation.isPending}
                  >
                    Cancel request
                  </Button>
                ) : null}
              </div>
            }
          />
        ) : null}

        {(data?.status === "returned" || data?.status === "rejected") && !canEditAndResubmit ? (
          <OperationalAlert
            level="warning"
            title={data.status === "rejected" ? "Rejected" : "Needs revision"}
            description={
              <>
                <p>Only the requestor can edit and resubmit this request.</p>
                {data.revision_remarks ? (
                  <p className="mt-2 whitespace-pre-wrap">{data.revision_remarks}</p>
                ) : null}
                {data.status === "returned" ? (
                  <p className="mt-2 text-xs text-muted-foreground">
                    {describeResubmitRoutingOutlook({
                      status: data.status,
                      forceFullRestart: data.force_full_restart,
                      routing: data.revision_config?.routing,
                      returnedFromStep: data.returned_from_step,
                      materialFieldCount: data.revision_config?.material_fields?.length ?? 0,
                    })}
                  </p>
                ) : null}
              </>
            }
          />
        ) : null}

        {data ? (
          <div className="rounded-xl border border-border bg-card px-4 py-3 shadow-sm">
            <dl className="grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
              <div>
                <dt className="text-xs font-medium text-muted-foreground">Form</dt>
                <dd className="mt-0.5 font-medium">{data.form_name}</dd>
              </div>
              <div>
                <dt className="text-xs font-medium text-muted-foreground">Requestor</dt>
                <dd className="mt-0.5">{data.requestor?.name ?? "—"}</dd>
              </div>
              <div>
                <dt className="text-xs font-medium text-muted-foreground">Workflow step</dt>
                <dd className="mt-0.5">
                  {data.status === "returned" || data.status === "rejected" || data.current_step < 1
                    ? data.status === "returned"
                      ? data.returned_from_step
                        ? data.force_full_restart ||
                          data.revision_config?.routing !== "resume_returning_step"
                          ? `Awaiting resubmit · will restart from step 1`
                          : `Awaiting resubmit · will resume at step ${data.returned_from_step}`
                        : "Awaiting resubmit"
                      : "—"
                    : `Step ${data.current_step}`}
                </dd>
              </div>
              <div>
                <dt className="text-xs font-medium text-muted-foreground">Submitted</dt>
                <dd className="mt-0.5">{formatTimestamp(data.created_at)}</dd>
              </div>
              {data.form_schema_version_at_submit ? (
                <div>
                  <dt className="text-xs font-medium text-muted-foreground">Form version at submit</dt>
                  <dd className="mt-0.5">v{data.form_schema_version_at_submit}</dd>
                </div>
              ) : null}
            </dl>
            <div className="mt-3 flex flex-wrap gap-2 border-t border-border pt-3">
              <RaiseTicketButton
                prefill={{
                  title: `Issue with ${data.document_no} — ${data.form_name}`,
                  description: [
                    `Submission: ${data.document_no}`,
                    `Form: ${data.form_name}`,
                    `Status: ${data.status}`,
                    `Requestor: ${data.requestor?.name ?? "—"}`,
                    `Link: /e-approval/submissions/${submissionId}`,
                  ].join("\n"),
                  source_module: "e_approval",
                  source_reference_type: "submission",
                  source_reference_id: submissionId,
                  source_label: data.document_no,
                  links: [
                    {
                      link_module: "e_approval",
                      link_type: "submission",
                      link_id: submissionId,
                      link_label: data.document_no,
                    },
                  ],
                }}
              />
              <Button
                size="sm"
                variant="outline"
                type="button"
                disabled={isPdfGenerating}
                onClick={() => {
                  void openPdfPreview(submissionId).catch((e) => {
                    push({
                      level: "error",
                      title: "PDF preview failed",
                      message: getErrorMessage(e) || "Could not generate PDF.",
                    });
                  });
                }}
              >
                <FileDown className="h-3.5 w-3.5" />
                {isPdfGenerating ? "Generating PDF…" : "Print / PDF"}
              </Button>
            </div>
          </div>
        ) : null}

        {data ? (
          <Tabs key={tabsKey} defaultValue={defaultTab}>
            <TabsList variant="line" className="w-full justify-start gap-1 overflow-x-auto">
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
                {commentsBadgeCount > 0 ? (
                  <Badge variant="outline" className="ml-1 h-5 min-w-5 px-1 text-[10px]">
                    {commentsBadgeCount}
                  </Badge>
                ) : null}
              </TabsTrigger>
              {showDecideTab ? (
                <TabsTrigger value="decide" className="gap-1.5">
                  <Zap className="h-3.5 w-3.5" />
                  Decide
                  {decideBadgeCount > 0 ? (
                    <Badge variant="secondary" className="ml-1 h-5 min-w-5 px-1 text-[10px]">
                      {decideBadgeCount}
                    </Badge>
                  ) : null}
                </TabsTrigger>
              ) : null}
            </TabsList>

            <TabsContent value="request" className="mt-4 space-y-4">
              <EApprovalSectionCard
                title="Request details"
                description="Submitted form values and attachments. Use Open preview on files to view with stamped signatures when available."
              >
                {duplicateApproverIds.size > 0 ? (
                  <p className="mt-3 rounded-lg border border-amber-200/80 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-200">
                    The same approver was selected more than once. Email is shown under each matching field to help tell them apart.
                  </p>
                ) : null}
                {data.status === "awaiting_dcf" ? (
                  <div className="mt-4 space-y-3">
                    <p className="text-sm text-amber-700 dark:text-amber-400">
                      Complete document control fields in the Decide tab, then submit.
                    </p>
                    {visibleValues.map((v) => (
                      <div key={v.field_id} className="text-sm">
                        <span className="text-muted-foreground">{v.label ?? v.field_name}: </span>
                        {dcfValues[v.field_name ?? v.field_id] ?? (
                          <EApprovalSubmissionFieldDisplay field={v} duplicateApproverIds={duplicateApproverIds} />
                        )}
                      </div>
                    ))}
                  </div>
                ) : (
                  <EApprovalSubmissionFormContent
                    values={data.values}
                    formFields={data.form_fields}
                    attachments={data.attachments}
                  />
                )}

                {(data.attachments ?? []).length > 0 ? (
                  <div className="mt-6 border-t border-border pt-4">
                    <EApprovalSubmissionAttachmentsPanel
                      submissionId={submissionId}
                      attachments={data.attachments}
                      stampedApprovalCount={countStampedApprovals(data.approvals)}
                      fieldLabelsByName={attachmentFieldLabelsByName}
                    />
                  </div>
                ) : null}
              </EApprovalSectionCard>

              <EApprovalSectionCard title="Related & links" description="Linked submissions, documents, and tickets.">
                {data.requestor?.email ? (
                  <p className="mb-4 text-sm text-muted-foreground">
                    Requestor email: <span className="text-foreground">{data.requestor.email}</span>
                  </p>
                ) : null}

                <EApprovalRelatedSubmissionsPanel related={data.related_submissions} />

                <div className="space-y-3 border-t border-border pt-4">
                  <h3 className="text-sm font-medium">Linked documents</h3>
                  <ul className="space-y-2 text-sm text-muted-foreground">
                    {(data.document_links ?? []).length === 0 ? (
                      <li>No linked documents.</li>
                    ) : (
                      (data.document_links ?? []).map((link: EApprovalDocumentLinkRow) => (
                        <li key={link.id} className="flex flex-wrap items-center gap-2">
                          <Link
                            href={`/e-approval/submissions/${link.target_submission_id ?? link.submission_id}`}
                            className="text-primary hover:underline"
                          >
                            {link.target_document_no ?? link.document_no ?? link.target_submission_id ?? link.submission_id}
                          </Link>
                          <span>({link.link_type})</span>
                          {canManage ? (
                            <button
                              type="button"
                              className="text-destructive hover:underline"
                              onClick={async () => {
                                await deleteEApprovalDocumentLink(link.id);
                                queryClient.invalidateQueries({ queryKey: ["e-approval", "submission", submissionId] });
                              }}
                            >
                              Remove
                            </button>
                          ) : null}
                        </li>
                      ))
                    )}
                  </ul>
                  {canManage ? (
                    <div className="flex flex-wrap gap-2 pt-2">
                      <Input
                        className="max-w-md"
                        placeholder="Target submission UUID"
                        value={linkTargetId}
                        onChange={(e) => setLinkTargetId(e.target.value)}
                      />
                      <Button size="sm" variant="outline" onClick={() => linkMutation.mutate()} disabled={!linkTargetId.trim()}>
                        Link submission
                      </Button>
                    </div>
                  ) : null}
                </div>

                <div className="mt-6">
                  <TicketingRelatedTickets
                    sourceModule="e_approval"
                    sourceReferenceId={submissionId}
                  />
                </div>
              </EApprovalSectionCard>
            </TabsContent>

            <TabsContent value="approvals" className="mt-4 space-y-4">
              <EApprovalSubmissionWorkflowPathPanel
                submissionId={submissionId}
                revisionRoutingNote={
                  data.revision_routing_applied
                    ? describeRevisionRoutingApplied({
                        routing: data.revision_routing_applied.routing,
                        reason: data.revision_routing_applied.reason,
                        currentStep: data.revision_routing_applied.current_step,
                      })
                    : null
                }
              />
              <EApprovalWaitingOnPanel
                approvals={data.approvals}
                currentStep={data.current_step}
                submissionStatus={data.status}
                viewerPendingApprovalId={data.viewer_pending_approval_id}
              />
              <EApprovalApprovalTrail
                approvals={data.approvals}
                currentStep={data.current_step}
                revisionRoutingNote={
                  data.revision_routing_applied
                    ? describeRevisionRoutingApplied({
                        routing: data.revision_routing_applied.routing,
                        reason: data.revision_routing_applied.reason,
                        currentStep: data.revision_routing_applied.current_step,
                      })
                    : null
                }
              />
            </TabsContent>

            {showDecideTab ? (
            <TabsContent value="decide" className="mt-4">
              <div className="space-y-4">
                {data.status === "awaiting_dcf" ? (
                  <EApprovalSectionCard title="Document control" description="Update fields and submit for processing.">
                    <div className="space-y-3">
                      {data.values.map((v) => (
                        <div key={v.field_id} className="space-y-1.5">
                          <Label htmlFor={`dcf-${v.field_id}`}>{v.label ?? v.field_name}</Label>
                          <Input
                            id={`dcf-${v.field_id}`}
                            value={dcfValues[v.field_name ?? v.field_id] ?? ""}
                            onChange={(e) =>
                              setDcfValues((prev) => ({ ...prev, [v.field_name ?? v.field_id]: e.target.value }))
                            }
                          />
                        </div>
                      ))}
                      <Button size="sm" onClick={() => dcfMutation.mutate()} disabled={dcfMutation.isPending}>
                        Submit document control
                      </Button>
                    </div>
                  </EApprovalSectionCard>
                ) : null}

                {data.status === "pending" && data.viewer_is_requestor ? (
                  <EApprovalSectionCard title="Requestor actions">
                    <div className="mt-3 space-y-2">
                      <div className="flex flex-wrap gap-2">
                        <Button size="sm" variant="outline" onClick={() => cancelMutation.mutate()} disabled={cancelMutation.isPending}>
                          Cancel request
                        </Button>
                        <Button
                          size="sm"
                          variant="secondary"
                          onClick={() => followUpMutation.mutate()}
                          disabled={followUpMutation.isPending || !!data.manual_follow_up_next_allowed_at}
                        >
                          Send follow-up to approver
                        </Button>
                      </div>
                      {data.manual_follow_up_next_allowed_at ? (
                        <p className="text-xs text-muted-foreground">
                          Follow-up cooldown active. You can send another reminder after{" "}
                          {new Date(data.manual_follow_up_next_allowed_at).toLocaleString()}.
                        </p>
                      ) : (
                        <p className="text-xs text-muted-foreground">
                          Sends an email and in-app notification to every pending approver on the
                          current step (including parallel peers).
                        </p>
                      )}
                    </div>
                  </EApprovalSectionCard>
                ) : null}

                {canApprove && pendingApproval ? (
                  <EApprovalSectionCard
                    title="Your decision"
                    description="Sign to approve. Remarks are optional for approve, required for reject or revision."
                  >
                    <div className="mt-4 space-y-4">
                      <EApprovalApprovalSignatureField
                        value={approvalSignature}
                        onChange={setApprovalSignature}
                        consentAccepted={signatureConsentAccepted}
                        onConsentChange={setSignatureConsentAccepted}
                        disabled={decideMutation.isPending || revisionMutation.isPending}
                        error={approvalSignatureError}
                        onErrorChange={setApprovalSignatureError}
                      />

                      <div className="space-y-1.5 border-t border-border pt-4">
                        <Label htmlFor="decision-remarks">Remarks</Label>
                        <Textarea
                          id="decision-remarks"
                          placeholder="Optional for approve · required for reject or revision (min 5 characters)"
                          value={decisionRemarks}
                          onChange={(e) => setDecisionRemarks(e.target.value)}
                          rows={3}
                          disabled={decideMutation.isPending || revisionMutation.isPending}
                        />
                      </div>
                      {data.revision_config?.approver_can_force_full_restart === true &&
                      data.revision_config?.routing === "resume_returning_step" ? (
                        <label className="flex cursor-pointer items-start gap-2 text-sm">
                          <Checkbox
                            className="mt-0.5"
                            checked={forceFullRestart}
                            onCheckedChange={(checked) => setForceFullRestart(checked === true)}
                            disabled={decideMutation.isPending || revisionMutation.isPending}
                          />
                          <span>
                            <span className="font-medium">Require full re-approval</span>
                            <span className="mt-0.5 block text-xs text-muted-foreground">
                              Overrides resume routing: after the requestor resubmits, the workflow
                              restarts from step 1 instead of returning to this step.
                            </span>
                          </span>
                        </label>
                      ) : null}
                      <div className="flex flex-wrap gap-2">
                        <Button
                          size="sm"
                          onClick={() => {
                            const signatureError = validateApprovalSignature(approvalSignature);
                            if (signatureError) {
                              setApprovalSignatureError(signatureError);
                              return;
                            }
                            const consentError = validateApprovalSignatureConsent(signatureConsentAccepted);
                            if (consentError) {
                              setApprovalSignatureError(consentError);
                              return;
                            }
                            decideMutation.mutate({
                              approvalId: pendingApproval.id,
                              decision: "approved",
                              signature: approvalSignature!.trim(),
                            });
                          }}
                          disabled={
                            decideMutation.isPending ||
                            revisionMutation.isPending ||
                            !signatureConsentAccepted
                          }
                        >
                          Approve
                        </Button>
                        <Button
                          size="sm"
                          variant="destructive"
                          onClick={() =>
                            decideMutation.mutate({ approvalId: pendingApproval.id, decision: "rejected" })
                          }
                          disabled={
                            decideMutation.isPending ||
                            revisionMutation.isPending ||
                            decisionRemarks.trim().length < 5
                          }
                        >
                          Reject
                        </Button>
                        <Button
                          size="sm"
                          variant="outline"
                          onClick={() => revisionMutation.mutate()}
                          disabled={
                            decideMutation.isPending ||
                            revisionMutation.isPending ||
                            decisionRemarks.trim().length < 5
                          }
                        >
                          Request revision
                        </Button>
                      </div>
                    </div>

                    {canManage ? (
                      <div className="mt-6 space-y-3 border-t border-border pt-4">
                        <h3 className="text-sm font-medium">Admin reroute</h3>
                        <div className="flex flex-wrap items-end gap-2">
                          <div className="space-y-1.5">
                            <Label htmlFor="reroute-user">New approver</Label>
                            <Select
                              id="reroute-user"
                              className="min-w-[200px]"
                              value={rerouteUserId}
                              onChange={(e) => setRerouteUserId(e.target.value)}
                            >
                              <option value="">Select approver</option>
                              {mapEApprovalAssignableUsersToOptions(usersQuery.data).map((user) => (
                                <option key={user.id} value={user.id}>
                                  {user.label}
                                </option>
                              ))}
                            </Select>
                          </div>
                          <div className="min-w-[240px] flex-1 space-y-1.5">
                            <Label htmlFor="reroute-reason">Reason</Label>
                            <Input
                              id="reroute-reason"
                              placeholder="Reason (min 5 characters)"
                              value={rerouteReason}
                              onChange={(e) => setRerouteReason(e.target.value)}
                            />
                          </div>
                          <Button
                            size="sm"
                            variant="outline"
                            onClick={() => rerouteMutation.mutate()}
                            disabled={!rerouteUserId || rerouteReason.trim().length < 5 || rerouteMutation.isPending}
                          >
                            Reroute approval
                          </Button>
                        </div>
                      </div>
                    ) : null}
                  </EApprovalSectionCard>
                ) : null}

              </div>
            </TabsContent>
            ) : null}

            <TabsContent value="activity" className="mt-4">
              <EApprovalSectionCard
                title="Activity"
                description="Comments and workflow remarks visible to participants. Reject and revision remarks are posted here automatically."
              >
                {showLegacyWorkflowRemark || (commentsQuery.data ?? []).length > 0 ? (
                  <ul className="space-y-3 text-sm">
                    {showLegacyWorkflowRemark ? (
                      <li className="rounded-lg border border-amber-200/80 bg-amber-50/80 p-3 dark:border-amber-900/50 dark:bg-amber-950/30">
                        <div className="flex flex-wrap items-baseline justify-between gap-2">
                          <span className="font-medium">
                            {data.revision_remarks_by ?? "Approver"}
                            <span className="ml-2 text-xs font-normal text-muted-foreground">
                              {data.status === "rejected" ? "Rejection" : "Revision requested"}
                            </span>
                          </span>
                          <span className="text-xs text-muted-foreground">
                            {formatTimestamp(data.revision_remarks_at)}
                          </span>
                        </div>
                        <p className="mt-1 whitespace-pre-wrap">{data.revision_remarks}</p>
                      </li>
                    ) : null}
                    {(commentsQuery.data ?? []).map((c) => {
                      const isRejection = /^Rejected:/i.test(c.message);
                      const isRevision = /^Revision requested:/i.test(c.message);
                      const isWorkflowRemark = isRejection || isRevision;
                      return (
                        <li
                          key={c.id}
                          className={
                            isWorkflowRemark
                              ? "rounded-lg border border-amber-200/80 bg-amber-50/80 p-3 dark:border-amber-900/50 dark:bg-amber-950/30"
                              : "rounded-lg border border-border bg-muted/20 p-3"
                          }
                        >
                          <div className="flex flex-wrap items-baseline justify-between gap-2">
                            <span className="font-medium">
                              {c.user_name}
                              {isWorkflowRemark ? (
                                <span className="ml-2 text-xs font-normal text-muted-foreground">
                                  {isRejection ? "Rejection" : "Revision requested"}
                                </span>
                              ) : null}
                            </span>
                            <span className="text-xs text-muted-foreground">{formatTimestamp(c.created_at)}</span>
                          </div>
                          <p className="mt-1 whitespace-pre-wrap">{c.message}</p>
                          {(c.replies ?? []).length > 0 ? (
                            <ul className="mt-2 space-y-2 border-l-2 border-border pl-3">
                              {c.replies.map((reply) => (
                                <li key={reply.id}>
                                  <span className="font-medium">{reply.user_name}: </span>
                                  {reply.message}
                                </li>
                              ))}
                            </ul>
                          ) : null}
                        </li>
                      );
                    })}
                  </ul>
                ) : (
                  <p className="text-sm text-muted-foreground">No comments yet.</p>
                )}
                <div className="mt-4 flex flex-col gap-2 sm:flex-row">
                  <Textarea
                    value={comment}
                    onChange={(e) => setComment(e.target.value)}
                    placeholder="Add a comment"
                    rows={2}
                    className="flex-1"
                  />
                  <Button
                    size="sm"
                    type="button"
                    className="self-end sm:self-auto"
                    onClick={() => commentMutation.mutate()}
                    disabled={!comment.trim() || commentMutation.isPending}
                  >
                    Post
                  </Button>
                </div>
              </EApprovalSectionCard>
            </TabsContent>
          </Tabs>
        ) : null}
      </div>
    </PermissionGate>
  );
}
