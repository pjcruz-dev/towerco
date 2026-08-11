"use client";

import { useRouter } from "next/navigation";
import { useEffect } from "react";

import { useAuthProfileSync } from "@/hooks/use-auth-profile-sync";
import { setLoginNotice } from "@/lib/auth/login-notice";
import { sessionMatchesCurrentTenant } from "@/lib/auth/tenant-session";
import { useAuthStore } from "@/stores/auth-store";

export function PlatformGuard({ children }: { children: React.ReactNode }) {
  const router = useRouter();
  const isHydrated = useAuthStore((state) => state.isHydrated);
  const accessToken = useAuthStore((state) => state.accessToken);
  const pendingMfa = useAuthStore((state) => state.pendingMfa);
  const user = useAuthStore((state) => state.user);

  useAuthProfileSync();

  const hasSession = Boolean(accessToken || pendingMfa?.accessToken);

  useEffect(() => {
    if (!isHydrated) {
      return;
    }

    const state = useAuthStore.getState();
    if (hasSession && !sessionMatchesCurrentTenant(state)) {
      setLoginNotice({
        level: "warning",
        title: "Wrong tenant session",
        message: "Your saved session belongs to a different tenant host. Sign in again for this environment.",
      });
      state.clearSession();
      router.replace("/login");
      return;
    }

    if (hasSession || pendingMfa) {
      return;
    }

    router.replace("/login");
  }, [hasSession, isHydrated, pendingMfa, router]);

  if (!isHydrated) {
    return (
      <div className="flex min-h-[50vh] items-center justify-center text-sm text-muted-foreground">
        Loading workspace...
      </div>
    );
  }

  if (!hasSession && !user) {
    return (
      <div className="flex min-h-[50vh] items-center justify-center text-sm text-muted-foreground">
        Loading workspace...
      </div>
    );
  }

  return <>{children}</>;
}
