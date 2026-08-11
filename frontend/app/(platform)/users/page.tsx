import { Suspense } from "react";

import { UsersPageClient } from "./users-page-client";

export default function UsersPage() {
  return (
    <Suspense fallback={<div className="px-4 py-8 text-sm text-muted-foreground">Loading users…</div>}>
      <UsersPageClient />
    </Suspense>
  );
}
