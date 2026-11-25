"use client";

import { create } from "zustand";

import type { ModuleAccentMap } from "@/lib/theme/module-accents";
import { resolveTenantChrome, type TenantChrome } from "@/lib/theme/tenant-chrome";
import type { WorkspaceAppearance, WorkspaceColorMode } from "@/lib/theme/workspace-appearance";

type TenantAppearanceState = {
  open: boolean;
  preview: TenantChrome | null;
  /** Unsaved module colors while Customize workspace is open. */
  modulePreview: ModuleAccentMap | null;
  /** This signed-in user's saved design. Null until they save one. */
  saved: WorkspaceAppearance | null;
  setOpen: (open: boolean) => void;
  setPreview: (chrome: TenantChrome | null) => void;
  setModulePreview: (accents: ModuleAccentMap | null) => void;
  setSaved: (appearance: WorkspaceAppearance | null) => void;
};

export const useTenantAppearanceStore = create<TenantAppearanceState>()((set) => ({
  open: false,
  preview: null,
  modulePreview: null,
  saved: null,
  setOpen: (open) =>
    set((state) => ({
      open,
      preview: open ? state.preview : null,
      modulePreview: open ? state.modulePreview : null,
    })),
  setPreview: (chrome) => set({ preview: chrome }),
  setModulePreview: (accents) => set({ modulePreview: accents }),
  setSaved: (appearance) => set({ saved: appearance }),
}));

export function useResolvedTenantChrome(): TenantChrome | null {
  const saved = useTenantAppearanceStore((state) => state.saved);
  const preview = useTenantAppearanceStore((state) => state.preview);
  return resolveTenantChrome(saved, preview);
}

export function useSavedColorMode(): WorkspaceColorMode | null {
  return useTenantAppearanceStore((state) => state.saved?.colorMode ?? null);
}
