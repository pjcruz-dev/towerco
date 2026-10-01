export type FormRequestAccessMode = "all" | "selected";

export type FormRequestAccess = {
  mode: FormRequestAccessMode;
  userIds: string[];
};

const UUID_RE =
  /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;

export function formRequestAccessFromMetadata(metadata: unknown): FormRequestAccess {
  const raw =
    metadata && typeof metadata === "object"
      ? (metadata as Record<string, unknown>).request_access
      : null;
  const record = raw && typeof raw === "object" ? (raw as Record<string, unknown>) : {};
  const mode: FormRequestAccessMode = record.mode === "selected" ? "selected" : "all";
  const userIds = Array.isArray(record.user_ids)
    ? record.user_ids
        .map((id) => (typeof id === "string" ? id.trim() : ""))
        .filter((id) => UUID_RE.test(id))
    : [];

  return {
    mode,
    userIds: [...new Set(userIds)],
  };
}

export function formRequestAccessPatch(access: FormRequestAccess): { request_access: { mode: FormRequestAccessMode; user_ids: string[] } } {
  return {
    request_access: {
      mode: access.mode === "selected" ? "selected" : "all",
      user_ids: access.mode === "selected" ? access.userIds : [],
    },
  };
}

export function viewerCanStartFormRequest(metadata: unknown, userId: string | null | undefined): boolean {
  const access = formRequestAccessFromMetadata(metadata);
  if (access.mode !== "selected") {
    return true;
  }
  const id = (userId ?? "").trim();
  return id !== "" && access.userIds.includes(id);
}
