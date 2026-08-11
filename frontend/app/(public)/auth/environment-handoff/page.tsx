import { Suspense } from "react";

import { EnvironmentHandoffPageClient } from "./environment-handoff-page-client";

export default function EnvironmentHandoffPage() {
  return (
    <Suspense
      fallback={
        <div className="flex min-h-screen items-center justify-center bg-background px-6 text-sm text-muted-foreground">
          Switching environment…
        </div>
      }
    >
      <EnvironmentHandoffPageClient />
    </Suspense>
  );
}
