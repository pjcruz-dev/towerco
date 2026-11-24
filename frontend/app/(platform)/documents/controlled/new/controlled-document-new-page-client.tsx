"use client";

import Link from "next/link";
import { useSearchParams } from "next/navigation";

import { EApprovalRequestFormPageClient } from "@/app/(platform)/e-approval/request/[formId]/e-approval-request-form-page-client";

export function ControlledDocumentNewPageClient() {
  const formId = useSearchParams().get("form_id")?.trim() ?? "";

  if (!formId) {
    return (
      <div className="rounded-xl border border-border bg-card px-6 py-10 text-center">
        <h1 className="text-base font-medium text-foreground">Choose a document form</h1>
        <p className="mx-auto mt-2 max-w-md text-sm text-muted-foreground">
          Start a controlled document from the register so the form is already selected.
        </p>
        <Link href="/documents/controlled/register" className="mt-4 inline-block text-sm text-primary hover:underline">
          Back to the register
        </Link>
      </div>
    );
  }

  return <EApprovalRequestFormPageClient formId={formId} />;
}
