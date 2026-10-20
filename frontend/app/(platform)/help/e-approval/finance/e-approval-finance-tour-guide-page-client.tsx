"use client";

import Link from "next/link";
import { Check, Play } from "lucide-react";
import { useEffect, useMemo, useState } from "react";

import { VisualGuidePrintButton } from "@/components/help/visual-guide-print-button";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Badge } from "@/components/ui/badge";
import { buttonVariants } from "@/components/ui/button";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { usePermission } from "@/hooks/use-permission";
import { dismissLiveTourPrompt } from "@/lib/help/e-approval-tour-prompt-preference";
import {
  LIVE_TOUR_CHAPTER_LABELS,
  type LiveTourAudience,
  type LiveTourChapterId,
} from "@/lib/help/e-approval-live-tour";
import {
  E_APPROVAL_FINANCE_TOUR_ID,
  financeTourChapterStartHref,
  financeTourChaptersForCapabilities,
  financeTourStartHref,
} from "@/lib/help/e-approval-finance-tour";
import {
  getCompletedEApprovalFinanceTourChapters,
  subscribeEApprovalFinanceTourChapterProgress,
} from "@/lib/help/e-approval-finance-tour-chapter-progress";
import { permissions } from "@/lib/rbac/permissions";
import { cn } from "@/lib/utils";
import { useAuthStore } from "@/stores/auth-store";

const GUIDE_TABS: Array<{ id: string; label: string; sections: Array<{ title: string; body: string; points: string[] }> }> = [
  {
    id: "overview",
    label: "Overview",
    sections: [
      {
        title: "Where these forms live",
        body: "Cash advance, liquidation, and reimbursement are E-Forms requests. They use the same submissions list, approvals queue, and Decide tab as Document Approval.",
        points: [
          "Overview shows what is waiting on you and what you still need to finish.",
          "Submissions is where you track document number, status, and Step Show.",
          "Approvals is the queue for people who sign. Your signature is saved on My E-Forms profile.",
        ],
      },
    ],
  },
  {
    id: "cash-advance",
    label: "Cash advance",
    sections: [
      {
        title: "Request the forecast",
        body: "Each line is one trip: dates, from and to site, expense type, amount, and VAT treatment. Requested amount is the total of the Amount column.",
        points: [
          "Subsidiary chooses the letterhead. Funds needed by is the date the cash must be released.",
          "Chain: Department Head → Finance → CFO → Release cash. Release pays the full requested amount.",
          "A new cash advance is blocked while one is still open. Liquidate or return it first.",
        ],
      },
    ],
  },
  {
    id: "liquidation",
    label: "Liquidation",
    sections: [
      {
        title: "Settle one released advance",
        body: "Pick the released cash advance. Document number, purpose, and cash released fill in from that parent. Actual lines carry the OR, supplier, forecast amount, and a variance reason.",
        points: [
          "Total expenses is blocked when it exceeds the open balance. Pending liquidations count. Rejected ones do not.",
          "Settlement amount is actual expenses minus cash released. Positive pays the employee. Negative is a surrender.",
          "Chain: Department Head → Finance → CFO → Finance validation → Settlement.",
        ],
      },
    ],
  },
  {
    id: "reimbursement",
    label: "Reimbursement",
    sections: [
      {
        title: "Out-of-pocket expenses",
        body: "Reimbursement does not link to a cash advance and does not change an open balance. Receipts are required.",
        points: [
          "Lines need OR number, supplier, description, expense type, amount, and VAT treatment.",
          "Total expenses is what Finance or Treasury pays after approval.",
          "Chain: Department Head → Finance → CFO → Payment.",
        ],
      },
    ],
  },
  {
    id: "decide",
    label: "Decide",
    sections: [
      {
        title: "Cancel, follow-up, and sign-off",
        body: "While a request is pending, the requestor can cancel it or send a follow-up to the current approvers. Approvers sign on Decide.",
        points: [
          "Follow-up notifies every pending approver on the current step. A cooldown may apply.",
          "Approve moves the request to the next step. Reject ends it. Request revision sends it back. Remarks are required for reject and revision.",
          "The printed page is landscape and includes the expense grid. Liquidation also prints the settlement.",
        ],
      },
    ],
  },
];

function useCompletedChapters(userId: string | null, tenantId: string | null): Set<LiveTourChapterId> {
  const [completed, setCompleted] = useState<Set<LiveTourChapterId>>(() => new Set());

  useEffect(() => {
    const sync = () => setCompleted(getCompletedEApprovalFinanceTourChapters(userId, tenantId));
    sync();
    return subscribeEApprovalFinanceTourChapterProgress(sync);
  }, [tenantId, userId]);

  return completed;
}

function AudienceBadges({ audience }: { audience?: LiveTourAudience }) {
  const role = audience ?? "all";
  if (role === "requestor") {
    return (
      <Badge variant="outline" className="text-[11px] font-medium">
        Requestor
      </Badge>
    );
  }
  if (role === "approver") {
    return (
      <Badge variant="outline" className="text-[11px] font-medium">
        Approver
      </Badge>
    );
  }
  return (
    <span className="flex flex-wrap gap-1">
      <Badge variant="outline" className="text-[11px] font-medium">
        Requestor
      </Badge>
      <Badge variant="outline" className="text-[11px] font-medium">
        Approver
      </Badge>
    </span>
  );
}

