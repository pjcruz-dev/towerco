export type ControlledDocumentSyncMeta = {
  enabled: boolean;
  autoRevision: boolean;
  documentCodeField: string;
  revisionFieldName: string;
  fieldMap: Record<string, string>;
  attachmentField: string;
  composeUi: {
    hideSectionProgress: boolean;
    hideRegistryPicker: boolean;
  };
};

export type ControlledDocumentAccessPolicyMeta = {
  viewerRoles: string[];
  fullAccessRoles: string[];
  fullAccessPermissions: string[];
  ownOnlyRoles: string[];
  roleDepartmentMap: Record<string, string[]>;
};

function asRecord(value: unknown): Record<string, unknown> | null {
  if (!value || typeof value !== "object" || Array.isArray(value)) {
    return null;
  }

  return value as Record<string, unknown>;
}

export function parseControlledDocumentSync(metadata: unknown): ControlledDocumentSyncMeta | null {
  const root = asRecord(metadata);
  const raw = asRecord(root?.controlledDocumentSync) ?? asRecord(root?.controlled_document_sync);
  if (!raw || raw.enabled !== true) {
    return null;
  }

  const fieldMap: Record<string, string> = {
    title: "title",
    document_type: "document_type",
    department: "department",
    revision_number: "revision_number",
    effective_date: "effective_date",
    next_review_date: "next_review_date",
    change_summary: "change_summary",
  };

  const custom = asRecord(raw.fieldMap) ?? asRecord(raw.field_map);
  if (custom) {
    for (const [key, fieldName] of Object.entries(custom)) {
      if (typeof fieldName === "string" && fieldName.trim() !== "") {
        fieldMap[key] = fieldName.trim();
      }
    }
  }

  const documentCodeField = String(raw.documentCodeField ?? raw.document_code_field ?? "document_code").trim();
  const autoRevision =
    raw.autoRevision !== undefined || raw.auto_revision !== undefined
      ? Boolean(raw.autoRevision ?? raw.auto_revision)
      : true;

  const attachmentField = String(raw.attachmentField ?? raw.attachment_field ?? "attachments").trim() || "attachments";

  const composeRaw = asRecord(raw.composeUi) ?? asRecord(raw.compose_ui);
  const hideByDefault = true;

  return {
    enabled: true,
    autoRevision,
    documentCodeField,
    revisionFieldName: fieldMap.revision_number ?? "revision_number",
    fieldMap,
    attachmentField,
    composeUi: {
      hideSectionProgress: composeRaw?.hideSectionProgress !== false && composeRaw?.hide_section_progress !== false ? hideByDefault : Boolean(composeRaw?.hideSectionProgress ?? composeRaw?.hide_section_progress),
      hideRegistryPicker: composeRaw?.hideRegistryPicker !== false && composeRaw?.hide_registry_picker !== false ? hideByDefault : Boolean(composeRaw?.hideRegistryPicker ?? composeRaw?.hide_registry_picker),
    },
  };
}

export function parseControlledDocumentAccessPolicy(metadata: unknown): ControlledDocumentAccessPolicyMeta {
  const root = asRecord(metadata);
  const raw = asRecord(root?.controlledDocumentSync) ?? asRecord(root?.controlled_document_sync);
  const policy = asRecord(raw?.accessPolicy) ?? asRecord(raw?.access_policy);

  const list = (value: unknown): string[] => {
    if (!Array.isArray(value)) {
      return [];
    }
    return value.filter((item): item is string => typeof item === "string" && item.trim() !== "").map((item) => item.trim());
  };

  const roleDepartmentMap: Record<string, string[]> = {};
  const mapRaw = asRecord(policy?.roleDepartmentMap) ?? asRecord(policy?.role_department_map);
  if (mapRaw) {
    for (const [role, departments] of Object.entries(mapRaw)) {
      if (Array.isArray(departments)) {
        roleDepartmentMap[role] = departments.filter((d): d is string => typeof d === "string" && d.trim() !== "");
      }
    }
  }

  return {
    viewerRoles: list(policy?.viewerRoles ?? policy?.viewer_roles),
    fullAccessRoles: list(policy?.fullAccessRoles ?? policy?.full_access_roles) || [
      "document_controller",
      "quality_manager",
      "dcf_controller",
      "dcf_admin",
    ],
    fullAccessPermissions: list(policy?.fullAccessPermissions ?? policy?.full_access_permissions) || [
      "documents:controlled:manage",
    ],
    ownOnlyRoles:
      policy && ("ownOnlyRoles" in policy || "own_only_roles" in policy)
        ? list(policy?.ownOnlyRoles ?? policy?.own_only_roles)
        : ["dcf_author"],
    roleDepartmentMap,
  };
}

export function controlledDocumentFieldHelp(
  sync: ControlledDocumentSyncMeta | null,
): Record<string, string> | undefined {
  if (!sync) {
    return undefined;
  }

  return {
    [sync.revisionFieldName]:
      "Assigned automatically from the document register. Override only when you need a specific revision number.",
    [sync.fieldMap.change_summary ?? "change_summary"]:
      "Optional description of what changed in this revision. Stored on the revision history.",
  };
}
