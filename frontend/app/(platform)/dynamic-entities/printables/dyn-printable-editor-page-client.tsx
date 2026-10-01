"use client";

import Link from "next/link";
import { useCallback, useEffect, useMemo, useRef, useState, type ReactNode } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import { Check, ChevronDown, ExternalLink, Plus, Printer, RefreshCw } from "lucide-react";

import {
  DynPrintableWysiwyg,
  type DynPrintableWysiwygHandle,
} from "@/components/dynamic-entities/dyn-printable-wysiwyg";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import {
  fetchDynEntity,
  fetchDynRecords,
  updateDynEntity,
  type DynEntityDetail,
  type DynField,
  type DynPrintSettings,
} from "@/lib/api/modules/dynamic-entities-api";
import {
  defaultPrintableCss,
  defaultPrintableHtml,
} from "@/lib/dynamic-entities/dyn-print-template";
import {
  listPrintTemplates,
  packPrintSettingsJson,
  resolvePrintTemplate,
  templateDisplayName,
  type DynPrintTemplate,
} from "@/lib/dynamic-entities/dyn-print-templates";
import { permissions } from "@/lib/rbac/permissions";
import { cn } from "@/lib/utils";

const LAYOUT_OPTIONS = [
  { value: "transmittal", label: "Transmittal (e.g. Permit)" },
  { value: "agreement", label: "Agreement (e.g. Land Lease)" },
  { value: "status_report", label: "Status report" },
  { value: "grouped", label: "Grouped fields" },
  { value: "bir_2307", label: "BIR Form 2307" },
] as const;

const SYSTEM_DATE_TOKENS = [
  { token: "{{system.today}}", label: "Current Date" },
  { token: "{{system.next_year}}", label: "Next Year" },
  { token: "{{system.next_month}}", label: "Next Month" },
  { token: "{{system.now}}", label: "Date & time" },
] as const;

const SYSTEM_GENERAL_TOKENS = [
  { token: "{{system.company_logo}}", label: "COMPANY LOGO PATH" },
  { token: "{{system.company_name}}", label: "COMPANY NAME" },
  { token: "{{system.company_address}}", label: "COMPANY ADDRESS" },
  { token: "{{system.company_phone}}", label: "COMPANY CONTACT" },
  { token: "{{system.company_email}}", label: "COMPANY EMAIL" },
  { token: "{{system.company_tin}}", label: "COMPANY TIN" },
  { token: "{{system.prepared_by}}", label: "PREPARED BY" },
  { token: "{{system.approved_by}}", label: "APPROVED BY" },
  { token: "{{theme.accent}}", label: "THEME ACCENT" },
] as const;

type SideTab = "fields" | "images" | "bir";

function withTemplateDefaults(settings: DynPrintTemplate, entityName: string): DynPrintTemplate {
  return {
    ...settings,
    name: settings.name?.trim() || templateDisplayName(settings),
    document_title: settings.document_title?.trim() || entityName.toUpperCase(),
    orientation: settings.orientation === "landscape" ? "landscape" : "portrait",
    template_css: settings.template_css ?? "",
  };
}

function csvFromList(values: string[] | undefined): string {
  return (values ?? []).join(", ");
}

function listFromCsv(raw: string): string[] {
  return raw
    .split(",")
    .map((s) => s.trim())
    .filter(Boolean);
}

