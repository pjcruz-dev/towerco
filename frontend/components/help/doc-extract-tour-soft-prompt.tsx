"use client";

import { Suspense, useCallback, useEffect, useState } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import { Route } from "lucide-react";

import { Button } from "@/components/ui/button";
import { usePermission } from "@/hooks/use-permission";
import {
  dismissDocExtractTourPrompt,
  hasDismissedDocExtractTourPrompt,
} from "@/lib/help/e-approval-tour-prompt-preference";
import {
  DOC_EXTRACT_LIVE_TOUR_ID,
  docExtractTourStartHref,
} from "@/lib/help/doc-extract-live-tour";
import { permissions } from "@/lib/rbac/permissions";
import { useAuthStore } from "@/stores/auth-store";

function DocExtractTourSoftPromptInner() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const userId = useAuthStore((state) => state.user?.id ?? null);
  const tenantId = useAuthStore((state) => state.activeTenantId);
  const canRun = usePermission([permissions.docExtractRun]);
  const [visible, setVisible] = useState(false);

  const tourActive = searchParams.get("tour") === DOC_EXTRACT_LIVE_TOUR_ID;

  useEffect(() => {
    if (!userId || tourActive) {
      setVisible(false);
      return;
    }
    setVisible(!hasDismissedDocExtractTourPrompt(userId, tenantId));
  }, [tenantId, tourActive, userId]);

  const dismiss = useCallback(() => {
    dismissDocExtractTourPrompt(userId, tenantId);
    setVisible(false);
  }, [tenantId, userId]);

  const startTour = useCallback(() => {
    dismissDocExtractTourPrompt(userId, tenantId);
    setVisible(false);
    router.push(docExtractTourStartHref(0));
  }, [router, tenantId, userId]);

  if (!visible) {
    return null;
  }

  const description = canRun
    ? "Walks batches, upload, consolidate, extract, customize columns, and export results. Takes about two minutes."
    : "Walks DocExtract batches and how extraction workspaces are organized for your role.";

  return (
    <aside
      className="flex flex-col gap-3 rounded-xl border border-sky-200 bg-sky-50/80 p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between dark:border-sky-900/50 dark:bg-sky-950/30"
      aria-label="DocExtract tour invitation"
    >
      <div className="flex min-w-0 items-start gap-3">
        <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-sky-100 text-sky-800 dark:bg-sky-900/60 dark:text-sky-100">
          <Route className="size-4" aria-hidden />
        </div>
        <div className="min-w-0">
          <p className="text-sm font-medium text-foreground">Take a DocExtract tour</p>
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

export function DocExtractTourSoftPrompt() {
  return (
    <Suspense fallback={null}>
      <DocExtractTourSoftPromptInner />
    </Suspense>
  );
}
