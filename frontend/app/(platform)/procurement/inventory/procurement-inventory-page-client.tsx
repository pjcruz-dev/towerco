"use client";

import Link from "next/link";
import { useMemo, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ArrowRightLeft, Boxes, History, MapPin } from "lucide-react";

import { ProcurementOnePageHeader } from "@/components/procurement-one/procurement-one-page-header";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Label } from "@/components/ui/label";
import { SectionCardSkeleton } from "@/components/ui/page-skeletons";
import {
  deployProcurementInventoryStock,
  fetchProcurementInventoryLocations,
  fetchProcurementInventoryMovements,
  fetchProcurementInventoryStockBalances,
  transferProcurementInventoryStock,
} from "@/lib/api/modules/procurement-one-api";
import { getErrorMessage } from "@/lib/api/error";
import { permissions } from "@/lib/rbac/permissions";
import { useNotificationStore } from "@/stores/notification-store";

export function ProcurementInventoryPageClient() {
  const queryClient = useQueryClient();
  const pushNotification = useNotificationStore((s) => s.push);
  const [fromLocationId, setFromLocationId] = useState("");
  const [toLocationId, setToLocationId] = useState("");
  const [poLineId, setPoLineId] = useState("");
  const [quantity, setQuantity] = useState("1");
  const [deployMode, setDeployMode] = useState(false);
  const [createAsset, setCreateAsset] = useState(true);

  const locationsQuery = useQuery({
    queryKey: ["procurement-one", "inventory", "locations"],
    queryFn: () => fetchProcurementInventoryLocations(),
  });

  const balancesQuery = useQuery({
    queryKey: ["procurement-one", "inventory", "balances"],
    queryFn: () => fetchProcurementInventoryStockBalances({ per_page: 50 }),
  });

  const movementsQuery = useQuery({
    queryKey: ["procurement-one", "inventory", "movements"],
    queryFn: () => fetchProcurementInventoryMovements({ per_page: 20 }),
  });

  const poLineOptions = useMemo(() => {
    return (balancesQuery.data?.data ?? []).map((row) => ({
      id: row.po_line_id ?? "",
      label: `${row.description} (${row.location?.code ?? "?"}) — ${row.quantity_on_hand} on hand`,
      locationId: row.location_id,
    })).filter((row) => row.id);
  }, [balancesQuery.data]);

  const transferMutation = useMutation({
    mutationFn: () =>
      deployMode
        ? deployProcurementInventoryStock({
            from_location_id: fromLocationId,
            to_location_id: toLocationId,
            po_line_id: poLineId,
            quantity: Number(quantity),
            create_asset: createAsset,
          })
        : transferProcurementInventoryStock({
            from_location_id: fromLocationId,
            to_location_id: toLocationId,
            po_line_id: poLineId,
            quantity: Number(quantity),
          }),
    onSuccess: (result) => {
      queryClient.invalidateQueries({ queryKey: ["procurement-one", "inventory"] });
      if (deployMode && "asset" in result && result.asset) {
        pushNotification({
          title: `Deployed — asset ${String(result.asset.asset_code ?? "")} created`,
          variant: "success",
        });
      } else {
        pushNotification({ title: deployMode ? "Stock deployed" : "Stock transferred", variant: "success" });
      }
    },
    onError: (error) => pushNotification({ title: getErrorMessage(error), variant: "destructive" }),
  });

  const isLoading = locationsQuery.isLoading || balancesQuery.isLoading;

  return (
    <PermissionGate requiredPermissions={[permissions.procurementOneInventoryView]}>
      <div className="space-y-6">
        <ProcurementOnePageHeader
          eyebrow={
            <Link href="/procurement" className="hover:text-primary">
              Procurement-One
            </Link>
          }
          title="Inventory"
          description="Warehouse locations, on-hand balances, transfers, and deployment into Asset-One."
        />

        {isLoading ? <SectionCardSkeleton /> : null}

        {!isLoading ? (
          <>
            <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
              <div className="flex items-center gap-2 text-sm font-medium text-foreground">
                <MapPin className="h-4 w-4 text-primary" aria-hidden />
                Locations
              </div>
              <div className="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                {(locationsQuery.data ?? []).map((location) => (
                  <div key={location.id} className="rounded-lg border border-border p-3 text-sm">
                    <div className="font-medium text-foreground">{location.name}</div>
                    <div className="mt-1 text-muted-foreground">
                      {location.code} · {location.location_kind_label}
                      {location.is_default_receipt ? " · default receipt" : ""}
                    </div>
                  </div>
                ))}
                {(locationsQuery.data ?? []).length === 0 ? (
                  <p className="text-sm text-muted-foreground">No inventory locations yet. Configure in settings or seed via API.</p>
                ) : null}
              </div>
            </section>

            <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
              <div className="flex items-center gap-2 text-sm font-medium text-foreground">
                <Boxes className="h-4 w-4 text-primary" aria-hidden />
                Stock on hand
              </div>
              <div className="mt-3 overflow-x-auto">
                <table className="w-full min-w-[640px] text-left text-sm">
                  <thead>
                    <tr className="border-b border-border text-xs text-muted-foreground">
                      <th className="py-2 pr-3 font-medium">Location</th>
                      <th className="py-2 pr-3 font-medium">Item</th>
                      <th className="py-2 pr-3 font-medium">Qty</th>
                      <th className="py-2 font-medium">UOM</th>
                    </tr>
                  </thead>
                  <tbody>
                    {(balancesQuery.data?.data ?? []).map((row) => (
                      <tr key={row.id} className="border-b border-border/70">
                        <td className="py-2 pr-3">{row.location?.name ?? "—"}</td>
                        <td className="py-2 pr-3">{row.description}</td>
                        <td className="py-2 pr-3 tabular-nums">{row.quantity_on_hand}</td>
                        <td className="py-2">{row.uom ?? "—"}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </section>

            <PermissionGate requiredPermissions={[permissions.procurementOneInventoryManage]}>
              <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
                <div className="flex items-center gap-2 text-sm font-medium text-foreground">
                  <ArrowRightLeft className="h-4 w-4 text-primary" aria-hidden />
                  Transfer or deploy
                </div>
                <div className="mt-4 grid gap-4 md:grid-cols-2">
                  <div className="space-y-2">
                    <Label>Action</Label>
                    <select
                      value={deployMode ? "deploy" : "transfer"}
                      onChange={(event) => setDeployMode(event.target.value === "deploy")}
                      className="h-9 w-full rounded-lg border border-input bg-background px-3 text-sm"
                    >
                      <option value="transfer">Transfer between warehouses</option>
                      <option value="deploy">Deploy to site (create asset)</option>
                    </select>
                  </div>
                  <div className="space-y-2">
                    <Label>Stock line</Label>
                    <select
                      value={poLineId}
                      onChange={(event) => {
                        setPoLineId(event.target.value);
                        const match = poLineOptions.find((row) => row.id === event.target.value);
                        if (match?.locationId) setFromLocationId(match.locationId);
                      }}
                      className="h-9 w-full rounded-lg border border-input bg-background px-3 text-sm"
                    >
                      <option value="">Select on-hand line</option>
                      {poLineOptions.map((row) => (
                        <option key={row.id} value={row.id}>
                          {row.label}
                        </option>
                      ))}
                    </select>
                  </div>
                  <div className="space-y-2">
                    <Label>From location</Label>
                    <select
                      value={fromLocationId}
                      onChange={(event) => setFromLocationId(event.target.value)}
                      className="h-9 w-full rounded-lg border border-input bg-background px-3 text-sm"
                    >
                      <option value="">Select source</option>
                      {(locationsQuery.data ?? []).map((location) => (
                        <option key={location.id} value={location.id}>
                          {location.name}
                        </option>
                      ))}
                    </select>
                  </div>
                  <div className="space-y-2">
                    <Label>To location</Label>
                    <select
                      value={toLocationId}
                      onChange={(event) => setToLocationId(event.target.value)}
                      className="h-9 w-full rounded-lg border border-input bg-background px-3 text-sm"
                    >
                      <option value="">Select destination</option>
                      {(locationsQuery.data ?? []).map((location) => (
                        <option key={location.id} value={location.id}>
                          {location.name}
                        </option>
                      ))}
                    </select>
                  </div>
                  <div className="space-y-2">
                    <Label>Quantity</Label>
                    <input
                      type="number"
                      min={0.0001}
                      step="any"
                      value={quantity}
                      onChange={(event) => setQuantity(event.target.value)}
                      className="h-9 w-full rounded-lg border border-input bg-background px-3 text-sm"
                    />
                  </div>
                  {deployMode ? (
                    <label className="flex items-center gap-2 text-sm">
                      <Checkbox
                        checked={createAsset}
                        onCheckedChange={(v) => setCreateAsset(v === true)}
                        className="size-4"
                      />
                      Create Asset-One record on deploy
                    </label>
                  ) : null}
                </div>
                <Button
                  className="mt-4"
                  size="sm"
                  disabled={transferMutation.isPending || !fromLocationId || !toLocationId || !poLineId}
                  onClick={() => transferMutation.mutate()}
                >
                  {deployMode ? "Deploy stock" : "Transfer stock"}
                </Button>
              </section>
            </PermissionGate>

            <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
              <div className="flex items-center gap-2 text-sm font-medium text-foreground">
                <History className="h-4 w-4 text-primary" aria-hidden />
                Movement history
              </div>
              <div className="mt-3 overflow-x-auto">
                <table className="w-full min-w-[720px] text-left text-sm">
                  <thead>
                    <tr className="border-b border-border text-xs text-muted-foreground">
                      <th className="py-2 pr-3 font-medium">When</th>
                      <th className="py-2 pr-3 font-medium">Type</th>
                      <th className="py-2 pr-3 font-medium">Location</th>
                      <th className="py-2 pr-3 font-medium">Item</th>
                      <th className="py-2 pr-3 font-medium">Qty</th>
                      <th className="py-2 font-medium">Asset</th>
                    </tr>
                  </thead>
                  <tbody>
                    {(movementsQuery.data?.data ?? []).map((row) => (
                      <tr key={row.id} className="border-b border-border/70">
                        <td className="py-2 pr-3">{row.created_at ? new Date(row.created_at).toLocaleString() : "—"}</td>
                        <td className="py-2 pr-3">{row.movement_type_label}</td>
                        <td className="py-2 pr-3">{row.location?.name ?? "—"}</td>
                        <td className="py-2 pr-3">{row.description}</td>
                        <td className="py-2 pr-3 tabular-nums">{row.quantity}</td>
                        <td className="py-2">{row.asset?.asset_code ?? "—"}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </section>
          </>
        ) : null}
      </div>
    </PermissionGate>
  );
}
