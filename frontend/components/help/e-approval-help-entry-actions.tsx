"use client";

import Link from "next/link";
import { BookOpen, Play } from "lucide-react";
import { useMemo } from "react";

import { buttonVariants } from "@/components/ui/button";
import { usePermission } from "@/hooks/use-permission";
import { dismissEApprovalTourPrompt } from "@/lib/help/e-approval-tour-prompt-preference";
import { liveTourStartHref } from "@/lib/help/e-approval-live-tour";
import { permissions } from "@/lib/rbac/permissions";
import { cn } from "@/lib/utils";
import { useAuthStore } from "@/stores/auth-store";

type EApprovalHelpEntryActionsProps = {
  className?: string;
  /** Smaller outline buttons for page headers. */
  size?: "sm" | "default";
};

/** Visual guide + live tour — preferred help entry (not written role guides). */
export function EApprovalHelpEntryActions({
  className,
  size = "sm",
}: EApprovalHelpEntryActionsProps) {
  const userId = useAuthStore((state) => state.user?.id ?? null);
  const tenantId = useAuthStore((state) => state.activeTenantId);
  const canApprove = usePermission([permissions.eApprovalApprove]);
  const canCreate = usePermission([permissions.eApprovalSubmissionsCreate]);
  const liveTourHref = useMemo(
    () => liveTourStartHref("e-approval", 0, { canApprove, canCreate }),
    [canApprove, canCreate],
  );

  return (
    <div className={cn("flex flex-wrap items-center gap-2", className)}>
      <Link
        href="/help/e-approval/visual"
        className={cn(buttonVariants({ variant: "outline", size }))}
      >
        <BookOpen className="mr-1.5 h-3.5 w-3.5" aria-hidden />
        Visual guide
      </Link>
      <Link
        href={liveTourHref}
        className={cn(buttonVariants({ variant: "outline", size }))}
        onClick={() => dismissEApprovalTourPrompt(userId, tenantId)}
      >
        <Play className="mr-1.5 h-3.5 w-3.5" aria-hidden />
        Start tour
      </Link>
    </div>
  );
}
