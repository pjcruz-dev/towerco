/** Per-user, per-tenant completed Ticketing live-tour chapters. */

import type { LiveTourChapterId } from "@/lib/help/e-approval-live-tour";

const STORAGE_PREFIX = "toweros.help.ticketingTour.chapters.completed";
const CHANGE_EVENT = "toweros-ticketing-tour-chapters";

function storageKey(userId: string, tenantId: string | null): string {
  const tenant = tenantId && tenantId.length > 0 ? tenantId : "none";
  return `${STORAGE_PREFIX}:${tenant}:${userId}`;
}

function notifyChapterProgressChanged(): void {
  if (typeof window === "undefined") {
    return;
  }
  window.dispatchEvent(new Event(CHANGE_EVENT));
}

export function subscribeTicketingTourChapterProgress(onStoreChange: () => void): () => void {
  if (typeof window === "undefined") {
    return () => undefined;
  }
  const onStorage = (event: StorageEvent) => {
    if (event.key?.startsWith(STORAGE_PREFIX)) {
      onStoreChange();
    }
  };
  window.addEventListener("storage", onStorage);
  window.addEventListener(CHANGE_EVENT, onStoreChange);
  return () => {
    window.removeEventListener("storage", onStorage);
    window.removeEventListener(CHANGE_EVENT, onStoreChange);
  };
}

function readCompletedIds(userId: string, tenantId: string | null): Set<string> {
  if (typeof window === "undefined") {
    return new Set();
  }
  try {
    const raw = window.localStorage.getItem(storageKey(userId, tenantId));
    if (!raw) {
      return new Set();
    }
    const parsed = JSON.parse(raw) as unknown;
    if (!Array.isArray(parsed)) {
      return new Set();
    }
    return new Set(parsed.filter((value): value is string => typeof value === "string"));
  } catch {
    return new Set();
  }
}

function writeCompletedIds(userId: string, tenantId: string | null, ids: Set<string>): void {
  if (typeof window === "undefined") {
    return;
  }
  try {
    window.localStorage.setItem(storageKey(userId, tenantId), JSON.stringify([...ids]));
    notifyChapterProgressChanged();
  } catch {
    // ignore quota / private mode
  }
}

export function getCompletedTicketingTourChapters(
  userId: string | null | undefined,
  tenantId: string | null | undefined,
): Set<LiveTourChapterId> {
  if (!userId) {
    return new Set();
  }
  return readCompletedIds(userId, tenantId ?? null) as Set<LiveTourChapterId>;
}

export function markTicketingTourChapterComplete(
  chapterId: LiveTourChapterId,
  userId: string | null | undefined,
  tenantId: string | null | undefined,
): void {
  if (!userId || chapterId === "complete") {
    return;
  }
  const ids = readCompletedIds(userId, tenantId ?? null);
  ids.add(chapterId);
  writeCompletedIds(userId, tenantId ?? null, ids);
}
