"use client";

import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";

import { consumePlatformHandoffPayload } from "@/lib/auth/platform-impersonation-handoff";
import { tenantDomainFromBrowserHostname } from "@/lib/tenant/resolve-tenant-domain";
import { useAuthStore } from "@/stores/auth-store";

export function PlatformHandoffPageClient() {
  const router = useRouter();
  const [message, setMessage] = useState("Signing you in…");

  useEffect(() => {
    const hash = window.location.hash.slice(1);
    if (!hash) {
      setMessage("Missing handoff token.");
      router.replace("/login");
      return;
    }

    const session = consumePlatformHandoffPayload(hash);
    if (!session?.accessToken || !session.refreshToken || !session.user) {
      setMessage("Invalid or expired handoff token.");
      router.replace("/login");
      return;
    }

    const browserDomain = tenantDomainFromBrowserHostname(window.location.hostname);
    const expectedDomain = session.user.tenantDomain?.toLowerCase();
    if (browserDomain && expectedDomain && browserDomain !== expectedDomain) {
      setMessage("Tenant domain mismatch.");
      router.replace("/login");
      return;
    }

    useAuthStore.getState().setSession(session);
    router.replace("/");
  }, [router]);

  return (
    <div className="flex min-h-screen items-center justify-center bg-background px-6 text-sm text-muted-foreground">
      {message}
    </div>
  );
}
