"use client";

import { useMutation, useQuery } from "@tanstack/react-query";
import { useEffect, useMemo, useRef, useState } from "react";

import { BillingEstimateCard } from "@/components/billing/billing-estimate-card";
import {
  PlanTierComparisonTable,
  type PlanCatalogTier,
} from "@/components/billing/plan-tier-comparison-table";
import { computeTenantBillingEstimate } from "@/lib/billing/tenant-billing-estimate";
import { PlatformBillingFormSection } from "@/components/platform/platform-billing-form-section";
import { FormInput } from "@/components/forms/form-input";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Label } from "@/components/ui/label";
import { Skeleton } from "@/components/ui/skeleton";
import { Select } from "@/components/ui/select";
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetFooter,
  SheetHeader,
  SheetTitle,
} from "@/components/ui/sheet";
import { getErrorMessage } from "@/lib/api/error";
import {
  platformCreateTenantBillingPortalSession,
  platformFetchPlanCatalog,
  platformFetchTenantBillingAudit,
  type PlatformTenantBillingAuditRow,
  type PlatformTenantRow,
  type PlatformTenantSettingsPatch,
} from "@/lib/api/modules/platform-api";
import { platformHasPermission, PLATFORM_PERMS } from "@/lib/platform/platform-permissions";
import { usePlatformAuthStore } from "@/stores/platform-auth-store";
import { useNotificationStore } from "@/stores/notification-store";

type Props = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  tenant: PlatformTenantRow;
  isPending: boolean;
  downgradeWarnings: string[];
  confirmDowngrade: boolean;
  onConfirmDowngradeChange: (value: boolean) => void;
  onClearDowngradeWarnings: () => void;
  onSave: (payload: {
    plan_tier: "starter" | "professional" | "enterprise";
    subscription_status: "trial" | "active" | "past_due" | "canceled";
    trial_ends_at?: string | null;
    past_due_grace_ends_at?: string | null;
    seat_limit: number;
    billing_meter_starts_at?: string | null;
    billing_interval?: "monthly" | "annual";
    confirm_plan_downgrade?: boolean;
    billing_overrides?: PlatformTenantSettingsPatch["billing_overrides"];
  }) => void;
};

const PLAN_OPTIONS = [
  { value: "starter", label: "Starter" },
  { value: "professional", label: "Professional" },
  { value: "enterprise", label: "Enterprise" },
] as const;

const STATUS_OPTIONS = [
  { value: "trial", label: "Trial" },
  { value: "active", label: "Active" },
  { value: "past_due", label: "Past due" },
  { value: "canceled", label: "Canceled" },
] as const;

function toDatetimeLocalValue(iso: string | null | undefined): string {
  if (!iso) {
    return "";
  }
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) {
    return "";
  }
  const pad = (value: number) => String(value).padStart(2, "0");
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

