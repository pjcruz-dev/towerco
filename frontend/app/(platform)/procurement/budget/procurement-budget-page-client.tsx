"use client";

import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { PiggyBank } from "lucide-react";

import { FinanceModuleEyebrow } from "@/components/finance-one/finance-module-eyebrow";
import { ProcurementOnePageHeader } from "@/components/procurement-one/procurement-one-page-header";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { SectionCardSkeleton } from "@/components/ui/page-skeletons";
import { useFinanceModulePaths } from "@/hooks/use-finance-module-paths";
import {
  createProcurementBudgetLine,
  createProcurementCostCenter,
  fetchProcurementBudgetLines,
  fetchProcurementBudgetUtilization,
  fetchProcurementCostCenters,
} from "@/lib/api/modules/procurement-one-api";
import { getErrorMessage } from "@/lib/api/error";
import { permissions } from "@/lib/rbac/permissions";
import { useNotificationStore } from "@/stores/notification-store";

export function ProcurementBudgetPageClient() {
  const financePaths = useFinanceModulePaths();
  const queryClient = useQueryClient();
  const pushNotification = useNotificationStore((s) => s.push);
  const [rolloutId, setRolloutId] = useState("");
  const [lineDescription, setLineDescription] = useState("");
  const [lineAmount, setLineAmount] = useState("");
  const [expenseType, setExpenseType] = useState<"capex" | "opex">("capex");
  const [centerCode, setCenterCode] = useState("");
  const [centerName, setCenterName] = useState("");

  const centersQuery = useQuery({
    queryKey: ["procurement-one", "cost-centers"],
    queryFn: fetchProcurementCostCenters,
  });

  const linesQuery = useQuery({
    queryKey: ["procurement-one", "budget-lines", rolloutId],
    queryFn: () => fetchProcurementBudgetLines({ rollout_id: rolloutId || undefined }),
    enabled: Boolean(rolloutId),
  });

  const utilizationQuery = useQuery({
    queryKey: ["procurement-one", "budget-utilization", rolloutId],
    queryFn: () => fetchProcurementBudgetUtilization({ rollout_id: rolloutId }),
    enabled: Boolean(rolloutId),
  });

  const createLineMutation = useMutation({
    mutationFn: () =>
      createProcurementBudgetLine({
        rollout_id: rolloutId,
        description: lineDescription,
        budget_amount: Number(lineAmount),
        expense_type: expenseType,
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["procurement-one", "budget-lines"] });
      queryClient.invalidateQueries({ queryKey: ["procurement-one", "budget-utilization"] });
      queryClient.invalidateQueries({ queryKey: ["procurement-one", "dashboard"] });
      setLineDescription("");
      setLineAmount("");
      pushNotification({ title: "Budget line added", variant: "success" });
    },
    onError: (error) => pushNotification({ title: getErrorMessage(error), variant: "destructive" }),
  });

  const createCenterMutation = useMutation({
    mutationFn: () => createProcurementCostCenter({ code: centerCode, name: centerName }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["procurement-one", "cost-centers"] });
      setCenterCode("");
      setCenterName("");
      pushNotification({ title: "Cost center created", variant: "success" });
    },
    onError: (error) => pushNotification({ title: getErrorMessage(error), variant: "destructive" }),
  });

  const utilization = utilizationQuery.data;

  return (
    <PermissionGate requiredPermissions={[permissions.procurementOneView]}>
      <div className="space-y-6">
        <ProcurementOnePageHeader
          eyebrow={
            <FinanceModuleEyebrow homeHref={financePaths.home} label={financePaths.moduleLabel} />
          }
          title="Budget & encumbrance"
          description="BOQ budget lines, cost centers, and live PR + PO commitment against rollout budget."
        />

        <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
          <Label htmlFor="rollout_id">Rollout ID</Label>
          <input
            id="rollout_id"
            value={rolloutId}
            onChange={(e) => setRolloutId(e.target.value)}
            placeholder="Paste rollout UUID to load budget"
            className="mt-2 h-9 w-full max-w-xl rounded-lg border border-input bg-background px-3 text-sm"
          />
        </section>

        {rolloutId && utilizationQuery.isLoading ? <SectionCardSkeleton /> : null}

        {utilization ? (
          <section className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            {[
              { label: "Budget", value: utilization.budget_total ?? "—" },
              { label: "Committed (PR)", value: utilization.committed_pr },
              { label: "Committed (PO)", value: utilization.committed_po },
              { label: "Available", value: utilization.available ?? "—" },
            ].map((card) => (
              <div key={card.label} className="rounded-xl border border-border bg-card p-4 shadow-sm">
                <div className="text-xs font-medium text-muted-foreground">{card.label}</div>
                <div className="mt-1 text-xl font-medium tabular-nums text-foreground">{card.value}</div>
              </div>
            ))}
          </section>
        ) : null}

        <div className="grid gap-6 lg:grid-cols-2">
          <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
            <div className="flex items-center gap-2 text-sm font-medium">
              <PiggyBank className="h-4 w-4 text-primary" aria-hidden />
              Budget lines
            </div>
            <PermissionGate requiredPermissions={[permissions.procurementOneSettingsManage]}>
              <div className="mt-4 grid gap-3">
                <input
                  value={lineDescription}
                  onChange={(e) => setLineDescription(e.target.value)}
                  placeholder="Line description"
                  className="h-9 rounded-lg border border-input bg-background px-3 text-sm"
                />
                <div className="flex gap-2">
                  <input
                    type="number"
                    min={0}
                    value={lineAmount}
                    onChange={(e) => setLineAmount(e.target.value)}
                    placeholder="Amount"
                    className="h-9 flex-1 rounded-lg border border-input bg-background px-3 text-sm"
                  />
                  <select
                    value={expenseType}
                    onChange={(e) => setExpenseType(e.target.value as "capex" | "opex")}
                    className="h-9 rounded-lg border border-input bg-background px-3 text-sm"
                  >
                    <option value="capex">CAPEX</option>
                    <option value="opex">OPEX</option>
                  </select>
                </div>
                <Button
                  size="sm"
                  disabled={!rolloutId || !lineDescription || !lineAmount || createLineMutation.isPending}
                  onClick={() => createLineMutation.mutate()}
                >
                  Add budget line
                </Button>
              </div>
            </PermissionGate>
            <ul className="mt-4 space-y-2 text-sm">
              {(linesQuery.data ?? []).map((line) => (
                <li key={line.id} className="rounded-lg border border-border px-3 py-2">
                  <div className="font-medium">{line.description}</div>
                  <div className="text-muted-foreground">
                    {line.expense_type_label} · {line.budget_amount.toLocaleString()}
                  </div>
                </li>
              ))}
            </ul>
          </section>

          <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
            <div className="text-sm font-medium">Cost centers</div>
            <PermissionGate requiredPermissions={[permissions.procurementOneSettingsManage]}>
              <div className="mt-4 grid gap-2 sm:grid-cols-2">
                <input
                  value={centerCode}
                  onChange={(e) => setCenterCode(e.target.value)}
                  placeholder="Code"
                  className="h-9 rounded-lg border border-input bg-background px-3 text-sm"
                />
                <input
                  value={centerName}
                  onChange={(e) => setCenterName(e.target.value)}
                  placeholder="Name"
                  className="h-9 rounded-lg border border-input bg-background px-3 text-sm"
                />
                <Button
                  size="sm"
                  className="sm:col-span-2"
                  disabled={!centerCode || !centerName || createCenterMutation.isPending}
                  onClick={() => createCenterMutation.mutate()}
                >
                  Add cost center
                </Button>
              </div>
            </PermissionGate>
            <ul className="mt-4 space-y-2 text-sm">
              {(centersQuery.data ?? []).map((center) => (
                <li key={center.id} className="rounded-lg border border-border px-3 py-2">
                  <span className="font-medium">{center.code}</span> — {center.name}
                </li>
              ))}
            </ul>
          </section>
        </div>
      </div>
    </PermissionGate>
  );
}
