"use client";

import { create } from "zustand";

type SubscriptionAccessState = {
  mode: "full" | "read_only" | "blocked" | null;
  message: string | null;
  setAccessNotice: (mode: "read_only" | "blocked", message: string) => void;
  clear: () => void;
};

export const useSubscriptionAccessStore = create<SubscriptionAccessState>()((set) => ({
  mode: null,
  message: null,
  setAccessNotice: (mode, message) => set({ mode, message }),
  clear: () => set({ mode: null, message: null }),
}));
