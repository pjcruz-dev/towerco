import type { DynPrintSettings } from "@/lib/api/modules/dynamic-entities-api";

/** One named printable under an entity (Metacoresoft multi-template model). */
export type DynPrintTemplate = DynPrintSettings & {
  id: string;
  name: string;
  updated_at?: string | null;
};

export function newPrintTemplateId(): string {
  if (typeof crypto !== "undefined" && typeof crypto.randomUUID === "function") {
    return crypto.randomUUID();
  }
  return `tpl-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 10)}`;
}

export function templateDisplayName(t: Pick<DynPrintTemplate, "name" | "list_label" | "document_title">): string {
  const name = t.name?.trim();
  if (name) return name;
  const label = t.list_label?.trim();
  if (label && label !== "Print") return label;
  const doc = t.document_title?.trim();
  if (doc) return doc;
  return "Print Template";
}

/** Resolve templates[] from API print_settings (migrates legacy flat blob). */
export function listPrintTemplates(
  settings: DynPrintSettings | null | undefined,
  entityName = "Print",
): DynPrintTemplate[] {
  if (!settings) {
    return [createEmptyPrintTemplate(entityName)];
  }

  const raw = settings.templates;
  if (Array.isArray(raw) && raw.length > 0) {
    return raw.map((row, index) => normalizeTemplateRow(row, settings, entityName, index));
  }

  // Legacy: flat fields only
  return [
    normalizeTemplateRow(
      {
        ...settings,
        id: settings.default_template_id || `legacy-${entityName}`,
        name: templateDisplayName(settings as DynPrintTemplate),
      },
      settings,
      entityName,
      0,
    ),
  ];
}

export function resolvePrintTemplate(
  settings: DynPrintSettings | null | undefined,
  templateId?: string | null,
  entityName = "Print",
): DynPrintTemplate {
  const templates = listPrintTemplates(settings, entityName);
  if (templateId) {
    const match = templates.find((t) => t.id === templateId);
    if (match) return match;
  }
  const defaultId = settings?.default_template_id;
  if (defaultId) {
    const match = templates.find((t) => t.id === defaultId);
    if (match) return match;
  }
  return templates[0]!;
}

export function createEmptyPrintTemplate(entityName: string, name?: string): DynPrintTemplate {
  const documentTitle = entityName.toUpperCase();
  const label = name?.trim() || `${entityName} Template`;
  return {
    id: newPrintTemplateId(),
    name: label,
    list_label: label,
    document_title: documentTitle,
    layout: "grouped",
    status_stamp: true,
    signatures: true,
    signature_style: "prepared",
    company_name: null,
    company_address: null,
    subtitle_fields: [],
    subtitle_prefix: null,
    title_align: "left",
    highlight_fields: [],
    currency_fields: [],
    terms_paragraphs: [],
    related_expand: [],
    workflow_stages: [],
    milestone_fields: [],
    remarks_field: null,
    show_site_assignment: false,
    department: null,
    orientation: "portrait",
    template_html: null,
    template_css: null,
    updated_at: new Date().toISOString(),
  };
}

export function clonePrintTemplate(source: DynPrintTemplate, nameSuffix = "(Copy)"): DynPrintTemplate {
  const name = `${templateDisplayName(source)} ${nameSuffix}`.trim();
  return {
    ...source,
    id: newPrintTemplateId(),
    name,
    list_label: name,
    updated_at: new Date().toISOString(),
  };
}

/**
 * Pack templates for API save. Flattens default template fields for backward-compatible readers.
 */
