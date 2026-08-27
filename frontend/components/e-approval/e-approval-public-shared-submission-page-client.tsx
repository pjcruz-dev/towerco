"use client";

import { useQuery } from "@tanstack/react-query";
import { Download } from "lucide-react";

import { OperationalAlert } from "@/components/feedback/operational-alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { getErrorMessage } from "@/lib/api/error";
import { fetchEApprovalPublicSharedSubmission } from "@/lib/api/modules/e-approval-public-api";
import { E_APPROVAL_FORM_SHELL_CLASS } from "@/modules/e-approval/form-layout";

type Props = {
  shareToken: string;
};

export function EApprovalPublicSharedSubmissionPageClient({ shareToken }: Props) {
  const query = useQuery({
    queryKey: ["e-approval", "public", "shared", shareToken],
    queryFn: () => fetchEApprovalPublicSharedSubmission(shareToken),
    retry: false,
  });

  if (query.isLoading) {
    return (
      <div className={E_APPROVAL_FORM_SHELL_CLASS}>
        <p className="text-sm text-muted-foreground">Loading shared request…</p>
      </div>
    );
  }

  if (query.isError || !query.data) {
    return (
      <div className={E_APPROVAL_FORM_SHELL_CLASS}>
        <OperationalAlert
          level="error"
          title="Link unavailable"
          description={getErrorMessage(query.error) || "This share link is invalid, revoked, or expired."}
        />
      </div>
    );
  }

  const data = query.data;

  return (
    <div className={E_APPROVAL_FORM_SHELL_CLASS}>
      <div className="space-y-6">
        <header className="space-y-2 border-b border-border pb-4">
          <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
            {data.brand_label} · Shared E-Approval
          </p>
          <div className="flex flex-wrap items-center gap-2">
            <h1 className="text-2xl font-semibold text-foreground">{data.document_no}</h1>
            <Badge variant="secondary" className="capitalize">
              {data.status}
            </Badge>
          </div>
          <p className="text-sm text-muted-foreground">
            {[data.form_name, data.requestor_name ? `Requestor: ${data.requestor_name}` : null]
              .filter(Boolean)
              .join(" · ")}
          </p>
          {data.expires_at ? (
            <p className="text-xs text-muted-foreground">
              Link expires {new Date(data.expires_at).toLocaleString()}
            </p>
          ) : null}
        </header>

        <section className="space-y-3">
          <h2 className="text-base font-medium text-foreground">Details</h2>
          <dl className="grid gap-3 sm:grid-cols-2">
            {data.values.map((row) => (
              <div key={row.field_id} className="rounded-lg border border-border px-3 py-2">
                <dt className="text-xs font-medium text-muted-foreground">
                  {row.label ?? row.field_name ?? "Field"}
                </dt>
                <dd className="mt-1 whitespace-pre-wrap text-sm text-foreground">
                  {row.display_value ?? row.value ?? "—"}
                </dd>
              </div>
            ))}
          </dl>
        </section>

        {data.approvals.length > 0 ? (
          <section className="space-y-3">
            <h2 className="text-base font-medium text-foreground">Approvals</h2>
            <ul className="space-y-2">
              {data.approvals.map((row, index) => (
                <li key={`${row.approver_name}-${index}`} className="rounded-lg border border-border px-3 py-2 text-sm">
                  <div className="flex flex-wrap items-center gap-2">
                    <span className="font-medium text-foreground">{row.approver_name ?? "Approver"}</span>
                    <Badge variant="outline" className="capitalize">
                      {row.status}
                    </Badge>
                  </div>
                  {row.remarks ? <p className="mt-1 text-muted-foreground">{row.remarks}</p> : null}
                  {row.decided_at ? (
                    <p className="mt-1 text-xs text-muted-foreground">
                      {new Date(row.decided_at).toLocaleString()}
                    </p>
                  ) : null}
                </li>
              ))}
            </ul>
          </section>
        ) : null}

        {data.attachments.length > 0 ? (
          <section className="space-y-3">
            <h2 className="text-base font-medium text-foreground">Attachments</h2>
            <ul className="space-y-2">
              {data.attachments.map((file) => (
                <li key={file.id}>
                  <Button
                    size="sm"
                    variant="outline"
                    className="gap-1.5"
                    render={<a href={file.download_url} target="_blank" rel="noopener noreferrer" download />}
                  >
                    <Download className="h-3.5 w-3.5" />
                    {file.file_name}
                  </Button>
                </li>
              ))}
            </ul>
          </section>
        ) : null}
      </div>
    </div>
  );
}
