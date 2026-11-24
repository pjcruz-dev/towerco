import { Suspense } from "react";

import { MfaPageClient } from "./mfa-page-client";

export default function Page() {
  return (
    <Suspense fallback={null}>
      <MfaPageClient />
    </Suspense>
  );
}
