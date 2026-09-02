"use client";

import { create } from "zustand";

export type NavigationLayout = "sidebar" | "navbar";

const STORAGE_KEY = "toweros.ui.navigation-layout";

type NavigationLayoutState = {
  layout: NavigationLayout;
  hydrated: boolean;
  setLayout: (layout: NavigationLayout) => void;
  hydrate: () => void;
};

function readStored(): NavigationLayout {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    if (raw === "sidebar" || raw === "navbar") return raw;
  } catch {
    // ignore
  }
  return "sidebar";
}

export const useNavigationLayoutStore = create<NavigationLayoutState>((set) => ({
  layout: "sidebar",
  hydrated: false,
  setLayout: (layout) => {
    try {
      localStorage.setItem(STORAGE_KEY, layout);
    } catch {
      // ignore
    }
    set({ layout });
  },
  hydrate: () => {
    set({ layout: readStored(), hydrated: true });
  },
}));
