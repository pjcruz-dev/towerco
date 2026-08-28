"use client";

import Link from "next/link";
import { useParams, useSearchParams } from "next/navigation";
import { useEffect, useState } from "react";

import { PermissionGate } from "@/components/layout/permission-gate";
import { Spinner } from "@/components/ui/spinner";
import { downloadEApprovalAttachment } from "@/lib/api/modules/e-approval-api";
import { getErrorMessage } from "@/lib/api/error";
import { permissions } from "@/lib/rbac/permissions";
import { useAuthStore } from "@/stores/auth-store";

/**
 * Opens from Excel/export hyperlinks on the tenant UI host.
 * Fetches the file with the SPA auth token (local + production), then triggers download.
 * Raw /api/v1/... links on the UI host 404 in Next.js without an Nginx /api proxy.
 */
export function EApprovalAttachmentDownloadPageClient() {
  const params = useParams<{ id: string }>();
  const searchParams = useSearchParams();
  const attachmentId = typeof params.id === "string" ? params.id : "";
  const suggestedName = (searchParams.get("name") ?? "").trim() || "attachment";
  const permissionsReady = useAuthStore((state) => state.permissionsReady);
  const user = useAuthStore((state) => state.user);
  const [status, setStatus] = useState<"idle" | "loading" | "done" | "error">("idle");
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!permissionsReady || !user || !attachmentId || status !== "idle") {
      return;
    }

    let cancelled = false;
    setStatus("loading");

    void (async () => {
      try {
        const blob = await downloadEApprovalAttachment(attachmentId);
        if (cancelled) return;

        const url = window.URL.createObjectURL(blob);
        const anchor = document.createElement("a");
        anchor.href = url;
        anchor.download = suggestedName;
        anchor.rel = "noopener";
        document.body.appendChild(anchor);
        anchor.click();
        anchor.remove();
        window.URL.revokeObjectURL(url);
        setStatus("done");
      } catch (err) {
        if (cancelled) return;
        setError(getErrorMessage(err));
        setStatus("error");
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [attachmentId, permissionsReady, status, suggestedName, user]);

  return (
    <PermissionGate requiredPermissions={[permissions.eApprovalSubmissionsView]}>
      <div className="mx-auto flex min-h-[40vh] max-w-lg flex-col items-center justify-center gap-3 px-4 text-center">
        {status === "loading" || status === "idle" ? (
          <>
            <Spinner className="size-5" />
            <p className="text-sm text-muted-foreground">Preparing download…</p>
          </>
        ) : null}
        {status === "done" ? (
          <>
            <p className="text-sm font-medium text-foreground">Download started</p>
            <p className="text-sm text-muted-foreground">
              If the file did not appear, check your browser download bar.
            </p>
            <Link href="/e-approval/submissions" className="text-sm text-sky-600 hover:underline">
              Back to submissions
            </Link>
          </>
        ) : null}
        {status === "error" ? (
          <>
            <p className="text-sm font-medium text-destructive">Could not download attachment</p>
            <p className="text-sm text-muted-foreground">{error}</p>
            <Link href="/e-approval/submissions" className="text-sm text-sky-600 hover:underline">
              Back to submissions
            </Link>
          </>
        ) : null}
      </div>
    </PermissionGate>
  );
}
