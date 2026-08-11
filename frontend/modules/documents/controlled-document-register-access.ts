import { parseControlledDocumentAccessPolicy } from "@/modules/e-approval/controlled-document-sync";

export type ControlledDocumentRegisterAccessPolicy = {
  viewer_roles: string[];
  full_access_roles: string[];
  full_access_permissions: string[];
  own_only_roles: string[];
  role_department_map: Record<string, string[]>;
};

export type ControlledDocumentRegisterAccessPayload = {
  form_id: string | null;
  configured: boolean;
  access_policy: ControlledDocumentRegisterAccessPolicy;
};

export type ControlledDocumentRegisterAccessEditor = {
  viewerRoles: string;
  fullAccessRoles: string;
  ownOnlyRoles: string;
  roleDepartmentMapJson: string;
};

export const DEFAULT_REGISTER_ACCESS_EDITOR: ControlledDocumentRegisterAccessEditor = {
  viewerRoles: "",
  fullAccessRoles: "document_controller, quality_manager, dcf_controller, dcf_admin",
  ownOnlyRoles: "dcf_author",
  roleDepartmentMapJson: "",
};

export function registerAccessEditorFromPolicy(
  policy: ControlledDocumentRegisterAccessPolicy,
): ControlledDocumentRegisterAccessEditor {
  return {
    viewerRoles: policy.viewer_roles.join(", "),
    fullAccessRoles: policy.full_access_roles.join(", "),
    ownOnlyRoles: (policy.own_only_roles ?? ["dcf_author"]).join(", "),
    roleDepartmentMapJson:
      Object.keys(policy.role_department_map).length > 0
        ? JSON.stringify(policy.role_department_map, null, 2)
        : "",
  };
}

export function registerAccessEditorFromMetadata(metadata: unknown): ControlledDocumentRegisterAccessEditor {
  const parsed = parseControlledDocumentAccessPolicy(metadata);

  return {
    viewerRoles: parsed.viewerRoles.join(", "),
    fullAccessRoles: parsed.fullAccessRoles.join(", "),
    ownOnlyRoles: parsed.ownOnlyRoles.join(", "),
    roleDepartmentMapJson:
      Object.keys(parsed.roleDepartmentMap).length > 0
        ? JSON.stringify(parsed.roleDepartmentMap, null, 2)
        : "",
  };
}

function parseCsv(raw: string): string[] {
  return raw
    .split(",")
    .map((item) => item.trim())
    .filter((item) => item !== "");
}

export function registerAccessEditorToApiPayload(editor: ControlledDocumentRegisterAccessEditor): {
  viewer_roles: string[];
  full_access_roles: string[];
  own_only_roles: string[];
  role_department_map: Record<string, string[]>;
} {
  let roleDepartmentMap: Record<string, string[]> = {};
  const mapJson = editor.roleDepartmentMapJson.trim();

  if (mapJson !== "") {
    const parsed = JSON.parse(mapJson) as unknown;
    if (!parsed || typeof parsed !== "object" || Array.isArray(parsed)) {
      throw new Error("Role → department map must be a JSON object.");
    }
    roleDepartmentMap = parsed as Record<string, string[]>;
  }

  return {
    viewer_roles: parseCsv(editor.viewerRoles),
    full_access_roles: parseCsv(editor.fullAccessRoles),
    own_only_roles: parseCsv(editor.ownOnlyRoles),
    role_department_map: roleDepartmentMap,
  };
}

export function validateRegisterAccessEditor(editor: ControlledDocumentRegisterAccessEditor): string | null {
  const mapJson = editor.roleDepartmentMapJson.trim();
  if (mapJson === "") {
    return null;
  }

  try {
    const parsed = JSON.parse(mapJson) as unknown;
    if (!parsed || typeof parsed !== "object" || Array.isArray(parsed)) {
      return "Role → department map must be a JSON object.";
    }
  } catch {
    return "Role → department map is not valid JSON.";
  }

  return null;
}
