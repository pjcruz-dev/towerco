"use client";

import { Suspense, useEffect } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import { FileText, Paperclip } from "lucide-react";

import { EApprovalTourSampleNotice } from "@/components/help/e-approval-tour-fixtures";
import { LiveProductTourHost } from "@/components/help/live-product-tour-host";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { isEApprovalTourActive } from "@/lib/help/e-approval-tour-fixtures";
import {
  E_APPROVAL_TOUR_SAMPLE_FORM_NAME,
  eApprovalTourSampleComposeDefaults,
} from "@/lib/help/e-approval-tour-sample-data";

function TourSampleComposeInner() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const tourActive = isEApprovalTourActive(searchParams);
  const defaults = eApprovalTourSampleComposeDefaults;

  useEffect(() => {
    if (!tourActive) {
      router.replace("/e-approval/submissions/new");
    }
  }, [router, tourActive]);

  if (!tourActive) {
    return <p className="text-sm text-muted-foreground">Redirecting…</p>;
  }

  return (
    <div className="mx-auto max-w-3xl space-y-4">
      <LiveProductTourHost />
      <EApprovalTourSampleNotice />
      <header className="space-y-1">
        <h1 className="text-2xl font-semibold text-foreground">{E_APPROVAL_TOUR_SAMPLE_FORM_NAME}</h1>
        <p className="text-sm text-muted-foreground">
          Title, Approver 1–3, and Attachments only. Sample compose for the tour — nothing is saved.
        </p>
      </header>

      <div
        data-help="ea-compose-fields"
        className="space-y-4 rounded-xl border border-border bg-card p-4 shadow-sm"
      >
        <label className="block space-y-1.5 text-sm">
          <span className="font-medium text-foreground">Title</span>
          <Input disabled defaultValue={defaults.title} />
        </label>
        <div className="grid gap-4 sm:grid-cols-3">
          <label className="space-y-1.5 text-sm">
            <span className="font-medium text-foreground">Approver 1</span>
            <Input disabled defaultValue={defaults.approver1} />
          </label>
          <label className="space-y-1.5 text-sm">
            <span className="font-medium text-foreground">Approver 2</span>
            <Input disabled defaultValue={defaults.approver2} />
          </label>
          <label className="space-y-1.5 text-sm">
            <span className="font-medium text-foreground">Approver 3</span>
            <Input disabled defaultValue={defaults.approver3} />
          </label>
        </div>
      </div>

      <div
        data-help="ea-compose-upload"
        className="space-y-3 rounded-xl border border-dashed border-border bg-card p-4 shadow-sm"
      >
        <div className="flex items-center gap-2 text-sm font-medium text-foreground">
          <Paperclip className="h-4 w-4 text-muted-foreground" aria-hidden />
          Attachments
        </div>
        <ul className="space-y-2">
          {defaults.files.map((fileName) => (
            <li
              key={fileName}
              className="flex items-center gap-2 rounded-lg border border-border bg-muted/20 px-3 py-2 text-sm"
            >
              <FileText className="h-4 w-4 shrink-0 text-muted-foreground" aria-hidden />
              <span className="truncate text-foreground">{fileName}</span>
            </li>
          ))}
        </ul>
        <p className="text-xs text-muted-foreground">PDF attachments — sample only, not uploaded</p>
      </div>

      <div className="flex flex-wrap items-center justify-end gap-2 border-t border-border pt-4">
        <span data-help="ea-compose-save-draft" className="inline-flex">
          <Button type="button" size="sm" variant="outline" disabled>
            Save draft
          </Button>
        </span>
        <span data-help="ea-compose-submit" className="inline-flex">
          <Button type="button" size="sm" disabled>
            Submit request
          </Button>
        </span>
      </div>
    </div>
  );
}

export function EApprovalTourSampleComposePageClient() {
  return (
    <Suspense fallback={null}>
      <TourSampleComposeInner />
    </Suspense>
  );
}
