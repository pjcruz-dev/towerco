"use client";

import { useMutation } from "@tanstack/react-query";
import { useRouter, useSearchParams } from "next/navigation";
import { Suspense, useEffect, useRef, useState } from "react";

import { OtpauthQrCode } from "@/components/auth/otpauth-qr-code";
import { AuthOtpInput } from "@/components/auth/auth-otp-input";
import { LiveProductTourHost } from "@/components/help/live-product-tour-host";
import { Button } from "@/components/ui/button";
import { getErrorMessage } from "@/lib/api/error";
import { setSessionCookie } from "@/lib/auth/session-cookie";
import { tenantPostLoginPath } from "@/lib/auth/tenant-post-login-path";
import { completeMfaEnrollment, startMfaEnrollment } from "@/lib/api/modules/auth-api";
import {
  dismissLiveTourPrompt,
  hasDismissedLiveTourPrompt,
} from "@/lib/help/e-approval-tour-prompt-preference";
import { LIVE_TOUR_QUERY } from "@/lib/help/e-approval-live-tour";
import {
  MFA_LOGIN_LIVE_TOUR_ID,
  mfaLoginTourStartHref,
} from "@/lib/help/mfa-live-tour";
import { useAuthStore } from "@/stores/auth-store";
import { useNotificationStore } from "@/stores/notification-store";

function MfaEnrollPageInner() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const pendingMfa = useAuthStore((state) => state.pendingMfa);
  const setSession = useAuthStore((state) => state.setSession);
  const setPendingMfa = useAuthStore((state) => state.setPendingMfa);
  const notify = useNotificationStore((state) => state.push);
  const [setup, setSetup] = useState<{ secret: string; otpauth_uri: string } | null>(null);
  const [code, setCode] = useState("");
  const [recoveryCodes, setRecoveryCodes] = useState<string[]>([]);
  const enrollmentStarted = useRef(false);
  const autoTourStarted = useRef(false);

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
      // Advance coach mark to recovery codes when the login tour is active.
      if (searchParams.get(LIVE_TOUR_QUERY) === MFA_LOGIN_LIVE_TOUR_ID) {
        window.setTimeout(() => {
          router.replace(mfaLoginTourStartHref(5));
        }, 50);
      }
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

  // Auto-start first-login MFA coach marks once the QR is ready (unless dismissed).
  useEffect(() => {
    if (!setup || autoTourStarted.current) {
      return;
    }
    if (searchParams.get(LIVE_TOUR_QUERY)) {
      autoTourStarted.current = true;
      return;
    }
    const userId = pendingMfa?.user?.id ?? "mfa-enroll";
    const tenantId = pendingMfa?.user?.tenantId ?? null;
    if (hasDismissedLiveTourPrompt(MFA_LOGIN_LIVE_TOUR_ID, userId, tenantId)) {
      autoTourStarted.current = true;
      return;
    }
    autoTourStarted.current = true;
    router.replace(mfaLoginTourStartHref(0));
  }, [pendingMfa?.user?.id, pendingMfa?.user?.tenantId, router, searchParams, setup]);

  if (!pendingMfa?.accessToken) {
    return null;
  }

  return (
    <>
      <LiveProductTourHost />
      <div className="w-full rounded-2xl border border-border bg-card p-6 shadow-sm sm:p-8">
        <h1 className="text-2xl font-semibold tracking-tight text-foreground">Set up MFA</h1>
        <p className="mt-1.5 text-sm text-muted-foreground">
          Complete authenticator setup before your workspace opens.
        </p>
        <ol
          className="mt-4 list-decimal space-y-1 pl-4 text-sm text-muted-foreground"
          data-help="ea-mfa-login-enroll"
        >
          <li>Scan the QR with your authenticator app (Microsoft, Google, or 1Password).</li>
          <li>Enter the 6-digit code from the app.</li>
          <li>Save recovery codes, then continue to the workspace.</li>
        </ol>
        <p className="mt-2 text-xs text-muted-foreground">
          Not email-only: this also appears after{" "}
          <span className="font-medium text-foreground">Sign in with Microsoft</span> when MFA is
          required and you have not enrolled yet. Passkey sign-in may skip this step if your
          organization treats passkeys as MFA.
        </p>

        {startMutation.isPending && !setup ? (
          <p className="mt-6 text-sm text-muted-foreground">Preparing enrollment…</p>
        ) : null}

        {setup ? (
          <div className="mt-6 space-y-4">
            <div data-help="ea-mfa-login-qr">
              <p className="mb-2 text-xs font-medium text-foreground">1. Scan this QR</p>
              <OtpauthQrCode otpauthUri={setup.otpauth_uri} />
            </div>
            <details
              className="rounded-lg border border-border bg-muted/20 px-3 py-2"
              data-help="ea-mfa-login-manual"
            >
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
            <div data-help="ea-mfa-login-code">
              <p className="mb-3 text-xs font-medium text-foreground">2. Enter the 6-digit code</p>
              <AuthOtpInput value={code} onChange={setCode} disabled={completeMutation.isPending} />
            </div>
            <Button
              className="w-full"
              data-help="ea-mfa-login-verify"
              disabled={completeMutation.isPending || code.replace(/\D/g, "").length < 6}
              onClick={() => completeMutation.mutate(code.replace(/\D/g, "").slice(0, 6))}
            >
              {completeMutation.isPending ? "Verifying…" : "Confirm verification"}
            </Button>
          </div>
        ) : null}

        {recoveryCodes.length > 0 ? (
          <div className="mt-6 space-y-3 border-t pt-4" data-help="ea-mfa-login-recovery">
            <p className="text-sm font-medium">3. Recovery codes</p>
            <p className="text-xs text-muted-foreground">
              Store these in a secure place. Each code works once.
            </p>
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
                const userId = state.user?.id ?? pendingMfa.user?.id ?? "mfa-enroll";
                const tenantId = state.activeTenantId ?? pendingMfa.user?.tenantId ?? null;
                dismissLiveTourPrompt(MFA_LOGIN_LIVE_TOUR_ID, userId, tenantId);
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
        ) : (
          <div className="sr-only" data-help="ea-mfa-login-recovery" aria-hidden>
            Recovery codes appear after verify
          </div>
        )}

        {!searchParams.get(LIVE_TOUR_QUERY) && setup && recoveryCodes.length === 0 ? (
          <button
            type="button"
            className="mt-4 text-xs text-sky-600 hover:underline"
            onClick={() => {
              autoTourStarted.current = true;
              router.replace(mfaLoginTourStartHref(0));
            }}
          >
            Show setup tips
          </button>
        ) : null}
      </div>
    </>
  );
}

export function MfaEnrollPageClient() {
  return (
    <Suspense fallback={null}>
      <MfaEnrollPageInner />
    </Suspense>
  );
}
