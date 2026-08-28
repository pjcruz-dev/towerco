"use client";

import { Suspense, useCallback, useEffect, useState } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import { Route } from "lucide-react";

import { Button } from "@/components/ui/button";
import { usePermission } from "@/hooks/use-permission";
import {
  dismissEApprovalTourPrompt,
  hasDismissedEApprovalTourPrompt,
} from "@/lib/help/e-approval-tour-prompt-preference";
import { LIVE_TOUR_QUERY, liveTourStartHref } from "@/lib/help/e-approval-live-tour";
import { permissions } from "@/lib/rbac/permissions";
import { useAuthStore } from "@/stores/auth-store";

type EApprovalTourSoftPromptProps = {
  /** Defaults to full E-Approval live tour. */
  tourId?: string;
};

function EApprovalTourSoftPromptInner({ tourId = "e-approval" }: EApprovalTourSoftPromptProps) {
  const router = useRouter();
  const searchParams = useSearchParams();
  const userId = useAuthStore((state) => state.user?.id ?? null);
  const tenantId = useAuthStore((state) => state.activeTenantId);
  const canApprove = usePermission([permissions.eApprovalApprove]);
  const canCreate = usePermission([permissions.eApprovalSubmissionsCreate]);
  const [visible, setVisible] = useState(false);

  const tourActive = searchParams.get(LIVE_TOUR_QUERY) === tourId;

  useEffect(() => {
    if (!userId || tourActive) {
      setVisible(false);
      return;
    }
    setVisible(!hasDismissedEApprovalTourPrompt(userId, tenantId));
  }, [tenantId, tourActive, tourId, userId]);

  const dismiss = useCallback(() => {
    dismissEApprovalTourPrompt(userId, tenantId);
    setVisible(false);
  }, [tenantId, userId]);

  const startTour = useCallback(() => {
    dismissEApprovalTourPrompt(userId, tenantId);
    setVisible(false);
    router.push(liveTourStartHref(tourId, 0));
  }, [router, tenantId, tourId, userId]);

  if (!visible) {
    return null;
  }

  const description = canApprove
    ? "Covers requestor flow and approver decisions (signature, approve, reject, revision). Sample cards appear only during the tour and are not saved."
    : canCreate
      ? "Walks overview, submissions, and new requests for your role. Approver steps (Decide, signature) are skipped. Sample cards are not saved."
      : "Walks overview and submissions for your role. Create and approve steps are skipped if you don’t have those permissions.";

  return (
    <aside
      className="flex flex-col gap-3 rounded-xl border border-sky-200 bg-sky-50/80 p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between dark:border-sky-900/50 dark:bg-sky-950/30"
      aria-label="E-Approval tour invitation"
    >
      <div className="flex min-w-0 items-start gap-3">
        <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-sky-100 text-sky-800 dark:bg-sky-900/60 dark:text-sky-100">
          <Route className="size-4" aria-hidden />
        </div>
        <div className="min-w-0">
          <p className="text-sm font-medium text-foreground">Take a 2-minute E-Approval tour</p>
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

/**
 * One-time soft prompt on E-Approval Overview for users who have not dismissed
 * or completed the interactive tour (scoped by user + tenant in localStorage).
 */
export function EApprovalTourSoftPrompt(props: EApprovalTourSoftPromptProps) {
  return (
    <Suspense fallback={null}>
      <EApprovalTourSoftPromptInner {...props} />
    </Suspense>
  );
}
