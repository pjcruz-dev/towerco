"use client";

import Link from "next/link";
import { useSearchParams } from "next/navigation";
import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { ArrowLeft, ChevronLeft, ChevronRight, LayoutGrid, Printer } from "lucide-react";

import {
  DynRelationshipPicker,
  DynRelationshipView,
} from "@/components/dynamic-entities/dyn-relationship-field";
import { DynRelatedRecordsPanel } from "@/components/dynamic-entities/dyn-related-records-panel";
import {
  DynSchemaSheets,
  type DynSchemaMode,
} from "@/components/dynamic-entities/dyn-schema-sheets";
import { DynStructuredLayout } from "@/components/dynamic-entities/dyn-structured-layout";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { SelectField } from "@/components/ui/select-field";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import { DatePicker } from "@/components/ui/date-picker";
import { Textarea } from "@/components/ui/textarea";
import { usePermission } from "@/hooks/use-permission";
import {
  fetchDynRecord,
  fetchDynRecords,
  runDynWorkflowAction,
  updateDynRecord,
  type DynField,
  type DynRecordDetail,
} from "@/lib/api/modules/dynamic-entities-api";
import { getErrorMessage } from "@/lib/api/error";
import { permissions } from "@/lib/rbac/permissions";
import {
  canEntityAction,
  canWorkflowAction,
  isFieldHiddenForRole,
  isFieldReadOnlyForRole,
  notifyDynPermissionDenied,
} from "@/lib/rbac/entity-access";
import { dynWorkflowActionsForStatus } from "@/lib/dynamic-entities/dyn-workflow-actions";
import {
  dynSelectChoiceBadgeClass,
  dynSelectChoiceLabel,
  parseDynSelectOptions,
} from "@/lib/dynamic-entities/select-choices";
import { dynStatusTone } from "@/lib/dynamic-entities/dyn-list-cell-format";
import {
  listPrintTemplates,
  templateDisplayName,
} from "@/lib/dynamic-entities/dyn-print-templates";
import { useAuthStore } from "@/stores/auth-store";
import { useNotificationStore } from "@/stores/notification-store";
import { cn } from "@/lib/utils";

type RelatedTab = {
  entity_slug?: string;
  foreign_field?: string;
  label?: string;
};

