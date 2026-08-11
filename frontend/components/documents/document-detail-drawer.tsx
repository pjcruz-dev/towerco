"use client";

import Link from "next/link";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useEffect, useRef, useState } from "react";
import {
  Clock,
  Download,
  FileText,
  History,
  Upload,
} from "lucide-react";

import { Button } from "@/components/ui/button";
import { DatePicker } from "@/components/ui/date-picker";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { Spinner } from "@/components/ui/spinner";
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from "@/components/ui/sheet";
import { formatFileSize } from "@/components/ticketing/ticketing-utils";
import {
  fetchDocumentDetail,
  updateSiteDocumentMetadata,
  uploadDocumentVersion,
} from "@/lib/api/modules/documents-api";
import { getErrorMessage } from "@/lib/api/error";
import { useNotificationStore } from "@/stores/notification-store";

type Props = {
  documentId: string | null;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  siteId?: string;
  canUpload?: boolean;
  canRequestApproval?: boolean;
  onRequestApproval?: (documentId: string) => void;
};

const ACTIVITY_LABELS: Record<string, string> = {
  uploaded: "Uploaded",
  metadata_updated: "Metadata updated",
  version_uploaded: "New version uploaded",
  approval_requested: "Approval requested",
  migrated_from_rollout: "Imported from rollout",
};

function formatRelative(iso: string | null | undefined): string {
  if (!iso) return "—";
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return iso;
  const diff = Date.now() - date.getTime();
  const mins = Math.floor(diff / 60000);
  if (mins < 60) return `${mins}m ago`;
  const hours = Math.floor(mins / 60);
  if (hours < 48) return `${hours}h ago`;
  return date.toLocaleDateString();
}

function activityDetail(event: string, metadata: Record<string, unknown> | null): string | null {
  if (!metadata) return null;
  if (event === "version_uploaded" && metadata.version != null) {
    return `Version ${String(metadata.version)}`;
  }
  if (event === "approval_requested" && metadata.document_no != null) {
    return String(metadata.document_no);
  }
  if (event === "uploaded" && metadata.upload_method != null) {
    return String(metadata.upload_method) === "presigned" ? "Direct S3 upload" : "Multipart upload";
  }
  if (event === "migrated_from_rollout" && metadata.rollout_ref != null) {
    return String(metadata.rollout_ref);
  }
  return null;
}

