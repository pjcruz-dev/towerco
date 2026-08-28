"use client";

import { useQuery } from "@tanstack/react-query";
import Link from "next/link";
import { BookOpen, Fingerprint, Play, ShieldCheck } from "lucide-react";
import { useMemo } from "react";

import { PermissionGate } from "@/components/layout/permission-gate";
import { usePermission } from "@/hooks/use-permission";
import { getErrorMessage } from "@/lib/api/error";
import { fetchPublishedHelpGuides, type HelpGuideListRow } from "@/lib/api/modules/help-guides-api";
import { dismissEApprovalTourPrompt, dismissTicketingTourPrompt } from "@/lib/help/e-approval-tour-prompt-preference";
import { liveTourStartHref } from "@/lib/help/e-approval-live-tour";
import { passkeysTourStartHref } from "@/lib/help/passkeys-live-tour";
import { mfaTourStartHref } from "@/lib/help/mfa-live-tour";
import { ticketingTourStartHref, TICKETING_TOUR_GUIDE_PATH } from "@/lib/help/ticketing-live-tour";
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
    // E-Approval / Ticketing use Visual guide + live tour instead of written role cards.
    if (guide.module_key === "e_approval" || guide.module_key === "ticketing") {
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
      <p className="text-xs font-medium text-muted-foreground">Guide</p>
      <h3 className="mt-2 text-base font-medium text-foreground">E-Approval tour guide</h3>
      <p className="mt-1 text-sm text-muted-foreground">
        Annotated screenshots plus jump-into-chapter starts — overview, submissions, compose, decide,
        cancel, and follow-up. Print-friendly for desk training.
      </p>
      <p className="mt-3 inline-flex items-center gap-1.5 text-sm text-sky-700 dark:text-sky-400">
        <BookOpen className="h-3.5 w-3.5" aria-hidden />
        Open tour guide →
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
        saved — jump into chapters from the tour guide anytime.
      </p>
      <p className="mt-3 inline-flex items-center gap-1.5 text-sm text-sky-700 dark:text-sky-400">
        <Play className="h-3.5 w-3.5" aria-hidden />
        Start full tour →
      </p>
    </Link>
  );
}

function TicketingTourGuideCard() {
  const canView = usePermission([permissions.ticketingView]);

  if (!canView) {
    return null;
  }

  return (
    <Link
      href={TICKETING_TOUR_GUIDE_PATH}
      className="rounded-xl border border-border bg-card p-5 shadow-sm transition-colors hover:bg-muted/30"
    >
      <p className="text-xs font-medium text-muted-foreground">Guide</p>
      <h3 className="mt-2 text-base font-medium text-foreground">Ticketing tour guide</h3>
      <p className="mt-1 text-sm text-muted-foreground">
        Annotated screenshots plus jump-into-chapter starts — overview, queue, create, and detail.
        Print-friendly for desk training.
      </p>
      <p className="mt-3 inline-flex items-center gap-1.5 text-sm text-sky-700 dark:text-sky-400">
        <BookOpen className="h-3.5 w-3.5" aria-hidden />
        Open tour guide →
      </p>
    </Link>
  );
}

function TicketingFullTourGuideCard() {
  const userId = useAuthStore((state) => state.user?.id ?? null);
  const tenantId = useAuthStore((state) => state.activeTenantId);
  const canView = usePermission([permissions.ticketingView]);
  const canCreate = usePermission([permissions.ticketingTicketsCreate]);
  const canManage = usePermission([permissions.ticketingTicketsManage]);
  const canSettings = usePermission([permissions.ticketingSettingsManage]);
  const isAdmin = canManage || canSettings;
  const liveTourHref = useMemo(
    () => ticketingTourStartHref(0, { canCreate, canManage, canSettings }),
    [canCreate, canManage, canSettings],
  );

  if (!canView) {
    return null;
  }

  return (
    <Link
      href={liveTourHref}
      onClick={() => dismissTicketingTourPrompt(userId, tenantId)}
      className="rounded-xl border border-border bg-card p-5 shadow-sm transition-colors hover:bg-muted/30"
    >
      <p className="text-xs font-medium text-muted-foreground">Interactive</p>
      <h3 className="mt-2 text-base font-medium text-foreground">Ticketing product tour</h3>
      <p className="mt-1 text-sm text-muted-foreground">
        {isAdmin
          ? "Walk the real screens with coach marks. Sample UI appears while the tour runs and is never saved."
          : "Walk Overview, the ticket queue, and raising a ticket. Sample UI appears while the tour runs and is never saved."}
      </p>
      <p className="mt-3 inline-flex items-center gap-1.5 text-sm text-sky-700 dark:text-sky-400">
        <Play className="h-3.5 w-3.5" aria-hidden />
        Start full tour →
      </p>
    </Link>
  );
}

function PasskeysTourGuideCard() {
  return (
    <Link
      href={passkeysTourStartHref(0)}
      className="rounded-xl border border-border bg-card p-5 shadow-sm transition-colors hover:bg-muted/30"
    >
      <p className="text-xs font-medium text-muted-foreground">Interactive</p>
      <h3 className="mt-2 text-base font-medium text-foreground">Add a passkey</h3>
      <p className="mt-1 text-sm text-muted-foreground">
        Starts from your account menu → My security → Passkeys. Enroll fingerprint, Face ID, or
        Windows Hello on this organization host.
      </p>
      <p className="mt-3 inline-flex items-center gap-1.5 text-sm text-sky-700 dark:text-sky-400">
        <Fingerprint className="h-3.5 w-3.5" aria-hidden />
        Start passkey tour →
      </p>
    </Link>
  );
}

function MfaTourGuideCard() {
  return (
    <Link
      href={mfaTourStartHref(0)}
      className="rounded-xl border border-border bg-card p-5 shadow-sm transition-colors hover:bg-muted/30"
    >
      <p className="text-xs font-medium text-muted-foreground">Interactive</p>
      <h3 className="mt-2 text-base font-medium text-foreground">Set up MFA (first time)</h3>
      <p className="mt-1 text-sm text-muted-foreground">
        Workspace tour: My security → sample QR → Start setup → verify. First login also opens a
        short guided tour on the Set up MFA screen after sign-in when enrollment is required.
      </p>
      <p className="mt-3 inline-flex items-center gap-1.5 text-sm text-sky-700 dark:text-sky-400">
        <ShieldCheck className="h-3.5 w-3.5" aria-hidden />
        Start MFA tour →
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
  const canViewTicketing = usePermission([permissions.ticketingView]);

  return (
    <PermissionGate requiredPermissions={[permissions.eApprovalView]}>
      <div className="space-y-8">
        <header>
          <h1 className="text-2xl font-semibold tracking-tight text-foreground">Help</h1>
          <p className="mt-1 text-sm text-muted-foreground">
            Product tours and guides for E-Approval, Ticketing, and account security.
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

        {canViewTicketing ? (
          <section className="space-y-4">
            <h2 className="text-xl font-semibold text-foreground">Ticketing</h2>
            <div className="grid gap-4 sm:grid-cols-2">
              <TicketingTourGuideCard />
              <TicketingFullTourGuideCard />
            </div>
          </section>
        ) : null}
        <section className="space-y-4">
          <h2 className="text-xl font-semibold text-foreground">Account &amp; security</h2>
          <div className="grid gap-4 sm:grid-cols-2">
            <MfaTourGuideCard />
            <PasskeysTourGuideCard />
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
