"use client";

import Link from "next/link";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useRouter, useSearchParams } from "next/navigation";
import { useRef, useState } from "react";

import { WorkspacePageHeader } from "@/components/layout/workspace-page-header";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Button } from "@/components/ui/button";
import { Spinner } from "@/components/ui/spinner";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { apiClient } from "@/lib/api/client";
import {
  fetchControlledDocumentObsoleteBoard,
  getControlledRevisionDownloadInfo,
  uploadControlledRevisionFile,
  type ControlledDocumentObsoleteRow,
} from "@/lib/api/modules/controlled-documents-api";
import { getErrorMessage } from "@/lib/api/error";
import { usePermission } from "@/hooks/use-permission";
import { permissions } from "@/lib/rbac/permissions";
import { useNotificationStore } from "@/stores/notification-store";
import { cn } from "@/lib/utils";

type FileView = "obsolete" | "approved" | "pending";

const FILE_VIEWS: { id: FileView; label: string; empty: string; note: string }[] = [
  {
    id: "obsolete",
    label: "Obsolete",
    empty: "No obsolete approved revisions.",
    note: "Missing files are listed first. Upload attaches the file to that revision. The live revision stays on the register.",
  },
  {
    id: "approved",
    label: "Approved files",
    empty: "No live approved files.",
    note: "The current published revision, and only when it already has a file.",
  },
  {
    id: "pending",
    label: "Pending",
    empty: "No revisions in approval.",
    note: "Revisions still in approval. Missing files are listed first so you can upload them.",
  },
];

function readFileView(value: string | null): FileView {
  return value === "approved" || value === "pending" ? value : "obsolete";
}

function formatDay(value: string | null): string {
  if (!value) {
    return "—";
  }
  const dateOnly = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value);
  const date = dateOnly
    ? new Date(Number(dateOnly[1]), Number(dateOnly[2]) - 1, Number(dateOnly[3]))
    : new Date(value);
  return Number.isNaN(date.getTime()) ? "—" : date.toLocaleDateString();
}

function displayTitle(title: string): string {
  return title.replace(/_+/g, " ").replace(/\s+/g, " ").trim();
}

function FileCell({
  row,
  canUpload,
  uploading,
  onUpload,
}: {
  row: ControlledDocumentObsoleteRow;
  canUpload: boolean;
  uploading: boolean;
  onUpload: (file: File) => void;
}) {
  const push = useNotificationStore((state) => state.push);
  const inputRef = useRef<HTMLInputElement>(null);
  if (!row.has_file || !row.revision_id) {
    if (!canUpload || !row.revision_id) {
      return <span className="text-muted-foreground">No file</span>;
    }

    return (
      <>
        <input
          ref={inputRef}
          type="file"
          className="sr-only"
          onChange={(event) => {
            const file = event.target.files?.[0];
            event.target.value = "";
            if (file) onUpload(file);
          }}
        />
        <Button
          type="button"
          variant="outline"
          size="sm"
          className="h-7"
          disabled={uploading}
          onClick={() => inputRef.current?.click()}
        >
          {uploading ? "Uploading…" : "Upload"}
        </Button>
      </>
    );
  }

  return (
    <Button
      type="button"
      variant="outline"
      size="sm"
      className="h-7 max-w-56 truncate"
      onClick={() => {
        void (async () => {
          try {
            const info = await getControlledRevisionDownloadInfo(row.document_id, row.revision_id!);
            if (info.stream) {
              const blob = await apiClient.get<Blob>(info.url, { responseType: "blob" }).then((response) => response.data);
              const objectUrl = URL.createObjectURL(blob);
              const link = document.createElement("a");
              link.href = objectUrl;
              link.download = row.filename ?? "document";
              link.click();
              URL.revokeObjectURL(objectUrl);
            } else {
              window.open(info.url, "_blank", "noopener,noreferrer");
            }
          } catch (error) {
            push({ level: "error", title: "Download failed", message: getErrorMessage(error) });
          }
        })();
      }}
    >
      {row.filename ?? "Download"}
    </Button>
  );
}

