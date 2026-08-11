"use client";

import Link from "next/link";
import type { ReactNode } from "react";

import { AcronymLabel } from "@/components/help/acronym-label";
import { PermissionGate } from "@/components/layout/permission-gate";
import { RolloutPlaybookEmailNotifications } from "@/components/rollout/rollout-playbook-email-notifications";
import { RolloutPlaybookGatePolicies } from "@/components/rollout/rollout-playbook-gate-policies";
import { RolloutPlaybookOverrides } from "@/components/rollout/rollout-playbook-overrides";
import { Button } from "@/components/ui/button";
import { RefreshingHint } from "@/components/ui/refreshing-hint";
import { usePermission } from "@/hooks/use-permission";
import { useRolloutPlaybookStatus } from "@/hooks/use-rollout-playbook";
import { permissions } from "@/lib/rbac/permissions";

export function RolloutPlaybookPageClient() {
  const canConfigure = usePermission([permissions.playbookConfigure, permissions.tenantManage]);
  const { data, isFetching, isError, refetch } = useRolloutPlaybookStatus();

  return (
    <PermissionGate requiredPermissions={[permissions.projectOneView]}>
      <div className="space-y-5">
        <header className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h1 className="text-2xl font-semibold tracking-tight text-foreground">Rollout playbook</h1>
            <p className="mt-1 text-sm text-muted-foreground">
              Assigned master timeline version and tenant day-count overrides.
            </p>
            <p className="mt-2 text-xs font-medium">
              <Link className="text-primary underline-offset-4 hover:underline" href="/project-one/rollouts">
                Back to rollouts
              </Link>
            </p>
          </div>
          <Button size="sm" variant="outline" onClick={() => refetch()}>
            Refresh
          </Button>
        </header>

        {data?.upgrade_available ? (
          <div className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-100">
            Platform playbook <strong>{data.latest_platform_version}</strong> is available. Your tenant is on{" "}
            <strong>{data.assigned_version}</strong>. Live rollouts stay on their assigned version until you start new
            programs after upgrade.
          </div>
        ) : null}

        <div className="grid gap-3 sm:grid-cols-2">
          <InfoCard label="Assigned version" value={data?.assigned_version ?? "Not assigned"} />
          <InfoCard label="Latest platform version" value={data?.latest_platform_version ?? "—"} />
          <InfoCard
            label={<AcronymLabel term="SLA">SLA basis</AcronymLabel>}
            value={data?.sla_working_days_only ? "Working days only" : "Calendar days"}
          />
          <InfoCard
            label="Day overrides"
            value={
              data?.day_overrides && Object.keys(data.day_overrides).length > 0
                ? `${Object.keys(data.day_overrides).length} template(s)`
                : "None"
            }
          />
          <InfoCard
            label="PH holidays loaded"
            value={data?.public_holidays_count ? `${data.public_holidays_count} dates (${new Date().getFullYear()})` : "None seeded"}
          />
          <InfoCard
            label="Holiday breakdown"
            value={
              data?.public_holidays_count
                ? `${data.national_holidays_count ?? 0} national · ${data.regional_holidays_count ?? 0} regional`
                : "—"
            }
          />
        </div>

        {data?.sla_holiday_policy ? (
          <div className="rounded-xl border border-border bg-card p-4 text-sm text-muted-foreground shadow-sm">
            {data.sla_holiday_policy}
          </div>
        ) : null}

        <RolloutPlaybookOverrides status={data} canConfigure={canConfigure} />
        <RolloutPlaybookGatePolicies status={data} canConfigure={canConfigure} />
        <RolloutPlaybookEmailNotifications status={data} canConfigure={canConfigure} />

        <div className="rounded-xl border border-border bg-card p-4 shadow-sm">
          <div className="flex flex-wrap items-center justify-between gap-3">
            <div>
              <p className="text-base font-medium text-foreground">Public holiday calendar</p>
              <p className="mt-1 text-sm text-muted-foreground">
                {data?.public_holidays_count
                  ? `${data.public_holidays_count} dates (${data.national_holidays_count ?? 0} national, ${data.regional_holidays_count ?? 0} regional) for ${new Date().getFullYear()}`
                  : "No holidays seeded for the current year"}
              </p>
            </div>
            <Link
              href="/project-one/public-holidays"
              className="text-sm font-medium text-primary underline-offset-4 hover:underline"
            >
              Manage holidays
            </Link>
          </div>
        </div>

        {!canConfigure ? (
          <p className="text-xs text-muted-foreground">
            Day override editing requires <code className="rounded bg-muted px-1">project_one:playbook:configure</code>.
          </p>
        ) : null}

        {isFetching ? <RefreshingHint label="Loading playbook status" /> : null}
        {isError ? (
          <p className="text-xs text-red-600 dark:text-red-400">Unable to load playbook status.</p>
        ) : null}
      </div>
    </PermissionGate>
  );
}

function InfoCard({ label, value }: { label: ReactNode; value: string }) {
  return (
    <div className="rounded-xl border border-border bg-card p-4 shadow-sm">
      <p className="text-xs font-medium text-muted-foreground">{label}</p>
      <p className="mt-1 text-base font-medium text-foreground">{value}</p>
    </div>
  );
}
