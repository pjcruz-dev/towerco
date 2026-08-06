"use client";

import { create, type StoreApi, type UseBoundStore } from "zustand";

type NotificationLevel = "info" | "success" | "warning" | "error";

export type AppNotification = {
  id: string;
  title: string;
  message?: string;
  level: NotificationLevel;
};

export type NotificationState = {
  items: AppNotification[];
  push: (notification: Omit<AppNotification, "id">) => void;
  dismiss: (id: string) => void;
  clear: () => void;
};

/** Prefer crypto.randomUUID; fall back on HTTP LAN hosts (not a secure context). */
function createNotificationId(): string {
  if (typeof crypto !== "undefined" && typeof crypto.randomUUID === "function") {
    return crypto.randomUUID();
  }

  return `notice-${Date.now()}-${Math.random().toString(16).slice(2)}`;
}

export const useNotificationStore = create<NotificationState>()((set) => ({
  items: [],
  push: (notification) =>
    set((state) => ({
      items: [
        {
          ...notification,
          id: createNotificationId(),
        },
        ...state.items,
      ].slice(0, 5),
    })),
  dismiss: (id) =>
    set((state) => ({
      items: state.items.filter((item) => item.id !== id),
    })),
  clear: () => set({ items: [] }),
})) as UseBoundStore<StoreApi<NotificationState>>;