export function DocumentDetailDrawer({
  documentId,
  open,
  onOpenChange,
  siteId,
  canUpload = false,
  canRequestApproval = false,
  onRequestApproval,
}: Props) {
  const push = useNotificationStore((s) => s.push);
  const queryClient = useQueryClient();
  const versionInputRef = useRef<HTMLInputElement>(null);
  const [title, setTitle] = useState("");
  const [status, setStatus] = useState("draft");
  const [expiresAt, setExpiresAt] = useState("");

  const detailQuery = useQuery({
    queryKey: ["documents", "detail", documentId],
    queryFn: () => fetchDocumentDetail(documentId!),
    enabled: open && !!documentId,
  });

  const detail = detailQuery.data;

  useEffect(() => {
    if (!detail) return;
    setTitle(detail.title);
    setStatus(detail.status);
    setExpiresAt(detail.expires_at ? detail.expires_at.slice(0, 10) : "");
  }, [detail]);

  const invalidateRelated = () => {
    queryClient.invalidateQueries({ queryKey: ["documents", "detail", documentId] });
    if (siteId) {
      queryClient.invalidateQueries({ queryKey: ["documents", "files", siteId] });
      queryClient.invalidateQueries({ queryKey: ["documents", "workspace", siteId] });
      queryClient.invalidateQueries({ queryKey: ["documents", "gate-checklist", siteId] });
    }
    queryClient.invalidateQueries({ queryKey: ["documents", "expiring"] });
  };

  const metadataMutation = useMutation({
    mutationFn: () =>
      updateSiteDocumentMetadata(documentId!, {
        title: title.trim() || undefined,
        status,
        expires_at: expiresAt ? expiresAt : null,
      }),
    onSuccess: () => {
      invalidateRelated();
      push({ level: "success", title: "Document updated" });
    },
    onError: (e) =>
      push({ level: "error", title: "Update failed", message: getErrorMessage(e) }),
  });

  const versionMutation = useMutation({
    mutationFn: (file: File) => uploadDocumentVersion(documentId!, file),
    onSuccess: () => {
      invalidateRelated();
      push({ level: "success", title: "New version uploaded" });
    },
    onError: (e) =>
      push({ level: "error", title: "Version upload failed", message: getErrorMessage(e) }),
  });

  const showRequestApproval =
    canRequestApproval &&
    detail &&
    detail.approval_status !== "pending" &&
    detail.approval_status !== "approved";

  const metadataDirty =
    detail != null &&
    (title.trim() !== detail.title ||
      status !== detail.status ||
      (expiresAt || "") !== (detail.expires_at ? detail.expires_at.slice(0, 10) : ""));

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent side="right" className="flex w-full flex-col sm:max-w-lg">
        <SheetHeader>
          <SheetTitle className="flex items-start gap-2 pr-6">
            <FileText className="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground" />
            <span className="line-clamp-2">{detail?.title ?? "Document"}</span>
          </SheetTitle>
          <SheetDescription>
            {detail
              ? `${detail.original_filename} · v${detail.version} · ${formatFileSize(detail.size_bytes)}`
              : "Loading document details…"}
          </SheetDescription>
        </SheetHeader>

        <div className="flex flex-1 flex-col gap-5 overflow-y-auto px-4 pb-4">
          {detailQuery.isLoading ? (
            <div className="flex items-center gap-2 text-sm text-muted-foreground">
              <Spinner />
              Loading…
            </div>
          ) : null}

          {detailQuery.isError ? (
            <p className="text-sm text-destructive">Could not load document details.</p>
          ) : null}

          {detail ? (
            <>
              <div className="flex flex-wrap gap-2">
                {detail.download_url ? (
                  <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    onClick={() =>
                      window.open(detail.download_url, "_blank", "noopener,noreferrer")
                    }
                  >
                    <Download className="mr-1.5 h-3.5 w-3.5" />
                    Download
                  </Button>
                ) : null}
                {canUpload ? (
                  <>
                    <input
                      ref={versionInputRef}
                      type="file"
                      className="sr-only"
                      onChange={(e) => {
                        const file = e.target.files?.[0];
                        if (file) versionMutation.mutate(file);
                        e.target.value = "";
                      }}
                    />
                    <Button
                      type="button"
                      size="sm"
                      variant="outline"
                      disabled={versionMutation.isPending}
                      onClick={() => versionInputRef.current?.click()}
                    >
                      <Upload className="mr-1.5 h-3.5 w-3.5" />
                      New version
                    </Button>
                  </>
                ) : null}
                {showRequestApproval && onRequestApproval ? (
                  <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    onClick={() => onRequestApproval(detail.id)}
                  >
                    Request approval
                  </Button>
                ) : null}
              </div>

              <section className="space-y-3 rounded-lg border border-border p-3">
                <h3 className="text-sm font-medium">Details</h3>
                <dl className="grid gap-3 text-sm sm:grid-cols-2">
                  <div>
                    <dt className="text-xs text-muted-foreground">MIME type</dt>
                    <dd className="mt-0.5 break-all">{detail.mime_type}</dd>
                  </div>
                  <div>
                    <dt className="text-xs text-muted-foreground">Uploaded</dt>
                    <dd className="mt-0.5">
                      {formatRelative(detail.uploaded_at)}
                      {detail.uploaded_by ? ` · ${detail.uploaded_by.name}` : ""}
                    </dd>
                  </div>
                  <div>
                    <dt className="text-xs text-muted-foreground">Last touch</dt>
                    <dd className="mt-0.5">
                      {formatRelative(detail.last_touched_at)}
                      {detail.last_touched_by ? ` · ${detail.last_touched_by.name}` : ""}
                    </dd>
                  </div>
                  <div>
                    <dt className="text-xs text-muted-foreground">Approval</dt>
                    <dd className="mt-0.5">
                      {detail.e_approval_submission ? (
                        <Link
                          href={detail.e_approval_submission.href}
                          className="text-primary hover:underline"
                        >
                          {detail.e_approval_submission.document_no}
                        </Link>
                      ) : (
                        <span className="text-muted-foreground">
                          {detail.approval_status ?? "none"}
                        </span>
                      )}
                    </dd>
                  </div>
                </dl>
              </section>

              {canUpload ? (
                <section className="space-y-3 rounded-lg border border-border p-3">
                  <h3 className="text-sm font-medium">Edit metadata</h3>
                  <div className="space-y-3">
                    <div>
                      <Label className="text-xs">Title</Label>
                      <Input
                        className="mt-1 h-9"
                        value={title}
                        onChange={(e) => setTitle(e.target.value)}
                      />
                    </div>
                    <div className="grid gap-3 sm:grid-cols-2">
                      <div>
                        <Label className="text-xs">Status</Label>
                        <Select
                          className="mt-1 h-9 text-sm"
                          value={status}
                          onChange={(e) => setStatus(e.target.value)}
                        >
                          <option value="draft">Draft</option>
                          <option value="final">Final</option>
                          <option value="superseded">Superseded</option>
                        </Select>
                      </div>
                      <div>
                        <Label className="text-xs">Expires</Label>
                        <DatePicker
                          className="mt-1 h-9"
                          value={expiresAt}
                          onChange={setExpiresAt}
                        />
                      </div>
                    </div>
                    <Button
                      type="button"
                      size="sm"
                      disabled={!metadataDirty || metadataMutation.isPending}
                      onClick={() => metadataMutation.mutate()}
                    >
                      Save changes
                    </Button>
                  </div>
                </section>
              ) : null}

              <section className="space-y-2">
                <h3 className="flex items-center gap-1.5 text-sm font-medium">
                  <History className="h-3.5 w-3.5 text-muted-foreground" />
                  Version history
                </h3>
                <ul className="divide-y divide-border rounded-lg border border-border text-[13px]">
                  {detail.versions.length === 0 ? (
                    <li className="px-3 py-2 text-muted-foreground">No versions recorded.</li>
                  ) : (
                    detail.versions.map((version) => (
                      <li
                        key={version.version}
                        className="flex items-center justify-between gap-2 px-3 py-2"
                      >
                        <div className="min-w-0">
                          <p className="truncate font-medium">
                            v{version.version}
                            {version.version === detail.version ? (
                              <span className="ml-1.5 text-xs font-normal text-muted-foreground">
                                current
                              </span>
                            ) : null}
                          </p>
                          <p className="truncate text-xs text-muted-foreground">
                            {version.original_filename}
                          </p>
                        </div>
                        <div className="shrink-0 text-right text-xs text-muted-foreground">
                          <p>{formatFileSize(version.size_bytes)}</p>
                          <p>{formatRelative(version.uploaded_at)}</p>
                        </div>
                      </li>
                    ))
                  )}
                </ul>
              </section>

              <section className="space-y-2">
                <h3 className="flex items-center gap-1.5 text-sm font-medium">
                  <Clock className="h-3.5 w-3.5 text-muted-foreground" />
                  Activity
                </h3>
                <ul className="space-y-2">
                  {detail.activities.length === 0 ? (
                    <li className="text-sm text-muted-foreground">No activity yet.</li>
                  ) : (
                    detail.activities.map((activity) => {
                      const detailLine = activityDetail(activity.event, activity.metadata);
                      return (
                        <li
                          key={activity.id}
                          className="rounded-md border border-border/60 px-3 py-2 text-[13px]"
                        >
                          <div className="flex items-start justify-between gap-2">
                            <p className="font-medium">
                              {ACTIVITY_LABELS[activity.event] ?? activity.event}
                            </p>
                            <span className="shrink-0 text-xs text-muted-foreground">
                              {formatRelative(activity.at)}
                            </span>
                          </div>
                          <p className="mt-0.5 text-xs text-muted-foreground">
                            {activity.actor?.name ?? "System"}
                            {detailLine ? ` · ${detailLine}` : ""}
                          </p>
                        </li>
                      );
                    })
                  )}
                </ul>
              </section>
            </>
          ) : null}
        </div>
      </SheetContent>
    </Sheet>
  );
}
