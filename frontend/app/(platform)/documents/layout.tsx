"use client";

import type { ReactNode } from "react";

import {
  DocumentRegisterModuleFrame,
  DocumentRegisterRoleAccessProvider,
} from "@/components/documents/document-register-role-field-access";

export default function DocumentsLayout({ children }: { children: ReactNode }) {
  return (
    <DocumentRegisterRoleAccessProvider>
      <DocumentRegisterModuleFrame>{children}</DocumentRegisterModuleFrame>
    </DocumentRegisterRoleAccessProvider>
  );
}
