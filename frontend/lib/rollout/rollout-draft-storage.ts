import type { RolloutFieldDraft } from "@/modules/rollout/draft-types";

export function rolloutDraftStorageKey(tenantId: string): string {
  return `toweros:rollout-drafts:${tenantId}`;
}

export function loadRolloutDrafts(tenantId: string): RolloutFieldDraft[] {
  if (typeof window === "undefined") {
    return [];
  }

  try {
    const raw = localStorage.getItem(rolloutDraftStorageKey(tenantId));
    if (!raw) {
      return [];
    }

    const parsed = JSON.parse(raw) as unknown;
    if (!Array.isArray(parsed)) {
      return [];
    }

    return parsed.filter(isRolloutFieldDraft);
  } catch {
    return [];
  }
}

export function saveRolloutDraft(tenantId: string, draft: RolloutFieldDraft): void {
  const drafts = loadRolloutDrafts(tenantId).filter((row) => row.client_draft_id !== draft.client_draft_id);
  drafts.push(draft);

  try {
    localStorage.setItem(rolloutDraftStorageKey(tenantId), JSON.stringify(drafts));
  } catch {
    // Ignore storage write errors (private mode, quota).
  }
}

export function removeRolloutDraft(tenantId: string, clientDraftId: string): void {
  const drafts = loadRolloutDrafts(tenantId).filter((row) => row.client_draft_id !== clientDraftId);

  try {
    localStorage.setItem(rolloutDraftStorageKey(tenantId), JSON.stringify(drafts));
  } catch {
    // Ignore storage write errors.
  }
}

function isRolloutFieldDraft(value: unknown): value is RolloutFieldDraft {
  if (!value || typeof value !== "object") {
    return false;
  }

  const row = value as RolloutFieldDraft;
  return (
    typeof row.client_draft_id === "string" &&
    typeof row.rolloutId === "string" &&
    typeof row.createdAt === "string" &&
    (row.kind === "candidate_create" || row.kind === "hunting_log" || row.kind === "cme_report") &&
    typeof row.payload === "object" &&
    row.payload !== null
  );
}
