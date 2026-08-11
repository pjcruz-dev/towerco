"use client";

import { create } from "zustand";

type WorkspaceBreadcrumbState = {
  pageLabel: string | null;
  setPageLabel: (label: string | null) => void;
};

export const useWorkspaceBreadcrumbStore = create<WorkspaceBreadcrumbState>((set) => ({
  pageLabel: null,
  setPageLabel: (pageLabel) => set({ pageLabel }),
}));
