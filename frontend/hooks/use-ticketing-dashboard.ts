"use client";

import { useQuery } from "@tanstack/react-query";

import { fetchTicketingDashboard } from "@/lib/api/modules/ticketing-api";
import type { TicketingDashboardResponse } from "@/modules/ticketing/types";

const emptyState: TicketingDashboardResponse = {
  kpis: [],
  recent_tickets: [],
  message: "",
};

export type TicketingDashboardFilters = {
  status?: string;
  priority?: string;
  category?: string;
  department?: string;
  mine?: boolean;
  assigned_me?: boolean;
};

export function useTicketingDashboard(filters: TicketingDashboardFilters = {}) {
  return useQuery({
    queryKey: [
      "ticketing",
      "dashboard",
      filters.status ?? "",
      filters.priority ?? "",
      filters.category ?? "",
      filters.department ?? "",
      filters.mine ? 1 : 0,
      filters.assigned_me ? 1 : 0,
    ],
    queryFn: () =>
      fetchTicketingDashboard({
        status: filters.status || undefined,
        priority: filters.priority || undefined,
        category: filters.category || undefined,
        department: filters.department || undefined,
        mine: filters.mine || undefined,
        assigned_me: filters.assigned_me || undefined,
      }),
    staleTime: 60_000,
    placeholderData: emptyState,
  });
}
