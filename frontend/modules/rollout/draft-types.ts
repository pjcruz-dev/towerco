export type RolloutDraftKind = "candidate_create" | "hunting_log" | "cme_report";

export type RolloutFieldDraft = {
  client_draft_id: string;
  kind: RolloutDraftKind;
  rolloutId: string;
  payload: Record<string, unknown>;
  createdAt: string;
};
