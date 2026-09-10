"use client";

import { useCallback, useEffect, useRef, useState } from "react";

import { useLocalStorageJsonState } from "@/hooks/use-local-storage-json-state";
import {
  clearPersonalDashboardLayout,
  loadDashboardLayoutBundle,
  persistPersonalDashboardLayout,
  publishTenantDashboardLayout,
  resolveEffectiveDashboardLayout,
} from "@/lib/ui/dashboard-page-layouts";
import {
  EMPTY_DASHBOARD_LAYOUT_PREFS,
  isDashboardLayoutPrefs,
  normalizeDashboardLayoutPrefs,
  type DashboardLayoutPrefs,
} from "@/lib/ui/dashboard-widget-registry";

/**
 * Persist dashboard widget order, visibility, catalog selection, spans, and options.
 * Local cache + personal server override; optional tenant-shared default (Phase 4).
 */
export function useDashboardLayoutPrefs(storageKey: string | null) {
  const [prefs, setPrefs] = useLocalStorageJsonState<DashboardLayoutPrefs>(
    storageKey,
    EMPTY_DASHBOARD_LAYOUT_PREFS,
    isDashboardLayoutPrefs,
  );

  const [tenantDefault, setTenantDefault] = useState<DashboardLayoutPrefs | null>(null);
  const [hasPersonalOverride, setHasPersonalOverride] = useState(false);
  const [serverReady, setServerReady] = useState(() => storageKey === null);
  const followingTenantRef = useRef(false);
  const persistTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const prefsRef = useRef(prefs);
  prefsRef.current = prefs;

  useEffect(() => {
    if (storageKey === null) {
      setServerReady(true);
      return;
    }

    let cancelled = false;
    setServerReady(false);

    void loadDashboardLayoutBundle(storageKey).then((bundle) => {
      if (cancelled) return;
      setTenantDefault(bundle.tenantDefault);
      const resolved = resolveEffectiveDashboardLayout({
        personal: bundle.personal,
        tenantDefault: bundle.tenantDefault,
        local: normalizeDashboardLayoutPrefs(prefsRef.current),
      });
      setHasPersonalOverride(resolved.source === "personal");
      followingTenantRef.current = resolved.source === "tenant";
      if (resolved.source === "personal" || resolved.source === "tenant") {
        setPrefs(resolved.layout);
      }
      setServerReady(true);
    });

    return () => {
      cancelled = true;
    };
  }, [setPrefs, storageKey]);

  const schedulePersonalPersist = useCallback(
    (next: DashboardLayoutPrefs) => {
      if (storageKey === null) return;
      if (persistTimerRef.current) clearTimeout(persistTimerRef.current);
      persistTimerRef.current = setTimeout(() => {
        void persistPersonalDashboardLayout(storageKey, next);
      }, 450);
    },
    [storageKey],
  );

  useEffect(() => {
    return () => {
      if (persistTimerRef.current) clearTimeout(persistTimerRef.current);
    };
  }, []);

  const layout = normalizeDashboardLayoutPrefs(prefs);

  const patchLayout = useCallback(
    (patch: Partial<DashboardLayoutPrefs>) => {
      setPrefs((current) => {
        const base = normalizeDashboardLayoutPrefs(current);
        const next = {
          widgetOrder: patch.widgetOrder ?? base.widgetOrder,
          hiddenWidgetIds: patch.hiddenWidgetIds ?? base.hiddenWidgetIds,
          enabledWidgetIds: patch.enabledWidgetIds ?? base.enabledWidgetIds,
          spans: patch.spans ?? base.spans,
          widgetOptions: patch.widgetOptions ?? base.widgetOptions,
          pageChrome: patch.pageChrome ?? base.pageChrome,
        };
        followingTenantRef.current = false;
        setHasPersonalOverride(true);
        schedulePersonalPersist(next);
        return next;
      });
    },
    [schedulePersonalPersist, setPrefs],
  );

  const setLayout = useCallback(
    (next: DashboardLayoutPrefs) => {
      const normalized = normalizeDashboardLayoutPrefs(next);
      followingTenantRef.current = false;
      setHasPersonalOverride(true);
      setPrefs(normalized);
      schedulePersonalPersist(normalized);
    },
    [schedulePersonalPersist, setPrefs],
  );

  const resetLayout = useCallback(() => {
    const empty = { ...EMPTY_DASHBOARD_LAYOUT_PREFS, spans: {}, widgetOptions: {}, pageChrome: {} };
    followingTenantRef.current = false;
    setHasPersonalOverride(true);
    setPrefs(empty);
    schedulePersonalPersist(empty);
  }, [schedulePersonalPersist, setPrefs]);

  const publishTenantDefault = useCallback(async () => {
    if (storageKey === null) return null;
    const published = await publishTenantDashboardLayout(storageKey, layout);
    if (published) {
      setTenantDefault(published);
    }
    return published;
  }, [layout, storageKey]);

  const resetToTenantDefault = useCallback(async () => {
    if (storageKey === null) return;
    if (persistTimerRef.current) {
      clearTimeout(persistTimerRef.current);
      persistTimerRef.current = null;
    }
    await clearPersonalDashboardLayout(storageKey);
    setHasPersonalOverride(false);
    if (tenantDefault) {
      followingTenantRef.current = true;
      setPrefs(normalizeDashboardLayoutPrefs(tenantDefault));
    } else {
      followingTenantRef.current = false;
      setPrefs({ ...EMPTY_DASHBOARD_LAYOUT_PREFS, spans: {}, widgetOptions: {}, pageChrome: {} });
    }
  }, [setPrefs, storageKey, tenantDefault]);

  return {
    layout,
    widgetOrder: layout.widgetOrder,
    hiddenWidgetIds: layout.hiddenWidgetIds,
    enabledWidgetIds: layout.enabledWidgetIds,
    spans: layout.spans,
    widgetOptions: layout.widgetOptions,
    pageChrome: layout.pageChrome,
    tenantDefault,
    hasPersonalOverride,
    serverReady,
    patchLayout,
    setLayout,
    resetLayout,
    publishTenantDefault,
    resetToTenantDefault,
  };
}

/** Local Customize-mode toggle (not persisted). */
export function useDashboardCustomizeMode(initial = false) {
  const [editing, setEditing] = useState(initial);
  return { editing, setEditing, toggleEditing: () => setEditing((v) => !v) };
}
