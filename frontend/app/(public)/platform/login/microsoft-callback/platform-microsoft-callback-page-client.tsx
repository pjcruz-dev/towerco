"use client";

import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";

import { PlatformLoginMfaPanel } from "@/components/platform/platform-login-mfa-panel";
import { usePlatformAuthStore, type PlatformUser } from "@/stores/platform-auth-store";

export function PlatformMicrosoftCallbackPageClient() {
  const router = useRouter();
  const setSession = usePlatformAuthStore((s) => s.setSession);
  const [message, setMessage] = useState("Completing Microsoft sign-in…");
  const [mfaPending, setMfaPending] = useState<{
    login_session_id: string;
    mfa_enrollment_required: boolean;
    mfa_challenge?: { id: string; expires_at: string };
  } | null>(null);

  useEffect(() => {
    const hash = window.location.hash.slice(1);
    if (!hash) {
      setMessage("Missing SSO handoff payload.");
      router.replace("/platform/login");
      return;
    }

    try {
      const parsed = JSON.parse(atob(hash)) as {
        access_token?: string;
        user?: PlatformUser;
        mfa_required?: boolean;
        login_session_id?: string;
        mfa_enrollment_required?: boolean;
        mfa_challenge?: { id: string; expires_at: string };
      };

      if (parsed.mfa_required && parsed.login_session_id) {
        setMfaPending({
          login_session_id: parsed.login_session_id,
          mfa_enrollment_required: Boolean(parsed.mfa_enrollment_required),
          mfa_challenge: parsed.mfa_challenge,
        });
        setMessage("");
        return;
      }

      if (!parsed.access_token || !parsed.user) {
        throw new Error("Invalid handoff");
      }

      setSession({ access_token: parsed.access_token, user: parsed.user });
      router.replace("/platform");
    } catch {
      setMessage("Microsoft sign-in handoff failed.");
      router.replace("/platform/login");
    }
  }, [router, setSession]);

  if (mfaPending) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-background px-6">
        <div className="w-full max-w-md rounded-xl border border-border bg-card p-6 shadow-sm">
          <h1 className="text-base font-medium text-foreground">Verify your identity</h1>
          <p className="mt-1 text-sm text-muted-foreground">
            Complete MFA to finish Microsoft sign-in.
          </p>
          <div className="mt-4">
            <PlatformLoginMfaPanel
              pending={mfaPending}
              onBack={() => router.replace("/platform/login")}
              onComplete={() => router.replace("/platform")}
            />
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-background px-6 text-sm text-muted-foreground">
      {message}
    </div>
  );
}
