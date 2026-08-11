"use client";

import { useEffect, useState } from "react";
import { useRouter, useSearchParams } from "next/navigation";

import { redeemWorkspaceEnvironmentHandoff } from "@/lib/api/modules/workspace-environments-api";
import { setSessionCookie } from "@/lib/auth/session-cookie";
import { getApiFieldErrors, getErrorMessage } from "@/lib/api/error";
import { normalizeAuthSession } from "@/modules/identity/auth-normalizer";
import {
  clearEnvSwitchActorEmail,
  resolveEnvSwitchLoginEmail,
} from "@/lib/tenant/environment-switch";
import { tenantDomainFromBrowserHostname } from "@/lib/tenant/resolve-tenant-domain";
import { useAuthStore } from "@/stores/auth-store";

export function EnvironmentHandoffPageClient() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const [message, setMessage] = useState("Switching environment…");

  useEffect(() => {
    const ticket = searchParams.get("ticket")?.trim() ?? "";
    if (!ticket) {
      setMessage("Missing switch ticket.");
      router.replace("/login");
      return;
    }

    const browserDomain = tenantDomainFromBrowserHostname(window.location.hostname);
    if (!browserDomain) {
      setMessage("Open this link on the target organization hostname.");
      router.replace("/login");
      return;
    }

    let cancelled = false;

    (async () => {
      try {
        const raw = await redeemWorkspaceEnvironmentHandoff(ticket);
        if (cancelled) {
          return;
        }

        const session = normalizeAuthSession(raw);
        if (!session.accessToken || !session.refreshToken || !session.user) {
          setMessage("Invalid switch response.");
          router.replace("/login");
          return;
        }

        const expectedDomain = session.user.tenantDomain?.toLowerCase();
        if (expectedDomain && browserDomain !== expectedDomain) {
          setMessage("Tenant domain mismatch.");
          router.replace("/login");
          return;
        }

        // Strip ticket from the address bar before storing the session.
        window.history.replaceState({}, "", "/auth/environment-handoff");
        useAuthStore.getState().clearSession();
        clearEnvSwitchActorEmail();

        if (session.mfaRequired) {
          useAuthStore.getState().beginMfaLogin(session);
          router.replace(session.mfaEnrollmentRequired ? "/login/mfa/enroll" : "/login/mfa");
          return;
        }

        useAuthStore.getState().setSession(session);
        setSessionCookie();
        window.location.assign("/dashboard");
      } catch (error) {
        if (cancelled) {
          return;
        }
        const detail = getErrorMessage(error);
        setMessage(detail);

        const fieldEmail = getApiFieldErrors(error).login_email?.trim().toLowerCase() ?? "";
        const loginEmail =
          fieldEmail || resolveEnvSwitchLoginEmail(detail, browserDomain);
        const params = new URLSearchParams();
        if (loginEmail) {
          params.set("email", loginEmail);
        }
        const query = params.toString();

        window.setTimeout(() => {
          router.replace(query ? `/login?${query}` : "/login");
        }, 3200);
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [router, searchParams]);

  return (
    <div className="flex min-h-screen flex-col items-center justify-center gap-2 bg-background px-6 text-center text-sm text-muted-foreground">
      <p>{message}</p>
      <p className="max-w-md text-[11px] leading-snug">
        Seamless switch needs the same email on the other environment. If you use Microsoft sign-in,
        enable SSO there or ask an admin to add your account.
      </p>
    </div>
  );
}
