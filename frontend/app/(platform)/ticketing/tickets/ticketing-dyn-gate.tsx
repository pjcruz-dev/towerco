"use client";

import { useEffect, useState, type ReactNode } from "react";

import { fetchTicketingSchema } from "@/lib/api/modules/ticketing-api";

/** Ensures the helpdesk record schema (and the copied tickets) exist before the list or form loads. */
export function TicketingDynGate({ children }: { children: ReactNode }) {
  const [ready, setReady] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;
    fetchTicketingSchema()
      .then(() => {
        if (!cancelled) setReady(true);
      })
      .catch(() => {
        if (!cancelled) setError("Unable to load tickets.");
      });
    return () => {
      cancelled = true;
    };
  }, []);

  if (error) {
    return <p className="p-6 text-sm text-destructive">{error}</p>;
  }
  if (!ready) {
    return <p className="p-6 text-sm text-muted-foreground">Loading tickets…</p>;
  }

  return children;
}
