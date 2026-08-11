import type { Metadata } from "next";

export const metadata: Metadata = {
  title: "External form — TowerOS",
  description: "Submit a request without signing in.",
};

export default function EApprovalPublicFormLayout({ children }: { children: React.ReactNode }) {
  return children;
}
