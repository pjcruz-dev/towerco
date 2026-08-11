"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useEffect, useState } from "react";

import { FormInput } from "@/components/forms/form-input";
import { AcronymLabel } from "@/components/help/acronym-label";
import { Button } from "@/components/ui/button";
import { getErrorMessage } from "@/lib/api/error";
import { fetchRolloutProfitability, updateRolloutProfitability } from "@/lib/api/modules/rollout-api";
import { useNotificationStore } from "@/stores/notification-store";

const bucketAcronyms: Record<string, string> = {
  saq: "SAQ",
  cme: "CME",
  permitting: "BP",
};

const bucketLabels: Record<string, string> = {
  saq: "SAQ",
  engineering: "Engineering",
  permitting: "Permitting",
  cme: "CME",
  tower_material: "Tower material",
  dc_plant: "DC plant",
  power: "Power",
};

const statusOptions = [
  { value: "on_track", label: "On track" },
  { value: "watch", label: "Watch" },
  { value: "underperforming", label: "Underperforming" },
  { value: "at_loss", label: "At loss" },
] as const;

type Props = {
  rolloutId: string;
  canView: boolean;
  canEdit: boolean;
};

function formatPhp(value: number | undefined): string {
  if (value === undefined) return "—";
  return new Intl.NumberFormat("en-PH", { style: "currency", currency: "PHP", maximumFractionDigits: 0 }).format(value);
}

