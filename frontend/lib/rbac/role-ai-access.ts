export const AI_PRESETS = [
  {
    id: "unrestricted",
    title: "Not restricted",
    description: "Default. Everything the assistant can do, as before.",
  },
  {
    id: "none",
    title: "No AI assistant",
    description: "The assistant refuses to work for this role.",
  },
  {
    id: "ask",
    title: "Ask only",
    description: "Questions and look-ups. It cannot change anything.",
  },
  {
    id: "ask_data",
    title: "Ask & work with data",
    description: "Look-ups plus creating and updating records. No building.",
  },
  {
    id: "builder",
    title: "Builder",
    description: "Everything except deleting data, security and code.",
  },
  {
    id: "full",
    title: "Full access",
    description: "Everything the assistant can do.",
  },
  {
    id: "custom",
    title: "Custom",
    description: "Pick the capabilities below.",
  },
] as const;

export type AiPreset = (typeof AI_PRESETS)[number]["id"];

export const AI_CAPABILITIES = [
  {
    id: "ask_lookup",
    title: "Ask & look up data",
    description: "Answer questions, explain how this system works, search and summarise records (read-only queries).",
  },
  {
    id: "create_update",
    title: "Create & update records",
    description: "Add and edit records, fill missing data, and email documents on the user's behalf.",
  },
  {
    id: "delete_records",
    title: "Delete records",
    description: "Delete records or empty an entity.",
  },
  {
    id: "build_reports",
    title: "Build reports, forms & printables",
    description: "Create and change HTML reports, dashboards, forms and printable templates.",
  },
  {
    id: "build_system",
    title: "Build & change the system",
    description: "Create or change entities and fields, dropdown options, the sidebar, theme, locations, and sample data.",
  },
  {
    id: "workflows",
    title: "Workflows & automation",
    description: "Change process buttons and workflows, entity hooks, scheduled jobs, notification and email rules.",
  },
  {
    id: "users_roles",
    title: "Users, roles & permissions",
    description: "Create users, change roles and field-level permissions.",
  },
  {
    id: "read_code",
    title: "Read & edit code files",
    description: "Read, search or edit this application's source files (developers only).",
  },
] as const;

export type AiCapability = (typeof AI_CAPABILITIES)[number]["id"];

export type AiCapabilityMap = Record<AiCapability, boolean>;

export type RoleAiAccess = {
  preset: AiPreset;
  capabilities?: Partial<AiCapabilityMap>;
  instructions?: string;
};

const ALL_ON = Object.fromEntries(AI_CAPABILITIES.map((cap) => [cap.id, true])) as AiCapabilityMap;
const ALL_OFF = Object.fromEntries(AI_CAPABILITIES.map((cap) => [cap.id, false])) as AiCapabilityMap;

export function capabilitiesForPreset(preset: AiPreset): AiCapabilityMap {
  switch (preset) {
    case "none":
      return { ...ALL_OFF };
    case "ask":
      return { ...ALL_OFF, ask_lookup: true };
    case "ask_data":
      return { ...ALL_OFF, ask_lookup: true, create_update: true };
    case "builder":
      return { ...ALL_ON, delete_records: false, users_roles: false, read_code: false };
    case "custom":
      return { ...ALL_OFF };
    default:
      return { ...ALL_ON };
  }
}

export function resolvedAiCapabilities(access: RoleAiAccess | null | undefined): AiCapabilityMap {
  const preset = access?.preset ?? "unrestricted";
  if (preset === "custom") {
    const base = capabilitiesForPreset("custom");
    for (const cap of AI_CAPABILITIES) {
      if (typeof access?.capabilities?.[cap.id] === "boolean") {
        base[cap.id] = Boolean(access.capabilities[cap.id]);
      }
    }
    return base;
  }
  return capabilitiesForPreset(preset);
}

/** Workspace owner stays unrestricted. The API rejects a matrix on these names. */
const AI_LOCKED_ROLE_NAMES = new Set(["tenant_admin", "administrator"]);

export function roleAllowsPermissionEdit(roleName: string): boolean {
  return !AI_LOCKED_ROLE_NAMES.has(roleName);
}

/**
 * Flip one capability and keep the boxes that are on screen.
 * Callers store the result under the Custom preset.
 */
export function toggleAiCapability(
  access: RoleAiAccess | null | undefined,
  id: AiCapability,
  checked: boolean,
): AiCapabilityMap {
  return { ...resolvedAiCapabilities(access), [id]: checked };
}

export function isAiPreset(value: string): value is AiPreset {
  return AI_PRESETS.some((preset) => preset.id === value);
}