export function EApprovalFinanceTourGuidePageClient() {
  const userId = useAuthStore((state) => state.user?.id ?? null);
  const tenantId = useAuthStore((state) => state.activeTenantId);
  const canApprove = usePermission([permissions.eApprovalApprove]);
  const canCreate = usePermission([permissions.eApprovalSubmissionsCreate]);
  const capabilities = useMemo(() => ({ canApprove, canCreate }), [canApprove, canCreate]);
  const liveTourHref = financeTourStartHref(0, capabilities);
  const chapterStarts = useMemo(() => financeTourChaptersForCapabilities(capabilities), [capabilities]);
  const completedChapters = useCompletedChapters(userId, tenantId);

  const dismiss = () => dismissLiveTourPrompt(E_APPROVAL_FINANCE_TOUR_ID, userId, tenantId);

  return (
    <PermissionGate requiredPermissions={[permissions.eApprovalView]}>
      <div className="mx-auto max-w-5xl space-y-6 print:max-w-none">
        <div className="flex flex-wrap items-start justify-between gap-3 print:block">
          <header className="min-w-0 space-y-1">
            <p className="text-xs font-medium text-muted-foreground">E-Forms</p>
            <h1 className="text-2xl font-semibold tracking-tight text-foreground">
              Cash advance, liquidation & reimbursement
            </h1>
            <p className="max-w-2xl text-sm text-muted-foreground">
              Same full tour shape as Document Approval: overview, submissions, compose, the request,
              print, cancel, follow-up, and Decide. Sample screens are never saved. Document Approval
              stays on its own{" "}
              <Link href="/help/e-approval/visual" className="font-medium text-primary underline-offset-2 hover:underline">
                tour guide
              </Link>
              .
            </p>
          </header>
          <div className="flex flex-wrap items-center gap-2 print:hidden">
            <Link href="/help" className={cn(buttonVariants({ variant: "outline", size: "sm" }))}>
              All guides
            </Link>
            <Link href={liveTourHref} className={cn(buttonVariants({ size: "sm" }))} onClick={dismiss}>
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
              Start only the part you need. After a chapter finishes you return here. Sample UI appears
              while the tour runs and is never saved.
            </p>
          </div>
          <ul className="divide-y divide-border rounded-lg border border-border">
            {chapterStarts.map((chapter) => {
              const done = completedChapters.has(chapter.id);
              return (
                <li key={chapter.id}>
                  <Link
                    href={financeTourChapterStartHref(chapter.id, capabilities)}
                    onClick={dismiss}
                    className="flex flex-col gap-1 px-3 py-3 transition-colors hover:bg-muted/40 sm:flex-row sm:items-center sm:justify-between sm:gap-4"
                  >
                    <div className="min-w-0 space-y-1.5">
                      <div className="flex flex-wrap items-center gap-2">
                        <p className="text-sm font-medium text-foreground">{LIVE_TOUR_CHAPTER_LABELS[chapter.id]}</p>
                        <AudienceBadges audience={chapter.audience} />
                      </div>
                      <p className="text-xs text-muted-foreground">{chapter.how}</p>
                    </div>
                    <span
                      className={cn(
                        buttonVariants({ variant: done ? "secondary" : "outline", size: "sm" }),
                        "shrink-0 self-start sm:self-center",
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
        </section>

        <Tabs defaultValue={GUIDE_TABS[0]?.id ?? "overview"} className="gap-4 print:hidden">
          <TabsList variant="default" className="h-auto w-full flex-wrap justify-start gap-1">
            {GUIDE_TABS.map((tab) => (
              <TabsTrigger key={tab.id} value={tab.id} className="px-3 py-1.5">
                {tab.label}
              </TabsTrigger>
            ))}
          </TabsList>
          {GUIDE_TABS.map((tab) => (
            <TabsContent key={tab.id} value={tab.id} className="space-y-4">
              {tab.sections.map((section) => (
                <GuideSection key={section.title} title={section.title} body={section.body} points={section.points} />
              ))}
            </TabsContent>
          ))}
        </Tabs>

        <div className="hidden print:block">
          {GUIDE_TABS.map((tab, tabIndex) => (
            <section key={tab.id} className={cn("space-y-4", tabIndex > 0 && "break-before-page pt-2")}>
              <h2 className="text-xl font-semibold text-foreground">{tab.label}</h2>
              {tab.sections.map((section) => (
                <GuideSection key={section.title} title={section.title} body={section.body} points={section.points} />
              ))}
            </section>
          ))}
        </div>
      </div>
    </PermissionGate>
  );
}

function GuideSection({ title, body, points }: { title: string; body: string; points: string[] }) {
  return (
    <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
      <h3 className="text-base font-medium text-foreground">{title}</h3>
      <p className="mt-1 text-sm text-muted-foreground">{body}</p>
      <ol className="mt-3 list-decimal space-y-1.5 ps-5 text-sm text-foreground">
        {points.map((point) => (
          <li key={point}>{point}</li>
        ))}
      </ol>
    </section>
  );
}
