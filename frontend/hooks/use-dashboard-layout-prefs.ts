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

export type DashboardLayoutUpdater =
  | DashboardLayoutPrefs
  | ((prev: DashboardLayoutPrefs) => DashboardLayoutPrefs);

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
  const storageKeyRef = useRef(storageKey);
  storageKeyRef.current = storageKey;
  /** User edited before/while server bundle loaded — do not clobber Card appearance etc. */
  const dirtyRef = useRef(false);

  useEffect(() => {
    dirtyRef.current = false;
    if (storageKey === null) {
      setServerReady(true);
      return;
    }

    let cancelled = false;
    setServerReady(false);

    void loadDashboardLayoutBundle(storageKey).then((bundle) => {
      if (cancelled) return;
      setTenantDefault(bundle.tenantDefault);

      if (dirtyRef.current) {
        setHasPersonalOverride(true);
        followingTenantRef.current = false;
        setServerReady(true);
        return;
      }

      const resolved = resolveEffectiveDashboardLayout({
        personal: bundle.personal,
        tenantDefault: bundle.tenantDefault,
        local: normalizeDashboardLayoutPrefs(prefsRef.current),
      });
      setHasPersonalOverride(resolved.source === "personal");
      followingTenantRef.current = resolved.source === "tenant";
      if (resolved.source === "personal" || resolved.source === "tenant") {
        prefsRef.current = resolved.layout;
        setPrefs(resolved.layout);
      }
      setServerReady(true);
    });

    return () => {
      cancelled = true;
    };
  }, [setPrefs, storageKey]);

  const flushPersonalPersist = useCallback(async () => {
    const key = storageKeyRef.current;
    if (key === null) return;
    if (persistTimerRef.current) {
      clearTimeout(persistTimerRef.current);
      persistTimerRef.current = null;
    }
    await persistPersonalDashboardLayout(key, normalizeDashboardLayoutPrefs(prefsRef.current));
  }, []);

  const schedulePersonalPersist = useCallback(
    (next: DashboardLayoutPrefs) => {
      if (storageKey === null) return;
      if (persistTimerRef.current) clearTimeout(persistTimerRef.current);
      persistTimerRef.current = setTimeout(() => {
        persistTimerRef.current = null;
        void persistPersonalDashboardLayout(storageKey, next);
      }, 450);
    },
    [storageKey],
  );

  useEffect(() => {
    return () => {
      const key = storageKeyRef.current;
      if (!key) return;
      if (persistTimerRef.current) {
        clearTimeout(persistTimerRef.current);
        persistTimerRef.current = null;
        void persistPersonalDashboardLayout(key, normalizeDashboardLayoutPrefs(prefsRef.current));
      }
    };
  }, []);

  const commitLayout = useCallback(
    (next: DashboardLayoutPrefs, options?: { personalOverride?: boolean }) => {
      const normalized = normalizeDashboardLayoutPrefs(next);
      dirtyRef.current = true;
      followingTenantRef.current = false;
      if (options?.personalOverride !== false) {
        setHasPersonalOverride(true);
      }
      prefsRef.current = normalized;
      setPrefs(normalized);
      schedulePersonalPersist(normalized);
      return normalized;
    },
    [schedulePersonalPersist, setPrefs],
  );

  const layout = normalizeDashboardLayoutPrefs(prefs);

  const patchLayout = useCallback(
    (patch: Partial<DashboardLayoutPrefs>) => {
      const base = normalizeDashboardLayoutPrefs(prefsRef.current);
      commitLayout({
        widgetOrder: patch.widgetOrder ?? base.widgetOrder,
        hiddenWidgetIds: patch.hiddenWidgetIds ?? base.hiddenWidgetIds,
        enabledWidgetIds: patch.enabledWidgetIds ?? base.enabledWidgetIds,
        spans: patch.spans ?? base.spans,
        widgetOptions: patch.widgetOptions ?? base.widgetOptions,
        pageChrome: patch.pageChrome ?? base.pageChrome,
      });
    },
    [commitLayout],
  );

  const setLayout = useCallback(
    (next: DashboardLayoutUpdater) => {
      const prev = normalizeDashboardLayoutPrefs(prefsRef.current);
      const resolved = typeof next === "function" ? next(prev) : next;
      commitLayout(resolved);
    },
    [commitLayout],
  );

  const resetLayout = useCallback(() => {
    commitLayout({ ...EMPTY_DASHBOARD_LAYOUT_PREFS, spans: {}, widgetOptions: {}, pageChrome: {} });
  }, [commitLayout]);

  const publishTenantDefault = useCallback(async () => {
    if (storageKey === null) return null;
    const published = await publishTenantDashboardLayout(
      storageKey,
      normalizeDashboardLayoutPrefs(prefsRef.current),
    );
    if (published) {
      setTenantDefault(published);
    }
    return published;
  }, [storageKey]);

  const resetToTenantDefault = useCallback(async () => {
    if (storageKey === null) return;
    if (persistTimerRef.current) {
      clearTimeout(persistTimerRef.current);
      persistTimerRef.current = null;
    }
    await clearPersonalDashboardLayout(storageKey);
    setHasPersonalOverride(false);
    dirtyRef.current = false;
    if (tenantDefault) {
      followingTenantRef.current = true;
      const normalized = normalizeDashboardLayoutPrefs(tenantDefault);
      prefsRef.current = normalized;
      setPrefs(normalized);
    } else {
      followingTenantRef.current = false;
      const empty = { ...EMPTY_DASHBOARD_LAYOUT_PREFS, spans: {}, widgetOptions: {}, pageChrome: {} };
      prefsRef.current = empty;
      setPrefs(empty);
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
    flushPersonalPersist,
    publishTenantDefault,
    resetToTenantDefault,
  };
}

/** Local Customize-mode toggle (not persisted). Flushes personal layout when Done. */
export function useDashboardCustomizeMode(options?: {
  initial?: boolean;
  onExitEdit?: () => void | Promise<void>;
}) {
  const [editing, setEditingState] = useState(options?.initial ?? false);
  const onExitEditRef = useRef(options?.onExitEdit);
  onExitEditRef.current = options?.onExitEdit;

  const setEditing = useCallback((value: boolean | ((prev: boolean) => boolean)) => {
    setEditingState((prev) => {
      const next = typeof value === "function" ? value(prev) : value;
      if (prev && !next) {
        void onExitEditRef.current?.();
      }
      return next;
    });
  }, []);

  return {
    editing,
    setEditing,
    toggleEditing: () => setEditing((v) => !v),
  };
}
