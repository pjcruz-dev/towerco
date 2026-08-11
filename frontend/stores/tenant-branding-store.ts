"use client";

import { create } from "zustand";

import type { TenantBrandingPayload } from "@/lib/api/modules/branding-api";

type TenantBrandingState = {
  branding: TenantBrandingPayload | null;
  setBranding: (payload: TenantBrandingPayload | null) => void;
};

export const useTenantBrandingStore = create<TenantBrandingState>()((set) => ({
  branding: null,
  setBranding: (payload) => set({ branding: payload }),
}));
