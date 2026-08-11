"use client";

import Link from "next/link";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { FolderOpen } from "lucide-react";

import { FinanceModuleEyebrow } from "@/components/finance-one/finance-module-eyebrow";
import { ProcurementLifecycleActionButton } from "@/components/procurement-one/procurement-lifecycle-action-button";
import { ProcurementPaymentStatusBadge } from "@/components/procurement-one/procurement-payment-status-badge";
import { ProcurementOnePageHeader } from "@/components/procurement-one/procurement-one-page-header";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Button } from "@/components/ui/button";
import { SectionCardSkeleton } from "@/components/ui/page-skeletons";
import { useFinanceModulePaths } from "@/hooks/use-finance-module-paths";
import {
  activateProcurementContract,
  fetchProcurementContract,
  terminateProcurementContract,
} from "@/lib/api/modules/procurement-one-api";
import { getErrorMessage } from "@/lib/api/error";
import { permissions } from "@/lib/rbac/permissions";
import { useNotificationStore } from "@/stores/notification-store";

type Props = { contractId: string };

function formatMoney(value: number | null, currency = "PHP"): string {
  if (value === null) return "—";
  return `${currency} ${value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

export function ProcurementContractDetailPageClient({ contractId }: Props) {
  const financePaths = useFinanceModulePaths();
  const queryClient = useQueryClient();
  const pushNotification = useNotificationStore((s) => s.push);

  const query = useQuery({
    queryKey: ["procurement-one", "contract", contractId],
    queryFn: () => fetchProcurementContract(contractId),
    enabled: Boolean(contractId),
  });

  const activateMutation = useMutation({
    mutationFn: () => activateProcurementContract(contractId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["procurement-one", "contract", contractId] });
      pushNotification({ title: "Contract activated", variant: "success" });
    },
    onError: (error) => pushNotification({ title: getErrorMessage(error), variant: "destructive" }),
  });

  const terminateMutation = useMutation({
    mutationFn: (reason: string) => terminateProcurementContract(contractId, reason),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["procurement-one", "contract", contractId] });
      pushNotification({ title: "Contract terminated", variant: "success" });
    },
    onError: (error) => pushNotification({ title: getErrorMessage(error), variant: "destructive" }),
  });

  if (query.isLoading) return <SectionCardSkeleton />;
  if (!query.data) return <p className="text-sm text-destructive">Could not load contract.</p>;

  const contract = query.data;

  return (
    <PermissionGate requiredPermissions={[permissions.procurementOneView]}>
      <div className="space-y-6">
        <ProcurementOnePageHeader
          eyebrow={
            <>
              <FinanceModuleEyebrow homeHref={financePaths.home} label={financePaths.moduleLabel} />
              <span className="text-muted-foreground"> / </span>
              <Link href={financePaths.contracts} className="hover:text-primary">
                Vendor contracts
              </Link>
            </>
          }
          title={contract.document_no ?? contract.title}
          description={
            <span className="inline-flex flex-wrap items-center gap-2">
              <ProcurementPaymentStatusBadge status={contract.status} label={contract.status_label} />
              <span className="text-muted-foreground">{contract.vendor?.company_name ?? contract.vendor?.vendor_code}</span>
            </span>
          }
          actions={
            <div className="flex flex-wrap gap-2">
              {contract.status === "draft" ? (
                <PermissionGate requiredPermissions={[permissions.procurementOneDocumentsManage]}>
                  <Button size="sm" onClick={() => activateMutation.mutate()} disabled={activateMutation.isPending}>
                    Activate contract
                  </Button>
                </PermissionGate>
              ) : null}
              {contract.status === "active" ? (
                <PermissionGate requiredPermissions={[permissions.procurementOneDocumentsManage]}>
                  <ProcurementLifecycleActionButton
                    action="void"
                    label="Terminate"
                    pending={terminateMutation.isPending}
                    onConfirm={(reason) => terminateMutation.mutate(reason)}
                  />
                </PermissionGate>
              ) : null}
            </div>
          }
        />

        <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
          <dl className="grid gap-3 text-sm md:grid-cols-2">
            <div>
              <dt className="text-xs font-medium text-muted-foreground">Spend ceiling</dt>
              <dd className="mt-1 tabular-nums">{formatMoney(contract.spend_ceiling, contract.currency_code)}</dd>
            </div>
            <div>
              <dt className="text-xs font-medium text-muted-foreground">Committed (PO total)</dt>
              <dd className="mt-1 tabular-nums">{formatMoney(contract.live_committed_po_amount, contract.currency_code)}</dd>
            </div>
            <div>
              <dt className="text-xs font-medium text-muted-foreground">Available spend</dt>
              <dd className="mt-1 tabular-nums">{formatMoney(contract.available_spend, contract.currency_code)}</dd>
            </div>
            <div>
              <dt className="text-xs font-medium text-muted-foreground">Term</dt>
              <dd className="mt-1">
                {contract.effective_from ?? "—"} → {contract.end_date ?? "—"}
              </dd>
            </div>
            <div>
              <dt className="text-xs font-medium text-muted-foreground">Site</dt>
              <dd className="mt-1">{contract.site?.name ?? contract.site?.site_code ?? "All sites / corporate"}</dd>
            </div>
            <div>
              <dt className="text-xs font-medium text-muted-foreground">Linked POs</dt>
              <dd className="mt-1">{contract.purchase_order_count}</dd>
            </div>
          </dl>
          {contract.description ? <p className="mt-4 text-sm text-muted-foreground">{contract.description}</p> : null}
        </section>

        <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
          <div className="flex items-center gap-2">
            <FolderOpen className="h-4 w-4 text-primary" aria-hidden />
            <h2 className="text-base font-medium">Document repository</h2>
          </div>
          <p className="mt-2 text-sm text-muted-foreground">
            Store signed agreements under site Documents → Legal → Vendor contracts (`{contract.binder_node_key}`), then link the primary document ID on this contract. Expiry alerts reuse the Documents expiry notification pipeline via `expires_at`.
          </p>
          {contract.primary_document ? (
            <div className="mt-3 rounded-lg border border-border px-3 py-2 text-sm">
              <div className="font-medium">{contract.primary_document.title}</div>
              <div className="text-muted-foreground">
                Expires {contract.primary_document.expires_at ? new Date(contract.primary_document.expires_at).toLocaleDateString() : contract.end_date ?? "—"}
              </div>
            </div>
          ) : (
            <p className="mt-3 text-sm text-muted-foreground">No primary document linked yet.</p>
          )}
        </section>
      </div>
    </PermissionGate>
  );
}
