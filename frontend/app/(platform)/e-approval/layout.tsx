"use client";

import type { ReactNode } from "react";

import { EApprovalModuleFrame } from "@/components/e-approval/e-approval-role-field-access";

export default function EApprovalLayout({ children }: { children: ReactNode }) {
  return <EApprovalModuleFrame>{children}</EApprovalModuleFrame>;
}
