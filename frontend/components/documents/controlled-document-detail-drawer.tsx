"use client";

import Link from "next/link";
import {
  useMutation,
  useQuery,
  useQueryClient,
} from "@tanstack/react-query";
import { useRef, useState } from "react";
import {
  AlertTriangle,
  Archive,
  CalendarDays,
  CheckCircle2,
  Clock,
  Download,
  ExternalLink,
  FileText,
  GitBranch,
  History,
  Paperclip,
  Tag,
  UploadCloud,
  Users,
} from "lucide-react";

import { Button, buttonVariants } from "@/components/ui/button";
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from "@/components/ui/sheet";
import {
  fetchControlledDocument,
  getControlledRevisionDownloadInfo,
  markControlledDocumentObsolete,
  uploadControlledRevisionFile,
} from "@/lib/api/modules/controlled-documents-api";
import { apiClient } from "@/lib/api/client";
import { getErrorMessage } from "@/lib/api/error";
import { controlledDocumentSubmissionUrl } from "@/modules/documents/controlled-document-submission-url";
import { useNotificationStore } from "@/stores/notification-store";
import { cn } from "@/lib/utils";

type Props = {
  documentId: string | null;
  canCreate: boolean;
  canManage: boolean;
  onClose: () => void;
};

function StatusBadge({ status }: { status: string }) {
  if (status === "obsolete") {
    return (
      <span className="inline-flex items-center gap-1.5 rounded-full bg-muted px-2.5 py-1 text-xs font-medium text-muted-foreground">
        <Archive className="h-3 w-3" />
        Obsolete
      </span>
    );
  }
  return (
    <span className="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">
      <CheckCircle2 className="h-3 w-3" />
      Published
    </span>
  );
}

function RevisionStatusPill({ status }: { status: string }) {
  if (status === "superseded") {
    return (
      <span className="rounded bg-muted px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground">
        Superseded
      </span>
    );
  }
  if (status === "published") {
    return (
      <span className="rounded bg-emerald-50 px-1.5 py-0.5 text-[10px] font-medium text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">
        Published
      </span>
    );
  }
  return (
    <span className="rounded bg-muted px-1.5 py-0.5 text-[10px] font-medium capitalize text-muted-foreground">
      {status}
    </span>
  );
}

function MetaRow({
  icon: Icon,
  label,
  value,
}: {
  icon: React.ElementType;
  label: string;
  value: React.ReactNode;
}) {
  return (
    <div className="flex items-start gap-3">
      <span className="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-muted text-muted-foreground">
        <Icon className="h-3.5 w-3.5" />
      </span>
      <div className="min-w-0">
        <p className="text-[11px] font-medium text-muted-foreground">{label}</p>
        <p className="mt-0.5 text-sm text-foreground">{value}</p>
      </div>
    </div>
  );
}

function LoadingSkeleton() {
  return (
    <div className="mt-6 animate-pulse space-y-4">
      <div className="h-5 w-2/3 rounded bg-muted" />
      <div className="h-4 w-1/3 rounded bg-muted" />
      <div className="mt-4 grid grid-cols-2 gap-4">
        {Array.from({ length: 6 }).map((_, i) => (
          <div key={i} className="space-y-1.5">
            <div className="h-3 w-1/2 rounded bg-muted" />
            <div className="h-4 w-3/4 rounded bg-muted" />
          </div>
        ))}
      </div>
      <div className="h-24 rounded-lg bg-muted" />
      <div className="h-20 rounded-lg bg-muted" />
    </div>
  );
}

