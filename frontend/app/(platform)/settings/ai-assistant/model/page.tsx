import { Suspense } from "react";

import { LlmCredentialPageClient } from "./llm-credential-page-client";

export default function LlmCredentialPage() {
  return (
    <Suspense fallback={<div className="p-6 text-sm text-muted-foreground">Loading AI model…</div>}>
      <LlmCredentialPageClient />
    </Suspense>
  );
}