export function DynamicRecordDetailPageClient({ recordId }: { recordId: string }) {
  const searchParams = useSearchParams();
  const editMode = searchParams.get("edit") === "1";
  const canManageRecords = usePermission([permissions.dynamicEntitiesRecordsManage]);
  const canManageSchema = usePermission([permissions.dynamicEntitiesFieldsManage]);
  const canManagePrintables = usePermission([permissions.printablesManage]);
  const accessMatrix = useAuthStore((state) => state.user?.accessMatrix);
  const notify = useNotificationStore((state) => state.push);
  const [record, setRecord] = useState<DynRecordDetail | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [draft, setDraft] = useState<Record<string, string>>({});
  const [status, setStatus] = useState("");
  const [saving, setSaving] = useState(false);
  const [activeTab, setActiveTab] = useState<string>("fields");
  const [tabCounts, setTabCounts] = useState<Record<string, number>>({});
  const [schemaMode, setSchemaMode] = useState<DynSchemaMode>({ kind: "closed" });
  const [layoutEditing, setLayoutEditing] = useState(false);
  const [workflowBusy, setWorkflowBusy] = useState<string | null>(null);
  const tabsRef = useRef<HTMLDivElement | null>(null);

  const entitySlug = record?.entity.slug ?? "";
  const canEdit =
    canManageRecords && (!entitySlug || canEntityAction(accessMatrix, entitySlug, "edit"));
  const isEditing = editMode && canEdit;

  useEffect(() => {
    if (editMode && record && !canEdit) {
      notifyDynPermissionDenied({
        action: "edit",
        entityLabel: record.entity.name,
      });
    }
  }, [editMode, canEdit, record]);

  async function reloadRecord() {
    const row = await fetchDynRecord(recordId);
    setRecord(row);
    setStatus(row.status ?? "");
    const next: Record<string, string> = {};
    for (const [k, v] of Object.entries(row.values ?? {})) {
      next[k] = v === null || v === undefined ? "" : String(v);
    }
    setDraft(next);
    return row;
  }

  useEffect(() => {
    let cancelled = false;
    fetchDynRecord(recordId)
      .then((row) => {
        if (cancelled) return;
        setRecord(row);
        setStatus(row.status ?? "");
        const next: Record<string, string> = {};
        for (const [k, v] of Object.entries(row.values ?? {})) {
          next[k] = v === null || v === undefined ? "" : String(v);
        }
        setDraft(next);
        setError(null);
      })
      .catch((err) => {
        if (!cancelled) {
          notifyDynPermissionDenied({ action: "view", entityLabel: "this record", error: err });
          setError(null);
        }
      });
    return () => {
      cancelled = true;
    };
  }, [recordId]);

  const relatedTabs = useMemo(() => {
    const tabs = (record?.entity.related_tabs ?? []) as RelatedTab[];
    return tabs.filter(
      (t) =>
        t.entity_slug &&
        t.label &&
        canEntityAction(accessMatrix, t.entity_slug, "view"),
    );
  }, [record, accessMatrix]);

  const activeRelated = relatedTabs.find((t) => t.entity_slug === activeTab);

  useEffect(() => {
    if (!record || relatedTabs.length === 0) {
      setTabCounts({});
      return;
    }
    let cancelled = false;
    void Promise.all(
      relatedTabs.map(async (tab) => {
        try {
          const page = await fetchDynRecords(tab.entity_slug!, {
            per_page: 1,
            parent_record_id: record.id,
            foreign_field: tab.foreign_field || undefined,
          });
          return [tab.entity_slug!, page.meta.total] as const;
        } catch {
          return [tab.entity_slug!, 0] as const;
        }
      }),
    ).then((pairs) => {
      if (cancelled) return;
      const next: Record<string, number> = {};
      for (const [slug, count] of pairs) next[slug] = count;
      setTabCounts(next);
    });
    return () => {
      cancelled = true;
    };
  }, [record, relatedTabs]);

  const scrollTabs = useCallback((dir: -1 | 1) => {
    tabsRef.current?.scrollBy({ left: dir * 220, behavior: "smooth" });
  }, []);

  const groups = useMemo(
    () =>
      [...(record?.entity.field_groups ?? [])]
        .filter((g) => {
          // View mirrors form groups so the whole group shows (not a sparse view-only subset).
          if (isEditing || layoutEditing) return g.applies_to_form;
          return g.applies_to_view || g.applies_to_form;
        })
        .sort((a, b) => a.sort_order - b.sort_order),
    [record, isEditing, layoutEditing],
  );
  const fields = useMemo(
    () =>
      [...(record?.entity.fields ?? [])].filter(
        (f) =>
          !f.is_system_field &&
          !["actions", "workflows", "print", "id"].includes(f.name) &&
          !isFieldHiddenForRole(accessMatrix, entitySlug, f.name),
      ),
    [record, accessMatrix, entitySlug],
  );

  const subtitle = useMemo(() => {
    if (!record) return null;
    const site = record.resolved_relations?.tower_site_id?.title;
    if (site) return site;
    const overall = record.values.overall_status ?? record.values.status;
    return overall != null && overall !== "" ? String(overall) : null;
  }, [record]);

  const workflowActions = useMemo(() => {
    if (!record || !canEdit) return [];
    const fromApi = record.workflow_actions;
    const list =
      fromApi && fromApi.length > 0
        ? fromApi
        : dynWorkflowActionsForStatus(
            record.entity.slug,
            record.status,
            record.entity.fields,
            record.values,
          );
    return list.filter((a) => canWorkflowAction(accessMatrix, record.entity.slug, a.id));
  }, [record, canEdit, accessMatrix]);

  async function onWorkflowAction(actionId: string, label: string, confirmMsg?: string | null) {
    if (!record) return;
    if (confirmMsg && !window.confirm(confirmMsg)) return;
    setWorkflowBusy(actionId);
    setError(null);
    try {
      const updated = await runDynWorkflowAction(record.id, actionId);
      setRecord(updated);
      setStatus(updated.status ?? "");
      const next: Record<string, string> = {};
      for (const [k, v] of Object.entries(updated.values ?? {})) {
        next[k] = v === null || v === undefined ? "" : String(v);
      }
      setDraft(next);
      notify({
        level: "success",
        title: label,
        message: `Status is now ${updated.status ?? "—"}.`,
      });
    } catch (err: unknown) {
      notifyDynPermissionDenied({
        action: "edit",
        entityLabel: record.entity.name,
        error: err,
      });
      const msg = getErrorMessage(err);
      if (!/does not allow|access denied|forbidden/i.test(msg)) {
        setError(msg);
      }
    } finally {
      setWorkflowBusy(null);
    }
  }

  async function onSave() {
    if (!record) return;
    if (!canEdit) {
      notifyDynPermissionDenied({
        action: "edit",
        entityLabel: record.entity.name,
      });
      return;
    }
    setSaving(true);
    try {
      const values: Record<string, unknown> = {};
      for (const field of fields) {
        if (isFieldReadOnlyForRole(accessMatrix, entitySlug, field.name)) {
          continue;
        }
        const raw = draft[field.name];
        if (raw === undefined) continue;
        if (raw === "") {
          // Skip empty required fields so older records aren't blocked by new blank fields.
          if (field.is_required) continue;
          values[field.name] = null;
          continue;
        }
        if (field.type === "number" || field.type === "decimal") values[field.name] = Number(raw);
        else if (field.type === "boolean") values[field.name] = raw === "true" || raw === "1";
        else values[field.name] = raw;
      }
      const updated = await updateDynRecord(record.id, { status: status || undefined, values });
      setRecord(updated);
      setError(null);
      notify({
        level: "success",
        title: "Record saved",
        message: `${record.entity.name} was updated.`,
      });
    } catch (err: unknown) {
      notifyDynPermissionDenied({
        action: "edit",
        entityLabel: record.entity.name,
        error: err,
      });
      setError(null);
      const axiosErr = err as { response?: { data?: { errors?: Record<string, string[]> } } };
      const errors = axiosErr.response?.data?.errors;
      if (errors && typeof errors === "object") {
        const first = Object.values(errors).flat()[0];
        if (typeof first === "string") {
          setError(first);
        }
      } else {
        const msg = getErrorMessage(err);
        if (!/does not allow|access denied|forbidden/i.test(msg)) {
          setError(msg);
        }
      }
    } finally {
      setSaving(false);
    }
  }

  function renderViewField(field: DynField) {
    if (field.type === "relationship") {
      return (
        <DynRelationshipView
          field={field}
          raw={record?.values[field.name]}
          resolved={record?.resolved_relations?.[field.name] ?? null}
        />
      );
    }
    if (
      field.type === "select" ||
      field.name.includes("status") ||
      field.name.includes("milestone")
    ) {
      return <StatusValue value={record?.values[field.name]} field={field} />;
    }
    return formatValue(record?.values[field.name]);
  }

  function renderEditField(field: DynField) {
    if (isFieldReadOnlyForRole(accessMatrix, entitySlug, field.name)) {
      return renderViewField(field);
    }
    if (field.type === "automatic_id") {
      return (
        <Input
          value={draft[field.name] ?? ""}
          readOnly
          disabled
          className="bg-muted/40 text-muted-foreground"
        />
      );
    }
    if (field.type === "relationship") {
      return (
        <DynRelationshipPicker
          field={field}
          value={draft[field.name] ?? ""}
          onChange={(next) => setDraft((d) => ({ ...d, [field.name]: next }))}
          required={field.is_required}
        />
      );
    }
    if (field.type === "select" || field.type === "multiselect") {
      const choices = parseDynSelectOptions(field.options).choices;
      return (
        <SelectField
          value={draft[field.name] ?? ""}
          onChange={(next) => setDraft((d) => ({ ...d, [field.name]: next }))}
          options={choices.map((c) => ({ value: c.value, label: c.label }))}
          allowEmpty={!field.is_required}
          placeholder="Select…"
        />
      );
    }
    if (field.type === "textarea") {
      return (
        <Textarea
          className="min-h-20"
          value={draft[field.name] ?? ""}
          onChange={(e) => setDraft((d) => ({ ...d, [field.name]: e.target.value }))}
          required={field.is_required}
        />
      );
    }
    if (field.type === "boolean") {
      return (
        <label className="inline-flex h-9 items-center gap-2 text-sm">
          <Checkbox
            checked={draft[field.name] === "true" || draft[field.name] === "1"}
            onCheckedChange={(checked) =>
              setDraft((d) => ({ ...d, [field.name]: checked === true ? "true" : "false" }))
            }
          />
          <span className="text-muted-foreground">
            {draft[field.name] === "true" || draft[field.name] === "1" ? "Yes" : "No"}
          </span>
        </label>
      );
    }
    if (field.type === "date") {
      return (
        <DatePicker
          value={draft[field.name] ?? ""}
          onChange={(next) => setDraft((d) => ({ ...d, [field.name]: next }))}
        />
      );
    }
    return (
      <Input
        type={
          field.type === "number" || field.type === "decimal"
            ? "number"
            : field.type === "datetime"
              ? "datetime-local"
              : "text"
        }
        value={draft[field.name] ?? ""}
        onChange={(e) => setDraft((d) => ({ ...d, [field.name]: e.target.value }))}
        required={field.is_required}
      />
    );
  }

  const listHref = record ? `/dynamic-entities/${record.entity.slug}` : "/dynamic-entities";

  return (
    <PermissionGate requiredPermissions={[permissions.dynamicEntitiesView]}>
      <div className="w-full space-y-5">
        <header className="flex flex-wrap items-start justify-between gap-3">
          <div className="min-w-0">
            <h1 className="text-2xl font-semibold tracking-tight text-foreground">
              {record?.title ?? "Record"}
            </h1>
            {subtitle ? <p className="mt-1 text-sm text-muted-foreground">{subtitle}</p> : null}
          </div>
          <div className="flex flex-wrap gap-2">
            <Button size="sm" variant="outline" render={<Link href={listHref} />}>
              <ArrowLeft className="size-3.5" />
              Back
            </Button>
            {record && canManagePrintables ? (
              <div className="flex flex-wrap gap-2">
                {listPrintTemplates(record.entity.print_settings, record.entity.name).map((t) => {
                  const label = templateDisplayName(t);
                  return (
                    <Button
                      key={t.id}
                      size="sm"
                      variant="outline"
                      render={
                        <Link
                          href={`/dynamic-entities/records/${recordId}/print?template=${encodeURIComponent(t.id)}`}
                          target="_blank"
                        />
                      }
                    >
                      <Printer className="size-3.5" />
                      {label}
                    </Button>
                  );
                })}
              </div>
            ) : null}
            {workflowActions.map((action) => (
              <Button
                key={action.id}
                size="sm"
                variant={
                  action.variant === "outline"
                    ? "outline"
                    : action.variant === "destructive"
                      ? "destructive"
                      : "default"
                }
                disabled={workflowBusy !== null}
                title={
                  action.from_status
                    ? `Requires status: ${action.from_status}`
                    : undefined
                }
                onClick={() =>
                  void onWorkflowAction(action.id, action.label, action.confirm)
                }
              >
                {workflowBusy === action.id ? "Working…" : action.label}
              </Button>
            ))}
            {canManageSchema && !isEditing ? (
              <Button
                size="sm"
                variant={layoutEditing ? "default" : "outline"}
                onClick={() => setLayoutEditing((v) => !v)}
              >
                <LayoutGrid className="size-3.5" />
                {layoutEditing ? "Done layout" : "Customize"}
              </Button>
            ) : null}
            {isEditing ? (
              <Button size="sm" disabled={saving} onClick={() => void onSave()}>
                {saving ? "Saving…" : "Save"}
              </Button>
            ) : canEdit ? (
              <Button
                size="sm"
                render={<Link href={`/dynamic-entities/records/${recordId}?edit=1`} />}
              >
                Edit
              </Button>
            ) : null}
          </div>
        </header>

        {error ? <p className="text-sm text-destructive">{error}</p> : null}

        <div className="flex items-center gap-1 border-b border-border">
          <Button
            type="button"
            size="sm"
            variant="ghost"
            className="h-8 w-8 shrink-0 px-0"
            onClick={() => scrollTabs(-1)}
            aria-label="Scroll tabs left"
          >
            <ChevronLeft className="size-4" />
          </Button>
          <div
            ref={tabsRef}
            className="flex min-w-0 flex-1 flex-nowrap gap-1 overflow-x-auto pb-px [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
          >
            <TabButton active={activeTab === "fields"} onClick={() => setActiveTab("fields")}>
              Details
            </TabButton>
            {relatedTabs.map((tab) => {
              const count = tabCounts[tab.entity_slug!] ?? null;
              return (
                <TabButton
                  key={tab.entity_slug}
                  active={activeTab === tab.entity_slug}
                  onClick={() => setActiveTab(tab.entity_slug!)}
                >
                  {tab.label}
                  {count !== null ? (
                    <span
                      className={cn(
                        "ml-1.5 rounded-full px-1.5 py-0.5 text-[10px] font-medium",
                        activeTab === tab.entity_slug
                          ? "bg-primary/15 text-foreground"
                          : "bg-muted text-muted-foreground",
                      )}
                    >
                      {count}
                    </span>
                  ) : null}
                </TabButton>
              );
            })}
          </div>
          <Button
            type="button"
            size="sm"
            variant="ghost"
            className="h-8 w-8 shrink-0 px-0"
            onClick={() => scrollTabs(1)}
            aria-label="Scroll tabs right"
          >
            <ChevronRight className="size-4" />
          </Button>
        </div>

        <div className="grid gap-5 xl:grid-cols-[minmax(0,1fr)_280px]">
          <div className="min-w-0 space-y-4">
            {activeTab === "fields" && record ? (
              <>
                {isEditing ? (
                  <Card className="rounded-xl">
                    <CardHeader>
                      <CardTitle className="text-base font-medium">Status</CardTitle>
                    </CardHeader>
                    <CardContent>
                      <Input value={status} onChange={(e) => setStatus(e.target.value)} />
                    </CardContent>
                  </Card>
                ) : null}

                <DynStructuredLayout
                  groups={groups}
                  fields={fields}
                  canManageSchema={canManageSchema}
                  layoutEditing={isEditing || layoutEditing}
                  mode={isEditing || layoutEditing ? "form" : "view"}
                  renderFieldValue={isEditing ? renderEditField : renderViewField}
                  onSchemaAction={setSchemaMode}
                  onLayoutChanged={async () => {
                    await reloadRecord();
                  }}
                />

                <DynSchemaSheets
                  entitySlug={record.entity.slug}
                  entityName={record.entity.name}
                  groups={record.entity.field_groups}
                  fields={record.entity.fields}
                  mode={schemaMode}
                  onClose={() => setSchemaMode({ kind: "closed" })}
                  onSaved={async () => {
                    await reloadRecord();
                  }}
                />
              </>
            ) : null}

            {activeTab !== "fields" && record && activeRelated?.entity_slug ? (
              <DynRelatedRecordsPanel
                parentRecordId={record.id}
                entitySlug={activeRelated.entity_slug}
                label={activeRelated.label || activeRelated.entity_slug}
                foreignField={activeRelated.foreign_field}
                canCreate={
                  canManageRecords &&
                  canEntityAction(accessMatrix, activeRelated.entity_slug, "create")
                }
                canEdit={
                  canManageRecords &&
                  canEntityAction(accessMatrix, activeRelated.entity_slug, "edit")
                }
                canDelete={
                  canManageRecords &&
                  canEntityAction(accessMatrix, activeRelated.entity_slug, "delete")
                }
                returnTo={`/dynamic-entities/records/${record.id}`}
                onCountChange={(count) =>
                  setTabCounts((prev) => ({
                    ...prev,
                    [activeRelated.entity_slug!]: count,
                  }))
                }
              />
            ) : null}
          </div>

          {record && !isEditing ? (
            <aside className="xl:sticky xl:top-6 xl:self-start">
              <HistoryAuditPanel record={record} />
            </aside>
          ) : null}
        </div>
      </div>
    </PermissionGate>
  );
}

