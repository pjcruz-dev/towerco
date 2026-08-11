import { DocumentsHomePageClient } from "./documents-home-page-client";

export default function DocumentsPage({
  searchParams,
}: {
  searchParams?: { document?: string };
}) {
  return <DocumentsHomePageClient initialDocumentId={searchParams?.document ?? null} />;
}
