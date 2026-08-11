"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useQuery } from "@tanstack/react-query";

import { ProcurementSchemaComposePanel } from "@/components/procurement-one/procurement-schema-compose-panel";
import { PROCUREMENT_DOCUMENT_COMPOSE_SHELL_CLASS } from "@/components/procurement-one/procurement-compose-layout";
import { ProcurementOnePageHeader } from "@/components/procurement-one/procurement-one-page-header";
import { PermissionGate } from "@/components/layout/permission-gate";
import { SectionCardSkeleton } from "@/components/ui/page-skeletons";
import {
  fetchProcurementApInvoice,
  fetchProcurementPo,
  fetchProcurementPr,
} from "@/lib/api/modules/procurement-one-api";
import { fetchEApprovalSubmission } from "@/lib/api/modules/e-approval-api";
import { permissions } from "@/lib/rbac/permissions";
import type { ProcurementDocumentKind, ProcurementPrAttachment } from "@/modules/procurement-one/types";

type Props = {
  kind: ProcurementDocumentKind;
  mode: "create" | "edit";
  documentId?: string;
  prId?: string;
  poId?: string;
};

const copy: Record<
  ProcurementDocumentKind,
  { listHref: string; listLabel: string; createTitle: string; editTitle: string; detailHref: (id: string) => string }
> = {
  purchase_requisition: {
    listHref: "/procurement/prs",
    listLabel: "Purchase requisitions",
    createTitle: "New purchase requisition",
    editTitle: "Edit purchase requisition",
    detailHref: (id) => `/procurement/prs/${id}`,
  },
  purchase_order: {
    listHref: "/procurement/pos",
    listLabel: "Purchase orders",
    createTitle: "New purchase order",
    editTitle: "Edit purchase order",
    detailHref: (id) => `/procurement/pos/${id}`,
  },
  ap_invoice: {
    listHref: "/procurement/ap-invoices",
    listLabel: "AP invoices",
    createTitle: "New AP invoice",
    editTitle: "Edit AP invoice",
    detailHref: (id) => `/procurement/ap-invoices/${id}`,
  },
};

export function ProcurementComposePageClient({ kind, mode, documentId, prId, poId }: Props) {
  const router = useRouter();
  const labels = copy[kind];

  const prQuery = useQuery({
    queryKey: ["procurement-one", "pr", prId, "compose-prefill"],
    queryFn: () => fetchProcurementPr(prId!),
    enabled: kind === "purchase_order" && !!prId,
  });

  const poQuery = useQuery({
    queryKey: ["procurement-one", "po", poId, "compose-prefill"],
    queryFn: () => fetchProcurementPo(poId!),
    enabled: (kind === "purchase_order" && mode === "create" && !!poId) || kind === "ap_invoice",
  });

  const documentQuery = useQuery({
    queryKey: ["procurement-one", kind, documentId, "compose"],
    queryFn: async () => {
      if (kind === "purchase_requisition") {
        return fetchProcurementPr(documentId!);
      }
      if (kind === "purchase_order") {
        return fetchProcurementPo(documentId!);
      }
      return fetchProcurementApInvoice(documentId!);
    },
    enabled: mode === "edit" && !!documentId,
  });

  const submissionIdForAttachments =
    mode === "edit" && documentQuery.data && "e_approval_submission_id" in documentQuery.data
      ? (documentQuery.data.e_approval_submission_id ?? null)
      : null;

  const submissionQuery = useQuery({
    queryKey: ["e-approval", "submission", submissionIdForAttachments, "compose-attachments"],
    queryFn: () => fetchEApprovalSubmission(submissionIdForAttachments!),
    enabled:
      mode === "edit" &&
      !!submissionIdForAttachments &&
      (kind === "purchase_order" || kind === "ap_invoice"),
    staleTime: 30_000,
  });

  const lockedParentSubmissionId =
    kind === "purchase_order" && prId
      ? (prQuery.data?.e_approval_submission_id ?? null)
      : kind === "ap_invoice" && poId
        ? (poQuery.data?.e_approval_submission_id ?? null)
        : null;

  const linkedParentSubmissionId =
    mode === "edit" &&
    kind === "purchase_order" &&
    documentQuery.data &&
    "parent_submission_id" in documentQuery.data
      ? (documentQuery.data.parent_submission_id ?? null)
      : null;

  const linkedParentFromPr =
    mode === "edit" &&
    kind === "purchase_order" &&
    documentQuery.data &&
    "purchase_requisitions" in documentQuery.data &&
    documentQuery.data.purchase_requisitions.length > 0
      ? (documentQuery.data.purchase_requisitions[0].e_approval_submission_id ?? null)
      : null;

  const linkedParentFromSubmission = submissionQuery.data?.parent_submission_id ?? null;

  const resolvedLockedParentSubmissionId =
    lockedParentSubmissionId ??
    linkedParentSubmissionId ??
    linkedParentFromSubmission ??
    linkedParentFromPr ??
    null;

  const initialValues =
    mode === "edit" && documentQuery.data && "compose_values" in documentQuery.data
      ? documentQuery.data.compose_values
      : kind === "purchase_order" && prId && prQuery.data?.compose_values
        ? prQuery.data.compose_values
        : undefined;

  const mapSubmissionAttachments = (attachments: Array<{ id: string; field_name: string | null; file_name: string }>): ProcurementPrAttachment[] =>
    attachments.map((attachment) => ({
      id: attachment.id,
      field_name: attachment.field_name ?? "",
      file_name: attachment.file_name,
      mime_type: null,
      size_bytes: null,
      e_approval_attachment_id: attachment.id,
    }));

  const existingAttachments: ProcurementPrAttachment[] | undefined =
    kind === "purchase_requisition" && mode === "edit" && documentQuery.data && "attachments" in documentQuery.data
      ? documentQuery.data.attachments
      : submissionQuery.data?.attachments
        ? mapSubmissionAttachments(submissionQuery.data.attachments)
        : undefined;

  const isLoading =
    (mode === "edit" && documentQuery.isLoading) ||
    (mode === "edit" && !!submissionIdForAttachments && submissionQuery.isLoading) ||
    (kind === "purchase_order" && !!prId && prQuery.isLoading) ||
    (kind === "ap_invoice" && !!poId && poQuery.isLoading);

  if (isLoading) {
    return <SectionCardSkeleton />;
  }

  return (
    <PermissionGate requiredPermissions={[permissions.procurementOneDocumentsCreate]}>
      <div className={PROCUREMENT_DOCUMENT_COMPOSE_SHELL_CLASS}>
        <ProcurementOnePageHeader
          eyebrow={
            <Link href={labels.listHref} className="hover:text-primary">
              {labels.listLabel}
            </Link>
          }
          title={mode === "edit" ? labels.editTitle : labels.createTitle}
          description="Form fields and approval steps are driven by your tenant E-Approval configuration."
        />

        <ProcurementSchemaComposePanel
          kind={kind}
          mode={mode}
          documentId={documentId}
          prId={prId}
          poId={poId}
          initialValues={initialValues}
          existingAttachments={existingAttachments}
          lockedParentSubmissionId={resolvedLockedParentSubmissionId}
          onCancel={() => router.push(mode === "edit" && documentId ? labels.detailHref(documentId) : labels.listHref)}
          onSaved={({ id }) => router.push(labels.detailHref(id))}
        />
      </div>
    </PermissionGate>
  );
}