export function DynPrintableEditorPageClient() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const selectedSlug = searchParams.get("entity") ?? "";
  const templateIdParam = searchParams.get("template") ?? "";
  const wysiwygRef = useRef<DynPrintableWysiwygHandle | null>(null);

  const [detail, setDetail] = useState<DynEntityDetail | null>(null);
  const [allTemplates, setAllTemplates] = useState<DynPrintTemplate[]>([]);
  const [defaultTemplateId, setDefaultTemplateId] = useState<string | null>(null);
  const [settings, setSettings] = useState<DynPrintTemplate | null>(null);
  const [fieldFilter, setFieldFilter] = useState("");
  const [sideTab, setSideTab] = useState<SideTab>("fields");
  const [showSettings, setShowSettings] = useState(false);
  const [previewRecordId, setPreviewRecordId] = useState("");
  const [previewOptions, setPreviewOptions] = useState<Array<{ id: string; title: string }>>([]);
  const [inserted, setInserted] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);
  const [loadingDetail, setLoadingDetail] = useState(false);

  const loadDetail = useCallback(async (slug: string, preferredTemplateId: string) => {
    if (!slug) {
      setDetail(null);
      setSettings(null);
      setAllTemplates([]);
      return;
    }
    setLoadingDetail(true);
    setError(null);
    try {
      const entity = await fetchDynEntity(slug);
      setDetail(entity);
      const templates = listPrintTemplates(entity.print_settings, entity.name);
      setAllTemplates(templates);
      setDefaultTemplateId(entity.print_settings?.default_template_id ?? templates[0]?.id ?? null);
      const active = resolvePrintTemplate(entity.print_settings, preferredTemplateId || null, entity.name);
      setSettings(withTemplateDefaults(active, entity.name));
      if (preferredTemplateId && preferredTemplateId !== active.id) {
        router.replace(
          `/dynamic-entities/printables/edit?entity=${encodeURIComponent(slug)}&template=${encodeURIComponent(active.id)}`,
        );
      }
      const records = await fetchDynRecords(slug, { per_page: 20, page: 1 });
      setPreviewOptions(
        records.data.map((r) => ({
          id: r.id,
          title: r.title?.trim() || r.source_external_id || r.id.slice(0, 8),
        })),
      );
      setPreviewRecordId(records.data[0]?.id ?? "");
    } catch {
      setError("Unable to load printable settings.");
      setDetail(null);
      setSettings(null);
      setAllTemplates([]);
    } finally {
      setLoadingDetail(false);
    }
  }, [router]);

  useEffect(() => {
    void loadDetail(selectedSlug, templateIdParam);
  }, [loadDetail, selectedSlug, templateIdParam]);

  const dataFields = useMemo(() => {
    const fields = detail?.fields ?? [];
    return fields
      .filter((f) => !["actions", "workflows", "print"].includes(f.name))
      .sort((a, b) => a.field_order - b.field_order);
  }, [detail]);

  const filteredFields = useMemo(() => {
    const q = fieldFilter.trim().toLowerCase();
    if (!q) return dataFields;
    return dataFields.filter(
      (f) => f.label.toLowerCase().includes(q) || f.name.toLowerCase().includes(q),
    );
  }, [dataFields, fieldFilter]);

  const linkedFields = useMemo(
    () => dataFields.filter((f) => f.type === "relationship"),
    [dataFields],
  );

  function insertToken(token: string) {
    wysiwygRef.current?.insertToken(token);
    setInserted(token);
    window.setTimeout(() => setInserted(null), 1200);
  }

  function buildSavePayload() {
    if (!detail || !settings) return null;
    const merged = allTemplates.map((t) =>
      t.id === settings.id
        ? {
            ...settings,
            name: settings.name?.trim() || templateDisplayName(settings),
            list_label: settings.list_label?.trim() || settings.name || templateDisplayName(settings),
            updated_at: new Date().toISOString(),
          }
        : t,
    );
    if (!merged.some((t) => t.id === settings.id)) {
      merged.push({
        ...settings,
        updated_at: new Date().toISOString(),
      });
    }
    return packPrintSettingsJson(merged, defaultTemplateId ?? settings.id);
  }

  async function save() {
    if (!detail || !settings) return;
    const payload = buildSavePayload();
    if (!payload) return;
    setSaving(true);
    setError(null);
    try {
      const updated = await updateDynEntity(detail.slug, {
        print_settings_json: payload,
      });
      setDetail(updated);
      const templates = listPrintTemplates(updated.print_settings, updated.name);
      setAllTemplates(templates);
      setDefaultTemplateId(updated.print_settings?.default_template_id ?? settings.id);
      setSettings(
        withTemplateDefaults(
          resolvePrintTemplate(updated.print_settings, settings.id, updated.name),
          updated.name,
        ),
      );
    } catch {
      setError("Unable to save printable. Check you have Manage Printables permission.");
    } finally {
      setSaving(false);
    }
  }

  async function saveAndClose() {
    if (!detail || !settings) return;
    const payload = buildSavePayload();
    if (!payload) return;
    setSaving(true);
    setError(null);
    try {
      await updateDynEntity(detail.slug, {
        print_settings_json: payload,
      });
      router.push("/dynamic-entities/printables");
    } catch {
      setError("Unable to save printable. Check you have Manage Printables permission.");
      setSaving(false);
    }
  }

  function patchSettings(patch: Partial<DynPrintTemplate>) {
    setSettings((current) => (current ? { ...current, ...patch } : current));
  }

  function resetTemplate() {
    if (!settings || !detail) return;
    const title = settings.document_title?.trim() || detail.name.toUpperCase();
    patchSettings({
      template_html: defaultPrintableHtml(title),
      template_css: defaultPrintableCss(),
    });
  }

  const previewHref = previewRecordId
    ? `/dynamic-entities/records/${previewRecordId}/print${settings?.id ? `?template=${encodeURIComponent(settings.id)}` : ""}`
    : "/dynamic-entities/printables";

  return (
    <PermissionGate requiredPermissions={[permissions.printablesManage]}>
      <div className="space-y-3">
        <p className="text-sm text-muted-foreground">
          <Link href="/dynamic-entities/printables" className="underline-offset-4 hover:underline">
            Printables
          </Link>
          {" / Edit"}
        </p>

        {error ? <p className="text-sm text-destructive">{error}</p> : null}

        {!selectedSlug ? (
          <p className="text-sm text-muted-foreground">
            No entity selected.{" "}
            <Link href="/dynamic-entities/printables" className="underline-offset-4 hover:underline">
              Choose a template
            </Link>
            .
          </p>
        ) : loadingDetail || !settings || !detail ? (
          <p className="text-sm text-muted-foreground">Loading printable…</p>
        ) : (
          <section className="flex min-h-[75vh] flex-col overflow-hidden rounded-xl border border-border bg-card">
            {/* Metacoresoft-style top chrome */}
            <div className="flex flex-wrap items-center gap-2 border-b border-border px-3 py-2.5">
              <Printer className="size-4 shrink-0 text-muted-foreground" />
              <Input
                className="h-9 max-w-[14rem] font-medium"
                value={settings.name || settings.list_label}
                onChange={(e) =>
                  patchSettings({
                    name: e.target.value,
                    list_label: e.target.value,
                  })
                }
                aria-label="Printable name"
              />
              <span className="rounded-md border border-border bg-muted/40 px-2.5 py-1.5 text-xs text-muted-foreground">
                {detail.name}
              </span>
              {allTemplates.length > 1 ? (
                <Select
                  className="h-9 max-w-[12rem] rounded-md border border-input bg-background px-2 text-sm"
                  value={settings.id}
                  onChange={(e) => {
                    router.push(
                      `/dynamic-entities/printables/edit?entity=${encodeURIComponent(detail.slug)}&template=${encodeURIComponent(e.target.value)}`,
                    );
                  }}
                  aria-label="Switch template"
                >
                  {allTemplates.map((t) => (
                    <option key={t.id} value={t.id}>
                      {templateDisplayName(t)}
                    </option>
                  ))}
                </Select>
              ) : null}
              <Select
                className="h-9 rounded-md border border-input bg-background px-2 text-sm"
                value={settings.orientation === "landscape" ? "landscape" : "portrait"}
                onChange={(e) =>
                  patchSettings({ orientation: e.target.value === "landscape" ? "landscape" : "portrait" })
                }
                aria-label="Orientation"
              >
                <option value="portrait">Portrait</option>
                <option value="landscape">Landscape</option>
              </Select>
              <div className="ml-auto flex flex-wrap items-center gap-2">
                <Select
                  className="h-9 min-w-[10rem] rounded-md border border-input bg-background px-2 text-sm"
                  value={previewRecordId}
                  onChange={(e) => setPreviewRecordId(e.target.value)}
                  aria-label="Select record for preview"
                >
                  <option value="">Select Record…</option>
                  {previewOptions.map((r) => (
                    <option key={r.id} value={r.id}>
                      {r.title}
                    </option>
                  ))}
                </Select>
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  className="h-9 px-2"
                  onClick={() => void loadDetail(selectedSlug, templateIdParam)}
                  aria-label="Refresh records"
                >
                  <RefreshCw className="size-3.5" />
                </Button>
                {previewRecordId ? (
                  <Link
                    href={previewHref}
                    target="_blank"
                    rel="noreferrer"
                    className="inline-flex h-9 items-center gap-1.5 rounded-md border border-border bg-background px-3 text-xs font-medium hover:bg-muted"
                  >
                    <ExternalLink className="size-3.5" />
                    Open Preview
                  </Link>
                ) : (
                  <Button type="button" variant="outline" size="sm" className="h-9" disabled>
                    Open Preview
                  </Button>
                )}
              </div>
            </div>

            <div className="grid min-h-0 flex-1 lg:grid-cols-[280px_minmax(0,1fr)]">
              <aside className="flex min-h-0 flex-col border-r border-border">
                <div className="flex border-b border-border">
                  {(
                    [
                      ["fields", "Fields"],
                      ["images", "Images"],
                      ["bir", "BIR Forms"],
                    ] as const
                  ).map(([id, label]) => (
                    <button
                      key={id}
                      type="button"
                      onClick={() => setSideTab(id)}
                      className={cn(
                        "flex-1 px-2 py-2 text-xs font-medium",
                        sideTab === id
                          ? "border-b-2 border-sky-600 text-foreground"
                          : "text-muted-foreground hover:text-foreground",
                      )}
                    >
                      {label}
                    </button>
                  ))}
                </div>

                {sideTab === "fields" ? (
                  <TokenSidebar
                    fieldFilter={fieldFilter}
                    onFieldFilter={setFieldFilter}
                    fields={filteredFields}
                    linked={linkedFields}
                    inserted={inserted}
                    onInsert={insertToken}
                  />
                ) : null}

                {sideTab === "images" ? (
                  <div className="space-y-2 p-4 text-sm text-muted-foreground">
                    <p className="font-medium text-foreground">Images</p>
                    <p>
                      Insert company logo with{" "}
                      <button
                        type="button"
                        className="font-mono text-xs text-sky-700 underline-offset-2 hover:underline dark:text-sky-300"
                        onClick={() => insertToken("{{system.company_logo}}")}
                      >
                        {"{{system.company_logo}}"}
                      </button>
                      . Custom image library upload can be added later.
                    </p>
                  </div>
                ) : null}

                {sideTab === "bir" ? (
                  <div className="space-y-3 p-4 text-sm">
                    <p className="font-medium text-foreground">BIR form backgrounds</p>
                    <p className="text-muted-foreground">
                      Manage overlays in{" "}
                      <Link
                        href="/dynamic-entities/printables/pdf-forms"
                        className="font-medium text-sky-700 underline-offset-4 hover:underline dark:text-sky-300"
                      >
                        PDF Forms Manager
                      </Link>
                      .
                    </p>
                    <Button
                      type="button"
                      variant="outline"
                      size="sm"
                      onClick={() => patchSettings({ layout: "bir_2307" })}
                    >
                      Use BIR 2307 layout
                    </Button>
                  </div>
                ) : null}
              </aside>

              <div className="flex min-h-0 flex-col">
                <div className="flex flex-wrap items-center justify-between gap-2 border-b border-border px-3 py-1.5">
                  <p className="text-[11px] text-muted-foreground">
                    {settings.template_html?.trim()
                      ? <>Design / Styles / Source with {"{{tokens}}"}. Save before Open Preview.</>
                      : "No HTML template yet — Start from default, or paste Source + Styles from Metacoresoft."}
                  </p>
                  <div className="flex items-center gap-1">
                    <Button
                      type="button"
                      variant="ghost"
                      size="sm"
                      className="h-7 text-xs"
                      onClick={() => setShowSettings((v) => !v)}
                    >
                      Fallback settings
                      <ChevronDown className={cn("size-3.5 transition", showSettings && "rotate-180")} />
                    </Button>
                    <Button type="button" variant="ghost" size="sm" className="h-7 text-xs" onClick={resetTemplate}>
                      {settings.template_html?.trim() ? "Reset template" : "Start from default"}
                    </Button>
                  </div>
                </div>

                {showSettings ? (
                  <div className="max-h-64 overflow-y-auto border-b border-border p-3">
                    <DesignPanel
                      settings={settings}
                      onChange={patchSettings}
                      fieldNames={dataFields.map((f) => f.name)}
                    />
                  </div>
                ) : null}

                <DynPrintableWysiwyg
                  ref={wysiwygRef}
                  html={settings.template_html ?? ""}
                  css={settings.template_css ?? ""}
                  orientation={settings.orientation === "landscape" ? "landscape" : "portrait"}
                  onHtmlChange={(template_html) => patchSettings({ template_html })}
                  onCssChange={(template_css) => patchSettings({ template_css })}
                />
              </div>
            </div>

            <div className="flex flex-wrap items-center justify-end gap-2 border-t border-border px-3 py-2.5">
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() => router.push("/dynamic-entities/printables")}
              >
                Close
              </Button>
              <Button type="button" variant="outline" size="sm" disabled={saving} onClick={() => void save()}>
                {saving ? "Saving…" : "Save"}
              </Button>
              <Button type="button" size="sm" disabled={saving} onClick={() => void saveAndClose()}>
                Save & Close
              </Button>
            </div>
          </section>
        )}
      </div>
    </PermissionGate>
  );
}