export function packPrintSettingsJson(
  templates: DynPrintTemplate[],
  defaultTemplateId?: string | null,
): DynPrintSettings {
  const list = templates.length > 0 ? templates : [createEmptyPrintTemplate("Print")];
  const defaultId =
    (defaultTemplateId && list.some((t) => t.id === defaultTemplateId) && defaultTemplateId) ||
    list[0]!.id;
  const active = list.find((t) => t.id === defaultId) ?? list[0]!;
  const { templates: _ignored, default_template_id: _d, ...flat } = active as DynPrintSettings & {
    templates?: DynPrintTemplate[];
    default_template_id?: string | null;
  };

  return {
    ...flat,
    id: active.id,
    name: active.name,
    updated_at: active.updated_at ?? new Date().toISOString(),
    templates: list.map((t) => {
      const { templates: _t, default_template_id: _id, ...row } = t as DynPrintSettings & {
        templates?: DynPrintTemplate[];
        default_template_id?: string | null;
      };
      return {
        ...row,
        id: t.id,
        name: t.name,
        updated_at: t.updated_at ?? null,
      };
    }),
    default_template_id: defaultId,
  } as DynPrintSettings;
}

function normalizeTemplateRow(
  row: Partial<DynPrintTemplate> & Record<string, unknown>,
  fallback: DynPrintSettings,
  entityName: string,
  index: number,
): DynPrintTemplate {
  const id =
    (typeof row.id === "string" && row.id) ||
    (typeof fallback.default_template_id === "string" && index === 0 && fallback.default_template_id) ||
    newPrintTemplateId();
  const base: DynPrintTemplate = {
    id,
    name:
      (typeof row.name === "string" && row.name.trim()) ||
      templateDisplayName({
        name: "",
        list_label: String(row.list_label ?? fallback.list_label ?? ""),
        document_title: String(row.document_title ?? fallback.document_title ?? ""),
      }) ||
      `${entityName} Template`,
    list_label: String(row.list_label ?? fallback.list_label ?? "Print"),
    document_title: String(row.document_title ?? fallback.document_title ?? entityName.toUpperCase()),
    layout: String(row.layout ?? fallback.layout ?? "grouped"),
    status_stamp: Boolean(row.status_stamp ?? fallback.status_stamp ?? true),
    signatures: Boolean(row.signatures ?? fallback.signatures ?? true),
    signature_style: String(row.signature_style ?? fallback.signature_style ?? "prepared"),
    company_name: (row.company_name as string | null | undefined) ?? fallback.company_name ?? null,
    company_address: (row.company_address as string | null | undefined) ?? fallback.company_address ?? null,
    subtitle_fields: Array.isArray(row.subtitle_fields)
      ? (row.subtitle_fields as string[])
      : (fallback.subtitle_fields ?? []),
    subtitle_prefix: (row.subtitle_prefix as string | null | undefined) ?? fallback.subtitle_prefix ?? null,
    title_align: String(row.title_align ?? fallback.title_align ?? "left"),
    highlight_fields: Array.isArray(row.highlight_fields)
      ? (row.highlight_fields as string[])
      : (fallback.highlight_fields ?? []),
    currency_fields: Array.isArray(row.currency_fields)
      ? (row.currency_fields as string[])
      : (fallback.currency_fields ?? []),
    terms_paragraphs: Array.isArray(row.terms_paragraphs)
      ? (row.terms_paragraphs as string[])
      : (fallback.terms_paragraphs ?? []),
    related_expand: Array.isArray(row.related_expand)
      ? (row.related_expand as DynPrintSettings["related_expand"])
      : (fallback.related_expand ?? []),
    workflow_stages: Array.isArray(row.workflow_stages)
      ? (row.workflow_stages as DynPrintSettings["workflow_stages"])
      : (fallback.workflow_stages ?? []),
    milestone_fields: Array.isArray(row.milestone_fields)
      ? (row.milestone_fields as string[])
      : (fallback.milestone_fields ?? []),
    remarks_field: (row.remarks_field as string | null | undefined) ?? fallback.remarks_field ?? null,
    show_site_assignment: Boolean(row.show_site_assignment ?? fallback.show_site_assignment ?? false),
    department: (row.department as string | null | undefined) ?? fallback.department ?? null,
    orientation:
      row.orientation === "landscape" || fallback.orientation === "landscape" ? "landscape" : "portrait",
    template_html:
      (row.template_html as string | null | undefined) ?? fallback.template_html ?? null,
    template_css: (row.template_css as string | null | undefined) ?? fallback.template_css ?? null,
    updated_at: (row.updated_at as string | null | undefined) ?? null,
  };
  return base;
}
