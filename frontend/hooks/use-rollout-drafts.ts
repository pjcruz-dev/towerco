"use client";

import { useCallback, useEffect, useMemo, useState } from "react";

import {
  loadRolloutDrafts,
  removeRolloutDraft,
  saveRolloutDraft,
} from "@/lib/rollout/rollout-draft-storage";
import {
  createRolloutCandidate,
  createRolloutCmeReport,
  createRolloutHuntingLog,
} from "@/lib/api/modules/rollout-api";
import type { CreateCmeReportInput } from "@/lib/api/modules/rollout-api";
import type { RolloutFieldDraft } from "@/modules/rollout/draft-types";
import { useAuthStore } from "@/stores/auth-store";
import { useNotificationStore } from "@/stores/notification-store";

function isNetworkError(error: unknown): boolean {
  if (typeof window !== "undefined" && !window.navigator.onLine) {
    return true;
  }

  if (error && typeof error === "object" && "message" in error) {
    const message = String((error as { message?: string }).message ?? "").toLowerCase();
    return message.includes("network") || message.includes("failed to fetch");
  }

  return false;
}

export function useRolloutDrafts(rolloutId?: string) {
  const tenantId = useAuthStore((state) => state.activeTenantId);
  const push = useNotificationStore((state) => state.push);
  const [drafts, setDrafts] = useState<RolloutFieldDraft[]>([]);
  const [isSyncing, setIsSyncing] = useState(false);

  const refresh = useCallback(() => {
    if (!tenantId) {
      setDrafts([]);
      return;
    }

    setDrafts(loadRolloutDrafts(tenantId));
  }, [tenantId]);

  useEffect(() => {
    refresh();
  }, [refresh]);

  useEffect(() => {
    const handleOnline = () => {
      void syncDrafts();
    };

    window.addEventListener("online", handleOnline);
    return () => window.removeEventListener("online", handleOnline);
    // eslint-disable-next-line react-hooks/exhaustive-deps -- syncDrafts identity is stable enough for reconnect
  }, [tenantId, rolloutId]);

  const pendingForRollout = useMemo(() => {
    if (!rolloutId) {
      return drafts;
    }

    return drafts.filter((draft) => draft.rolloutId === rolloutId);
  }, [drafts, rolloutId]);

  const queueDraft = useCallback(
    (draft: RolloutFieldDraft) => {
      if (!tenantId) {
        push({ level: "error", title: "Cannot save draft", message: "No active tenant session." });
        return;
      }

      saveRolloutDraft(tenantId, draft);
      refresh();
      push({ level: "success", title: "Draft saved offline", message: "Sync when back online." });
    },
    [tenantId, refresh, push],
  );

  const syncOneDraft = useCallback(
    async (draft: RolloutFieldDraft): Promise<"synced" | "conflict" | "failed"> => {
      if (!tenantId) {
        return "failed";
      }

      try {
        if (draft.kind === "candidate_create") {
          await createRolloutCandidate(draft.rolloutId, draft.payload);
        } else if (draft.kind === "hunting_log") {
          await createRolloutHuntingLog(
            draft.rolloutId,
            draft.payload as Parameters<typeof createRolloutHuntingLog>[1],
          );
        } else {
          await createRolloutCmeReport(draft.rolloutId, draft.payload as CreateCmeReportInput);
        }

        removeRolloutDraft(tenantId, draft.client_draft_id);
        return "synced";
      } catch {
        return "failed";
      }
    },
    [tenantId],
  );

  const syncDrafts = useCallback(
    async (scopeRolloutId?: string) => {
      if (!tenantId) {
        return { synced: 0, failed: 0 };
      }

      const pending = loadRolloutDrafts(tenantId).filter((draft) =>
        scopeRolloutId ? draft.rolloutId === scopeRolloutId : true,
      );

      if (pending.length === 0) {
        return { synced: 0, failed: 0 };
      }

      setIsSyncing(true);
      let synced = 0;
      let failed = 0;

      for (const draft of pending) {
        const result = await syncOneDraft(draft);
        if (result === "synced") {
          synced += 1;
        } else {
          failed += 1;
        }
      }

      refresh();
      setIsSyncing(false);

      if (synced > 0) {
        push({
          level: "success",
          title: synced === 1 ? "Draft synced" : `${synced} drafts synced`,
        });
      }

      if (failed > 0) {
        push({
          level: "warning",
          title: "Some drafts could not sync",
          message: "Server record kept where a conflict exists. Retry sync.",
        });
      }

      return { synced, failed };
    },
    [tenantId, syncOneDraft, refresh, push],
  );

  return {
    drafts,
    pendingForRollout,
    pendingCount: pendingForRollout.length,
    totalPendingCount: drafts.length,
    isSyncing,
    queueDraft,
    syncDrafts,
    refresh,
    isNetworkError,
  };
}

export function createClientDraftId(): string {
  if (typeof crypto !== "undefined" && "randomUUID" in crypto) {
    return crypto.randomUUID();
  }

  return `draft-${Date.now()}-${Math.random().toString(16).slice(2)}`;
}
