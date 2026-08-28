"use client";

import Link from "next/link";
import { Check, Play } from "lucide-react";
import { useEffect, useMemo, useState } from "react";

import { VisualGuideFigure } from "@/components/help/visual-guide-figure";
import { VisualGuidePrintButton } from "@/components/help/visual-guide-print-button";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Badge } from "@/components/ui/badge";
import { buttonVariants } from "@/components/ui/button";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { usePermission } from "@/hooks/use-permission";
import {
  getCompletedEApprovalTourChapters,
  subscribeEApprovalTourChapterProgress,
} from "@/lib/help/e-approval-tour-chapter-progress";
import { dismissEApprovalTourPrompt } from "@/lib/help/e-approval-tour-prompt-preference";
import {
  LIVE_TOUR_CHAPTER_LABELS,
  eApprovalTourChaptersForCapabilities,
  liveTourChapterStartHref,
  liveTourStartHref,
  type LiveTourAudience,
  type LiveTourChapterId,
} from "@/lib/help/e-approval-live-tour";
import { eApprovalVisualGuideTabs } from "@/lib/help/e-approval-visual-guide";
import { permissions } from "@/lib/rbac/permissions";
import { cn } from "@/lib/utils";
import { useAuthStore } from "@/stores/auth-store";

function useCompletedTourChapters(
  userId: string | null,
  tenantId: string | null,
): Set<LiveTourChapterId> {
  const [completed, setCompleted] = useState<Set<LiveTourChapterId>>(() => new Set());

  useEffect(() => {
    const sync = () => {
      setCompleted(getCompletedEApprovalTourChapters(userId, tenantId));
    };
    sync();
    return subscribeEApprovalTourChapterProgress(sync);
  }, [tenantId, userId]);

  return completed;
}

function TourChapterAudienceBadges({ audience }: { audience?: LiveTourAudience }) {
  const role = audience ?? "all";

  if (role === "requestor") {
    return (
      <Badge
        variant="outline"
        className="border-sky-200 bg-sky-50 text-[11px] font-medium text-sky-800 dark:border-sky-900/50 dark:bg-sky-950/40 dark:text-sky-100"
      >
        Requestor
      </Badge>
    );
  }

  if (role === "approver") {
    return (
      <Badge
        variant="outline"
        className="border-amber-200 bg-amber-50 text-[11px] font-medium text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-100"
      >
        Approver
      </Badge>
    );
  }

  return (
    <span className="flex flex-wrap gap-1">
      <Badge
        variant="outline"
        className="border-sky-200 bg-sky-50 text-[11px] font-medium text-sky-800 dark:border-sky-900/50 dark:bg-sky-950/40 dark:text-sky-100"
      >
        Requestor
      </Badge>
      <Badge
        variant="outline"
        className="border-amber-200 bg-amber-50 text-[11px] font-medium text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-100"
      >
        Approver
      </Badge>
    </span>
  );
}