export function RolloutProfitabilityTab({ rolloutId, canView, canEdit }: Props) {
  const queryClient = useQueryClient();
  const push = useNotificationStore((state) => state.push);

  const query = useQuery({
    queryKey: ["project-one", "rollouts", "profitability", rolloutId],
    queryFn: () => fetchRolloutProfitability(rolloutId),
    enabled: canView && Boolean(rolloutId),
  });

  const [actual, setActual] = useState<Record<string, string>>({});
  const [baseline, setBaseline] = useState<Record<string, string>>({});
  const [status, setStatus] = useState<(typeof statusOptions)[number]["value"]>("on_track");
  const [voCost, setVoCost] = useState("");
  const [ldAccrued, setLdAccrued] = useState("");
  const [leaseFee, setLeaseFee] = useState("");

  useEffect(() => {
    const data = query.data;
    if (!data) return;
    setActual(mapNumbersToStrings(data.actual));
    setBaseline(mapNumbersToStrings(data.baseline));
    if (data.profitability_status) {
      setStatus(data.profitability_status as (typeof statusOptions)[number]["value"]);
    }
    setVoCost(data.vo_cost_cumulative != null ? String(data.vo_cost_cumulative) : "");
    setLdAccrued(data.ld_accrued_php != null ? String(data.ld_accrued_php) : "");
    setLeaseFee(data.anchor_tenant_lease_fee_php != null ? String(data.anchor_tenant_lease_fee_php) : "");
  }, [query.data]);

  const mutation = useMutation({
    mutationFn: () =>
      updateRolloutProfitability(rolloutId, {
        actual: parseBucketRecord(actual),
        baseline: query.data?.access === "full" ? parseBucketRecord(baseline) : undefined,
        profitability_status: status,
        vo_cost_cumulative: voCost ? Number(voCost) : undefined,
        ld_accrued_php: ldAccrued ? Number(ldAccrued) : undefined,
        anchor_tenant_lease_fee_php: leaseFee ? Number(leaseFee) : undefined,
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["project-one", "rollouts", "profitability", rolloutId] });
      push({ level: "success", title: "Profitability updated" });
    },
    onError: (error) =>
      push({ level: "error", title: "Could not update profitability", message: getErrorMessage(error) }),
  });

  if (!canView) {
    return <p className="text-sm text-muted-foreground">You do not have permission to view profitability.</p>;
  }

  const data = query.data;
  const buckets = Object.keys(data?.actual ?? data?.baseline ?? bucketLabels);

  return (
    <div className="space-y-4">
      {query.isLoading ? <p className="text-sm text-muted-foreground">Loading profitability…</p> : null}

      {data ? (
        <>
          <div className="rounded-xl border border-border bg-card p-4 shadow-sm">
            <p className="text-sm text-muted-foreground">
              Access: <span className="text-foreground">{data.access ?? "—"}</span> · Status:{" "}
              <span className="capitalize text-foreground">{data.profitability_status ?? "—"}</span>
            </p>
            {data.baseline_total !== undefined ? (
              <div className="mt-4 grid gap-3 sm:grid-cols-3">
                <SummaryCard label="Baseline total" value={formatPhp(data.baseline_total)} />
                <SummaryCard label="Actual total" value={formatPhp(data.actual_total)} />
                <SummaryCard label="Variance" value={formatPhp(data.variance_php)} />
              </div>
            ) : (
              <p className="mt-3 text-sm text-muted-foreground">Discipline-level view — portfolio totals hidden.</p>
            )}
          </div>

          {canEdit ? (
            <form
              className="space-y-4 rounded-xl border border-border bg-card p-4 shadow-sm"
              onSubmit={(e) => {
                e.preventDefault();
                mutation.mutate();
              }}
            >
              <div>
                <h2 className="text-base font-medium text-foreground">Update costs</h2>
                <p className="mt-1 text-xs text-muted-foreground">
                  Costs are entered manually. They are not auto-filled from SAQ, CME, or procurement
                  yet — update baseline/actual when invoices or estimates are confirmed.
                </p>
              </div>
              <div className="grid gap-3 sm:grid-cols-2">
                {buckets.map((key) => (
                  <div key={key} className="space-y-2 rounded-lg border border-border p-3">
                    <p className="text-xs font-medium text-muted-foreground">
                      {bucketAcronyms[key] ? (
                        <AcronymLabel term={bucketAcronyms[key]}>{bucketLabels[key] ?? key}</AcronymLabel>
                      ) : (
                        bucketLabels[key] ?? key
                      )}
                    </p>
                    {data.access === "full" ? (
                      <FormInput
                        label="Baseline (PHP)"
                        value={baseline[key] ?? ""}
                        onChange={(e) => setBaseline((prev) => ({ ...prev, [key]: e.target.value }))}
                      />
                    ) : null}
                    <FormInput
                      label="Actual (PHP)"
                      value={actual[key] ?? ""}
                      onChange={(e) => setActual((prev) => ({ ...prev, [key]: e.target.value }))}
                    />
                  </div>
                ))}
              </div>

              {data.access === "full" ? (
                <div className="grid gap-3 sm:grid-cols-3">
                  <FormInput label="VO cost cumulative" value={voCost} onChange={(e) => setVoCost(e.target.value)} />
                  <FormInput label="LD accrued (PHP)" value={ldAccrued} onChange={(e) => setLdAccrued(e.target.value)} />
                  <FormInput
                    label="Anchor tenant lease fee"
                    value={leaseFee}
                    onChange={(e) => setLeaseFee(e.target.value)}
                  />
                </div>
              ) : null}

              <label className="block max-w-xs space-y-1.5 text-sm">
                <span className="font-medium">Profitability status</span>
                <select
                  className="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                  value={status}
                  onChange={(e) => setStatus(e.target.value as typeof status)}
                >
                  {statusOptions.map((opt) => (
                    <option key={opt.value} value={opt.value}>
                      {opt.label}
                    </option>
                  ))}
                </select>
              </label>

              <Button type="submit" size="sm" disabled={mutation.isPending}>
                {mutation.isPending ? "Saving…" : "Save profitability"}
              </Button>
            </form>
          ) : null}
        </>
      ) : null}
    </div>
  );
}

function SummaryCard({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-lg border border-border p-3">
      <p className="text-xs font-medium text-muted-foreground">{label}</p>
      <p className="mt-1 text-base font-medium text-foreground">{value}</p>
    </div>
  );
}

function mapNumbersToStrings(record: Record<string, number> | undefined): Record<string, string> {
  if (!record) return {};
  return Object.fromEntries(Object.entries(record).map(([k, v]) => [k, String(v ?? "")]));
}

function parseBucketRecord(record: Record<string, string>): Record<string, number> {
  const out: Record<string, number> = {};
  for (const [key, value] of Object.entries(record)) {
    if (value.trim() === "") continue;
    out[key] = Number(value);
  }
  return out;
}
