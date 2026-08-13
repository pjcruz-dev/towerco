"use client";

import { useMutation } from "@tanstack/react-query";
import { useRouter } from "next/navigation";
import { useEffect, useRef, useState } from "react";

import { OtpauthQrCode } from "@/components/auth/otpauth-qr-code";
import { Button } from "@/components/ui/button";
import { getErrorMessage } from "@/lib/api/error";
import { setSessionCookie } from "@/lib/auth/session-cookie";
import { tenantPostLoginPath } from "@/lib/auth/tenant-post-login-path";
import { completeMfaEnrollment, startMfaEnrollment } from "@/lib/api/modules/auth-api";
import { useAuthStore } from "@/stores/auth-store";
import { useNotificationStore } from "@/stores/notification-store";

export function MfaEnrollPageClient() {
  const router = useRouter();
  const pendingMfa = useAuthStore((state) => state.pendingMfa);
  const setSession = useAuthStore((state) => state.setSession);
  const setPendingMfa = useAuthStore((state) => state.setPendingMfa);
  const notify = useNotificationStore((state) => state.push);
  const [setup, setSetup] = useState<{ secret: string; otpauth_uri: string } | null>(null);
  const [code, setCode] = useState("");
  const [recoveryCodes, setRecoveryCodes] = useState<string[]>([]);
  const enrollmentStarted = useRef(false);

  const startMutation = useMutation({
    mutationFn: startMfaEnrollment,
    onSuccess: setSetup,
    onError: (error) =>
      notify({
        level: "error",
        title: "Unable to start MFA setup",
        message: getErrorMessage(error),
      }),
  });

  const completeMutation = useMutation({
    mutationFn: completeMfaEnrollment,
    onSuccess: (data) => {
      if (!pendingMfa) return;
      setRecoveryCodes(data.recovery_codes);
      const nextSession = {
        ...pendingMfa,
        mfaRequired: false,
        mfaEnrollmentRequired: false,
      };
      setSession(nextSession);
      setPendingMfa(null);
      setSessionCookie();
      notify({
        level: "success",
        title: "MFA enabled",
        message: "Save your recovery codes, then continue to the workspace.",
      });
    },
    onError: (error) =>
      notify({
        level: "error",
        title: "MFA verification failed",
        message: getErrorMessage(error),
      }),
  });

  useEffect(() => {
    if (!pendingMfa?.accessToken) {
      router.replace("/login");
      return;
    }
    if (!pendingMfa.mfaEnrollmentRequired) {
      router.replace("/login/mfa");
      return;
    }
    if (!enrollmentStarted.current) {
      enrollmentStarted.current = true;
      startMutation.mutate();
    }
  }, [pendingMfa, router, startMutation]);

  if (!pendingMfa?.accessToken) {
    return null;
  }

  return (
    <div className="w-full max-w-md rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
      <h1 className="text-2xl font-semibold tracking-tight text-foreground">Set up MFA</h1>
      <p className="mt-2 text-sm text-muted-foreground">
        Your organization requires multi-factor authentication. Scan the QR code with Microsoft
        Authenticator (or another TOTP app), then enter the 6-digit code to finish sign-in.
      </p>

      {startMutation.isPending && !setup ? (
        <p className="mt-6 text-sm text-muted-foreground">Preparing enrollment…</p>
      ) : null}

      {setup ? (
        <div className="mt-6 space-y-4">
          <OtpauthQrCode otpauthUri={setup.otpauth_uri} />
          <details className="rounded-lg border border-border bg-muted/20 px-3 py-2">
            <summary className="cursor-pointer text-sm font-medium text-foreground">
              Can&apos;t scan? Enter key manually
            </summary>
            <div className="mt-2 space-y-1">
              <p className="text-sm">
                Secret: <span className="font-mono text-xs break-all">{setup.secret}</span>
              </p>
              <p className="break-all text-xs text-muted-foreground">{setup.otpauth_uri}</p>
            </div>
          </details>
          <input
            className="h-10 w-full rounded-md border bg-background px-3 text-sm"
            placeholder="Enter 6-digit code"
            autoComplete="one-time-code"
            value={code}
            onChange={(event) => setCode(event.target.value)}
          />
          <Button
            className="w-full"
            disabled={completeMutation.isPending || code.trim().length < 6}
            onClick={() => completeMutation.mutate(code)}
          >
            {completeMutation.isPending ? "Verifying…" : "Verify and continue"}
          </Button>
        </div>
      ) : null}

      {recoveryCodes.length > 0 ? (
        <div className="mt-6 space-y-3 border-t pt-4">
          <p className="text-sm font-medium">Recovery codes</p>
          <p className="text-xs text-muted-foreground">Store these in a secure place. Each code works once.</p>
          <div className="grid gap-2 sm:grid-cols-2">
            {recoveryCodes.map((item) => (
              <div key={item} className="rounded border bg-muted/30 px-3 py-2 font-mono text-xs">
                {item}
              </div>
            ))}
          </div>
          <Button
            className="w-full"
            onClick={() => {
              const state = useAuthStore.getState();
              router.replace(
                tenantPostLoginPath({
                  passkeyEnrollmentRequired: state.passkeyEnrollmentRequired,
                }),
              );
            }}
          >
            Continue
          </Button>
        </div>
      ) : null}
    </div>
  );
}
