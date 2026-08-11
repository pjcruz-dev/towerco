import { Suspense } from "react";

import { ProjectDetailPageClient } from "./project-detail-page-client";

type PageProps = {
  params: Promise<{ id: string }>;
};

export default async function ProjectDetailPage({ params }: PageProps) {
  const { id } = await params;

  return (
    <Suspense fallback={<p className="p-6 text-sm text-muted-foreground">Loading project…</p>}>
      <ProjectDetailPageClient projectId={id} />
    </Suspense>
  );
}