function buildBillingOverrides(input: {
  clearOverrides: boolean;
  planTier: "starter" | "professional" | "enterprise";
  overrideSeatLimit: string;
  overrideIncludedRfiUnits: string;
  overrideGrandfatherRfiUnits: string;
  overrideAnnualDiscount: string;
  overrideFileUploads: boolean;
  overrideUnlimitedFiles: boolean;
  overrideMaxFileFields: string;
  overrideRolloutUploads: boolean;
  overrideTicketingEnabled: boolean;
  overrideTicketingFileUploads: boolean;
  overrideTicketingUnlimitedAttachments: boolean;
  overrideTicketingMaxAttachments: string;
}): PlatformTenantSettingsPatch["billing_overrides"] {
  if (input.clearOverrides) {
    return null;
  }

  const overrides: NonNullable<PlatformTenantSettingsPatch["billing_overrides"]> = {};

  const seat = Number.parseInt(input.overrideSeatLimit, 10);
  if (Number.isFinite(seat) && seat >= 1) {
    overrides.seat_limit = seat;
  }

  const includedRfi = Number.parseInt(input.overrideIncludedRfiUnits, 10);
  if (Number.isFinite(includedRfi) && includedRfi >= 0) {
    overrides.included_rfi_units = includedRfi;
  }

  const grandfatherRfi = Number.parseInt(input.overrideGrandfatherRfiUnits, 10);
  if (Number.isFinite(grandfatherRfi) && grandfatherRfi >= 0) {
    overrides.grandfather_rfi_units = grandfatherRfi;
  }

  if (input.overrideAnnualDiscount.trim() !== "") {
    const discount = Number.parseFloat(input.overrideAnnualDiscount);
    if (Number.isFinite(discount)) {
      overrides.annual_discount_percent = discount;
    }
  }

  if (input.planTier === "enterprise") {
    overrides.modules = {};
  }

  if (input.planTier === "enterprise" && input.overrideFileUploads) {
    overrides.modules!.e_approval = {
      file_uploads: true,
      max_file_fields: input.overrideUnlimitedFiles
        ? null
        : Number.parseInt(input.overrideMaxFileFields, 10) || 0,
    };
  }

  if (input.planTier === "enterprise" && input.overrideRolloutUploads) {
    overrides.modules!.project_one = { rollout_file_uploads: true };
  }

  if (
    input.planTier === "enterprise"
    && (input.overrideTicketingEnabled || input.overrideTicketingFileUploads)
  ) {
    const ticketing: NonNullable<
      NonNullable<PlatformTenantSettingsPatch["billing_overrides"]>["modules"]
    >["ticketing"] = {};

    if (input.overrideTicketingEnabled) {
      ticketing.enabled = true;
    }
    if (input.overrideTicketingFileUploads) {
      ticketing.file_uploads = true;
      ticketing.enabled = true;
      ticketing.max_attachments_per_ticket = input.overrideTicketingUnlimitedAttachments
        ? null
        : Number.parseInt(input.overrideTicketingMaxAttachments, 10) || 0;
    }

    overrides.modules!.ticketing = ticketing;
  }

  const hasModules = Object.keys(overrides.modules ?? {}).length > 0;
  if (!hasModules) {
    delete overrides.modules;
  }

  if (Object.keys(overrides).length === 0) {
    return undefined;
  }

  return overrides;
}

const TIER_RANK: Record<string, number> = {
  starter: 1,
  professional: 2,
  enterprise: 3,
};

function formatAuditChange(row: PlatformTenantBillingAuditRow): string {
  const parts = Object.entries(row.changes ?? {}).map(([field, change]) => {
    const from = change?.from ?? "—";
    const to = change?.to ?? "—";
    return `${field}: ${from} → ${to}`;
  });
  return parts.join("; ") || "Updated";
}

