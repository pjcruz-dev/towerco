"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ChevronDown } from "lucide-react";
import { useEffect, useMemo, useRef, useState } from "react";

import { PlatformBillingFormSection } from "@/components/platform/platform-billing-form-section";
import { FormInput } from "@/components/forms/form-input";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { convertCurrencyAmount } from "@/lib/billing/convert-currency-amount";
import { SectionCardSkeleton } from "@/components/ui/page-skeletons";
import { formatMoney } from "@/lib/billing/format-money";
import { getErrorMessage } from "@/lib/api/error";
import {
  platformFetchPlanCatalog,
  platformPatchBillingCatalog,
  type PlatformPlanCatalogResponse,
  type PlatformPlanCatalogTier,
} from "@/lib/api/modules/platform-api";
import { platformHasPermission, PLATFORM_PERMS } from "@/lib/platform/platform-permissions";
import { cn } from "@/lib/utils";
import { usePlatformAuthStore } from "@/stores/platform-auth-store";
import { useNotificationStore } from "@/stores/notification-store";

type TierDraft = {
  plan_tier: string;
  label: string;
  annual_discount_percent: string;
  included_paid_seats: string;
  included_rfi_units: string;
  monthly_base_usd: string;
  rfi_overage_usd: string;
  paid_seat_overage_usd: string;
};

function tierToDraft(tier: PlatformPlanCatalogTier): TierDraft {
  const included = tier.included ?? {};
  const pricing = tier.pricing ?? {};

  return {
    plan_tier: tier.plan_tier,
    label: tier.label,
    annual_discount_percent: String(tier.annual_discount_percent ?? ""),
    included_paid_seats: String(included.paid_seats ?? ""),
    included_rfi_units: String(included.rfi_units ?? ""),
    monthly_base_usd: String(pricing.monthly_base_usd ?? ""),
    rfi_overage_usd: String(pricing.rfi_overage_usd ?? ""),
    paid_seat_overage_usd: String(pricing.paid_seat_overage_usd ?? ""),
  };
}

function buildPatch(
  currency: string,
  defaultAnnualDiscount: string,
  tiers: TierDraft[],
): Parameters<typeof platformPatchBillingCatalog>[0] {
  return {
    currency: currency.trim().toUpperCase(),
    default_annual_discount_percent: Number.parseFloat(defaultAnnualDiscount),
    tiers: tiers.map((tier) => ({
      plan_tier: tier.plan_tier,
      annual_discount_percent:
        tier.annual_discount_percent.trim() === ""
          ? undefined
          : Number.parseFloat(tier.annual_discount_percent),
      included: {
        paid_seats: Number.parseInt(tier.included_paid_seats, 10),
        rfi_units: Number.parseInt(tier.included_rfi_units, 10),
      },
      pricing: {
        monthly_base_usd: Number.parseFloat(tier.monthly_base_usd),
        rfi_overage_usd: Number.parseFloat(tier.rfi_overage_usd),
        paid_seat_overage_usd: Number.parseFloat(tier.paid_seat_overage_usd),
      },
    })),
  };
}

