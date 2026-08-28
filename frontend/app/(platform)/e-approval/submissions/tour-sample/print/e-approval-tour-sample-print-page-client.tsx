"use client";

import { Suspense, useEffect } from "react";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";

import { EApprovalGenericFormPrintView } from "@/components/e-approval/e-approval-generic-form-print-view";
import { EApprovalTourSampleAttachmentPages } from "@/components/help/e-approval-tour-sample-attachment-pages";
import { EApprovalTourSampleNotice } from "@/components/help/e-approval-tour-fixtures";
import { LiveProductTourHost } from "@/components/help/live-product-tour-host";
import { Button } from "@/components/ui/button";
import {
  E_APPROVAL_TOUR_SAMPLE_DETAIL_PATH,
  isEApprovalTourActive,
} from "@/lib/help/e-approval-tour-fixtures";
import { eApprovalTourSamplePrintPayload } from "@/lib/help/e-approval-tour-sample-data";
import { buildTourSearchParams } from "@/lib/help/e-approval-live-tour";

function hasPrintableAttachment(fileName: string): boolean {
  return /\.(pdf|png|jpe?g)$/i.test(fileName);
}

function TourSamplePrintInner() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const tourActive = isEApprovalTourActive(searchParams);
  const data = eApprovalTourSamplePrintPayload;
  const attachments = data.attachments ?? [];
  const canMergePdf = attachments.some((a) => hasPrintableAttachment(a.file_name));
  const showAttachmentPages = attachments.length > 0;

  useEffect(() => {
    if (!tourActive) {
      router.replace("/e-approval/submissions");
    }
  }, [router, tourActive]);

  if (!tourActive) {
    return <p className="text-sm text-muted-foreground">Redirecting…</p>;
  }

  const backParams = buildTourSearchParams(
    "e-approval",
    Number.parseInt(searchParams.get("tourStep") ?? "0", 10) || 0,
    {
      id: "_",
      path: "/",
      target: "_",
      title: "",
      body: "",
    },
    searchParams,
  );
  const backHref = `${E_APPROVAL_TOUR_SAMPLE_DETAIL_PATH}?${backParams.toString()}`;

  return (
    <div className="space-y-0">
      <LiveProductTourHost />
      <div className="print:hidden space-y-3 border-b border-border bg-background px-1 pb-3">
        <EApprovalTourSampleNotice />
        <div
          data-help="ea-print-sample"
          className="rounded-xl border border-sky-200 bg-sky-50/80 px-4 py-3 shadow-sm dark:border-sky-900/50 dark:bg-sky-950/30"
        >
          <p className="text-sm font-medium text-foreground">Printed copy & approval history</p>
          <p className="mt-1 text-xs text-muted-foreground">
            Document approval fields and approval trail first, then attachment pages each stamped with
            signature history. Fields match live Document Approval: Title, Approver 1–3, Attachments.
            Scroll freely — the tour stays open.
          </p>
          <div className="mt-3 flex flex-wrap items-center justify-between gap-2">
            <Link href={backHref} className="text-sm text-primary hover:underline">
              ← Back to submission
            </Link>
            <Button type="button" size="sm" variant="outline" disabled>
              {canMergePdf ? "Open PDF preview" : "Print / Save as PDF"}
            </Button>
          </div>
        </div>
      </div>

      {canMergePdf ? (
        <p className="border-b border-slate-200 bg-slate-50 px-4 py-2 text-center text-sm text-slate-600 print:hidden">
          Live print stamps <strong>approval history on every attachment page</strong>. Tour sample
          shows document fields, approval trail, then stamped attachment pages (not saved).
        </p>
      ) : null}

      <div className="space-y-6">
        <EApprovalGenericFormPrintView
          data={data}
          showApprovalFooter={!canMergePdf}
          fieldsDataHelp="ea-print-doc-fields"
          trailDataHelp="ea-print-approval-trail"
          footerDataHelp="ea-print-approval-footer"
        />
        {showAttachmentPages ? (
          <div className="print:break-before-page">
            <EApprovalTourSampleAttachmentPages data={data} />
          </div>
        ) : null}
      </div>
    </div>
  );
}

export function EApprovalTourSamplePrintPageClient() {
  return (
    <Suspense fallback={null}>
      <TourSamplePrintInner />
    </Suspense>
  );
}