export function TenantBillingSheet({
  open,
  onOpenChange,
  tenant,
  isPending,
  downgradeWarnings,
  confirmDowngrade,
  onConfirmDowngradeChange,
  onClearDowngradeWarnings,
  onSave,
}: Props) {
  const user = usePlatformAuthStore((s) => s.user);
  const notify = useNotificationStore((s) => s.push);
  const canManageBilling = platformHasPermission(user, PLATFORM_PERMS.billingManage);

  const label = tenant.domains[0] ?? tenant.slug ?? tenant.id;
  const initialTier = (tenant.plan_tier ?? "starter") as "starter" | "professional" | "enterprise";

  const [planTier, setPlanTier] = useState<"starter" | "professional" | "enterprise">("starter");
  const [subscriptionStatus, setSubscriptionStatus] = useState<
    "trial" | "active" | "past_due" | "canceled"
  >("active");
  const [seatLimit, setSeatLimit] = useState("25");
  const [trialEndsAt, setTrialEndsAt] = useState("");
  const [graceEndsAt, setGraceEndsAt] = useState("");
  const [overrideSeatLimit, setOverrideSeatLimit] = useState("");
  const [overrideFileUploads, setOverrideFileUploads] = useState(false);
  const [overrideUnlimitedFiles, setOverrideUnlimitedFiles] = useState(false);
  const [overrideMaxFileFields, setOverrideMaxFileFields] = useState("");
  const [overrideRolloutUploads, setOverrideRolloutUploads] = useState(false);
  const [overrideTicketingEnabled, setOverrideTicketingEnabled] = useState(false);
  const [overrideTicketingFileUploads, setOverrideTicketingFileUploads] = useState(false);
  const [overrideTicketingUnlimitedAttachments, setOverrideTicketingUnlimitedAttachments] =
    useState(false);
  const [overrideTicketingMaxAttachments, setOverrideTicketingMaxAttachments] = useState("");
  const [overrideIncludedRfiUnits, setOverrideIncludedRfiUnits] = useState("");
  const [overrideGrandfatherRfiUnits, setOverrideGrandfatherRfiUnits] = useState("");
  const [overrideAnnualDiscount, setOverrideAnnualDiscount] = useState("");
  const [billingMeterStartsAt, setBillingMeterStartsAt] = useState("");
  const [billingInterval, setBillingInterval] = useState<"monthly" | "annual">("monthly");
  const [clearOverrides, setClearOverrides] = useState(false);
  const initTenantIdRef = useRef<string | null>(null);

  // Initialize form only when the sheet opens or a different tenant is selected — not on
  // background tenant list refetches (which was resetting seat limit while typing).
  useEffect(() => {
    if (!open) {
      initTenantIdRef.current = null;
      return;
    }
    if (initTenantIdRef.current === tenant.id) {
      return;
    }
    initTenantIdRef.current = tenant.id;

    setPlanTier(
      initialTier === "professional" || initialTier === "enterprise" ? initialTier : "starter",
    );
    const status = (tenant.subscription_status ?? "active") as
      | "trial"
      | "active"
      | "past_due"
      | "canceled";
    setSubscriptionStatus(
      status === "trial" || status === "past_due" || status === "canceled" ? status : "active",
    );
    setSeatLimit(String(tenant.seat_limit ?? 25));
    setTrialEndsAt("");
    setGraceEndsAt("");

    const overrides = tenant.billing_overrides;
    const ea = overrides?.modules?.e_approval;
    const po = overrides?.modules?.project_one;
    const tk = overrides?.modules?.ticketing;
    setOverrideSeatLimit(
      overrides?.seat_limit != null ? String(overrides.seat_limit) : "",
    );
    setOverrideFileUploads(Boolean(ea?.file_uploads));
    setOverrideUnlimitedFiles(ea?.max_file_fields === null);
    setOverrideMaxFileFields(
      ea?.max_file_fields != null ? String(ea.max_file_fields) : "",
    );
    setOverrideRolloutUploads(Boolean(po?.rollout_file_uploads));
    setOverrideTicketingEnabled(Boolean(tk?.enabled));
    setOverrideTicketingFileUploads(Boolean(tk?.file_uploads));
    setOverrideTicketingUnlimitedAttachments(tk?.max_attachments_per_ticket === null);
    setOverrideTicketingMaxAttachments(
      tk?.max_attachments_per_ticket != null ? String(tk.max_attachments_per_ticket) : "",
    );
    setOverrideIncludedRfiUnits(
      overrides?.included_rfi_units != null ? String(overrides.included_rfi_units) : "",
    );
    setOverrideGrandfatherRfiUnits(
      overrides?.grandfather_rfi_units != null ? String(overrides.grandfather_rfi_units) : "",
    );
    setOverrideAnnualDiscount(
      overrides?.annual_discount_percent != null ? String(overrides.annual_discount_percent) : "",
    );
    setBillingMeterStartsAt(toDatetimeLocalValue(tenant.billing_meter_starts_at));
    setBillingInterval(tenant.billing_interval === "annual" ? "annual" : "monthly");
    setClearOverrides(false);
    onClearDowngradeWarnings();
    onConfirmDowngradeChange(false);
  }, [open, tenant.id, initialTier, onClearDowngradeWarnings, onConfirmDowngradeChange]);

  const parsedSeatLimit = Number.parseInt(seatLimit, 10);

  const overrideSeat =
    overrideSeatLimit !== "" && Number.isFinite(Number.parseInt(overrideSeatLimit, 10))
      ? Number.parseInt(overrideSeatLimit, 10)
      : null;
  const effectiveSeatPreview =
    overrideSeat != null && !clearOverrides
      ? overrideSeat
      : Number.isFinite(parsedSeatLimit)
        ? parsedSeatLimit
        : (tenant.effective_seat_limit ?? tenant.seat_limit ?? 25);

  const catalogQuery = useQuery({
    queryKey: ["platform", "billing", "plan-catalog"],
    queryFn: platformFetchPlanCatalog,
    enabled: open,
    staleTime: 300_000,
  });

  const auditQuery = useQuery({
    queryKey: ["platform", "tenants", tenant.id, "billing-audit"],
    queryFn: () => platformFetchTenantBillingAudit(tenant.id),
    enabled: open,
  });

  const catalogTiers = useMemo(
    () => (catalogQuery.data?.tiers ?? []) as PlanCatalogTier[],
    [catalogQuery.data],
  );

  const billingEstimatePreview = useMemo(() => {
    if (catalogTiers.length === 0) {
      return null;
    }

    const parsedRfiOverride =
      overrideIncludedRfiUnits.trim() !== ""
        ? Number.parseInt(overrideIncludedRfiUnits, 10)
        : null;
    const parsedAnnualDiscount =
      overrideAnnualDiscount.trim() !== ""
        ? Number.parseFloat(overrideAnnualDiscount)
        : null;
    const parsedGrandfather =
      overrideGrandfatherRfiUnits.trim() !== ""
        ? Number.parseInt(overrideGrandfatherRfiUnits, 10)
        : 0;

    return computeTenantBillingEstimate({
      planTier,
      currency: catalogQuery.data?.currency ?? "USD",
      catalogTiers,
      defaultAnnualDiscountPercent: catalogQuery.data?.default_annual_discount_percent ?? 20,
      billingInterval,
      annualDiscountOverride:
        parsedAnnualDiscount != null && Number.isFinite(parsedAnnualDiscount)
          ? parsedAnnualDiscount
          : null,
      effectiveSeatLimit: effectiveSeatPreview,
      includedRfiUnitsOverride:
        parsedRfiOverride != null && Number.isFinite(parsedRfiOverride) ? parsedRfiOverride : null,
      grandfatherRfiUnits: Number.isFinite(parsedGrandfather) ? parsedGrandfather : 0,
      rfiUsed: tenant.rfi_units_used ?? undefined,
    });
  }, [
    billingInterval,
    catalogQuery.data?.currency,
    catalogQuery.data?.default_annual_discount_percent,
    catalogTiers,
    effectiveSeatPreview,
    overrideAnnualDiscount,
    overrideGrandfatherRfiUnits,
    overrideIncludedRfiUnits,
    planTier,
    tenant.rfi_units_used,
  ]);

  const stripePayments = catalogQuery.data?.payments;

  const portalMutation = useMutation({
    mutationFn: () => platformCreateTenantBillingPortalSession(tenant.id),
    onSuccess: (data) => {
      window.open(data.url, "_blank", "noopener,noreferrer");
    },
    onError: (error) =>
      notify({
        level: "error",
        title: "Stripe portal unavailable",
        message: getErrorMessage(error),
      }),
  });

  const isDowngradeSelection =
    (TIER_RANK[planTier] ?? 0) < (TIER_RANK[initialTier] ?? 0);

  const meteringActive = Boolean(billingMeterStartsAt);
  const rfiUsed = tenant.rfi_units_used ?? 0;
  const rfiLimit = tenant.effective_rfi_limit ?? 0;

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent className="flex w-full flex-col gap-0 p-0 sm:max-w-xl md:max-w-2xl lg:max-w-3xl">
        <SheetHeader className="border-b border-border px-6 py-4">
          <SheetTitle>Billing & plan</SheetTitle>
          <SheetDescription>
            Subscription, RFI metering, and seat limits for{" "}
            <span className="font-medium text-foreground">{label}</span>.
          </SheetDescription>
        </SheetHeader>

        <div className="flex flex-1 flex-col gap-4 overflow-y-auto px-6 py-4">
          <div className="grid gap-3 sm:grid-cols-3">
            <div className="rounded-xl border border-border bg-muted/30 px-3 py-2.5">
              <p className="text-xs font-medium text-muted-foreground">RFI units</p>
              <p className="mt-1 text-lg font-medium tabular-nums text-foreground">
                {rfiUsed}
                <span className="text-sm font-normal text-muted-foreground"> / {rfiLimit}</span>
              </p>
              <p className="mt-0.5 text-[11px] text-muted-foreground">
                {meteringActive ? "Metering active" : "Metering off until go-live is set"}
              </p>
            </div>
            <div className="rounded-xl border border-border bg-muted/30 px-3 py-2.5">
              <p className="text-xs font-medium text-muted-foreground">Paid seats</p>
              <p className="mt-1 text-lg font-medium tabular-nums text-foreground">
                {effectiveSeatPreview}
                <span className="text-sm font-normal text-muted-foreground"> limit</span>
              </p>
              <p className="mt-0.5 text-[11px] text-muted-foreground">Viewers do not count</p>
            </div>
            <div className="rounded-xl border border-border bg-muted/30 px-3 py-2.5">
              <p className="text-xs font-medium text-muted-foreground">Billing interval</p>
              <p className="mt-1 text-lg font-medium capitalize text-foreground">{billingInterval}</p>
              <p className="mt-0.5 text-[11px] text-muted-foreground">
                {billingInterval === "annual" ? "Catalog annual discount applies" : "Monthly list price"}
              </p>
            </div>
          </div>

          <PlatformBillingFormSection
            title="Subscription"
            description="Plan tier and subscription lifecycle for this tenant."
          >
            <div className="grid gap-4 sm:grid-cols-2">
              <div className="space-y-2">
                <Label htmlFor="billing-plan-tier" className="text-xs font-medium text-muted-foreground">
                  Plan tier
                </Label>
                <Select
                  id="billing-plan-tier"
                  className="h-10 w-full"
                  value={planTier}
                  onChange={(event) => {
                    setPlanTier(event.target.value as "starter" | "professional" | "enterprise");
                    onClearDowngradeWarnings();
                    onConfirmDowngradeChange(false);
                  }}
                >
                  {PLAN_OPTIONS.map((option) => (
                    <option key={option.value} value={option.value}>
                      {option.label}
                    </option>
                  ))}
                </Select>
              </div>

              <div className="space-y-2">
                <Label
                  htmlFor="billing-subscription-status"
                  className="text-xs font-medium text-muted-foreground"
                >
                  Subscription status
                </Label>
                <Select
                  id="billing-subscription-status"
                  className="h-10 w-full"
                  value={subscriptionStatus}
                  onChange={(event) =>
                    setSubscriptionStatus(
                      event.target.value as "trial" | "active" | "past_due" | "canceled",
                    )
                  }
                >
                  {STATUS_OPTIONS.map((option) => (
                    <option key={option.value} value={option.value}>
                      {option.label}
                    </option>
                  ))}
                </Select>
              </div>
            </div>

            {subscriptionStatus === "trial" ? (
              <FormInput
                label="Trial ends (optional)"
                id="billing-trial-ends"
                dateTime
                value={trialEndsAt}
                onChange={(event) => setTrialEndsAt(event.target.value)}
              />
            ) : null}

            {subscriptionStatus === "past_due" ? (
              <FormInput
                label="Grace period ends (optional)"
                id="billing-grace-ends"
                dateTime
                value={graceEndsAt}
                onChange={(event) => setGraceEndsAt(event.target.value)}
              />
            ) : null}
          </PlatformBillingFormSection>

          <PlatformBillingFormSection
            title="RFI metering"
            description="Only RFIs recorded on or after go-live count toward limits. Tower inventory is never blocked."
          >
            <div className="grid gap-4 lg:grid-cols-2">
              <FormInput
                label="Go-live date & time"
                id="billing-meter-starts"
                dateTime
                className="min-w-0"
                value={billingMeterStartsAt}
                onChange={(event) => setBillingMeterStartsAt(event.target.value)}
              />
              <div className="space-y-2">
                <Label htmlFor="billing-interval" className="text-xs font-medium text-muted-foreground">
                  Billing interval
                </Label>
                <Select
                  id="billing-interval"
                  className="h-10 w-full"
                  value={billingInterval}
                  onChange={(event) =>
                    setBillingInterval(event.target.value as "monthly" | "annual")
                  }
                >
                  <option value="monthly">Monthly</option>
                  <option value="annual">Annual prepay</option>
                </Select>
              </div>
            </div>
          </PlatformBillingFormSection>

          <PlatformBillingFormSection
            title="Sales overrides"
            description="Grandfather RFI capacity or set a tenant-specific annual discount without changing the platform catalog."
          >
            <div className="grid gap-4 sm:grid-cols-2">
              <FormInput
                label="Included RFI units"
                id="billing-override-rfi"
                type="number"
                min={0}
                placeholder="Catalog default"
                value={overrideIncludedRfiUnits}
                onChange={(event) => setOverrideIncludedRfiUnits(event.target.value)}
              />
              <FormInput
                label="Grandfather RFI units"
                id="billing-grandfather-rfi"
                type="number"
                min={0}
                placeholder="0"
                value={overrideGrandfatherRfiUnits}
                onChange={(event) => setOverrideGrandfatherRfiUnits(event.target.value)}
              />
              <FormInput
                label="Annual discount %"
                id="billing-annual-discount"
                type="number"
                min={0}
                max={80}
                step={0.5}
                placeholder="Catalog default"
                value={overrideAnnualDiscount}
                onChange={(event) => setOverrideAnnualDiscount(event.target.value)}
              />
            </div>
          </PlatformBillingFormSection>

          <PlatformBillingFormSection
            title="Paid seats"
            description="Tenant /billing is read-only — adjust limits here. Viewer-only users are free."
          >
            <FormInput
              label="Seat limit"
              id="billing-seat-limit"
              type="number"
              min={1}
              max={10000}
              step={1}
              inputMode="numeric"
              value={seatLimit}
              onChange={(event) => setSeatLimit(event.target.value)}
            />
            <p className="text-xs text-muted-foreground">
              Effective limit enforced:{" "}
              <span className="font-medium text-foreground">{effectiveSeatPreview}</span>
              {overrideSeat != null && !clearOverrides ? " (enterprise override)" : null}
            </p>
          </PlatformBillingFormSection>

          {billingEstimatePreview ? (
            <BillingEstimateCard estimate={billingEstimatePreview} compact />
          ) : null}

          <div className="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-border bg-muted/20 px-3 py-2.5">
            <div className="flex items-center gap-2">
              <Badge variant={stripePayments?.operational ? "default" : "secondary"}>
                {stripePayments?.operational ? "Stripe on" : "Manual billing"}
              </Badge>
              <p className="text-xs text-muted-foreground">
                {stripePayments?.operational
                  ? "Tenants can self-serve on /billing"
                  : "Stripe disabled or not configured"}
              </p>
            </div>
            {canManageBilling && stripePayments?.operational ? (
              <Button
                type="button"
                variant="outline"
                size="sm"
                disabled={portalMutation.isPending}
                onClick={() => portalMutation.mutate()}
              >
                {portalMutation.isPending ? "Opening…" : "Open Stripe portal"}
              </Button>
            ) : null}
          </div>

          {planTier === "enterprise" ? (
            <PlatformBillingFormSection
              title="Enterprise entitlements"
              description="Optional module overrides on top of the Enterprise catalog."
            >
              <label className="flex items-center gap-2 text-sm text-foreground">
                <Checkbox
                  className="size-4"
                  checked={clearOverrides}
                  onCheckedChange={(v) => setClearOverrides(v === true)}
                />
                Clear all enterprise overrides
              </label>
              {!clearOverrides ? (
                <div className="space-y-3">
                  <FormInput
                    label="Override seat limit (optional)"
                    id="billing-override-seats"
                    type="number"
                    min={1}
                    max={10000}
                    value={overrideSeatLimit}
                    onChange={(event) => setOverrideSeatLimit(event.target.value)}
                  />
                  <div className="space-y-2 rounded-lg border border-border/60 bg-muted/10 p-3">
                    <label className="flex items-center gap-2 text-sm text-foreground">
                      <Checkbox
                        className="size-4"
                        checked={overrideFileUploads}
                        onCheckedChange={(v) => setOverrideFileUploads(v === true)}
                      />
                      E-Approval file uploads
                    </label>
                    {overrideFileUploads ? (
                      <div className="ml-6 space-y-2">
                        <label className="flex items-center gap-2 text-sm text-foreground">
                          <Checkbox
                            className="size-4"
                            checked={overrideUnlimitedFiles}
                            onCheckedChange={(v) => setOverrideUnlimitedFiles(v === true)}
                          />
                          Unlimited file fields
                        </label>
                        {!overrideUnlimitedFiles ? (
                          <FormInput
                            label="Max file fields"
                            id="billing-override-max-files"
                            type="number"
                            min={0}
                            max={1000}
                            value={overrideMaxFileFields}
                            onChange={(event) => setOverrideMaxFileFields(event.target.value)}
                          />
                        ) : null}
                      </div>
                    ) : null}
                    <label className="flex items-center gap-2 text-sm text-foreground">
                      <Checkbox
                        className="size-4"
                        checked={overrideRolloutUploads}
                        onCheckedChange={(v) => setOverrideRolloutUploads(v === true)}
                      />
                      PROJECT-ONE rollout uploads
                    </label>
                    <label className="flex items-center gap-2 text-sm text-foreground">
                      <Checkbox
                        className="size-4"
                        checked={overrideTicketingEnabled}
                        onCheckedChange={(v) => setOverrideTicketingEnabled(v === true)}
                      />
                      Ticketing module
                    </label>
                    <label className="flex items-center gap-2 text-sm text-foreground">
                      <Checkbox
                        className="size-4"
                        checked={overrideTicketingFileUploads}
                        onCheckedChange={(v) => setOverrideTicketingFileUploads(v === true)}
                      />
                      Ticketing ticket attachments
                    </label>
                    {overrideTicketingFileUploads ? (
                      <div className="ml-6 space-y-2">
                        <label className="flex items-center gap-2 text-sm text-foreground">
                          <Checkbox
                            className="size-4"
                            checked={overrideTicketingUnlimitedAttachments}
                            onCheckedChange={(v) =>
                              setOverrideTicketingUnlimitedAttachments(v === true)
                            }
                          />
                          Unlimited attachments per ticket
                        </label>
                        {!overrideTicketingUnlimitedAttachments ? (
                          <FormInput
                            label="Max attachments per ticket"
                            id="billing-override-ticket-attachments"
                            type="number"
                            min={0}
                            max={100}
                            value={overrideTicketingMaxAttachments}
                            onChange={(event) =>
                              setOverrideTicketingMaxAttachments(event.target.value)
                            }
                          />
                        ) : null}
                      </div>
                    ) : null}
                  </div>
                </div>
              ) : null}
            </PlatformBillingFormSection>
          ) : null}

          {catalogTiers.length > 0 ? (
            <details className="rounded-xl border border-border bg-card px-4 py-3 shadow-sm">
              <summary className="cursor-pointer text-sm font-medium text-foreground">
                Plan catalog comparison
              </summary>
              <div className="mt-3">
                <PlanTierComparisonTable
                  tiers={catalogTiers}
                  currentTier={initialTier}
                  currency={catalogQuery.data?.currency ?? "USD"}
                />
              </div>
            </details>
          ) : null}

          {isDowngradeSelection && downgradeWarnings.length === 0 ? (
            <p className="text-xs text-amber-700 dark:text-amber-400">
              Downgrading may block E-Approval forms that use file fields. Save once to see
              warnings, then confirm if required.
            </p>
          ) : null}

          {downgradeWarnings.length > 0 ? (
            <div className="space-y-3 rounded-lg border border-amber-500/40 bg-amber-500/5 p-3">
              <p className="text-sm font-medium text-foreground">Confirm plan downgrade</p>
              <ul className="list-inside list-disc space-y-1 text-xs text-muted-foreground">
                {downgradeWarnings.map((warning) => (
                  <li key={warning}>{warning}</li>
                ))}
              </ul>
              <label className="flex items-start gap-2 text-sm">
                <Checkbox
                  className="mt-0.5 size-4"
                  checked={confirmDowngrade}
                  onCheckedChange={(v) => onConfirmDowngradeChange(v === true)}
                />
                I understand and want to apply this downgrade
              </label>
            </div>
          ) : null}

          <PlatformBillingFormSection title="Recent changes">
            {auditQuery.isLoading ? (
              <div className="space-y-2">
                {Array.from({ length: 4 }).map((_, index) => (
                  <Skeleton key={`audit-skeleton-${index}`} className="h-10 w-full" />
                ))}
              </div>
            ) : auditQuery.data && auditQuery.data.length > 0 ? (
              <ul className="max-h-36 space-y-2 overflow-y-auto text-xs">
                {auditQuery.data.map((row) => (
                  <li
                    key={row.id}
                    className="rounded-md border border-border/60 bg-muted/20 px-3 py-2"
                  >
                    <p className="font-medium text-foreground">{row.actor_email ?? "System"}</p>
                    <p className="mt-0.5 text-muted-foreground">{formatAuditChange(row)}</p>
                  </li>
                ))}
              </ul>
            ) : (
              <p className="text-xs text-muted-foreground">No billing changes recorded yet.</p>
            )}
          </PlatformBillingFormSection>
        </div>

        <SheetFooter className="sticky bottom-0 border-t border-border bg-background px-6 py-4 sm:flex-row sm:justify-end sm:gap-2">
          <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button
            type="button"
            disabled={
              isPending ||
              !Number.isFinite(parsedSeatLimit) ||
              parsedSeatLimit < 1 ||
              parsedSeatLimit > 10000 ||
              (downgradeWarnings.length > 0 && !confirmDowngrade)
            }
            onClick={() =>
              onSave({
                plan_tier: planTier,
                subscription_status: subscriptionStatus,
                trial_ends_at:
                  subscriptionStatus === "trial" && trialEndsAt !== ""
                    ? new Date(trialEndsAt).toISOString()
                    : subscriptionStatus === "trial"
                      ? null
                      : undefined,
                past_due_grace_ends_at:
                  subscriptionStatus === "past_due" && graceEndsAt !== ""
                    ? new Date(graceEndsAt).toISOString()
                    : subscriptionStatus === "past_due"
                      ? null
                      : undefined,
                seat_limit: parsedSeatLimit,
                billing_meter_starts_at:
                  billingMeterStartsAt !== ""
                    ? new Date(billingMeterStartsAt).toISOString()
                    : null,
                billing_interval: billingInterval,
                confirm_plan_downgrade: downgradeWarnings.length > 0 ? confirmDowngrade : undefined,
                billing_overrides: buildBillingOverrides({
                  clearOverrides,
                  planTier,
                  overrideSeatLimit,
                  overrideIncludedRfiUnits,
                  overrideGrandfatherRfiUnits,
                  overrideAnnualDiscount,
                  overrideFileUploads,
                  overrideUnlimitedFiles,
                  overrideMaxFileFields,
                  overrideRolloutUploads,
                  overrideTicketingEnabled,
                  overrideTicketingFileUploads,
                  overrideTicketingUnlimitedAttachments,
                  overrideTicketingMaxAttachments,
                }),
              })
            }
          >
            Save billing
          </Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>
  );
}
