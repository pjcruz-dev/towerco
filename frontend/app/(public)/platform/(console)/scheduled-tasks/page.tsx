import { Suspense } from "react";

import { PlatformScheduledTasksPageClient } from "@/app/(public)/platform/scheduled-tasks/platform-scheduled-tasks-page-client";
import { Skeleton } from "@/components/ui/skeleton";

export default function PlatformScheduledTasksPage() {
  return (
    <Suspense fallback={<Skeleton className="h-24 rounded-xl" aria-hidden="true" />}>
      <PlatformScheduledTasksPageClient />
    </Suspense>
  );
}
