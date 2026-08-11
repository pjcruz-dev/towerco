"use client";

import { useQuery } from "@tanstack/react-query";

import { fetchEApprovalDashboard } from "@/lib/api/modules/e-approval-api";
import type { EApprovalDashboardResponse } from "@/modules/e-approval/types";

const emptyState: EApprovalDashboardResponse = {
  kpis: [],
  finance_kpis: [],
  queues: { awaiting_approval: [], my_attention: [] },
  capabilities: {
    can_approve: false,
    can_create: false,
    can_manage_forms: false,
    can_audit: false,
  },
  actions: [],
  phase: "P7",
  message: "",
  recent_audit: [],
};

export function useEApprovalDashboard() {
  return useQuery({
    queryKey: ["e-approval", "dashboard"],
    queryFn: fetchEApprovalDashboard,
    staleTime: 60_000,
    placeholderData: emptyState,
  });
}
