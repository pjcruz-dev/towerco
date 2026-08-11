"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Hash, Layers, ListTree, Mail, PackageCheck, Scale, ShieldCheck, Warehouse, FileSpreadsheet } from "lucide-react";

import { ProcurementOnePageHeader } from "@/components/procurement-one/procurement-one-page-header";
import { SettingsPageSkeleton } from "@/components/ui/page-skeletons";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import {
  fetchProcurementOneSettings,
  updateProcurementOneSettings,
} from "@/lib/api/modules/procurement-one-api";
import type { ProcurementApInvoiceMatchPolicy, ProcurementExportColumnMap, ProcurementExportSchedulePolicy, ProcurementGrReceiptPolicy, ProcurementInventoryPolicy, ProcurementRfqScoringPolicy, ProcurementVendorEmailTemplates } from "@/modules/procurement-one/types";
import { getErrorMessage } from "@/lib/api/error";
import { permissions } from "@/lib/rbac/permissions";
import { useNotificationStore } from "@/stores/notification-store";

const EXPORT_ENTITY_LABELS: Record<string, string> = {
  vendors: "Vendors",
  prs: "Purchase requisitions",
  pr_lines: "PR lines",
  pos: "Purchase orders",
  po_lines: "PO lines",
};

export function ProcurementOneSettingsPageClient() {
  const queryClient = useQueryClient();
  const pushNotification = useNotificationStore((state) => state.push);
  const [moduleMessage, setModuleMessage] = useState("");
  const [vendorPolicyEnabled, setVendorPolicyEnabled] = useState(false);
  const [vendorPolicyMode, setVendorPolicyMode] = useState<"warn" | "block">("warn");
  const [prBudgetEnabled, setPrBudgetEnabled] = useState(false);
  const [prBudgetMode, setPrBudgetMode] = useState<"warn" | "block">("warn");
  const [vendorEmailTemplates, setVendorEmailTemplates] = useState<ProcurementVendorEmailTemplates | null>(null);
  const [grReceiptPolicy, setGrReceiptPolicy] = useState<ProcurementGrReceiptPolicy>({
    tolerance_percent: 5,
    mode: "block",
  });
  const [inventoryPolicy, setInventoryPolicy] = useState<ProcurementInventoryPolicy>({
    inventory_mode: "none",
    default_receipt_location_id: null,
    auto_create_assets_on_deploy: false,
  });
  const [apMatchPolicy, setApMatchPolicy] = useState({
    match_mode: "three_way" as "two_way" | "three_way",
    tolerance_percent: 2,
    mode: "block" as "warn" | "block",
    require_grn_posted: true,
  });
  const [rfqScoringPolicy, setRfqScoringPolicy] = useState<ProcurementRfqScoringPolicy>({
    weight_price: 50,
    weight_lead_time: 25,
    weight_accreditation: 15,
    weight_line_coverage: 10,
    vendor_portal_enabled: false,
  });
  const [contractSpendEnabled, setContractSpendEnabled] = useState(false);
  const [contractSpendMode, setContractSpendMode] = useState<"warn" | "block">("warn");
  const [exportColumnMaps, setExportColumnMaps] = useState<Record<string, ProcurementExportColumnMap[]>>({});
  const [exportSchedule, setExportSchedule] = useState<ProcurementExportSchedulePolicy>({
    enabled: false,
    frequency: "monthly",
    day_of_month: 1,
    hour: 6,
    recipients: [],
    period: "previous_month",
    last_run_at: null,
  });
  const [exportRecipientsText, setExportRecipientsText] = useState("");

  const settingsQuery = useQuery({
    queryKey: ["procurement-one", "settings"],
    queryFn: fetchProcurementOneSettings,
  });

  useEffect(() => {
    if (settingsQuery.data) {
      setModuleMessage(settingsQuery.data.module_message ?? "");
      setVendorPolicyEnabled(settingsQuery.data.vendor_accreditation_policy?.enabled ?? false);
      setVendorPolicyMode(settingsQuery.data.vendor_accreditation_policy?.mode ?? "warn");
      setPrBudgetEnabled(settingsQuery.data.pr_budget_policy?.enabled ?? false);
      setPrBudgetMode(settingsQuery.data.pr_budget_policy?.mode ?? "warn");
      setVendorEmailTemplates(settingsQuery.data.vendor_email_templates ?? null);
      setGrReceiptPolicy(
        settingsQuery.data.gr_receipt_policy ?? {
          tolerance_percent: 5,
          mode: "block",
        },
      );
      setInventoryPolicy(
        settingsQuery.data.inventory_policy ?? {
          inventory_mode: "none",
          default_receipt_location_id: null,
          auto_create_assets_on_deploy: false,
        },
      );
      setApMatchPolicy(
        settingsQuery.data.ap_invoice_match_policy ?? {
          match_mode: "three_way",
          tolerance_percent: 2,
          mode: "block",
          require_grn_posted: true,
        },
      );
      setRfqScoringPolicy(
        settingsQuery.data.rfq_scoring_policy ?? {
          weight_price: 50,
          weight_lead_time: 25,
          weight_accreditation: 15,
          weight_line_coverage: 10,
          vendor_portal_enabled: false,
        },
      );
      setContractSpendEnabled(settingsQuery.data.contract_spend_policy?.enabled ?? false);
      setContractSpendMode(settingsQuery.data.contract_spend_policy?.mode ?? "warn");
      setExportColumnMaps(settingsQuery.data.export_column_maps ?? {});
      const schedule = settingsQuery.data.export_schedule ?? {
        enabled: false,
        frequency: "monthly",
        day_of_month: 1,
        hour: 6,
        recipients: [],
        period: "previous_month",
        last_run_at: null,
      };
      setExportSchedule(schedule);
      setExportRecipientsText((schedule.recipients ?? []).join("\n"));
    }
  }, [settingsQuery.data]);

  const saveMutation = useMutation({
    mutationFn: () =>
      updateProcurementOneSettings({
        module_message: moduleMessage,
        vendor_accreditation_policy: {
          enabled: vendorPolicyEnabled,
          mode: vendorPolicyMode,
        },
        pr_budget_policy: {
          enabled: prBudgetEnabled,
          mode: prBudgetMode,
        },
        vendor_email_templates: vendorEmailTemplates ?? undefined,
        gr_receipt_policy: grReceiptPolicy,
        inventory_policy: inventoryPolicy,
        ap_invoice_match_policy: apMatchPolicy,
        rfq_scoring_policy: rfqScoringPolicy,
        contract_spend_policy: {
          enabled: contractSpendEnabled,
          mode: contractSpendMode,
        },
        export_column_maps: exportColumnMaps,
        export_schedule: {
          ...exportSchedule,
          recipients: exportRecipientsText
            .split(/[\n,;]+/)
            .map((email) => email.trim())
            .filter(Boolean),
        },
      }),
    onSuccess: (data) => {
      queryClient.setQueryData(["procurement-one", "settings"], data);
      pushNotification({ title: "Procurement settings saved", variant: "success" });
    },
    onError: (error) => {
      pushNotification({ title: getErrorMessage(error), variant: "destructive" });
    },
  });

  const settings = settingsQuery.data;

  return (
    <PermissionGate requiredPermissions={[permissions.procurementOneSettingsManage]}>
      <div className="space-y-6">
        <ProcurementOnePageHeader
          eyebrow={
            <Link href="/procurement" className="hover:text-primary">
              Procurement-One
            </Link>
          }
          title="Procurement settings"
          description="Configure document catalogs, status labels, and numbering series for PR, PO, and GRN."
          actions={
            <Button size="sm" onClick={() => saveMutation.mutate()} disabled={saveMutation.isPending}>
              {saveMutation.isPending ? "Saving…" : "Save settings"}
            </Button>
          }
        />

        {settingsQuery.isLoading ? <SettingsPageSkeleton /> : null}
        {settingsQuery.isError ? (
          <p className="text-sm text-destructive">Could not load module settings.</p>
        ) : null}

        {!settingsQuery.isLoading && !settingsQuery.isError && settings ? (
          <div className="space-y-6">
            <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
              <Label htmlFor="module_message" className="text-sm font-medium">
                Dashboard message
              </Label>
              <Textarea
                id="module_message"
                className="mt-2 min-h-24"
                value={moduleMessage}
                onChange={(event) => setModuleMessage(event.target.value)}
                placeholder="Optional guidance shown on the procurement overview."
              />
            </section>

            <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
              <div className="flex items-center gap-2 text-sm font-medium text-foreground">
                <ShieldCheck className="h-4 w-4 text-primary" aria-hidden />
                Vendor accreditation policy
              </div>
              <p className="mt-2 text-sm text-muted-foreground">
                When enabled, purchase orders validate vendor accreditation. Block mode hides non-accredited vendors from
                the PO vendor dropdown and rejects submission.
              </p>
              <div className="mt-4 space-y-4">
                <label className="inline-flex items-center gap-2 text-sm">
                  <Checkbox
                    checked={vendorPolicyEnabled}
                    onCheckedChange={(v) => setVendorPolicyEnabled(v === true)}
                    className="size-4"
                  />
                  Enforce vendor accreditation on purchase orders
                </label>
                <div className="space-y-2">
                  <Label htmlFor="vendor_policy_mode">Policy mode</Label>
                  <select
                    id="vendor_policy_mode"
                    value={vendorPolicyMode}
                    onChange={(event) => setVendorPolicyMode(event.target.value as "warn" | "block")}
                    disabled={!vendorPolicyEnabled}
                    className="h-9 w-full max-w-xs rounded-lg border border-input bg-background px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 disabled:opacity-50"
                  >
                    <option value="warn">Warn — allow PO but surface a warning</option>
                    <option value="block">Block — only accredited vendors on PO</option>
                  </select>
                </div>
                <p className="text-xs text-muted-foreground">
                  Manage vendor records in{" "}
                  <Link href="/procurement/vendors" className="font-medium text-primary hover:underline">
                    Procurement → Vendors
                  </Link>
                  .
                </p>
              </div>
            </section>

            <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
              <div className="flex items-center gap-2 text-sm font-medium text-foreground">
                <ShieldCheck className="h-4 w-4 text-primary" aria-hidden />
                PR budget policy
              </div>
              <p className="mt-2 text-sm text-muted-foreground">
                When enabled, purchase requisitions validate estimated totals against rollout budget lines (or Project-One
                baseline when no lines exist). Manage budget lines in{" "}
                <Link href="/procurement/budget" className="font-medium text-primary hover:underline">
                  Procurement → Budget
                </Link>
                .
              </p>
              <div className="mt-4 space-y-4">
                <label className="inline-flex items-center gap-2 text-sm">
                  <Checkbox
                    checked={prBudgetEnabled}
                    onCheckedChange={(v) => setPrBudgetEnabled(v === true)}
                    className="size-4"
                  />
                  Enforce project / rollout budget on PR submit
                </label>
                <div className="space-y-2">
                  <Label htmlFor="pr_budget_mode">Policy mode</Label>
                  <select
                    id="pr_budget_mode"
                    value={prBudgetMode}
                    onChange={(event) => setPrBudgetMode(event.target.value as "warn" | "block")}
                    disabled={!prBudgetEnabled}
                    className="h-9 w-full max-w-xs rounded-lg border border-input bg-background px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 disabled:opacity-50"
                  >
                    <option value="warn">Warn — allow submit with warning</option>
                    <option value="block">Block — reject PR over available budget</option>
                  </select>
                </div>
              </div>
            </section>

            {vendorEmailTemplates ? (
              <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
                <div className="flex items-center gap-2 text-sm font-medium text-foreground">
                  <Mail className="h-4 w-4 text-primary" aria-hidden />
                  Vendor email templates
                </div>
                <p className="mt-2 text-sm text-muted-foreground">
                  Configure PO emails to vendors. Placeholders: {"{{document_no}}"}, {"{{supplier}}"}, {"{{grand_total}}"},
                  {"{{print_url}}"}, {"{{reason}}"}, {"{{brand}}"}.
                </p>
                <div className="mt-4 space-y-4">
                  <label className="inline-flex items-center gap-2 text-sm">
                    <Checkbox
                      checked={vendorEmailTemplates.auto_on_approve}
                      onCheckedChange={(v) =>
                        setVendorEmailTemplates({ ...vendorEmailTemplates, auto_on_approve: v === true })
                      }
                      className="size-4"
                    />
                    Auto-send when PO is approved
                  </label>
                  <label className="inline-flex items-center gap-2 text-sm">
                    <Checkbox
                      checked={vendorEmailTemplates.auto_on_sent}
                      onCheckedChange={(v) =>
                        setVendorEmailTemplates({ ...vendorEmailTemplates, auto_on_sent: v === true })
                      }
                      className="size-4"
                    />
                    Auto-send when PO is marked sent
                  </label>
                  {(["po_sent", "po_cancelled", "po_voided"] as const).map((key) => (
                    <div key={key} className="rounded-lg border border-border p-3">
                      <label className="inline-flex items-center gap-2 text-sm font-medium">
                        <Checkbox
                          checked={vendorEmailTemplates[key].enabled}
                          onCheckedChange={(v) =>
                            setVendorEmailTemplates({
                              ...vendorEmailTemplates,
                              [key]: { ...vendorEmailTemplates[key], enabled: v === true },
                            })
                          }
                          className="size-4"
                        />
                        {key.replaceAll("_", " ")}
                      </label>
                      <Label className="mt-3 block text-xs text-muted-foreground">Subject</Label>
                      <input
                        className="mt-1 h-9 w-full rounded-lg border border-input bg-background px-3 text-sm"
                        value={vendorEmailTemplates[key].subject}
                        onChange={(event) =>
                          setVendorEmailTemplates({
                            ...vendorEmailTemplates,
                            [key]: { ...vendorEmailTemplates[key], subject: event.target.value },
                          })
                        }
                      />
                      <Label className="mt-3 block text-xs text-muted-foreground">Body</Label>
                      <Textarea
                        className="mt-1 min-h-24 text-sm"
                        value={vendorEmailTemplates[key].body}
                        onChange={(event) =>
                          setVendorEmailTemplates({
                            ...vendorEmailTemplates,
                            [key]: { ...vendorEmailTemplates[key], body: event.target.value },
                          })
                        }
                      />
                    </div>
                  ))}
                </div>
              </section>
            ) : null}

            <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
              <div className="flex items-center gap-2 text-sm font-medium text-foreground">
                <PackageCheck className="h-4 w-4 text-primary" aria-hidden />
                Goods receipt policy
              </div>
              <p className="mt-2 text-sm text-muted-foreground">
                Controls over-receipt tolerance when posting GRNs against purchase order lines.
              </p>
              <div className="mt-4 grid gap-4 md:grid-cols-2">
                <div className="space-y-2">
                  <Label htmlFor="gr_tolerance_percent">Tolerance (%)</Label>
                  <input
                    id="gr_tolerance_percent"
                    type="number"
                    min={0}
                    max={100}
                    step="0.01"
                    value={grReceiptPolicy.tolerance_percent}
                    onChange={(event) =>
                      setGrReceiptPolicy({
                        ...grReceiptPolicy,
                        tolerance_percent: Number(event.target.value),
                      })
                    }
                    className="h-9 w-full max-w-xs rounded-lg border border-input bg-background px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="gr_receipt_mode">Over-receipt mode</Label>
                  <select
                    id="gr_receipt_mode"
                    value={grReceiptPolicy.mode}
                    onChange={(event) =>
                      setGrReceiptPolicy({
                        ...grReceiptPolicy,
                        mode: event.target.value as "warn" | "block",
                      })
                    }
                    className="h-9 w-full max-w-xs rounded-lg border border-input bg-background px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
                  >
                    <option value="warn">Warn — allow within tolerance</option>
                    <option value="block">Block — reject over-receipt</option>
                  </select>
                </div>
              </div>
            </section>

            <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
              <div className="flex items-center gap-2 text-sm font-medium text-foreground">
                <PackageCheck className="h-4 w-4 text-primary" aria-hidden />
                AP invoice match policy
              </div>
              <p className="mt-2 text-sm text-muted-foreground">
                Controls 2-way (PO) or 3-way (PO + posted GRN) matching when supplier invoices are submitted.
              </p>
              <div className="mt-4 grid gap-4 md:grid-cols-2">
                <div className="space-y-2">
                  <Label htmlFor="ap_match_mode">Match mode</Label>
                  <select
                    id="ap_match_mode"
                    value={apMatchPolicy.match_mode}
                    onChange={(event) =>
                      setApMatchPolicy({ ...apMatchPolicy, match_mode: event.target.value as "two_way" | "three_way" })
                    }
                    className="h-9 w-full max-w-xs rounded-lg border border-input bg-background px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
                  >
                    <option value="two_way">2-way — PO only</option>
                    <option value="three_way">3-way — PO + GRN</option>
                  </select>
                </div>
                <div className="space-y-2">
                  <Label htmlFor="ap_tolerance_percent">Tolerance (%)</Label>
                  <input
                    id="ap_tolerance_percent"
                    type="number"
                    min={0}
                    max={100}
                    step="0.01"
                    value={apMatchPolicy.tolerance_percent}
                    onChange={(event) =>
                      setApMatchPolicy({ ...apMatchPolicy, tolerance_percent: Number(event.target.value) })
                    }
                    className="h-9 w-full max-w-xs rounded-lg border border-input bg-background px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="ap_match_policy_mode">Variance mode</Label>
                  <select
                    id="ap_match_policy_mode"
                    value={apMatchPolicy.mode}
                    onChange={(event) =>
                      setApMatchPolicy({ ...apMatchPolicy, mode: event.target.value as "warn" | "block" })
                    }
                    className="h-9 w-full max-w-xs rounded-lg border border-input bg-background px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
                  >
                    <option value="warn">Warn</option>
                    <option value="block">Block</option>
                  </select>
                </div>
              </div>
              <label className="mt-4 inline-flex items-center gap-2 text-sm">
                <Checkbox
                  checked={apMatchPolicy.require_grn_posted}
                  onCheckedChange={(v) =>
                    setApMatchPolicy({ ...apMatchPolicy, require_grn_posted: v === true })
                  }
                  className="size-4"
                />
                Require posted GRN for 3-way match
              </label>
            </section>

            <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
              <div className="flex items-center gap-2 text-sm font-medium text-foreground">
                <ListTree className="h-4 w-4 text-primary" aria-hidden />
                RFQ bid scoring weights
              </div>
              <p className="mt-2 text-sm text-muted-foreground">
                Configure comparison matrix weights for price, lead time, vendor accreditation, and line coverage. Values are normalized to 100% on save.
              </p>
              <div className="mt-4 grid gap-4 md:grid-cols-2">
                {(
                  [
                    ["weight_price", "Price"],
                    ["weight_lead_time", "Lead time"],
                    ["weight_accreditation", "Accreditation"],
                    ["weight_line_coverage", "Line coverage"],
                  ] as const
                ).map(([key, label]) => (
                  <div key={key} className="space-y-2">
                    <Label htmlFor={key}>{label} weight</Label>
                    <input
                      id={key}
                      type="number"
                      min={0}
                      max={100}
                      value={rfqScoringPolicy[key]}
                      onChange={(event) =>
                        setRfqScoringPolicy({ ...rfqScoringPolicy, [key]: Number(event.target.value) })
                      }
                      className="h-9 w-full max-w-xs rounded-lg border border-input bg-background px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
                    />
                  </div>
                ))}
              </div>
              <p className="mt-3 text-xs text-muted-foreground">
                VENDOR-ONE self-service vendor portal for quotes is reserved for a future release (`vendor_portal_enabled` remains off).
              </p>
            </section>

            <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
              <div className="flex items-center gap-2 text-sm font-medium text-foreground">
                <Scale className="h-4 w-4 text-primary" aria-hidden />
                Contract spend ceiling policy
              </div>
              <p className="mt-2 text-sm text-muted-foreground">
                When POs reference an active vendor contract, compare PO grand total against the contract spend ceiling and committed PO totals.
              </p>
              <div className="mt-4 grid gap-4 md:grid-cols-2">
                <label className="inline-flex items-center gap-2 text-sm">
                  <Checkbox
                    checked={contractSpendEnabled}
                    onCheckedChange={(v) => setContractSpendEnabled(v === true)}
                    className="size-4"
                  />
                  Enforce contract spend ceiling
                </label>
                <div className="space-y-2">
                  <Label htmlFor="contract_spend_mode">When over ceiling</Label>
                  <select
                    id="contract_spend_mode"
                    value={contractSpendMode}
                    onChange={(event) => setContractSpendMode(event.target.value as "warn" | "block")}
                    className="h-9 w-full max-w-xs rounded-lg border border-input bg-background px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
                  >
                    <option value="warn">Warn</option>
                    <option value="block">Block</option>
                  </select>
                </div>
              </div>
            </section>

            <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
              <div className="flex items-center gap-2 text-sm font-medium text-foreground">
                <Warehouse className="h-4 w-4 text-primary" aria-hidden />
                Inventory policy
              </div>
              <p className="mt-2 text-sm text-muted-foreground">
                Simple stock ledger for warehouse receipts, transfers, and deployment into Asset-One. Requires Enterprise plan.
              </p>
              <div className="mt-4 grid gap-4 md:grid-cols-2">
                <div className="space-y-2">
                  <Label htmlFor="inventory_mode">Inventory mode</Label>
                  <select
                    id="inventory_mode"
                    value={inventoryPolicy.inventory_mode}
                    onChange={(event) =>
                      setInventoryPolicy({
                        ...inventoryPolicy,
                        inventory_mode: event.target.value as "none" | "simple",
                      })
                    }
                    className="h-9 w-full max-w-xs rounded-lg border border-input bg-background px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
                  >
                    <option value="none">None — GRN only, no stock ledger</option>
                    <option value="simple">Simple — track on-hand by warehouse location</option>
                  </select>
                </div>
                <div className="space-y-2">
                  <Label htmlFor="default_receipt_location_id">Default receipt location ID</Label>
                  <input
                    id="default_receipt_location_id"
                    value={inventoryPolicy.default_receipt_location_id ?? ""}
                    onChange={(event) =>
                      setInventoryPolicy({
                        ...inventoryPolicy,
                        default_receipt_location_id: event.target.value || null,
                      })
                    }
                    placeholder="UUID of default warehouse"
                    className="h-9 w-full rounded-lg border border-input bg-background px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
                  />
                </div>
              </div>
              <label className="mt-4 inline-flex items-center gap-2 text-sm">
                <Checkbox
                  checked={inventoryPolicy.auto_create_assets_on_deploy}
                  onCheckedChange={(v) =>
                    setInventoryPolicy({
                      ...inventoryPolicy,
                      auto_create_assets_on_deploy: v === true,
                    })
                  }
                  className="size-4"
                />
                Auto-create Asset-One records when deploying stock to a site
              </label>
            </section>

            <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
              <div className="flex items-center gap-2 text-sm font-medium text-foreground">
                <FileSpreadsheet className="h-4 w-4 text-primary" aria-hidden />
                Export column maps
              </div>
              <p className="mt-2 text-sm text-muted-foreground">
                Choose which columns appear in finance Excel packs and CSV extracts. Requires Enterprise plan.
              </p>
              <div className="mt-4 space-y-4">
                {Object.entries(exportColumnMaps).map(([entity, columns]) => (
                  <div key={entity} className="rounded-lg border border-border p-3">
                    <div className="text-sm font-medium">{EXPORT_ENTITY_LABELS[entity] ?? entity}</div>
                    <div className="mt-2 grid gap-2 sm:grid-cols-2">
                      {columns.map((column, index) => (
                        <label key={column.key} className="inline-flex items-center gap-2 text-sm">
                          <Checkbox
                            checked={column.enabled}
                            onCheckedChange={(v) => {
                              const next = [...columns];
                              next[index] = { ...column, enabled: v === true };
                              setExportColumnMaps({ ...exportColumnMaps, [entity]: next });
                            }}
                            className="size-4"
                          />
                          {column.label}
                        </label>
                      ))}
                    </div>
                  </div>
                ))}
              </div>
            </section>

            <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
              <div className="flex items-center gap-2 text-sm font-medium text-foreground">
                <Mail className="h-4 w-4 text-primary" aria-hidden />
                Scheduled finance export
              </div>
              <p className="mt-2 text-sm text-muted-foreground">
                Email the monthly Excel pack to finance on a fixed day and hour (tenant local time).
              </p>
              <label className="mt-4 inline-flex items-center gap-2 text-sm">
                <Checkbox
                  checked={exportSchedule.enabled}
                  onCheckedChange={(v) => setExportSchedule({ ...exportSchedule, enabled: v === true })}
                  className="size-4"
                />
                Enable scheduled export
              </label>
              <div className="mt-4 grid gap-4 md:grid-cols-3">
                <div className="space-y-2">
                  <Label htmlFor="export_day_of_month">Day of month</Label>
                  <input
                    id="export_day_of_month"
                    type="number"
                    min={1}
                    max={28}
                    value={exportSchedule.day_of_month}
                    onChange={(event) =>
                      setExportSchedule({ ...exportSchedule, day_of_month: Number(event.target.value) })
                    }
                    className="h-9 w-full max-w-xs rounded-lg border border-input bg-background px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="export_hour">Hour (0–23)</Label>
                  <input
                    id="export_hour"
                    type="number"
                    min={0}
                    max={23}
                    value={exportSchedule.hour}
                    onChange={(event) => setExportSchedule({ ...exportSchedule, hour: Number(event.target.value) })}
                    className="h-9 w-full max-w-xs rounded-lg border border-input bg-background px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="export_period">Data period</Label>
                  <select
                    id="export_period"
                    value={exportSchedule.period}
                    onChange={(event) =>
                      setExportSchedule({
                        ...exportSchedule,
                        period: event.target.value as "previous_month" | "current_month",
                      })
                    }
                    className="h-9 w-full max-w-xs rounded-lg border border-input bg-background px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
                  >
                    <option value="previous_month">Previous month</option>
                    <option value="current_month">Current month</option>
                  </select>
                </div>
              </div>
              <div className="mt-4 space-y-2">
                <Label htmlFor="export_recipients">Finance recipients</Label>
                <Textarea
                  id="export_recipients"
                  value={exportRecipientsText}
                  onChange={(event) => setExportRecipientsText(event.target.value)}
                  placeholder="finance@example.com&#10;controller@example.com"
                  rows={3}
                />
                <p className="text-xs text-muted-foreground">One email per line (or comma-separated).</p>
              </div>
              {exportSchedule.last_run_at ? (
                <p className="mt-3 text-xs text-muted-foreground">
                  Last run: {new Date(exportSchedule.last_run_at).toLocaleString()}
                </p>
              ) : null}
            </section>

            <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
              <p className="text-sm text-muted-foreground">
                Approval routing for PR and PO forms is managed in{" "}
                <Link href="/e-approval/approval-policies" className="font-medium text-primary hover:underline">
                  E-Approval → Approval policies
                </Link>
                . Enable <code className="text-xs">use_approval_policy</code> on each form to compile steps from the tenant DOA matrix.
              </p>
            </section>

            <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
              <div className="flex items-center gap-2 text-sm font-medium text-foreground">
                <Layers className="h-4 w-4 text-primary" aria-hidden />
                Document types
              </div>
              <ul className="mt-3 space-y-2 text-sm text-muted-foreground">
                {settings.document_types.map((type) => (
                  <li key={type.id} className="flex items-center justify-between rounded-lg border border-border px-3 py-2">
                    <span className="font-medium text-foreground">{type.label}</span>
                    <span className="text-xs text-muted-foreground">{type.code}</span>
                  </li>
                ))}
              </ul>
              <p className="mt-3 text-xs text-muted-foreground">
                Full admin editing for document type labels arrives in a later phase. Defaults are seeded from the platform catalog.
              </p>
            </section>

            <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
              <div className="flex items-center gap-2 text-sm font-medium text-foreground">
                <ListTree className="h-4 w-4 text-primary" aria-hidden />
                Status catalogs
              </div>
              <div className="mt-3 space-y-4">
                {Object.entries(settings.status_catalogs).map(([documentType, statuses]) => (
                  <div key={documentType}>
                    <p className="text-xs font-medium text-muted-foreground">
                      {documentType.replaceAll("_", " ")}
                    </p>
                    <p className="mt-1 text-sm text-foreground">
                      {statuses.map((status) => status.label).join(" · ")}
                    </p>
                  </div>
                ))}
              </div>
            </section>

            <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
              <div className="flex items-center gap-2 text-sm font-medium text-foreground">
                <Hash className="h-4 w-4 text-primary" aria-hidden />
                Numbering series
              </div>
              <div className="mt-3 space-y-3">
                {Object.entries(settings.numbering_series).map(([documentType, series]) => (
                  <div
                    key={documentType}
                    className="rounded-lg border border-border px-3 py-2 text-sm text-muted-foreground"
                  >
                    <p className="font-medium text-foreground">{documentType.replaceAll("_", " ")}</p>
                    <p className="mt-1">
                      Prefix <span className="font-mono text-foreground">{series.prefix}</span> · padding{" "}
                      {series.padding} · reset {series.reset_rule} · next {series.next_sequence}
                    </p>
                  </div>
                ))}
              </div>
            </section>
          </div>
        ) : null}
      </div>
    </PermissionGate>
  );
}
