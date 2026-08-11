"use client";

import { useEffect, useRef, useState } from "react";

import { OperationalAlert } from "@/components/feedback/operational-alert";
import { getErrorMessage } from "@/lib/api/error";
import { downloadEApprovalPublicPackage } from "@/lib/api/modules/e-approval-public-api";
import { E_APPROVAL_FORM_SHELL_CLASS } from "@/modules/e-approval/form-layout";

type Props = {
  downloadToken: string;
};

type Status = "loading" | "done" | "error";

export function EApprovalPublicPackageDownloadPageClient({ downloadToken }: Props) {
  const [status, setStatus] = useState<Status>("loading");
  const [fileName, setFileName] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const started = useRef(false);

  useEffect(() => {
    if (!downloadToken || started.current) {
      return;
    }
    started.current = true;

    void (async () => {
      try {
        const { blob, fileName: name } = await downloadEApprovalPublicPackage(downloadToken);
        setFileName(name);

        const objectUrl = URL.createObjectURL(blob);
        const anchor = document.createElement("a");
        anchor.href = objectUrl;
        anchor.download = name;
        anchor.rel = "noopener";
        document.body.appendChild(anchor);
        anchor.click();
        anchor.remove();
        URL.revokeObjectURL(objectUrl);

        setStatus("done");
      } catch (downloadError) {
        setError(getErrorMessage(downloadError));
        setStatus("error");
      }
    })();
  }, [downloadToken]);

  return (
    <div className={E_APPROVAL_FORM_SHELL_CLASS}>
      {status === "loading" ? (
        <p className="text-sm text-muted-foreground">Preparing your download…</p>
      ) : null}

      {status === "done" ? (
        <div className="space-y-2">
          <h1 className="text-xl font-semibold text-foreground">Download started</h1>
          <p className="text-sm text-muted-foreground">
            {fileName
              ? `Your browser should save “${fileName}”. If nothing happens, check your downloads folder or allow pop-ups for this site.`
              : "Your browser should save the file shortly."}
          </p>
        </div>
      ) : null}

      {status === "error" ? (
        <OperationalAlert
          level="error"
          title="Download unavailable"
          description={error ?? "This download link is invalid or has expired."}
        />
      ) : null}
    </div>
  );
}
