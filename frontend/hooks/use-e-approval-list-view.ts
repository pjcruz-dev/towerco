"use client";

import { useCallback, useEffect, useState } from "react";

import type { EApprovalListViewMode } from "@/components/e-approval/e-approval-list-view-toggle";

export function useEApprovalListView(
  storageKey: string,
  defaultMode: EApprovalListViewMode = "gallery",
): [EApprovalListViewMode, (mode: EApprovalListViewMode) => void] {
  const [viewMode, setViewMode] = useState<EApprovalListViewMode>(defaultMode);

  useEffect(() => {
    const stored = sessionStorage.getItem(storageKey);
    if (stored === "table" || stored === "gallery") {
      setViewMode(stored);
    }
  }, [storageKey]);

  const setView = useCallback(
    (mode: EApprovalListViewMode) => {
      setViewMode(mode);
      sessionStorage.setItem(storageKey, mode);
    },
    [storageKey],
  );

  return [viewMode, setView];
}
