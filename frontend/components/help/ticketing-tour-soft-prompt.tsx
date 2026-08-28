"use client";

import { Suspense, useCallback, useEffect, useState } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import { Route } from "lucide-react";

import { Button } from "@/components/ui/button";
import { usePermission } from "@/hooks/use-permission";
import {
  dismissTicketingTourPrompt,
  hasDismissedTicketingTourPrompt,
} from "@/lib/help/e-approval-tour-prompt-preference";
import {
  TICKETING_LIVE_TOUR_ID,
  ticketingTourStartHref,
} from "@/lib/help/ticketing-live-tour";
import { permissions } from "@/lib/rbac/permissions";
import { useAuthStore } from "@/stores/auth-store";

function TicketingTourSoftPromptInner() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const userId = useAuthStore((state) => state.user?.id ?? null);
  const tenantId = useAuthStore((state) => state.activeTenantId);
  const canCreate = usePermission([permissions.ticketingTicketsCreate]);
  const canManage = usePermission([permissions.ticketingTicketsManage]);
  const canSettings = usePermission([permissions.ticketingSettingsManage]);
  const [visible, setVisible] = useState(false);

  const tourActive = searchParams.get("tour") === TICKETING_LIVE_TOUR_ID;

  useEffect(() => {
    if (!userId || tourActive) {
      setVisible(false);
      return;
    }
    setVisible(!hasDismissedTicketingTourPrompt(userId, tenantId));
  }, [tenantId, tourActive, userId]);

  const dismiss = useCallback(() => {
    dismissTicketingTourPrompt(userId, tenantId);
    setVisible(false);
  }, [tenantId, userId]);

  const startTour = useCallback(() => {
    dismissTicketingTourPrompt(userId, tenantId);
    setVisible(false);
    router.push(
      ticketingTourStartHref(0, { canCreate, canManage, canSettings }),
    );
  }, [canCreate, canManage, canSettings, router, tenantId, userId]);

  if (!visible) {
    return null;
  }

  const description = canManage || canSettings
    ? "Walks overview, the ticket queue, sample ticket detail, triage, and settings for your role. Sample UI is never saved."
    : canCreate
      ? "Walks overview, tickets, and creating a new issue. Sample UI appears during the tour and is never saved."
      : "Walks overview and the ticket queue for your role. Sample UI appears during the tour and is never saved.";

  return (
    <aside
      className="flex flex-col gap-3 rounded-xl border border-sky-200 bg-sky-50/80 p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between dark:border-sky-900/50 dark:bg-sky-950/30"
      aria-label="Ticketing tour invitation"
    >
      <div className="flex min-w-0 items-start gap-3">
        <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-sky-100 text-sky-800 dark:bg-sky-900/60 dark:text-sky-100">
          <Route className="size-4" aria-hidden />
        </div>
        <div className="min-w-0">
          <p className="text-sm font-medium text-foreground">Take a 2-minute Ticketing tour</p>
          <p className="mt-0.5 text-xs text-muted-foreground">{description}</p>
        </div>
      </div>
      <div className="flex shrink-0 flex-wrap items-center gap-2 sm:justify-end">
        <Button type="button" size="sm" variant="ghost" onClick={dismiss}>
          Not now
        </Button>
        <Button type="button" size="sm" onClick={startTour}>
          Start tour
        </Button>
      </div>
    </aside>
  );
}

export function TicketingTourSoftPrompt() {
  return (
    <Suspense fallback={null}>
      <TicketingTourSoftPromptInner />
    </Suspense>
  );
}
