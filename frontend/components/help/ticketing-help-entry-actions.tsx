"use client";

import Link from "next/link";
import { BookOpen, Play } from "lucide-react";
import { useMemo } from "react";

import { buttonVariants } from "@/components/ui/button";
import { usePermission } from "@/hooks/use-permission";
import { dismissTicketingTourPrompt } from "@/lib/help/e-approval-tour-prompt-preference";
import {
  TICKETING_TOUR_GUIDE_PATH,
  ticketingTourStartHref,
} from "@/lib/help/ticketing-live-tour";
import { permissions } from "@/lib/rbac/permissions";
import { cn } from "@/lib/utils";
import { useAuthStore } from "@/stores/auth-store";

type TicketingHelpEntryActionsProps = {
  className?: string;
  size?: "sm" | "default";
};

/** Tour chapters + live tour — preferred help entry on Ticketing pages. */
export function TicketingHelpEntryActions({
  className,
  size = "sm",
}: TicketingHelpEntryActionsProps) {
  const userId = useAuthStore((state) => state.user?.id ?? null);
  const tenantId = useAuthStore((state) => state.activeTenantId);
  const canCreate = usePermission([permissions.ticketingTicketsCreate]);
  const canManage = usePermission([permissions.ticketingTicketsManage]);
  const canSettings = usePermission([permissions.ticketingSettingsManage]);
  const liveTourHref = useMemo(
    () => ticketingTourStartHref(0, { canCreate, canManage, canSettings }),
    [canCreate, canManage, canSettings],
  );

  return (
    <div className={cn("flex flex-wrap items-center gap-2", className)}>
      <Link
        href={TICKETING_TOUR_GUIDE_PATH}
        className={cn(buttonVariants({ variant: "outline", size }))}
      >
        <BookOpen className="mr-1.5 h-3.5 w-3.5" aria-hidden />
        Tour chapters
      </Link>
      <Link
        href={liveTourHref}
        className={cn(buttonVariants({ variant: "outline", size }))}
        onClick={() => dismissTicketingTourPrompt(userId, tenantId)}
      >
        <Play className="mr-1.5 h-3.5 w-3.5" aria-hidden />
        Start tour
      </Link>
    </div>
  );
}
