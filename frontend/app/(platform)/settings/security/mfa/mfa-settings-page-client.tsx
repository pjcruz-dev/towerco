"use client";

import { useMutation } from "@tanstack/react-query";
import { useState } from "react";

import { OtpauthQrCode } from "@/components/auth/otpauth-qr-code";
import { Button } from "@/components/ui/button";
import { useOrganizationLabel } from "@/hooks/use-organization-label";
import { getErrorMessage } from "@/lib/api/error";
import {
  completeMfaEnrollment,
  regenerateRecoveryCodes,
  startMfaEnrollment,
} from "@/lib/api/modules/auth-api";
import { useNotificationStore } from "@/stores/notification-store";

/** Non-enrollable preview for tours / empty state — secret is intentionally invalid for real TOTP. */
function sampleMfaOtpauthUri(issuer: string): string {
  const safe = encodeURIComponent(issuer || "TowerOS");
  return `otpauth://totp/${safe}:sample-preview?secret=SAMPLEONLYNOTREAL&issuer=${safe}`;
}

type Props = {
  embedded?: boolean;
};

export function MfaSettingsPageClient({ embedded = false }: Props) {
  const notify = useNotificationStore((state) => state.push);
  const organizationLabel = useOrganizationLabel();
  const [setup, setSetup] = useState<{ secret: string; otpauth_uri: string } | null>(null);
  const [code, setCode] = useState("");
  const [recoveryCodes, setRecoveryCodes] = useState<string[]>([]);
  const [enrolled, setEnrolled] = useState(false);

  const startMutation = useMutation({
    mutationFn: startMfaEnrollment,
    onSuccess: setSetup,
    onError: (error) =>
      notify({ level: "error", title: "Unable to start enrollment", message: getErrorMessage(error) }),
  });

  const completeMutation = useMutation({
    mutationFn: completeMfaEnrollment,
    onSuccess: (data) => {
      setRecoveryCodes(data.recovery_codes);
      setSetup(null);
      setCode("");
      setEnrolled(true);
      notify({
        level: "success",
        title: "MFA enabled",
        message: "Store recovery codes safely. Your next sign-in will ask for an authenticator code.",
      });
    },
    onError: (error) =>
      notify({ level: "error", title: "MFA verification failed", message: getErrorMessage(error) }),
  });

  const regenMutation = useMutation({
    mutationFn: regenerateRecoveryCodes,
    onSuccess: (data) => {
      setRecoveryCodes(data.recovery_codes);
      notify({ level: "success", title: "Recovery codes regenerated" });
    },
  });

  return (
    <div className="space-y-6">
      {!embedded ? (
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">MFA Security</h1>
          <p className="text-sm text-muted-foreground">
            Enroll TOTP and manage recovery codes for enterprise account protection.
          </p>
        </div>
      ) : (
        <div>
          <h2 className="text-base font-medium text-foreground">Authenticator app</h2>
          <p className="text-sm text-muted-foreground">
            Enroll TOTP and manage recovery codes for your account.
          </p>
        </div>
      )}

      <section className="rounded-xl border bg-card p-4" data-help="ea-mfa-enroll">
        {!embedded ? <h2 className="text-lg font-semibold">TOTP Enrollment</h2> : null}
        <p className={embedded ? "text-sm text-muted-foreground" : "text-xs text-muted-foreground"}>
          Use Microsoft Authenticator, 1Password, or Google Authenticator. Prefer scanning the QR code
          with your phone camera in the app.
        </p>

        {enrolled || recoveryCodes.length > 0 ? (
          <p className="mt-3 rounded-lg border border-border bg-muted/30 px-3 py-2 text-sm text-foreground">
            Authenticator is enabled for this account. After you sign out, the next login (password,
            Microsoft, or passkey when MFA still applies) will ask for a 6-digit code from the app.
          </p>
        ) : null}

        {!setup ? (
          <div className="mt-4 space-y-4">
            {!enrolled ? (
              <div
                className="rounded-xl border border-dashed border-border bg-muted/20 p-4"
                data-help="ea-mfa-sample-qr"
              >
                <p className="mb-3 text-xs font-medium text-muted-foreground">
                  Sample preview — do not scan. Your real QR appears after Start setup.
                </p>
                <OtpauthQrCode
                  otpauthUri={sampleMfaOtpauthUri(organizationLabel)}
                  size={148}
                  hint="Example only. Click Start setup below for your personal code."
                />
              </div>
            ) : (
              <p className="text-sm text-muted-foreground" data-help="ea-mfa-sample-qr">
                You already enrolled. Choose Re-enroll authenticator to generate a new personal QR.
              </p>
            )}
            <Button
              onClick={() => startMutation.mutate()}
              disabled={startMutation.isPending}
              data-help="ea-mfa-start"
            >
              {startMutation.isPending ? "Preparing..." : enrolled ? "Re-enroll authenticator" : "Start setup"}
            </Button>
          </div>
        ) : (
          <div className="mt-4 space-y-4" data-help="ea-mfa-verify">
            <div data-help="ea-mfa-sample-qr">
              <p className="mb-2 text-xs font-medium text-foreground">Your authenticator QR</p>
              <OtpauthQrCode otpauthUri={setup.otpauth_uri} />
            </div>
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
            <div className="flex flex-wrap items-center gap-2">
              <input
                className="h-9 w-40 rounded-md border bg-background px-3 text-sm"
                placeholder="Enter 6-digit code"
                autoComplete="one-time-code"
                inputMode="numeric"
                value={code}
                onChange={(e) => setCode(e.target.value)}
              />
              <Button
                onClick={() => completeMutation.mutate(code)}
                disabled={completeMutation.isPending || code.trim().length < 6}
              >
                Verify and enable
              </Button>
            </div>
          </div>
        )}
      </section>

      <section className="rounded-xl border bg-card p-4" data-help="ea-mfa-recovery">
        <div className="flex items-center justify-between">
          <div>
            <h2 className="text-lg font-semibold">Recovery Codes</h2>
            <p className="text-xs text-muted-foreground">One-time backup codes for account recovery.</p>
          </div>
          <Button variant="outline" onClick={() => regenMutation.mutate()} disabled={regenMutation.isPending}>
            Regenerate
          </Button>
        </div>

        <div className="mt-3 grid gap-2 sm:grid-cols-2">
          {recoveryCodes.length ? (
            recoveryCodes.map((item) => (
              <div key={item} className="rounded border bg-muted/30 px-3 py-2 font-mono text-xs">
                {item}
              </div>
            ))
          ) : (
            <p className="text-sm text-muted-foreground">Recovery codes will appear after MFA enrollment.</p>
          )}
        </div>
      </section>
    </div>
  );
}