function TokenSidebar({
  fieldFilter,
  onFieldFilter,
  fields,
  linked,
  inserted,
  onInsert,
}: {
  fieldFilter: string;
  onFieldFilter: (v: string) => void;
  fields: DynField[];
  linked: DynField[];
  inserted: string | null;
  onInsert: (token: string) => void;
}) {
  return (
    <div className="flex min-h-0 flex-1 flex-col">
      <div className="border-b border-border p-3">
        <Input
          className="h-9"
          placeholder="Search fields…"
          value={fieldFilter}
          onChange={(e) => onFieldFilter(e.target.value)}
        />
        <p className="mt-2 text-[11px] text-muted-foreground">Click a token to insert at the caret.</p>
      </div>
      <div className="min-h-0 flex-1 space-y-3 overflow-y-auto p-3">
        <TokenGroup title="SYSTEM DATES">
          {SYSTEM_DATE_TOKENS.map((row) => (
            <TokenRow
              key={row.token}
              label={row.label}
              token={row.token}
              active={inserted === row.token}
              onInsert={() => onInsert(row.token)}
            />
          ))}
        </TokenGroup>
        <TokenGroup title="SYSTEM GENERAL">
          {SYSTEM_GENERAL_TOKENS.map((row) => (
            <TokenRow
              key={row.token}
              label={row.label}
              token={row.token}
              active={inserted === row.token}
              onInsert={() => onInsert(row.token)}
            />
          ))}
        </TokenGroup>
        <TokenGroup title="FIELDS">
          {fields.length === 0 ? (
            <p className="px-1 text-xs text-muted-foreground">No fields.</p>
          ) : (
            fields.map((f) => {
              const token = `{{record.${f.name}}}`;
              return (
                <TokenRow
                  key={f.id}
                  label={f.label.toUpperCase()}
                  token={token}
                  active={inserted === token}
                  onInsert={() => onInsert(token)}
                />
              );
            })
          )}
        </TokenGroup>
        {linked.length > 0 ? (
          <TokenGroup title="LINKED RECORDS">
            {linked.map((f) => {
              const token = `{{record.${f.name}.title}}`;
              return (
                <TokenRow
                  key={f.id}
                  label={`${f.label} → ${f.target_entity_slug ?? "entity"}`}
                  token={token}
                  active={inserted === token}
                  onInsert={() => onInsert(token)}
                />
              );
            })}
          </TokenGroup>
        ) : null}
        <TokenGroup title="LINE ITEMS">
          <TokenRow
            label="LOOP INDEX (#)"
            token="{{loop.index}}"
            active={inserted === "{{loop.index}}"}
            onInsert={() => onInsert("{{loop.index}}")}
          />
          <TokenRow
            label="ITEM FIELD (IN TABLE ROW)"
            token="{{item.field_name}}"
            active={inserted === "{{item.field_name}}"}
            onInsert={() => onInsert("{{item.field_name}}")}
          />
          <p className="px-1 text-[10px] text-muted-foreground">
            Put {"{{loop.index}}"} / {"{{item.*}}"} inside a table row; print expands one row per line
            item.
          </p>
        </TokenGroup>
      </div>
    </div>
  );
}