export function ControlledDocumentDetailDrawer({ documentId, canCreate, canManage, onClose }: Props) {
  const push = useNotificationStore((s) => s.push);
  const queryClient = useQueryClient();
  const fileInputRef = useRef<HTMLInputElement>(null);
  const [uploadRevisionId, setUploadRevisionId] = useState<string | null>(null);

  const query = useQuery({
    queryKey: ["documents", "controlled", documentId],
    queryFn: () => fetchControlledDocument(documentId!),
    enabled: documentId !== null,
  });

  const obsoleteMutation = useMutation({
    mutationFn: markControlledDocumentObsolete,
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["documents", "controlled"] });
      push({ level: "success", title: "Document marked obsolete" });
    },
    onError: (error) => {
      push({ level: "error", title: "Update failed", message: getErrorMessage(error) });
    },
  });

  const uploadMutation = useMutation({
    mutationFn: ({ revisionId, file }: { revisionId: string; file: File }) =>
      uploadControlledRevisionFile(documentId!, revisionId, file),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["documents", "controlled", documentId] });
      push({ level: "success", title: "File attached" });
      setUploadRevisionId(null);
    },
    onError: (error) => {
      push({ level: "error", title: "Upload failed", message: getErrorMessage(error) });
    },
  });

  const doc = query.data;
  const isObsolete = doc?.status === "obsolete";

  return (
    <Sheet open={documentId !== null} onOpenChange={(open) => !open && onClose()}>
      <SheetContent className="flex w-full flex-col gap-0 overflow-hidden p-0 sm:max-w-md">
        {/* ── Header ── */}
        <SheetHeader className="border-b border-border px-5 pb-4 pt-5">
          <div className="flex items-start justify-between gap-3">
            <div className="min-w-0 flex-1">
              <SheetTitle className="truncate text-base font-semibold leading-snug">
                {doc?.title ?? "Document"}
              </SheetTitle>
              <SheetDescription className="mt-1 flex items-center gap-2">
                <code className="rounded bg-muted px-1.5 py-0.5 font-mono text-[11px] text-foreground">
                  {doc?.document_code ?? "—"}
                </code>
                {doc ? <StatusBadge status={doc.status} /> : null}
              </SheetDescription>
            </div>
          </div>
        </SheetHeader>

        {/* ── Body ── */}
        <div className="flex-1 overflow-y-auto px-5 py-5">
          {query.isLoading ? (
            <LoadingSkeleton />
          ) : doc ? (
            <div className="space-y-6">

              {/* Metadata grid */}
              <div className="grid grid-cols-2 gap-x-4 gap-y-4">
                <MetaRow icon={Tag} label="Document type" value={doc.document_type ?? "—"} />
                <MetaRow icon={Users} label="Department" value={doc.department ?? "—"} />
                <MetaRow
                  icon={GitBranch}
                  label="Current revision"
                  value={
                    <span className="font-mono font-medium">Rev {doc.current_revision}</span>
                  }
                />
                <MetaRow
                  icon={CheckCircle2}
                  label="Status"
                  value={<StatusBadge status={doc.status} />}
                />
                <MetaRow
                  icon={CalendarDays}
                  label="Effective date"
                  value={doc.effective_date ?? "—"}
                />
                <MetaRow
                  icon={Clock}
                  label="Next review"
                  value={
                    doc.next_review_date ? (
                      <span
                        className={cn(
                          doc.next_review_date < new Date().toISOString().slice(0, 10) &&
                            "text-amber-600 dark:text-amber-400",
                        )}
                      >
                        {doc.next_review_date}
                      </span>
                    ) : (
                      "—"
                    )
                  }
                />
              </div>

              {/* Primary actions */}
              {!isObsolete ? (
                <div className="flex flex-wrap gap-2 rounded-xl border border-border bg-muted/30 px-4 py-3">
                  {canCreate && doc.e_approval_form_id ? (
                    <Link
                      href={controlledDocumentSubmissionUrl({
                        formId: doc.e_approval_form_id,
                        mode: "revision",
                        documentCode: doc.document_code,
                      })}
                      className={buttonVariants({ size: "sm" })}
                    >
                      <GitBranch className="mr-1.5 h-3.5 w-3.5" />
                      Submit revision
                    </Link>
                  ) : null}
                  {canManage ? (
                    <Button
                      type="button"
                      variant="outline"
                      size="sm"
                      disabled={obsoleteMutation.isPending}
                      onClick={() => obsoleteMutation.mutate(doc.id)}
                    >
                      <Archive className="mr-1.5 h-3.5 w-3.5" />
                      {obsoleteMutation.isPending ? "Updating…" : "Mark obsolete"}
                    </Button>
                  ) : null}
                </div>
              ) : (
                <div className="flex items-center gap-2 rounded-xl border border-muted bg-muted/20 px-4 py-3 text-sm text-muted-foreground">
                  <AlertTriangle className="h-4 w-4 shrink-0" />
                  This document is obsolete and no longer in active use.
                </div>
              )}

              {/* Revision history */}
              <section>
                <div className="mb-3 flex items-center gap-2">
                  <History className="h-4 w-4 text-muted-foreground" />
                  <h3 className="text-sm font-medium text-foreground">Revision history</h3>
                  <span className="ml-auto rounded-full bg-muted px-2 py-0.5 text-[11px] font-medium text-muted-foreground">
                    {doc.revisions.length}
                  </span>
                </div>

                {doc.revisions.length === 0 ? (
                  <p className="rounded-lg border border-dashed border-border py-6 text-center text-sm text-muted-foreground">
                    No revisions yet
                  </p>
                ) : (
                  <div className="space-y-2">
                    {doc.revisions.map((revision, idx) => (
                      <div
                        key={revision.id}
                        className={cn(
                          "rounded-xl border bg-card px-4 py-3 text-sm",
                          idx === 0
                            ? "border-primary/20 bg-primary/[0.02]"
                            : "border-border",
                        )}
                      >
                        {/* Rev header */}
                        <div className="flex items-center justify-between gap-2">
                          <div className="flex items-center gap-2">
                            <span
                              className={cn(
                                "font-mono text-xs font-semibold",
                                idx === 0 ? "text-primary" : "text-foreground",
                              )}
                            >
                              Rev {revision.revision_number}
                            </span>
                            <RevisionStatusPill status={revision.status} />
                          </div>
                          {revision.approved_at ? (
                            <span className="text-[11px] text-muted-foreground">
                              {new Date(revision.approved_at).toLocaleDateString()}
                            </span>
                          ) : null}
                        </div>

                        {/* Change summary */}
                        {revision.change_summary ? (
                          <p className="mt-2 text-xs text-muted-foreground">
                            {revision.change_summary}
                          </p>
                        ) : null}

                        {/* Approved by */}
                        {revision.approved_by_name ? (
                          <p className="mt-1.5 flex items-center gap-1 text-[11px] text-muted-foreground">
                            <CheckCircle2 className="h-3 w-3 text-emerald-500" />
                            Approved by {revision.approved_by_name}
                          </p>
                        ) : null}

                        {/* File / E-Approval links */}
                        <div className="mt-2.5 flex flex-wrap items-center gap-2 border-t border-border/60 pt-2.5">
                          {revision.has_file ? (
                            <Button
                              type="button"
                              variant="outline"
                              size="sm"
                              className="h-7 gap-1.5 text-xs"
                              onClick={async () => {
                                try {
                                  const info = await getControlledRevisionDownloadInfo(doc.id, revision.id);
                                  if (info.stream) {
                                    // Local-disk file: fetch through the authenticated API client
                                    // (carries bearer token + tenant headers) and trigger a blob download.
                                    const blob = await apiClient
                                      .get<Blob>(info.url, { responseType: "blob" })
                                      .then((r) => r.data);
                                    const objectUrl = URL.createObjectURL(blob);
                                    const a = document.createElement("a");
                                    a.href = objectUrl;
                                    a.download = revision.original_filename ?? "document";
                                    a.click();
                                    URL.revokeObjectURL(objectUrl);
                                  } else {
                                    // S3 presigned URL — open directly in a new tab.
                                    window.open(info.url, "_blank", "noopener,noreferrer");
                                  }
                                } catch (error) {
                                  push({
                                    level: "error",
                                    title: "Download failed",
                                    message: getErrorMessage(error),
                                  });
                                }
                              }}
                            >
                              <Download className="h-3 w-3" />
                              {revision.original_filename
                                ? revision.original_filename.length > 22
                                  ? revision.original_filename.slice(0, 22) + "…"
                                  : revision.original_filename
                                : "Download"}
                            </Button>
                          ) : canManage ? (
                            <Button
                              type="button"
                              variant="outline"
                              size="sm"
                              className="h-7 gap-1.5 text-xs"
                              onClick={() => {
                                setUploadRevisionId(revision.id);
                                fileInputRef.current?.click();
                              }}
                            >
                              {uploadMutation.isPending && uploadRevisionId === revision.id ? (
                                <UploadCloud className="h-3 w-3 animate-pulse" />
                              ) : (
                                <Paperclip className="h-3 w-3" />
                              )}
                              Attach file
                            </Button>
                          ) : (
                            <span className="flex items-center gap-1 text-[11px] text-muted-foreground">
                              <FileText className="h-3 w-3" />
                              No file attached
                            </span>
                          )}

                          {revision.e_approval_submission_id ? (
                            <a
                              href={`/e-approval/submissions/${revision.e_approval_submission_id}`}
                              className="flex items-center gap-1 text-xs font-medium text-primary hover:underline"
                            >
                              <ExternalLink className="h-3 w-3" />
                              {revision.e_approval_document_no ?? "E-Approval"}
                            </a>
                          ) : null}
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </section>
            </div>
          ) : null}
        </div>

        <input
          ref={fileInputRef}
          type="file"
          className="hidden"
          onChange={(event) => {
            const file = event.target.files?.[0];
            if (file && uploadRevisionId) {
              uploadMutation.mutate({ revisionId: uploadRevisionId, file });
            }
            event.target.value = "";
          }}
        />
      </SheetContent>
    </Sheet>
  );
}
