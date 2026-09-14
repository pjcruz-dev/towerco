import type { Metadata } from "next";

export const metadata: Metadata = {
  title: "External form — INFRA SUITE",
  description: "Submit a request without signing in.",
};

export default function EApprovalPublicFormLayout({ children }: { children: React.ReactNode }) {
  return children;
}