export function PlatformBillingCatalogPanel() {
  const user = usePlatformAuthStore((s) => s.user);
  const notify = useNotificationStore((s) => s.push);
  const queryClient = useQueryClient();
  const canManage = platformHasPermission(user, PLATFORM_PERMS.billingManage);

  const query = useQuery({
    queryKey: ["platform", "billing", "plan-catalog"],
    queryFn: platformFetchPlanCatalog,
  });

  const [currency, setCurrency] = useState("USD");
  const [defaultAnnualDiscount, setDefaultAnnualDiscount] = useState("20");
  const [tierDrafts, setTierDrafts] = useState<TierDraft[]>([]);
  const [openTier, setOpenTier] = useState<string | null>("starter");
  const syncedCatalogRef = useRef<string | null>(null);

  useEffect(() => {
    if (!query.data) {
      return;
    }
    const signature = JSON.stringify({
      currency: query.data.currency,
      tiers: query.data.tiers,
      discount: query.data.default_annual_discount_percent,
    });
    if (syncedCatalogRef.current === signature) {
      return;
    }
    syncedCatalogRef.current = signature;
    setCurrency(query.data.currency ?? "USD");
    setDefaultAnnualDiscount(String(query.data.default_annual_discount_percent ?? 20));
    setTierDrafts((query.data.tiers ?? []).map(tierToDraft));
  }, [query.data]);

  function handleCurrencyChange(nextCurrency: string) {
    const rates = query.data?.exchange_rates ?? { USD: 1 };
    const previousCurrency = currency;

    setTierDrafts((current) =>
      current.map((tier) => ({
        ...tier,
        monthly_base_usd: String(
          convertCurrencyAmount(
            Number.parseFloat(tier.monthly_base_usd) || 0,
            previousCurrency,
            nextCurrency,
            rates,
          ),
        ),
        rfi_overage_usd: String(
          convertCurrencyAmount(
            Number.parseFloat(tier.rfi_overage_usd) || 0,
            previousCurrency,
            nextCurrency,
            rates,
          ),
        ),
        paid_seat_overage_usd: String(
          convertCurrencyAmount(
            Number.parseFloat(tier.paid_seat_overage_usd) || 0,
            previousCurrency,
            nextCurrency,
            rates,
          ),
        ),
      })),
    );
    setCurrency(nextCurrency);
  }

  const annualPreview = useMemo(() => {
    return tierDrafts.map((tier) => {
      const monthly = Number.parseFloat(tier.monthly_base_usd) || 0;
      const discount =
        Number.parseFloat(tier.annual_discount_percent) ||
        Number.parseFloat(defaultAnnualDiscount) ||
        0;
      const annual = monthly * 12 * (1 - discount / 100);
      return { plan_tier: tier.plan_tier, annual: Math.round(annual) };
    });
  }, [tierDrafts, defaultAnnualDiscount]);

  const saveMutation = useMutation({
    mutationFn: () =>
      platformPatchBillingCatalog(buildPatch(currency, defaultAnnualDiscount, tierDrafts)),
    onSuccess: (data: PlatformPlanCatalogResponse) => {
      queryClient.setQueryData(["platform", "billing", "plan-catalog"], data);
      void queryClient.invalidateQueries({ queryKey: ["platform", "billing", "insights"] });
      notify({
        level: "success",
        title: "Billing catalog saved",
        message: "Currency, list prices, annual discount, and included RFI units are updated platform-wide.",
      });
    },
    onError: (error) =>
      notify({
        level: "error",
        title: "Could not save catalog",
        message: getErrorMessage(error),
      }),
  });

  if (query.isLoading) {
    return (
      <div className="space-y-4">
        <SectionCardSkeleton fields={2} />
        <SectionCardSkeleton fields={6} />
        <SectionCardSkeleton fields={6} />
      </div>
    );
  }

  if (query.isError || !query.data) {
    return <p className="text-sm text-destructive">Could not load plan catalog.</p>;
  }

  const currencyOptions = query.data.supported_currencies ?? [];

  return (
    <Card className="rounded-xl shadow-sm">
      <CardHeader className="border-b border-border pb-4">
        <CardTitle className="text-base font-medium">Plan catalog & pricing</CardTitle>
        <p className="text-sm text-muted-foreground">
          Platform-wide list prices, annual prepay discount, and included RFI / paid seat limits.
        </p>
      </CardHeader>
      <CardContent className="space-y-4 pt-4">
        <PlatformBillingFormSection
          title="Global defaults"
          description="Applied to all tiers unless a tier-specific annual discount is set."
        >
          <div className="grid gap-4 sm:grid-cols-2">
            <div className="space-y-1.5">
              <Label htmlFor="catalog-currency" className="text-sm font-medium">
                Currency
              </Label>
              <Select
                id="catalog-currency"
                value={currency}
                disabled={!canManage}
                onChange={(event) => handleCurrencyChange(event.target.value)}
              >
                {currencyOptions.length > 0 ? (
                  currencyOptions.map((option) => (
                    <option key={option.code} value={option.code}>
                      {option.code} — {option.label}
                    </option>
                  ))
                ) : (
                  <option value={currency}>{currency}</option>
                )}
              </Select>
              <p className="text-xs text-muted-foreground">
                List prices are stored in USD and converted using platform FX rates (e.g. $99 →{" "}
                {formatMoney(
                  convertCurrencyAmount(99, "USD", currency, query.data.exchange_rates ?? { USD: 1 }),
                  currency,
                )}
                ).
              </p>
            </div>
            <FormInput
              label="Default annual discount (%)"
              id="catalog-annual-discount"
              type="number"
              min={0}
              max={80}
              step={0.5}
              value={defaultAnnualDiscount}
              disabled={!canManage}
              onChange={(event) => setDefaultAnnualDiscount(event.target.value)}
            />
          </div>
        </PlatformBillingFormSection>

        <div className="space-y-2">
          {tierDrafts.map((tier, index) => {
            const preview = annualPreview.find((row) => row.plan_tier === tier.plan_tier);
            const expanded = openTier === tier.plan_tier;

            return (
              <div key={tier.plan_tier} className="overflow-hidden rounded-xl border border-border bg-card">
                <button
                  type="button"
                  className="flex w-full items-center justify-between gap-3 px-4 py-3 text-left hover:bg-muted/30"
                  onClick={() => setOpenTier(expanded ? null : tier.plan_tier)}
                >
                  <div>
                    <p className="text-sm font-medium text-foreground">{tier.label}</p>
                    <p className="text-xs text-muted-foreground">
                      {formatMoney(Number.parseFloat(tier.monthly_base_usd) || 0, currency)}/mo ·{" "}
                      {tier.included_rfi_units || "0"} RFI · {tier.included_paid_seats || "0"} seats
                    </p>
                  </div>
                  <ChevronDown
                    className={cn(
                      "size-4 shrink-0 text-muted-foreground transition-transform",
                      expanded && "rotate-180",
                    )}
                  />
                </button>
                {expanded ? (
                  <div className="space-y-4 border-t border-border px-4 py-4">
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                      <FormInput
                        label={`Monthly base (${currency})`}
                        id={`${tier.plan_tier}-monthly`}
                        type="number"
                        min={0}
                        step={1}
                        value={tier.monthly_base_usd}
                        disabled={!canManage}
                        onChange={(event) => {
                          const next = [...tierDrafts];
                          next[index] = { ...tier, monthly_base_usd: event.target.value };
                          setTierDrafts(next);
                        }}
                      />
                      <FormInput
                        label="Tier annual discount (%)"
                        id={`${tier.plan_tier}-discount`}
                        type="number"
                        min={0}
                        max={80}
                        step={0.5}
                        value={tier.annual_discount_percent}
                        disabled={!canManage}
                        onChange={(event) => {
                          const next = [...tierDrafts];
                          next[index] = { ...tier, annual_discount_percent: event.target.value };
                          setTierDrafts(next);
                        }}
                      />
                      <div className="space-y-1.5">
                        <Label className="text-sm font-medium">
                          Indicative annual ({currency})
                        </Label>
                        <p className="flex h-10 items-center rounded-md border border-border bg-muted/20 px-3 text-sm tabular-nums">
                          {formatMoney(preview?.annual ?? 0, currency)}
                        </p>
                      </div>
                      <FormInput
                        label="Included paid seats"
                        id={`${tier.plan_tier}-seats`}
                        type="number"
                        min={1}
                        value={tier.included_paid_seats}
                        disabled={!canManage}
                        onChange={(event) => {
                          const next = [...tierDrafts];
                          next[index] = { ...tier, included_paid_seats: event.target.value };
                          setTierDrafts(next);
                        }}
                      />
                      <FormInput
                        label="Included RFI units"
                        id={`${tier.plan_tier}-rfi`}
                        type="number"
                        min={0}
                        value={tier.included_rfi_units}
                        disabled={!canManage}
                        onChange={(event) => {
                          const next = [...tierDrafts];
                          next[index] = { ...tier, included_rfi_units: event.target.value };
                          setTierDrafts(next);
                        }}
                      />
                      <FormInput
                        label={`+1 RFI / month (${currency})`}
                        id={`${tier.plan_tier}-rfi-overage`}
                        type="number"
                        min={0}
                        step={1}
                        value={tier.rfi_overage_usd}
                        disabled={!canManage}
                        onChange={(event) => {
                          const next = [...tierDrafts];
                          next[index] = { ...tier, rfi_overage_usd: event.target.value };
                          setTierDrafts(next);
                        }}
                      />
                      <FormInput
                        label={`+1 paid seat / month (${currency})`}
                        id={`${tier.plan_tier}-seat-overage`}
                        type="number"
                        min={0}
                        step={1}
                        value={tier.paid_seat_overage_usd}
                        disabled={!canManage}
                        onChange={(event) => {
                          const next = [...tierDrafts];
                          next[index] = { ...tier, paid_seat_overage_usd: event.target.value };
                          setTierDrafts(next);
                        }}
                      />
                    </div>
                  </div>
                ) : null}
              </div>
            );
          })}
        </div>

        {canManage ? (
          <Button
            type="button"
            disabled={saveMutation.isPending}
            onClick={() => saveMutation.mutate()}
          >
            {saveMutation.isPending ? "Saving…" : "Save catalog"}
          </Button>
        ) : (
          <p className="text-xs text-muted-foreground">
            Read-only billing access. Ask a billing manager to edit catalog prices.
          </p>
        )}
      </CardContent>
    </Card>
  );
}
