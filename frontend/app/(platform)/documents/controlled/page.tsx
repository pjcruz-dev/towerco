import { Suspense } from "react";
import { redirect } from "next/navigation";

import { ControlledDocumentOverviewPageClient } from "./controlled-document-overview-page-client";

type ControlledSearchParams = Record<string, string | string[] | undefined>;

export default async function ControlledDocumentsPage({
  searchParams,
}: {
  searchParams: Promise<ControlledSearchParams>;
}) {
  const params = await searchParams;
  const documentId = params.document;
  if (typeof documentId === "string" && documentId !== "") {
    const query = new URLSearchParams();
    for (const [key, value] of Object.entries(params)) {
      if (typeof value === "string") {
        query.set(key, value);
      } else if (Array.isArray(value)) {
        for (const item of value) {
          query.append(key, item);
        }
      }
    }
    redirect(`/documents/controlled/register?${query.toString()}`);
  }

  return (
    <Suspense fallback={null}>
      <ControlledDocumentOverviewPageClient />
    </Suspense>
  );
}
