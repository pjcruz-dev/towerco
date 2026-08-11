import { headers } from "next/headers";
import { redirect } from "next/navigation";

import { isCentralHostname } from "@/lib/tenant/resolve-tenant-domain";

import { PlatformHydrate } from "./platform-hydrate";

/**
 * Superadmin console is central-host only. Tenant hosts must use tenant login.
 * Kept out of proxy.ts — matching /platform in Next.js 16 proxy can 404 App Router pages in dev.
 */
export default async function PlatformHostLayout({ children }: { children: React.ReactNode }) {
  const host = (await headers()).get("host")?.split(":")[0]?.toLowerCase() ?? "";

  if (!isCentralHostname(host)) {
    redirect("/login");
  }

  return (
    <>
      <PlatformHydrate />
      {children}
    </>
  );
}