export function EApprovalVisualGuidePageClient() {
  const defaultTab = eApprovalVisualGuideTabs[0]?.id ?? "overview";
  const userId = useAuthStore((state) => state.user?.id ?? null);
  const tenantId = useAuthStore((state) => state.activeTenantId);
  const canApprove = usePermission([permissions.eApprovalApprove]);
  const canCreate = usePermission([permissions.eApprovalSubmissionsCreate]);
  const capabilities = useMemo(
    () => ({ canApprove, canCreate }),
    [canApprove, canCreate],
  );
  const liveTourHref = liveTourStartHref("e-approval", 0, capabilities);
  const chapterStarts = useMemo(
    () => eApprovalTourChaptersForCapabilities(capabilities),
    [capabilities],
  );
  const completedChapters = useCompletedTourChapters(userId, tenantId);

  return (
    <PermissionGate requiredPermissions={[permissions.eApprovalView]}>
      <div className="mx-auto max-w-5xl space-y-6 print:max-w-none">
        <div className="flex flex-wrap items-start justify-between gap-3 print:block">
          <header className="min-w-0 space-y-1">
            <p className="text-xs font-medium text-muted-foreground">E-Approval</p>
            <h1 className="text-2xl font-semibold tracking-tight text-foreground">
              Visual guide
            </h1>
            <p className="max-w-2xl text-sm text-muted-foreground">
              Diagrams first — numbered callouts explain each screen. Prefer this when learning
              the layout; use Start full tour for a live walkthrough.
            </p>
          </header>
          <div className="flex flex-wrap items-center gap-2 print:hidden">
            <Link href="/help" className={cn(buttonVariants({ variant: "outline", size: "sm" }))}>
              All guides
            </Link>
            <Link
              href={liveTourHref}
              className={cn(buttonVariants({ size: "sm" }))}
              onClick={() => dismissEApprovalTourPrompt(userId, tenantId)}
            >
              <Play className="mr-1.5 h-3.5 w-3.5" aria-hidden />
              Start full tour
            </Link>
            <VisualGuidePrintButton />
          </div>
        </div>

        <section className="space-y-3 rounded-xl border border-border bg-card p-4 shadow-sm print:hidden">
          <div className="space-y-1">
            <h2 className="text-base font-medium text-foreground">Jump into a tour chapter</h2>
            <p className="text-sm text-muted-foreground">
              Start only the part you need. Badges show whether a chapter is for requestors,
              approvers, or both. After a chapter finishes you return here to pick another.
              Sample UI appears while the tour runs and is never saved.
            </p>
          </div>
          <ul className="divide-y divide-border rounded-lg border border-border">
            {chapterStarts.map((chapter) => {
              const done = completedChapters.has(chapter.id);
              return (
                <li key={chapter.id}>
                  <Link
                    href={liveTourChapterStartHref(chapter.id, capabilities)}
                    onClick={() => dismissEApprovalTourPrompt(userId, tenantId)}
                    className="flex flex-col gap-1 px-3 py-3 transition-colors hover:bg-muted/40 sm:flex-row sm:items-center sm:justify-between sm:gap-4"
                  >
                    <div className="min-w-0 space-y-1.5">
                      <div className="flex flex-wrap items-center gap-2">
                        <p className="text-sm font-medium text-foreground">
                          {LIVE_TOUR_CHAPTER_LABELS[chapter.id]}
                        </p>
                        <TourChapterAudienceBadges audience={chapter.audience} />
                      </div>
                      <p className="text-xs text-muted-foreground">{chapter.how}</p>
                    </div>
                    <span
                      className={cn(
                        buttonVariants({
                          variant: done ? "secondary" : "outline",
                          size: "sm",
                        }),
                        "shrink-0 self-start sm:self-center",
                        done &&
                          "border-emerald-200 bg-emerald-50 text-emerald-800 hover:bg-emerald-50 dark:border-emerald-900/50 dark:bg-emerald-950/40 dark:text-emerald-100",
                      )}
                    >
                      {done ? (
                        <>
                          <Check className="mr-1.5 h-3.5 w-3.5" aria-hidden />
                          Complete
                        </>
                      ) : (
                        <>
                          <Play className="mr-1.5 h-3.5 w-3.5" aria-hidden />
                          Start
                        </>
                      )}
                    </span>
                  </Link>
                </li>
              );
            })}
          </ul>
          <p className="text-xs text-muted-foreground">
            Completed chapters stay clickable if you want to replay. Or use{" "}
            <Link
              href={liveTourHref}
              className="font-medium text-sky-700 underline-offset-2 hover:underline dark:text-sky-400"
              onClick={() => dismissEApprovalTourPrompt(userId, tenantId)}
            >
              Start full tour
            </Link>{" "}
            for Overview through Finish in order.
          </p>
        </section>

        <Tabs defaultValue={defaultTab} className="gap-4 print:hidden">
          <TabsList variant="default" className="h-auto w-full flex-wrap justify-start gap-1">
            {eApprovalVisualGuideTabs.map((tab) => (
              <TabsTrigger key={tab.id} value={tab.id} className="px-3 py-1.5">
                {tab.label}
              </TabsTrigger>
            ))}
          </TabsList>

          {eApprovalVisualGuideTabs.map((tab) => (
            <TabsContent key={tab.id} value={tab.id} className="space-y-6">
              {tab.sections.map((section) => (
                <VisualGuideFigure key={section.id} section={section} />
              ))}
            </TabsContent>
          ))}
        </Tabs>

        {/* Full guide for Print / Save as PDF — all tabs, not only the active one. */}
        <div className="hidden print:block">
          {eApprovalVisualGuideTabs.map((tab, tabIndex) => (
            <section
              key={tab.id}
              className={cn("space-y-6", tabIndex > 0 && "break-before-page pt-2")}
            >
              <h2 className="text-xl font-semibold text-foreground">{tab.label}</h2>
              {tab.sections.map((section) => (
                <VisualGuideFigure key={section.id} section={section} />
              ))}
            </section>
          ))}
        </div>
      </div>
    </PermissionGate>
  );
}
