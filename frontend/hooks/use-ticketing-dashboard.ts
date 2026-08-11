"use client";

import { useQuery } from "@tanstack/react-query";

import { fetchTicketingDashboard } from "@/lib/api/modules/ticketing-api";
import type { TicketingDashboardResponse } from "@/modules/ticketing/types";

const emptyState: TicketingDashboardResponse = {
  kpis: [],
  recent_tickets: [],
  message: "",
};

export function useTicketingDashboard() {
  return useQuery({
    queryKey: ["ticketing", "dashboard"],
    queryFn: fetchTicketingDashboard,
    staleTime: 60_000,
    placeholderData: emptyState,
  });
}
