"use client";

import "@/lib/runtime/ensure-crypto-random-uuid";

import { QueryClientProvider } from "@tanstack/react-query";
import { isAxiosError } from "axios";
import { usePathname, useRouter } from "next/navigation";
import { useEffect, useRef, useState } from "react";

import { PostHydrationAuthRedirect } from "@/components/auth/post-hydration-auth-redirect";
import { AcronymProvider } from "@/components/help/acronym-provider";
import { NotificationCenter } from "@/components/feedback/notification-center";
import { TenantThemeBridge } from "@/components/providers/tenant-theme-bridge";
import { TooltipProvider } from "@/components/ui/tooltip";
import { setLoginNotice } from "@/lib/auth/login-notice";
import { me } from "@/lib/api/modules/auth-api";
import { sessionMatchesCurrentTenant } from "@/lib/auth/tenant-session";
import { createQueryClient } from "@/lib/query/query-client";
import { getSocket, isSocketEnabled } from "@/lib/socket/socket-client";
import { ThemeProvider } from "@/lib/theme/theme-provider";
import { useAuthStore } from "@/stores/auth-store";
import { useNotificationStore } from "@/stores/notification-store";

export function AppProviders({ children }: { children: React.ReactNode }) {
  const pathname = usePathname();
  const router = useRouter();
  const [queryClient] = useState(createQueryClient);
  const hydrate = useAuthStore((state) => state.hydrate);
  const isHydrated = useAuthStore((state) => state.isHydrated);
  const token = useAuthStore((state) => state.accessToken);
  const pendingMfa = useAuthStore((state) => state.pendingMfa);
  const setUser = useAuthStore((state) => state.setUser);
  const clearSession = useAuthStore((state) => state.clearSession);
  const push = useNotificationStore((state) => state.push);
  const sessionSyncWarned = useRef(false);

  useEffect(() => {
    hydrate();
  }, [hydrate]);

  useEffect(() => {
    if (!isSocketEnabled()) {
      return undefined;
    }

    const socket = getSocket();
    const socketToken = token ?? pendingMfa?.accessToken ?? null;
    if (socketToken) {
      socket.auth = { token: socketToken };
      socket.connect();
      return () => {
        socket.disconnect();
      };
    }
    socket.disconnect();
    return undefined;
  }, [pendingMfa?.accessToken, token]);

  useEffect(() => {
    if (!isHydrated || !token || pendingMfa) {
      return undefined;
    }

    const onAuthScreen =
      pathname === "/login" ||
      pathname.startsWith("/login/") ||
      pathname === "/platform/login" ||
      pathname.startsWith("/platform/login/");

    if (onAuthScreen) {
      return undefined;
    }

    if (useAuthStore.getState().user) {
      return undefined;
    }

    const state = useAuthStore.getState();
    if (!sessionMatchesCurrentTenant(state)) {
      setLoginNotice({
        level: "warning",
        title: "Wrong tenant session",
        message: "Your saved session belongs to a different tenant host. Sign in again for this environment.",
      });
      clearSession();
      return undefined;
    }

    let cancelled = false;

    me()
      .then((nextUser) => {
        if (!cancelled && nextUser) {
          setUser(nextUser);
          useAuthStore.getState().syncTenantContext();
        }
      })
      .catch((error: unknown) => {
        if (cancelled) {
          return;
        }

        const status = isAxiosError(error) ? error.response?.status : undefined;
        const message =
          isAxiosError(error) && typeof error.response?.data === "object"
            ? String((error.response.data as { message?: string }).message ?? "")
            : "";

        if (status === 403 && message.toLowerCase().includes("mfa")) {
          router.replace("/login/mfa");
          return;
        }

        if (status !== 401) {
          return;
        }

        setLoginNotice({
          level: "warning",
          title: "Session expired",
          message: "Please sign in again.",
        });
        clearSession();
        if (!sessionSyncWarned.current) {
          sessionSyncWarned.current = true;
          push({
            level: "warning",
            title: "Session expired",
            message: "Please sign in again.",
          });
        }
        router.replace("/login");
      });

    return () => {
      cancelled = true;
    };
  }, [clearSession, isHydrated, pathname, pendingMfa, push, router, setUser, token]);

  return (
    <ThemeProvider>
      <TenantThemeBridge />
      <TooltipProvider delay={0}>
        <QueryClientProvider client={queryClient}>
          <AcronymProvider>
            <PostHydrationAuthRedirect />
            {children}
            <NotificationCenter />
          </AcronymProvider>
        </QueryClientProvider>
      </TooltipProvider>
    </ThemeProvider>
  );
}