function TokenGroup({ title, children }: { title: string; children: ReactNode }) {
  return (
    <div className="space-y-1">
      <h3 className="px-1 text-[11px] font-medium tracking-wide text-muted-foreground">{title}</h3>
      <div className="space-y-0.5">{children}</div>
    </div>
  );
}

function TokenRow({
  label,
  token,
  active,
  onInsert,
}: {
  label: string;
  token: string;
  active: boolean;
  onInsert: () => void;
}) {
  return (
    <button
      type="button"
      onClick={onInsert}
      className={cn(
        "flex w-full items-start gap-2 rounded-md px-2 py-1.5 text-left hover:bg-muted/70",
        active && "bg-sky-50 dark:bg-sky-950/40",
      )}
      title={`Insert ${token}`}
    >
      <span className="min-w-0 flex-1">
        <span className="block truncate text-xs font-medium text-foreground">{label}</span>
        <span className="block truncate font-mono text-[10px] text-muted-foreground">{token}</span>
      </span>
      {active ? (
        <Check className="mt-0.5 size-3.5 shrink-0 text-emerald-600" />
      ) : (
        <Plus className="mt-0.5 size-3.5 shrink-0 text-muted-foreground" />
      )}
    </button>
  );
}

function DesignPanel({
  settings,
  onChange,
  fieldNames,
}: {
  settings: DynPrintSettings;
  onChange: (patch: Partial<DynPrintSettings>) => void;
  fieldNames: string[];
}) {
  return (
    <div className="mx-auto max-w-3xl space-y-4">
      <div className="grid gap-3 sm:grid-cols-2">
        <label className="space-y-1.5 sm:col-span-2">
          <span className="text-xs font-medium text-muted-foreground">Document title</span>
          <Input
            value={settings.document_title}
            onChange={(e) => onChange({ document_title: e.target.value })}
          />
        </label>
        <label className="space-y-1.5">
          <span className="text-xs font-medium text-muted-foreground">Layout preset (fallback)</span>
          <Select
            className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
            value={settings.layout}
            onChange={(e) => onChange({ layout: e.target.value })}
          >
            {LAYOUT_OPTIONS.map((opt) => (
              <option key={opt.value} value={opt.value}>
                {opt.label}
              </option>
            ))}
            {!LAYOUT_OPTIONS.some((o) => o.value === settings.layout) ? (
              <option value={settings.layout}>{settings.layout}</option>
            ) : null}
          </Select>
        </label>
        <label className="space-y-1.5">
          <span className="text-xs font-medium text-muted-foreground">Signature style</span>
          <Select
            className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
            value={settings.signature_style ?? "prepared"}
            onChange={(e) => onChange({ signature_style: e.target.value })}
          >
            <option value="prepared">Prepared / Approved</option>
            <option value="parties">Parties</option>
            <option value="none">None</option>
          </Select>
        </label>
        <label className="block space-y-1.5 sm:col-span-2">
          <span className="text-xs font-medium text-muted-foreground">Subtitle fields</span>
          <Input
            value={csvFromList(settings.subtitle_fields)}
            onChange={(e) => onChange({ subtitle_fields: listFromCsv(e.target.value) })}
            placeholder="permit_number, permit_type"
          />
          <span className="text-[10px] text-muted-foreground">
            Available: {fieldNames.slice(0, 8).join(", ")}
            {fieldNames.length > 8 ? "…" : ""}
          </span>
        </label>
      </div>
    </div>
  );
}
