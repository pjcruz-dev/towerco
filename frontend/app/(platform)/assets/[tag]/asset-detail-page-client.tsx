"use client";

import { useQuery, useQueryClient } from "@tanstack/react-query";
import { ArrowLeft, LayoutGrid, Printer } from "lucide-react";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { useEffect, useRef, useState } from "react";
import { QRCodeSVG } from "qrcode.react";

import { EApprovalSignaturePad } from "@/components/e-approval/e-approval-signature-pad";
import { DynSchemaSheets, type DynSchemaMode } from "@/components/dynamic-entities/dyn-schema-sheets";
import { DynStructuredLayout } from "@/components/dynamic-entities/dyn-structured-layout";
import {
  RequiredFieldsAlert,
  useRequiredFields,
} from "@/components/dynamic-entities/required-fields-alert";
import { RecordActionHistoryButton } from "@/components/dynamic-entities/record-action-history";
import { AssetStatusBadge } from "@/components/assets/asset-status-badge";
import { WorkspacePageHeader } from "@/components/layout/workspace-page-header";
import { AssetFieldInput, WRITABLE_SYSTEM, buildAssetWriteBody } from "@/app/(platform)/assets/asset-field-control";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import {
  assignAsset,
  disposeAsset,
  fetchAsset,
  fetchAssetSchema,
  openRepair,
  returnAsset,
  searchAssetPeople,
  sendAccountability,
  signAccountability,
  updateAsset,
  updateRepair,
} from "@/lib/api/modules/assets-api";
import { fetchDynEntity, type DynField } from "@/lib/api/modules/dynamic-entities-api";
import { listPrintTemplates, templateDisplayName } from "@/lib/dynamic-entities/dyn-print-templates";
import { fieldsForSurface, groupsForSurface } from "@/lib/dynamic-entities/form-surfaces";
import { dynSelectChoiceLabel } from "@/lib/dynamic-entities/select-choices";
import { getErrorMessage } from "@/lib/api/error";
import { formatDisplayDate } from "@/lib/format-display-date";
import { isFieldHiddenOnSurface, isFieldReadOnlyForRole } from "@/lib/rbac/entity-access";
import { notify } from "@/lib/ui/notify";
import type { AssetPerson, AssetRecord } from "@/modules/assets/types";
import { useAuthStore } from "@/stores/auth-store";

const TABS = [
  { id: "overview", label: "Details" },
  { id: "assignment", label: "Assignment" },
  { id: "repair", label: "Repair" },
  { id: "hardware", label: "Hardware" },
  { id: "qr", label: "QR" },
  { id: "dispose", label: "Dispose" },
  { id: "history", label: "History" },
] as const;

function assetDetailValue(record: AssetRecord, name: string): string {
  if (name === "assignee") {
    return record.assignee?.name ?? "—";
  }
  const bag = record as unknown as Record<string, unknown>;
  const direct = bag[name];
  if (direct != null && typeof direct !== "object") {
    return String(direct);
  }
  const custom = record.custom_values?.[name];
  if (custom == null || custom === "") {
    return "—";
  }
  return String(custom);
}

