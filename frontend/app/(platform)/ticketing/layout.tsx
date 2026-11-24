"use client";

import type { ReactNode } from "react";

import { TicketingModuleFrame, TicketingRoleAccessProvider } from "@/components/ticketing/ticketing-role-field-access";

export default function TicketingLayout({ children }: { children: ReactNode }) {
  return (
    <TicketingRoleAccessProvider>
      <TicketingModuleFrame>{children}</TicketingModuleFrame>
    </TicketingRoleAccessProvider>
  );
}
