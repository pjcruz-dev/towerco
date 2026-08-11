"use client";

import { useRouter, useSearchParams } from "next/navigation";
import { Suspense, useEffect } from "react";

import { Button } from "@/components/ui/button";
import { setSessionCookie } from "@/lib/auth/session-cookie";
import { normalizeAuthSession } from "@/modules/identity/auth-normalizer";
import { useAuthStore } from "@/stores/auth-store";

function decodePayload(value: string): unknown {
  const padded = value + "=".repeat((4 - (value.length % 4 || 4)) % 4);
  const base64 = padded.replace(/-/g, "+").replace(/_/g, "/");
  const decoded = atob(base64);
  return JSON.parse(decoded);
}

function SsoCallbackContent() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const setSession = useAuthStore((state) => state.setSession);
  const beginMfaLogin = useAuthStore((state) => state.beginMfaLogin);

  useEffect(() => {
    const payload = searchParams.get("payload");
    if (!payload) return;

    try {
      const raw = decodePayload(payload);
      const session = normalizeAuthSession(raw);

      if (session.mfaRequired) {
        beginMfaLogin(session);
        router.replace(session.mfaEnrollmentRequired ? "/login/mfa/enroll" : "/login/mfa");
        return;
      }

      setSession(session);
      setSessionCookie();
      router.replace("/dashboard");
    } catch {
      router.replace("/login");
    }
  }, [beginMfaLogin, router, searchParams, setSession]);

  return (
    <div className="w-full max-w-md rounded-xl border border-slate-200 bg-white p-6 text-center shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <h1 className="text-[30px] font-semibold leading-tight tracking-tight text-slate-900 dark:text-slate-50">
          Signing you in…
        </h1>
        <p className="mt-2 text-sm text-slate-600 dark:text-slate-400">
          Completing Microsoft Entra authentication.
        </p>
        <Button className="mt-4" variant="outline" onClick={() => router.replace("/login")}>
          Back to login
        </Button>
      </div>
  );
}

export function SsoCallbackPageClient() {
  return (
    <Suspense
      fallback={
        <div className="w-full max-w-md rounded-xl border border-slate-200 bg-white p-6 text-center text-sm text-slate-500 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400">
          Loading…
        </div>
      }
    >
      <SsoCallbackContent />
    </Suspense>
  );
}