export function AssetDetailPageClient({ tag }: { tag: string }) {
  const params = useSearchParams();
  const router = useRouter();
  const queryClient = useQueryClient();
  const user = useAuthStore((state) => state.user);
  const activeTenantId = useAuthStore((state) => state.activeTenantId);
  const permissions =
    user?.tenantAccesses.find((item) => item.tenantId === activeTenantId)?.permissions ??
    user?.permissions ??
    [];
  const userId = user?.id;
  const can = (key: string) => permissions.includes(key);
  const tabParam = params.get("tab") ?? "overview";
  const tab = TABS.some((item) => item.id === tabParam) ? tabParam : "overview";
  const editMode = params.get("edit") === "1";
  const isEditing = editMode && can("assets:manage");
  const asset = useQuery({ queryKey: ["asset", tag], queryFn: () => fetchAsset(tag) });
  const [error, setError] = useState<string | null>(null);
  const [employeeQuery, setEmployeeQuery] = useState("");
  const [employee, setEmployee] = useState<AssetPerson | null>(null);
  const [signature, setSignature] = useState("");
  const [disposeText, setDisposeText] = useState("");
  const [disposition, setDisposition] = useState("E_WASTE");
  const [schemaMode, setSchemaMode] = useState<DynSchemaMode>({ kind: "closed" });
  const [layoutEditing, setLayoutEditing] = useState(false);
  const [draft, setDraft] = useState<Record<string, string>>({});
  const [saving, setSaving] = useState(false);
  const seededFor = useRef<string | null>(null);
  const entity = useQuery({
    queryKey: ["it-asset-entity"],
    queryFn: async () => {
      await fetchAssetSchema();
      return fetchDynEntity("it_asset");
    },
    staleTime: 0,
    refetchOnMount: "always",
  });
  const people = useQuery({
    queryKey: ["asset-people", employeeQuery],
    queryFn: () => searchAssetPeople(employeeQuery),
    enabled: employeeQuery.trim().length > 1,
  });

  useEffect(() => {
    if (!isEditing || !asset.data) {
      seededFor.current = null;
      return;
    }
    const key = `${asset.data.id}:edit`;
    if (seededFor.current === key) {
      return;
    }
    seededFor.current = key;
    setDraft(seedAssetDraft(asset.data));
  }, [asset.data, isEditing]);

  const accessMatrix = user?.accessMatrix;
  const canManageFields = can("assets:fields:manage");
  const fieldSurface = isEditing || layoutEditing ? "form" : "record";
  const fields = (entity.data?.fields ?? []).filter(
    (field) => canManageFields || !isFieldHiddenOnSurface(accessMatrix, "it_asset", field.name, fieldSurface),
  );
  const required = useRequiredFields(fields, draft);

  if (asset.isLoading) {
    return <div className="p-6 text-sm text-muted-foreground">Loading asset…</div>;
  }
  if (asset.isError || !asset.data) {
    return (
      <div className="p-6">
        <p className="text-sm">{getErrorMessage(asset.error)}</p>
        <Button type="button" className="mt-3" variant="outline" onClick={() => void asset.refetch()}>
          Retry
        </Button>
      </div>
    );
  }

  const record = asset.data;
  const computer = record.asset_type === "laptop" || record.asset_type === "desktop";

  const groups = entity.data?.field_groups ?? [];
  const surfaceGroups = groupsForSurface(groups, tab);
  const surfaceFields = fieldsForSurface(fields, groups, tab);
  const formSurfaces = TABS.map((item) => ({ id: item.id, label: item.label }));

  async function refresh() {
    await queryClient.invalidateQueries({ queryKey: ["asset", tag] });
  }

  function setTab(nextTab: string) {
    const next = new URLSearchParams(params.toString());
    next.set("tab", nextTab);
    router.replace(`/assets/${record.asset_tag}?${next.toString()}`);
  }

  function openEdit() {
    const next = new URLSearchParams(params.toString());
    next.set("edit", "1");
    router.replace(`/assets/${record.asset_tag}?${next.toString()}`);
  }

  async function onSave() {
    if (required.blockIfMissing()) return;
    setSaving(true);
    setError(null);
    try {
      const writable = fields.filter((field) => !isFieldReadOnlyForRole(accessMatrix, "it_asset", field.name));
      await updateAsset(record.asset_tag, buildAssetWriteBody(writable, draft, record.custom_values ?? {}));
      notify.success("Asset saved", record.asset_tag);
      const next = new URLSearchParams(params.toString());
      next.delete("edit");
      router.replace(`/assets/${record.asset_tag}?${next.toString()}`);
      await refresh();
    } catch (caught: unknown) {
      setError(getErrorMessage(caught));
    } finally {
      setSaving(false);
    }
  }

  function renderViewField(field: DynField) {
    const text = assetDetailValue(record, field.name);
    if (text === "—") {
      return <span className="text-muted-foreground">N/A</span>;
    }
    if (field.name === "status") {
      return <AssetStatusBadge status={text} />;
    }
    if (field.type === "select" || field.type === "multiselect") {
      return <span>{dynSelectChoiceLabel(field.options, text) || text}</span>;
    }
    const kind = field.type === "date" || field.type === "datetime" ? field.type : undefined;
    const shown = formatDisplayDate(text, kind) ?? text;
    return (
      <span className={field.name === "asset_tag" || field.name === "serial_number" ? "font-mono" : undefined}>
        {shown}
      </span>
    );
  }

  function renderEditField(field: DynField) {
    if (!canManageFields && isFieldReadOnlyForRole(accessMatrix, "it_asset", field.name)) {
      return renderViewField(field);
    }
    return (
      <AssetFieldInput
        field={field}
        value={draft[field.name] ?? ""}
        onChange={(next) => setDraft((current) => ({ ...current, [field.name]: next }))}
        mode="edit"
      />
    );
  }

  return (
    <div className="flex w-full flex-col gap-6">
      <WorkspacePageHeader
        title={record.asset_tag}
        description={
          [
            `${record.manufacturer ?? ""} ${record.product_name ?? ""}`.trim() || record.asset_type,
            record.lifecycle.band?.replaceAll("_", " "),
          ]
            .filter(Boolean)
            .join(" · ")
        }
        actions={
          <div className="flex flex-wrap items-center justify-end gap-2">
            <RecordActionHistoryButton
              entitySlug="it_asset"
              entityLabel="Assets"
              canRevert={can("assets:manage")}
              onChanged={() => {
                void asset.refetch();
              }}
            />
            <Button type="button" size="sm" variant="outline" render={<Link href="/assets" />}>
              <ArrowLeft className="size-3.5" />
              Back
            </Button>
            {(entity.data
              ? listPrintTemplates(entity.data.print_settings, entity.data.name)
              : []
            ).map((template) => (
              <Button
                key={template.id}
                type="button"
                size="sm"
                variant="outline"
                render={
                  <Link
                    href={`/assets/${record.asset_tag}/print?template=${encodeURIComponent(template.id)}`}
                    target="_blank"
                  />
                }
              >
                <Printer className="size-3.5" />
                {templateDisplayName(template)}
              </Button>
            ))}
            {!entity.data ? (
              <Button
                type="button"
                size="sm"
                variant="outline"
                render={<Link href={`/assets/${record.asset_tag}/print`} target="_blank" />}
              >
                <Printer className="size-3.5" />
                Print
              </Button>
            ) : null}
            {can("assets:fields:manage") && !isEditing ? (
              <Button
                type="button"
                size="sm"
                variant={layoutEditing ? "default" : "outline"}
                onClick={() => setLayoutEditing((value) => !value)}
              >
                <LayoutGrid className="size-3.5" />
                {layoutEditing ? "Done layout" : "Customize"}
              </Button>
            ) : null}
            {isEditing ? (
              <Button type="button" size="sm" disabled={saving || !entity.data} onClick={() => void onSave()}>
                {saving ? "Saving…" : "Save"}
              </Button>
            ) : can("assets:manage") ? (
              <Button type="button" size="sm" onClick={openEdit}>
                Edit
              </Button>
            ) : null}
          </div>
        }
      />
      <div className="flex items-center gap-1 border-b border-border">
        {TABS.map((item) => (
          <button
            key={item.id}
            type="button"
            onClick={() => setTab(item.id)}
            className={
              tab === item.id
                ? "-mb-px inline-flex shrink-0 items-center border-b-2 border-primary px-3 py-2 text-sm font-medium whitespace-nowrap text-foreground"
                : "inline-flex shrink-0 items-center px-3 py-2 text-sm font-medium whitespace-nowrap text-muted-foreground hover:text-foreground"
            }
          >
            {item.label}
          </button>
        ))}
      </div>
      {error ? <p className="text-sm text-destructive">{error}</p> : null}
      <RequiredFieldsAlert fields={required.missing} pulse={required.pulse} />

      {entity.data && (canManageFields || surfaceGroups.length > 0 || surfaceFields.length > 0) ? (
        <DynStructuredLayout
          groups={surfaceGroups}
          fields={surfaceFields}
          entitySlug="it_asset"
          canManageSchema={canManageFields}
          layoutEditing={isEditing || layoutEditing}
          allowWidthAdjust={!isEditing}
          mode={isEditing || layoutEditing ? "form" : "view"}
          formSurface={tab}
          surfaces={formSurfaces}
          renderFieldValue={isEditing ? renderEditField : renderViewField}
          invalidFieldNames={isEditing ? required.invalidNames : undefined}
          onSchemaAction={setSchemaMode}
          onLayoutChanged={async () => {
            await entity.refetch();
          }}
        />
      ) : !entity.data ? (
        <div className="rounded-xl border border-border bg-card p-4 text-sm text-muted-foreground shadow-sm">
          {entity.isError ? getErrorMessage(entity.error) : "Loading fields…"}
        </div>
      ) : null}
      {entity.data ? (
        <DynSchemaSheets
          entitySlug="it_asset"
          entityName={entity.data.name}
          groups={groups}
          fields={fields}
          mode={schemaMode}
          onClose={() => setSchemaMode({ kind: "closed" })}
          onSaved={async () => {
            await entity.refetch();
          }}
        />
      ) : null}

      {tab === "assignment" ? (
        <section className="space-y-4 rounded-xl border border-border bg-card p-4 shadow-sm">
          {can("assets:assign") ? (
            <div className="space-y-2">
              <Label htmlFor="assign-search">Assign to</Label>
              <Input id="assign-search" value={employeeQuery} onChange={(event) => setEmployeeQuery(event.target.value)} />
              <ul className="text-sm">
                {(people.data ?? []).map((person) => (
                  <li key={person.id}>
                    <button type="button" className="px-2 py-2 text-left hover:bg-muted" onClick={() => setEmployee(person)}>
                      {person.name} · {person.department ?? "No department"}
                    </button>
                  </li>
                ))}
              </ul>
              <Button
                type="button"
                disabled={!employee}
                onClick={() => {
                  if (!employee) return;
                  setError(null);
                  void assignAsset(record.id, { employee_id: employee.id })
                    .then(() => {
                      notify.success("Asset assigned", employee.name);
                      return refresh();
                    })
                    .catch((caught: unknown) => setError(getErrorMessage(caught)));
                }}
              >
                Assign {employee ? employee.name : ""}
              </Button>
            </div>
          ) : null}
          {record.accountability ? (
            <div className="space-y-3">
              <p className="text-sm">
                Accountability {record.accountability.number} ·{" "}
                <AssetStatusBadge status={record.accountability.status} />
              </p>
              {can("assets:assign") && record.accountability.status === "DRAFT" ? (
                <Button
                  type="button"
                  onClick={() => {
                    setError(null);
                    void sendAccountability(record.accountability!.id)
                      .then(() => {
                        notify.success("Accountability sent", record.accountability?.number);
                        return refresh();
                      })
                      .catch((caught: unknown) => setError(getErrorMessage(caught)));
                  }}
                >
                  Send for signature
                </Button>
              ) : null}
              {record.accountability.status === "PENDING_SIGNATURE" &&
              (can("assets:accountability:sign") || userId === record.accountability.employee_id) ? (
                <div className="space-y-2">
                  <EApprovalSignaturePad value={signature} onChange={setSignature} />
                  <Button
                    type="button"
                    disabled={signature.trim() === ""}
                    onClick={() => {
                      setError(null);
                      void signAccountability(record.accountability!.id, signature)
                        .then(() => {
                          notify.success("Accountability signed", record.accountability?.number);
                          return refresh();
                        })
                        .catch((caught: unknown) => setError(getErrorMessage(caught)));
                    }}
                  >
                    Sign
                  </Button>
                </div>
              ) : null}
            </div>
          ) : null}
          <div className="space-y-2">
            <Label htmlFor="return-condition">Return condition</Label>
            <Input id="return-condition" name="condition" />
            {can("assets:transfer") ? (
              <Button
                type="button"
                variant="outline"
                onClick={() => {
                  const condition = (document.getElementById("return-condition") as HTMLInputElement | null)?.value ?? "";
                  setError(null);
                  void returnAsset(record.id, { status: "AVAILABLE", condition })
                    .then(() => {
                      notify.success("Asset returned", record.asset_tag);
                      return refresh();
                    })
                    .catch((caught: unknown) => setError(getErrorMessage(caught)));
                }}
              >
                Return to storage
              </Button>
            ) : null}
          </div>
          <ul className="space-y-2 text-sm">
            {record.assignments.map((row) => (
              <li key={row.id}>
                {row.employee?.name ?? "Unknown"} · <AssetStatusBadge status={row.status} /> · {row.assigned_at ?? ""}
              </li>
            ))}
          </ul>
        </section>
      ) : null}

      {tab === "repair" ? (
        <section className="space-y-4 rounded-xl border border-border bg-card p-4 shadow-sm">
          {can("assets:repair") ? (
            <form
              className="space-y-3"
              onSubmit={(event) => {
                event.preventDefault();
                const data = new FormData(event.currentTarget);
                setError(null);
                void openRepair(record.id, {
                  usable: computer ? data.get("usable") === "yes" : null,
                  replacement_required: data.get("replacement_required") === "yes",
                  problem: String(data.get("problem") ?? ""),
                })
                  .then(() => {
                    notify.success("Repair opened", record.asset_tag);
                    return refresh();
                  })
                  .catch((caught: unknown) => setError(getErrorMessage(caught)));
              }}
            >
              {computer ? (
                <div className="space-y-1.5">
                  <Label htmlFor="usable">Is the computer usable?</Label>
                  <Select id="usable" name="usable" defaultValue="">
                    <option value="">Choose</option>
                    <option value="yes">Yes</option>
                    <option value="no">No</option>
                  </Select>
                </div>
              ) : null}
              <div className="space-y-1.5">
                <Label htmlFor="replacement_required">Replacement required?</Label>
                <Select id="replacement_required" name="replacement_required" defaultValue="no">
                  <option value="no">No</option>
                  <option value="yes">Yes</option>
                </Select>
              </div>
              <div className="space-y-1.5">
                <Label htmlFor="problem">Problem</Label>
                <Textarea id="problem" name="problem" />
              </div>
              <Button type="submit">Open repair</Button>
            </form>
          ) : null}
          <ul className="space-y-3 text-sm">
            {record.repairs.map((repair) => (
              <li key={repair.id} className="rounded-lg border border-border p-3">
                <p>
                  <AssetStatusBadge status={repair.status} /> · {repair.problem ?? "No problem text"}
                </p>
                {repair.vendor_email_subject ? (
                  <p className="mt-2 text-muted-foreground">
                    Draft: {repair.vendor_email_subject}
                  </p>
                ) : null}
                {can("assets:repair") && repair.status === "OPEN" ? (
                  <Button
                    type="button"
                    className="mt-2"
                    variant="outline"
                    onClick={() => {
                      setError(null);
                      void updateRepair(repair.id, { result: "REPAIRED" })
                        .then(() => {
                          notify.success("Repair updated", record.asset_tag);
                          return refresh();
                        })
                        .catch((caught: unknown) => setError(getErrorMessage(caught)));
                    }}
                  >
                    Mark repaired
                  </Button>
                ) : null}
              </li>
            ))}
          </ul>
        </section>
      ) : null}

      {tab === "history" ? (
        <ul className="space-y-2 rounded-xl border border-border bg-card p-4 text-sm shadow-sm">
          {record.events.map((event) => (
            <li key={event.id}>
              {event.event_type} · {event.from_place ?? "—"} → {event.to_place ?? "—"} · {event.occurred_at ?? ""}
            </li>
          ))}
        </ul>
      ) : null}

      {tab === "hardware" ? (
        <div className="space-y-4">
          <section className="space-y-2 rounded-xl border border-border bg-card p-4 text-sm shadow-sm">
            <h2 className="text-sm font-medium">Computer</h2>
            <p>Processor: {record.processor ?? "—"}</p>
            <p>RAM: {record.installed_ram ?? "—"}</p>
            <p>OS: {record.operating_system ?? "—"}</p>
            <p>BIOS: {record.bios_version ?? "—"}</p>
          </section>
          <HardwareList
            title="Network adapters"
            empty="No network adapters recorded."
            rows={record.network_adapters.map((adapter) => ({
              id: adapter.id,
              text: [adapter.adapter_name, adapter.mac_address, adapter.ip_address].filter(Boolean).join(" · ") || "—",
            }))}
          />
          <HardwareList
            title="Disks"
            empty="No disks recorded."
            rows={record.disks.map((disk) => ({
              id: disk.id,
              text: [disk.model, disk.size_label, disk.serial_number].filter(Boolean).join(" · ") || "—",
            }))}
          />
          <HardwareList
            title="Video"
            empty="No video cards recorded."
            rows={record.video_cards.map((card) => ({
              id: card.id,
              text: [card.name, card.driver_version].filter(Boolean).join(" · ") || "—",
            }))}
          />
          <HardwareList
            title="Applications"
            empty="No applications recorded."
            rows={record.applications.map((app) => ({
              id: app.id,
              text: [app.application_name, app.version, app.publisher].filter(Boolean).join(" · "),
            }))}
          />
        </div>
      ) : null}

      {tab === "qr" ? (
        <section className="max-w-sm space-y-3 rounded-xl border border-border bg-card p-4 shadow-sm">
          <h2 className="text-sm font-medium">Scan code</h2>
          <div className="inline-flex rounded-lg border border-border bg-white p-3 dark:bg-white">
            <QRCodeSVG value={record.qr_path} size={180} />
          </div>
          <p className="font-mono text-sm text-muted-foreground">{record.qr_path}</p>
        </section>
      ) : null}

      {tab === "dispose" ? (
        <section className="max-w-lg space-y-3 rounded-xl border border-border bg-card p-4 shadow-sm">
          <h2 className="text-sm font-medium">Dispose {record.asset_tag}</h2>
          {record.status === "DISPOSED" ? (
            <p className="text-sm text-muted-foreground">This asset is already disposed. The row stays in the register.</p>
          ) : can("assets:dispose") ? (
            <>
              <p className="text-sm text-muted-foreground">The row stays in the register. Type DISPOSE to confirm.</p>
              <div className="space-y-1.5">
                <Label htmlFor="disposition">Disposition</Label>
                <Select id="disposition" value={disposition} onChange={(event) => setDisposition(event.target.value)}>
                  <option value="BUYOUT">Buyout</option>
                  <option value="BIDDING">Bidding</option>
                  <option value="E_WASTE">E-waste</option>
                  <option value="DISPOSAL">Disposal</option>
                </Select>
              </div>
              <div className="space-y-1.5">
                <Label htmlFor="dispose-confirm">Confirmation</Label>
                <Input id="dispose-confirm" value={disposeText} onChange={(event) => setDisposeText(event.target.value)} />
              </div>
              <Button
                type="button"
                disabled={disposeText !== "DISPOSE"}
                onClick={() => {
                  setError(null);
                  void disposeAsset(record.id, { disposition, confirm: true })
                    .then(async () => {
                      notify.success("Asset disposed", record.asset_tag);
                      setDisposeText("");
                      await refresh();
                    })
                    .catch((caught: unknown) => setError(getErrorMessage(caught)));
                }}
              >
                Confirm disposal
              </Button>
            </>
          ) : (
            <p className="text-sm text-muted-foreground">You do not have permission to dispose this asset.</p>
          )}
        </section>
      ) : null}
    </div>
  );
}

function HardwareList({
  title,
  empty,
  rows,
}: {
  title: string;
  empty: string;
  rows: Array<{ id: string; text: string }>;
}) {
  return (
    <section className="space-y-2 rounded-xl border border-border bg-card p-4 text-sm shadow-sm">
      <h2 className="text-sm font-medium">{title}</h2>
      {rows.length === 0 ? (
        <p className="text-muted-foreground">{empty}</p>
      ) : (
        <ul className="space-y-1">
          {rows.map((row) => (
            <li key={row.id} className="font-mono tabular-nums">
              {row.text}
            </li>
          ))}
        </ul>
      )}
    </section>
  );
}

function seedAssetDraft(record: AssetRecord): Record<string, string> {
  const draft: Record<string, string> = { asset_tag: record.asset_tag };
  const bag = record as unknown as Record<string, unknown>;
  for (const key of WRITABLE_SYSTEM) {
    const value = bag[key];
    draft[key] = value == null ? "" : String(value);
  }
  for (const [key, value] of Object.entries(record.custom_values ?? {})) {
    draft[key] = value == null ? "" : String(value);
  }
  return draft;
}

