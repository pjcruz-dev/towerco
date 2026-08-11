"use client";

import Link from "next/link";
import { AlertTriangle, ClipboardCheck, ShieldCheck } from "lucide-react";

import { AcronymLabel } from "@/components/help/acronym-label";
import { usePermission } from "@/hooks/use-permission";
import { permissions } from "@/lib/rbac/permissions";
import { cn } from "@/lib/utils";

type Props = {
  gateApprovalsAwaitingMe: number;
  programApprovalsPending: number;
  rolloutsSlaAtRisk: number;
};

function WorkChip({
  href,
  count,
  label,
  icon: Icon,
  tone,
}: {
  href: string;
  count: number;
  label: React.ReactNode;
  icon: typeof ShieldCheck;
  tone: "neutral" | "warning" | "danger";
}) {
  const toneClass =
    tone === "danger"
      ? "border-red-200 bg-red-50 text-red-900 hover:bg-red-100 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-100 dark:hover:bg-red-950/60"
      : tone === "warning"
        ? "border-amber-200 bg-amber-50 text-amber-950 hover:bg-amber-100 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-100 dark:hover:bg-amber-950/60"
        : "border-border bg-muted/40 text-foreground hover:bg-muted/60";

  return (
    <Link
      href={href}
      className={cn(
        "inline-flex min-h-11 w-full items-center gap-3 rounded-lg border px-3 py-2.5 text-sm transition-colors touch-manipulation sm:min-h-10 sm:w-auto sm:min-w-[200px] sm:flex-1",
        toneClass,
      )}
    >
      <Icon className="h-4 w-4 shrink-0 opacity-80" aria-hidden />
      <span className="min-w-0 flex-1">
        <span className="font-semibold tabular-nums">{count}</span>
        <span className="ml-1.5 font-medium">{label}</span>
      </span>
    </Link>
  );
}

export function MyWorkStrip({ gateApprovalsAwaitingMe, programApprovalsPending, rolloutsSlaAtRisk }: Props) {
  const canViewRollouts = usePermission([permissions.rolloutView]);
  const canViewApprovals = usePermission([permissions.projectOneView]);

  const chips: Array<{
    href: string;
    count: number;
    label: React.ReactNode;
    icon: typeof ShieldCheck;
    tone: "neutral" | "warning" | "danger";
    show: boolean;
  }> = [
    {
      href: "/project-one/gate-approvals?awaiting_me=1",
      count: gateApprovalsAwaitingMe,
      label: "gate approvals for you",
      icon: ShieldCheck,
      tone: gateApprovalsAwaitingMe > 0 ? "danger" : "neutral",
      show: canViewRollouts,
    },
    {
      href: "/project-one/approvals",
      count: programApprovalsPending,
      label: "program approvals pending",
      icon: ClipboardCheck,
      tone: programApprovalsPending > 0 ? "warning" : "neutral",
      show: canViewApprovals,
    },
    {
      href: "/project-one/rollouts?sla_at_risk=1",
      count: rolloutsSlaAtRisk,
      label: (
        <>
          rollouts at <AcronymLabel term="SLA" /> risk
        </>
      ),
      icon: AlertTriangle,
      tone: rolloutsSlaAtRisk > 0 ? "danger" : "neutral",
      show: canViewRollouts,
    },
  ];

  const visible = chips.filter((chip) => chip.show);
  if (visible.length === 0) {
    return null;
  }

  return (
    <section
      className="rounded-xl border border-border bg-card p-3 shadow-sm"
      aria-label="My work"
    >
      <p className="mb-2 px-1 text-xs font-medium text-muted-foreground">My work</p>
      <div className="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
        {visible.map((chip) => (
          <WorkChip key={chip.href} {...chip} />
        ))}
      </div>
    </section>
  );
}