function BoardTable({
  rows,
  empty,
  canUpload,
  uploadingId,
  onUpload,
}: {
  rows: ControlledDocumentObsoleteRow[];
  empty: string;
  canUpload: boolean;
  uploadingId: string | null;
  onUpload: (row: ControlledDocumentObsoleteRow, file: File) => void;
}) {
  if (rows.length === 0) {
    return <p className="px-4 py-8 text-center text-sm text-muted-foreground">{empty}</p>;
  }

  return (
    <div className="overflow-x-auto">
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>Code</TableHead>
            <TableHead>Title</TableHead>
            <TableHead>Department</TableHead>
            <TableHead className="text-right">Revision</TableHead>
            <TableHead>Effective</TableHead>
            <TableHead>Approved</TableHead>
            <TableHead>Attachment</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {rows.map((row) => (
            <TableRow key={`${row.document_id}-${row.revision_id ?? "document"}-${row.standing}`}>
              <TableCell>
                <Link
                  href={`/documents/controlled/register?q=${encodeURIComponent(row.document_code)}`}
                  className="font-medium text-foreground hover:underline"
                >
                  {row.document_code}
                </Link>
              </TableCell>
              <TableCell className="max-w-80 truncate" title={row.title}>
                {displayTitle(row.title)}
              </TableCell>
              <TableCell>{row.department || "—"}</TableCell>
              <TableCell className="text-right tabular-nums">{row.revision_number ?? "—"}</TableCell>
              <TableCell className="tabular-nums whitespace-nowrap">{formatDay(row.effective_date)}</TableCell>
              <TableCell className="tabular-nums whitespace-nowrap">{formatDay(row.approved_at)}</TableCell>
              <TableCell>
                <FileCell
                  row={row}
                  canUpload={canUpload}
                  uploading={uploadingId === row.revision_id}
                  onUpload={(file) => onUpload(row, file)}
                />
              </TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </div>
  );
}

export function ControlledDocumentObsoletePageClient() {
  const canManage = usePermission([permissions.documentsControlledManage]);
  const queryClient = useQueryClient();
  const push = useNotificationStore((state) => state.push);
  const router = useRouter();
  const searchParams = useSearchParams();
  const view = readFileView(searchParams.get("view"));
  const viewMeta = FILE_VIEWS.find((item) => item.id === view) ?? FILE_VIEWS[0]!;
  const [uploadingId, setUploadingId] = useState<string | null>(null);
  const boardQuery = useQuery({
    queryKey: ["documents", "controlled", "obsolete"],
    queryFn: fetchControlledDocumentObsoleteBoard,
  });

  function selectView(next: FileView) {
    const params = new URLSearchParams(searchParams.toString());
    if (next === "obsolete") {
      params.delete("view");
    } else {
      params.set("view", next);
    }
    const query = params.toString();
    router.replace(query ? `/documents/controlled/obsolete?${query}` : "/documents/controlled/obsolete", {
      scroll: false,
    });
  }
  const uploadMutation = useMutation({
    mutationFn: ({ row, file }: { row: ControlledDocumentObsoleteRow; file: File }) => {
      if (!row.revision_id) {
        throw new Error("This revision cannot take a file.");
      }
      setUploadingId(row.revision_id);
      return uploadControlledRevisionFile(row.document_id, row.revision_id, file);
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["documents", "controlled", "obsolete"] });
      push({ level: "success", title: "File uploaded" });
    },
    onError: (error) => {
      push({ level: "error", title: "Upload failed", message: getErrorMessage(error) });
    },
    onSettled: () => setUploadingId(null),
  });
  const data = boardQuery.data;
  const rows = data?.[view] ?? [];

  return (
    <PermissionGate requiredPermissions={[permissions.documentsControlledView]}>
      <div className="space-y-4">
        <WorkspacePageHeader
          title="Files"
          description="Obsolete revisions, the live approved file, and revisions still in approval."
          actions={
            <div className="flex flex-wrap items-center justify-end gap-1.5">
              <Button size="sm" variant="outline" render={<Link href="/documents/controlled" />}>
                Overview
              </Button>
              <Button
                size="sm"
                variant="outline"
                onClick={() => void boardQuery.refetch()}
                disabled={boardQuery.isFetching}
              >
                {boardQuery.isFetching ? <Spinner className="mr-1.5 size-3.5" /> : null}
                Refresh
              </Button>
            </div>
          }
        />

        {boardQuery.isError ? (
          <p className="text-sm text-destructive">{getErrorMessage(boardQuery.error)}</p>
        ) : null}

        <div className="grid gap-3 sm:grid-cols-3" role="group" aria-label="File status">
          {FILE_VIEWS.map((item) => (
            <button
              key={item.id}
              type="button"
              aria-pressed={view === item.id}
              onClick={() => selectView(item.id)}
              className={cn(
                "rounded-xl border border-border bg-card px-4 py-3 text-left shadow-sm transition-colors",
                "focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background",
                view === item.id ? "border-foreground" : "hover:bg-muted/40",
              )}
            >
              <p className="text-xs text-muted-foreground">{item.label}</p>
              <p className="mt-1 text-2xl font-medium tabular-nums text-foreground">
                {data ? data.counts[item.id] : "—"}
              </p>
            </button>
          ))}
        </div>

        <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
          <div className="border-b border-border px-4 py-3">
            <h2 className="text-sm font-medium text-foreground">{viewMeta.label}</h2>
            <p className="mt-0.5 text-xs text-muted-foreground">{viewMeta.note}</p>
          </div>
          {boardQuery.isLoading ? (
            <p className="px-4 py-8 text-center text-sm text-muted-foreground">Loading files…</p>
          ) : (
            <BoardTable
              rows={rows}
              empty={viewMeta.empty}
              canUpload={canManage && view !== "approved"}
              uploadingId={uploadingId}
              onUpload={(row, file) => uploadMutation.mutate({ row, file })}
            />
          )}
        </div>
      </div>
    </PermissionGate>
  );
}