function HistoryAuditPanel({ record }: { record: DynRecordDetail }) {
  return (
    <Card className="rounded-xl">
      <CardHeader className="pb-3">
        <CardTitle className="text-base font-medium">History & Audit</CardTitle>
      </CardHeader>
      <CardContent>
        <ol className="relative space-y-5 border-l border-border pl-4">
          <li className="relative">
            <span className="absolute -left-[1.3rem] top-1 size-2.5 rounded-full bg-emerald-500 ring-4 ring-background" />
            <p className="text-sm font-medium text-foreground">Created record</p>
            <p className="mt-0.5 text-xs text-muted-foreground">
              {record.created_by_name || "System"}
            </p>
            <p className="text-xs text-muted-foreground">
              {record.created_at ? new Date(record.created_at).toLocaleString() : "N/A"}
            </p>
          </li>
          <li className="relative">
            <span className="absolute -left-[1.3rem] top-1 size-2.5 rounded-full bg-sky-500 ring-4 ring-background" />
            <p className="text-sm font-medium text-foreground">Last updated</p>
            <p className="mt-0.5 text-xs text-muted-foreground">
              {record.updated_by_name || record.created_by_name || "System"}
            </p>
            <p className="text-xs text-muted-foreground">
              {record.updated_at ? new Date(record.updated_at).toLocaleString() : "N/A"}
            </p>
          </li>
        </ol>
      </CardContent>
    </Card>
  );
}

function TabButton({
  active,
  onClick,
  children,
}: {
  active: boolean;
  onClick: () => void;
  children: React.ReactNode;
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      className={
        active
          ? "-mb-px inline-flex shrink-0 items-center border-b-2 border-primary px-3 py-2 text-sm font-medium whitespace-nowrap text-foreground"
          : "inline-flex shrink-0 items-center px-3 py-2 text-sm font-medium whitespace-nowrap text-muted-foreground hover:text-foreground"
      }
    >
      {children}
    </button>
  );
}

function formatValue(value: unknown): string {
  if (value === null || value === undefined || value === "") return "N/A";
  if (typeof value === "object") return JSON.stringify(value);
  return String(value);
}

function StatusValue({ value, field }: { value: unknown; field?: DynField }) {
  const text = formatValue(value);
  if (text === "N/A") return <span className="text-muted-foreground">N/A</span>;
  const label = field ? dynSelectChoiceLabel(field.options, text) : text;
  const badgeClass = field ? dynSelectChoiceBadgeClass(field.options, text) : null;
  return (
    <span
      className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-sm font-medium ${
        badgeClass ?? dynStatusTone(text)
      }`}
    >
      {label}
    </span>
  );
}
