import type { Metadata } from "next";

import { AppMenuPageClient } from "./appmenu-page-client";

export const metadata: Metadata = {
  title: "App Menu",
  description: "Choose a workspace or tool to open.",
};

export default function AppMenuPage() {
  return <AppMenuPageClient />;
}
