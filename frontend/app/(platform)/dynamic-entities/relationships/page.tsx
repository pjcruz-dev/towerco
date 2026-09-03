import { Suspense } from "react";

import { DynRelationshipStudioPageClient } from "./dyn-relationship-studio-page-client";

export default function DynRelationshipStudioPage() {
  return (
    <Suspense fallback={<p className="text-sm text-muted-foreground">Loading…</p>}>
      <DynRelationshipStudioPageClient />
    </Suspense>
  );
}
