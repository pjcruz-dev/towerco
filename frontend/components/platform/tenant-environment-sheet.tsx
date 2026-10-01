"use client";

import { useMemo, useState } from "react";

import { FormInput } from "@/components/forms/form-input";
import { Checkbox } from "@/components/ui/checkbox";
import {
  TenantModulesPicker,
  type TenantModulesPickerValue,
} from "@/components/platform/tenant-modules-picker";
import { Button } from "@/components/ui/button";
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetFooter,
  SheetHeader,
  SheetTitle,
} from "@/components/ui/sheet";
import { EnvironmentCloneProgress } from "@/components/platform/environment-clone-progress";
import type { EnvironmentCloneSnapshot, PlatformTenantRow } from "@/lib/api/modules/platform-api";
import {
  recommendedTenantDomain,
  type TenantEnvironment,
} from "@/lib/tenant/recommended-tenant-domain";
import { resolveDevAppPort } from "@/lib/tenant/resolve-tenant-domain";

const ENVIRONMENT_OPTIONS = [
  { value: "local", label: "Local" },
  { value: "test", label: "Test / UAT" },
  { value: "staging", label: "Staging" },
  { value: "production", label: "Production" },
] as const;

type EnvironmentValue = Extract<TenantEnvironment, (typeof ENVIRONMENT_OPTIONS)[number]["value"]>;

export type TenantEnvironmentConfirmPayload = {
  environment: EnvironmentValue;
  domain?: string;
  enabled_modules?: TenantModulesPickerValue;
  admin_password?: string;
  copy_data?: boolean;
  pause_source?: boolean;
  confirm_domain?: string;
};

type Props = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  sourceTenant: PlatformTenantRow;
  existingEnvironments: string[];
  isPending: boolean;
  clone?: EnvironmentCloneSnapshot | null;
  cloneActionPending?: boolean;
  onConfirm: (payload: TenantEnvironmentConfirmPayload) => void;
  onRetryClone?: () => void;
  onCancelClone?: () => void;
  onDiscardClone?: () => void;
};

