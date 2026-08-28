"use client";

import { useQuery } from "@tanstack/react-query";
import Link from "next/link";
import { BookOpen, Play } from "lucide-react";
import { useMemo } from "react";

import { PermissionGate } from "@/components/layout/permission-gate";
import { usePermission } from "@/hooks/use-permission";
import { getErrorMessage } from "@/lib/api/error";
import { fetchPublishedHelpGuides, type HelpGuideListRow } from "@/lib/api/modules/help-guides-api";
import { dismissEApprovalTourPrompt } from "@/lib/help/e-approval-tour-prompt-preference";
import { liveTourStartHref } from "@/lib/help/e-approval-live-tour";
import { permissions } from "@/lib/rbac/permissions";
import { TENANT_MODULE_LABELS } from "@/lib/tenant/enabled-modules";
import { useAuthStore } from "@/stores/auth-store";

function moduleHeading(moduleKey: string): string {
  return TENANT_MODULE_LABELS[moduleKey] ?? moduleKey.replace(/_/g, " ");
}

function roleLabel(role: HelpGuideListRow["role"]): string {
  if (role === "approver") {
    return "Approver";
  }
  if (role === "requestor") {
    return "Requestor";
  }
  return "General";
}

function groupGuidesByModule(guides: HelpGuideListRow[]): Array<{ moduleKey: string; guides: HelpGuideListRow[] }> {
  const order: string[] = [];
  const map = new Map<string, HelpGuideListRow[]>();

  for (const guide of guides) {
    // E-Approval uses Visual guide + live tour instead of written role cards.
    if (guide.module_key === "e_approval") {
      continue;
    }
    const key = guide.module_key || "other";
    if (!map.has(key)) {
      order.push(key);
      map.set(key, []);
    }
    map.get(key)!.push(guide);
  }

  return order.map((moduleKey) => ({
    moduleKey,
    guides: map.get(moduleKey) ?? [],
  }));
}

function EApprovalVisualGuideCard() {
  return (
    <Link
      href="/help/e-approval/visual"
      className="rounded-xl border border-border bg-card p-5 shadow-sm transition-colors hover:bg-muted/30"
    >
      <p className="text-xs font-medium text-muted-foreground">Visual</p>
      <h3 className="mt-2 text-base font-medium text-foreground">E-Approval visual guide</h3>
      <p className="mt-1 text-sm text-muted-foreground">
        Annotated screenshots with numbered callouts — overview, submissions, compose, decide, and
        returns. Print-friendly for desk training.
      </p>
      <p className="mt-3 inline-flex items-center gap-1.5 text-sm text-sky-700 dark:text-sky-400">
        <BookOpen className="h-3.5 w-3.5" aria-hidden />
        Open visual guide →
      </p>
    </Link>
  );
}

function EApprovalTourGuideCard() {
  const userId = useAuthStore((state) => state.user?.id ?? null);
  const tenantId = useAuthStore((state) => state.activeTenantId);
  const canApprove = usePermission([permissions.eApprovalApprove]);
  const canCreate = usePermission([permissions.eApprovalSubmissionsCreate]);
  const liveTourHref = useMemo(
    () => liveTourStartHref("e-approval", 0, { canApprove, canCreate }),
    [canApprove, canCreate],
  );

  return (
    <Link
      href={liveTourHref}
      onClick={() => dismissEApprovalTourPrompt(userId, tenantId)}
      className="rounded-xl border border-border bg-card p-5 shadow-sm transition-colors hover:bg-muted/30"
    >
      <p className="text-xs font-medium text-muted-foreground">Interactive</p>
      <h3 className="mt-2 text-base font-medium text-foreground">E-Approval product tour</h3>
      <p className="mt-1 text-sm text-muted-foreground">
        Walk the real screens with coach marks. Sample UI appears while the tour runs and is never
        saved — jump into chapters from the visual guide anytime.
      </p>
      <p className="mt-3 inline-flex items-center gap-1.5 text-sm text-sky-700 dark:text-sky-400">
        <Play className="h-3.5 w-3.5" aria-hidden />
        Start tour →
      </p>
    </Link>
  );
}

export function HelpPageClient() {
  const query = useQuery({
    queryKey: ["help", "guides"],
    queryFn: () => fetchPublishedHelpGuides(),
  });

  const groups = groupGuidesByModule(query.data ?? []);

  return (
    <PermissionGate requiredPermissions={[permissions.eApprovalView]}>
      <div className="space-y-8">
        <header>
          <h1 className="text-2xl font-semibold tracking-tight text-foreground">Help</h1>
          <p className="mt-1 text-sm text-muted-foreground">
            Learn E-Approval with diagrams or an interactive tour on the live product.
          </p>
        </header>

        {query.isLoading ? <p className="text-sm text-muted-foreground">Loading guides…</p> : null}
        {query.isError ? (
          <p className="text-sm text-destructive">
            {getErrorMessage(query.error) || "Could not load help guides."}
          </p>
        ) : null}

        <section className="space-y-4">
          <h2 className="text-xl font-semibold text-foreground">E-Approval</h2>
          <div className="grid gap-4 sm:grid-cols-2">
            <EApprovalVisualGuideCard />
            <EApprovalTourGuideCard />
          </div>
        </section>

        {groups.map(({ moduleKey, guides }) => (
          <section key={moduleKey} className="space-y-4">
            <h2 className="text-xl font-semibold text-foreground">{moduleHeading(moduleKey)}</h2>
            <div className="grid gap-4 sm:grid-cols-2">
              {guides.map((guide) => (
                <Link
                  key={guide.id}
                  href={`/help/${guide.slug}`}
                  className="rounded-xl border border-border bg-card p-5 shadow-sm transition-colors hover:bg-muted/30"
                >
                  <p className="text-xs font-medium text-muted-foreground">{roleLabel(guide.role)}</p>
                  <h3 className="mt-2 text-base font-medium text-foreground">{guide.title}</h3>
                  <p className="mt-3 text-sm text-sky-700 dark:text-sky-400">Open guide →</p>
                </Link>
              ))}
            </div>
          </section>
        ))}
      </div>
    </PermissionGate>
  );
}
