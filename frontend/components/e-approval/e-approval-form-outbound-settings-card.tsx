"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { FileUp, Trash2 } from "lucide-react";
import { useRef } from "react";

import { EApprovalSectionCard } from "@/components/e-approval/e-approval-section-card";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import { getErrorMessage } from "@/lib/api/error";
import {
  deleteEApprovalFormOutboundFile,
  fetchEApprovalFormOutboundFiles,
  uploadEApprovalFormOutboundFile,
} from "@/lib/api/modules/e-approval-api";
import type { FormOutboundEditorSettings } from "@/modules/e-approval/form-outbound-config";
import { useNotificationStore } from "@/stores/notification-store";

type Props = {
  formId: string | null;
  value: FormOutboundEditorSettings;
  onChange: (next: FormOutboundEditorSettings) => void;
  disabled?: boolean;
};

function formatBytes(bytes: number): string {
  if (!Number.isFinite(bytes) || bytes <= 0) return "—";
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

export function EApprovalFormOutboundSettingsCard({ formId, value, onChange, disabled }: Props) {
  const queryClient = useQueryClient();
  const push = useNotificationStore((s) => s.push);
  const inputRef = useRef<HTMLInputElement>(null);

  const patch = (partial: Partial<FormOutboundEditorSettings>) => {
    onChange({ ...value, ...partial });
  };

  const filesQuery = useQuery({
    queryKey: ["e-approval", "form", formId, "outbound-files"],
    queryFn: () => fetchEApprovalFormOutboundFiles(formId!),
    enabled: Boolean(formId) && value.emailPackageOnApprove,
  });

  const uploadMutation = useMutation({
    mutationFn: (file: File) => uploadEApprovalFormOutboundFile(formId!, file),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["e-approval", "form", formId, "outbound-files"] });
      push({ level: "success", title: "Deliverable uploaded" });
    },
    onError: (error) =>
      push({ level: "error", title: "Upload failed", message: getErrorMessage(error) }),
  });

  const deleteMutation = useMutation({
    mutationFn: (fileId: string) => deleteEApprovalFormOutboundFile(fileId),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["e-approval", "form", formId, "outbound-files"] });
      push({ level: "success", title: "Deliverable removed" });
    },
    onError: (error) =>
      push({ level: "error", title: "Could not remove file", message: getErrorMessage(error) }),
  });

  const files = filesQuery.data ?? [];

  return (
    <EApprovalSectionCard
      title="External deliverables"
      description="Upload the files TowerOS should email to the vendor after an external submission is approved. Requires tenant external-approved email to be enabled."
    >
      <div className="flex items-start justify-between gap-3 rounded-lg border border-border bg-muted/20 px-3 py-3">
        <div className="space-y-1">
          <Label htmlFor="ea-outbound-package">Email package on approve</Label>
          <p className="text-xs text-muted-foreground">
            External submitters receive time-limited download links for the uploaded files below after final approval.
          </p>
        </div>
        <Switch
          id="ea-outbound-package"
          checked={value.emailPackageOnApprove}
          disabled={disabled}
          onCheckedChange={(checked) => patch({ emailPackageOnApprove: Boolean(checked) })}
        />
      </div>

      {value.emailPackageOnApprove ? (
        <div className="mt-4 space-y-3">
          <div className="space-y-1">
            <Label>Package files</Label>
            <p className="text-xs text-muted-foreground">
              These are ATC deliverables (signed packs, approvals, etc.) — not the files the vendor uploaded on the form.
            </p>
          </div>

          {!formId ? (
            <p className="text-sm text-muted-foreground">Save the form first, then upload deliverable files.</p>
          ) : (
            <>
              <input
                ref={inputRef}
                type="file"
                className="hidden"
                disabled={disabled || uploadMutation.isPending}
                onChange={(event) => {
                  const file = event.target.files?.[0];
                  event.target.value = "";
                  if (file) {
                    uploadMutation.mutate(file);
                  }
                }}
              />
              <Button
                type="button"
                variant="outline"
                className="gap-1.5"
                disabled={disabled || uploadMutation.isPending}
                onClick={() => inputRef.current?.click()}
              >
                <FileUp className="h-4 w-4" />
                {uploadMutation.isPending ? "Uploading…" : "Upload file"}
              </Button>

              {filesQuery.isLoading ? (
                <p className="text-sm text-muted-foreground">Loading files…</p>
              ) : files.length === 0 ? (
                <p className="text-sm text-muted-foreground">No deliverable files yet. Upload at least one before approving external requests.</p>
              ) : (
                <ul className="divide-y divide-border rounded-lg border border-border">
                  {files.map((file) => (
                    <li key={file.id} className="flex items-center justify-between gap-3 px-3 py-2.5 text-sm">
                      <div className="min-w-0">
                        <p className="truncate font-medium text-foreground">{file.file_name}</p>
                        <p className="text-xs text-muted-foreground">{formatBytes(file.byte_size)}</p>
                      </div>
                      <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        className="shrink-0 gap-1 text-destructive"
                        disabled={disabled || deleteMutation.isPending}
                        onClick={() => deleteMutation.mutate(file.id)}
                      >
                        <Trash2 className="h-3.5 w-3.5" />
                        Remove
                      </Button>
                    </li>
                  ))}
                </ul>
              )}
            </>
          )}
        </div>
      ) : null}
    </EApprovalSectionCard>
  );
}