export function TenantEnvironmentSheet({
  open,
  onOpenChange,
  sourceTenant,
  existingEnvironments,
  isPending,
  clone = null,
  cloneActionPending = false,
  onConfirm,
  onRetryClone,
  onCancelClone,
  onDiscardClone,
}: Props) {
  const availableOptions = ENVIRONMENT_OPTIONS.filter(
    (option) =>
      option.value !== sourceTenant.environment &&
      !existingEnvironments.includes(option.value),
  );

  const [environment, setEnvironment] = useState<EnvironmentValue>(availableOptions[0]?.value ?? "staging");
  const [domainOverride, setDomainOverride] = useState("");
  const [enabledModules, setEnabledModules] = useState<TenantModulesPickerValue>(
    () => sourceTenant.enabled_modules ?? null,
  );
  const [adminPassword, setAdminPassword] = useState("");
  const [copyData, setCopyData] = useState(false);
  const [pauseSource, setPauseSource] = useState(false);
  const [confirmDomain, setConfirmDomain] = useState("");

  const recommendedDomain = useMemo(
    () => recommendedTenantDomain(environment, sourceTenant.slug, sourceTenant.brand_domain),
    [environment, sourceTenant.brand_domain, sourceTenant.slug],
  );

  const effectiveDomain = domainOverride.trim() || recommendedDomain;
  const sourceDomain = sourceTenant.domains[0] ?? "";
  const productionConfirmOk =
    environment !== "production" || confirmDomain.trim().toLowerCase() === sourceDomain.toLowerCase();
  const copyRunning = clone !== null && !["ready", "failed", "cancelled", "discarded"].includes(clone.status);

  return (
    <Sheet
      open={open}
      onOpenChange={(nextOpen) => {
        onOpenChange(nextOpen);
        if (!nextOpen) {
          setDomainOverride("");
          setAdminPassword("");
          setCopyData(false);
          setPauseSource(false);
          setConfirmDomain("");
          setEnabledModules(sourceTenant.enabled_modules ?? null);
        }
      }}
    >
      <SheetContent className="w-full overflow-y-auto sm:max-w-lg">
        <SheetHeader>
          <SheetTitle>Add environment tenant</SheetTitle>
          <SheetDescription>
            Create a separate isolated tenant for another environment, linked to{" "}
            <span className="font-medium text-foreground">{sourceTenant.domains[0] ?? sourceTenant.slug ?? sourceTenant.id}</span>.
          </SheetDescription>
        </SheetHeader>

        <div className="space-y-4 px-4 py-2 text-sm text-muted-foreground">
          <p>
            Each environment has its own database, domains, and rollout data. Playbook and policy settings are copied
            from the source tenant.
          </p>

          {clone ? (
            <div className="rounded-lg border border-border bg-card p-3">
              <p className="mb-3 text-sm font-medium text-foreground">Copy progress</p>
              <EnvironmentCloneProgress
                clone={clone}
                pending={cloneActionPending}
                onRetry={onRetryClone}
                onCancel={onCancelClone}
                onDiscard={onDiscardClone}
              />
            </div>
          ) : isPending && copyData ? (
            <div className="space-y-2 rounded-lg border border-border bg-card p-3 text-foreground">
              <p className="text-sm font-medium">Copy progress</p>
              <div
                className="h-2 overflow-hidden rounded-full bg-muted"
                role="progressbar"
                aria-valuenow={5}
                aria-valuemin={0}
                aria-valuemax={100}
                aria-label="Environment copy"
              >
                <div className="h-full bg-primary" style={{ width: "5%" }} />
              </div>
              <p className="text-sm tabular-nums">5% · Creating the environment</p>
              <p className="text-muted-foreground">
                The percent moves to the database copy, then to the file count, as soon as this step finishes.
              </p>
            </div>
          ) : availableOptions.length === 0 ? (
            <p className="rounded-md border border-amber-200 bg-amber-50 p-3 text-amber-950 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-100">
              All standard environments already exist for this tenant group.
            </p>
          ) : (
            <>
              <label className="block space-y-1.5">
                <span className="text-xs font-medium text-foreground">Environment</span>
                <select
                  className="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                  value={environment}
                  onChange={(event) => setEnvironment(event.target.value as EnvironmentValue)}
                >
                  {availableOptions.map((option) => (
                    <option key={option.value} value={option.value}>
                      {option.label}
                    </option>
                  ))}
                </select>
              </label>

              <div className="rounded-md border border-border bg-muted/30 p-3 text-xs">
                <p>
                  <span className="font-medium text-foreground">Slug:</span>{" "}
                  <span className="font-mono">{sourceTenant.slug ?? "—"}</span>
                </p>
                <p className="mt-1">
                  <span className="font-medium text-foreground">Recommended domain:</span>{" "}
                  <span className="font-mono text-foreground">{recommendedDomain}</span>
                </p>
              </div>

              <FormInput
                label="Primary domain (optional override)"
                value={domainOverride}
                onChange={(event) => setDomainOverride(event.target.value)}
                placeholder={recommendedDomain}
                autoComplete="off"
              />

              <p className="text-xs leading-relaxed">
                Stancl will register <span className="font-mono text-foreground">{effectiveDomain}</span> for login.
                {environment === "local" || effectiveDomain.endsWith(".localhost")
                  ? ` Open http://${effectiveDomain}:${resolveDevAppPort()}/login after create.`
                  : " Point DNS to INFRA SUITE before go-live."}
              </p>

              <div className="space-y-2 rounded-lg border border-border p-3">
                <p className="text-xs font-medium text-foreground">Workspace modules</p>
                <TenantModulesPicker value={enabledModules} onChange={setEnabledModules} />
              </div>

              <div className="space-y-3 rounded-lg border border-border p-3">
                <label className="flex items-start gap-2 text-xs text-foreground">
                  <Checkbox
                    checked={copyData}
                    onCheckedChange={(checked) => setCopyData(checked === true)}
                    aria-label="Copy database and attachments"
                  />
                  <span>
                    <span className="font-medium">Copy database and attachments</span>
                    <span className="mt-1 block text-muted-foreground">
                      Copies the full workspace as of the moment the job starts, including users, automation,
                      pending approvals, and files. People can keep using this workspace. Work saved after the start
                      is not included. Schedules stay paused on the new environment until you enable them.
                    </span>
                  </span>
                </label>
                {copyData ? (
                  <label className="flex items-start gap-2 text-xs text-foreground">
                    <Checkbox
                      checked={pauseSource}
                      onCheckedChange={(checked) => setPauseSource(checked === true)}
                      aria-label="Pause changes on the source until the copy finishes"
                    />
                    <span>
                      <span className="font-medium">Pause changes until the copy finishes</span>
                      <span className="mt-1 block text-muted-foreground">
                        Sign-in stays open. New uploads and approvals wait, so the new environment matches this one
                        exactly. Leave this off for a rehearsal.
                      </span>
                    </span>
                  </label>
                ) : null}
              </div>

              {copyData && environment === "production" ? (
                <FormInput
                  label="Type the source domain to confirm"
                  value={confirmDomain}
                  onChange={(event) => setConfirmDomain(event.target.value)}
                  placeholder={sourceDomain}
                  autoComplete="off"
                />
              ) : null}

              {copyData ? null : (
                <>
                  <FormInput
                    label="Admin password (optional)"
                    type="password"
                    value={adminPassword}
                    onChange={(event) => setAdminPassword(event.target.value)}
                    placeholder="Leave blank to auto-generate"
                    autoComplete="new-password"
                  />
                  <p className="text-xs text-muted-foreground">
                    Sets the password for the bootstrap admin account. Minimum 12 characters when provided; leave blank to
                    auto-generate (shown once after create).
                  </p>
                </>
              )}
            </>
          )}
        </div>

        <SheetFooter className="gap-2 sm:gap-0">
          <Button type="button" variant="outline" disabled={isPending} onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          {clone || copyRunning ? null : (
          <Button
            type="button"
            disabled={isPending || availableOptions.length === 0 || (copyData && !productionConfirmOk)}
            onClick={() =>
              onConfirm({
                environment,
                domain: domainOverride.trim() || undefined,
                enabled_modules: enabledModules,
                admin_password: copyData ? undefined : adminPassword.trim() || undefined,
                copy_data: copyData || undefined,
                pause_source: copyData ? pauseSource : undefined,
                confirm_domain: copyData && environment === "production" ? confirmDomain.trim() : undefined,
              })
            }
          >
            {isPending ? (copyData ? "Starting copy…" : "Creating…") : copyData ? "Copy into new environment" : "Create environment tenant"}
          </Button>
          )}
        </SheetFooter>
      </SheetContent>
    </Sheet>
  );
}

export function environmentBadgeClass(environment: string | null | undefined): string {
  switch (environment) {
    case "production":
      return "bg-emerald-100 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-100";
    case "staging":
      return "bg-sky-100 text-sky-900 dark:bg-sky-950 dark:text-sky-100";
    case "test":
      return "bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-100";
    default:
      return "bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200";
  }
}
