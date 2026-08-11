"use client";

import { useMutation } from "@tanstack/react-query";
import { useState } from "react";

import { OtpauthQrCode } from "@/components/auth/otpauth-qr-code";
import { FormInput } from "@/components/forms/form-input";
import { Button } from "@/components/ui/button";
import { getErrorMessage } from "@/lib/api/error";
import {
  platformMfaEnrollComplete,
  platformMfaEnrollStart,
  platformMfaRecovery,
  platformMfaVerify,
  type PlatformAuthSession,
} from "@/lib/api/modules/platform-api";
import { usePlatformAuthStore } from "@/stores/platform-auth-store";
import { useNotificationStore } from "@/stores/notification-store";

type Props = {
  pending: {
    login_session_id: string;
    mfa_enrollment_required: boolean;
    mfa_challenge?: { id: string; expires_at: string };
    otpauth_uri?: string;
    secret?: string;
  };
  onBack: () => void;
  onComplete: () => void;
};

export function PlatformLoginMfaPanel({ pending, onBack, onComplete }: Props) {
  const notify = useNotificationStore((s) => s.push);
  const setSession = usePlatformAuthStore((s) => s.setSession);
  const [code, setCode] = useState("");
  const [recoveryCode, setRecoveryCode] = useState("");
  const [useRecovery, setUseRecovery] = useState(false);
  const [enrollSecret, setEnrollSecret] = useState(pending.secret ?? "");
  const [enrollOtpauthUri, setEnrollOtpauthUri] = useState(pending.otpauth_uri ?? "");
  const [recoveryCodes, setRecoveryCodes] = useState<string[]>([]);

  const finish = (data: PlatformAuthSession) => {
    if (data.access_token && data.user) {
      setSession({ access_token: data.access_token, user: data.user });
      onComplete();
    }
  };

  const verifyMutation = useMutation({
    mutationFn: () =>
      platformMfaVerify({
        login_session_id: pending.login_session_id,
        challenge_id: pending.mfa_challenge!.id,
        code,
      }),
    onSuccess: finish,
    onError: (error) =>
      notify({ level: "error", title: "MFA verification failed", message: getErrorMessage(error) }),
  });

  const recoveryMutation = useMutation({
    mutationFn: () =>
      platformMfaRecovery({
        login_session_id: pending.login_session_id,
        recovery_code: recoveryCode,
      }),
    onSuccess: finish,
    onError: (error) =>
      notify({ level: "error", title: "Recovery failed", message: getErrorMessage(error) }),
  });

  const enrollStartMutation = useMutation({
    mutationFn: () => platformMfaEnrollStart(pending.login_session_id),
    onSuccess: (data) => {
      setEnrollSecret(data.secret);
      setEnrollOtpauthUri(data.otpauth_uri);
    },
    onError: (error) =>
      notify({ level: "error", title: "Enrollment failed", message: getErrorMessage(error) }),
  });

  const enrollCompleteMutation = useMutation({
    mutationFn: () => platformMfaEnrollComplete({ login_session_id: pending.login_session_id, code }),
    onSuccess: (data) => {
      if (data.recovery_codes?.length) {
        setRecoveryCodes(data.recovery_codes);
      }
      finish(data);
    },
    onError: (error) =>
      notify({ level: "error", title: "Enrollment failed", message: getErrorMessage(error) }),
  });

  if (recoveryCodes.length > 0) {
    return (
      <div className="space-y-4">
        <p className="text-sm text-muted-foreground">
          Save these recovery codes in a secure location. Each code can be used once.
        </p>
        <ul className="rounded-lg border border-border bg-muted/20 p-3 font-mono text-xs">
          {recoveryCodes.map((item) => (
            <li key={item}>{item}</li>
          ))}
        </ul>
        <Button type="button" className="w-full" onClick={onComplete}>
          Continue to console
        </Button>
      </div>
    );
  }

  if (pending.mfa_enrollment_required) {
    return (
      <div className="space-y-4">
        <p className="text-sm text-muted-foreground">
          Platform MFA is required. Enroll an authenticator app to continue.
        </p>
        {!enrollSecret ? (
          <Button
            type="button"
            className="w-full"
            disabled={enrollStartMutation.isPending}
            onClick={() => enrollStartMutation.mutate()}
          >
            {enrollStartMutation.isPending ? "Preparing…" : "Start MFA enrollment"}
          </Button>
        ) : (
          <>
            {enrollOtpauthUri ? <OtpauthQrCode otpauthUri={enrollOtpauthUri} /> : null}
            <details className="rounded-lg border border-border bg-muted/20 px-3 py-2">
              <summary className="cursor-pointer text-sm font-medium text-foreground">
                Can&apos;t scan? Enter key manually
              </summary>
              <p className="mt-2 break-all font-mono text-xs text-muted-foreground">{enrollSecret}</p>
            </details>
            <FormInput
              label="Verification code"
              inputMode="numeric"
              autoComplete="one-time-code"
              value={code}
              onChange={(event) => setCode(event.target.value)}
            />
            <Button
              type="button"
              className="w-full"
              disabled={code.length !== 6 || enrollCompleteMutation.isPending}
              onClick={() => enrollCompleteMutation.mutate()}
            >
              {enrollCompleteMutation.isPending ? "Verifying…" : "Complete enrollment"}
            </Button>
          </>
        )}
        <Button type="button" variant="outline" className="w-full" onClick={onBack}>
          Back
        </Button>
      </div>
    );
  }

  return (
    <div className="space-y-4">
      <p className="text-sm text-muted-foreground">
        Enter the 6-digit code from your authenticator app.
      </p>
      {!useRecovery ? (
        <>
          <FormInput
            label="Authentication code"
            inputMode="numeric"
            autoComplete="one-time-code"
            value={code}
            onChange={(event) => setCode(event.target.value)}
          />
          <Button
            type="button"
            className="w-full"
            disabled={code.length !== 6 || verifyMutation.isPending}
            onClick={() => verifyMutation.mutate()}
          >
            {verifyMutation.isPending ? "Verifying…" : "Verify and sign in"}
          </Button>
          <button
            type="button"
            className="text-xs text-primary underline-offset-4 hover:underline"
            onClick={() => setUseRecovery(true)}
          >
            Use a recovery code
          </button>
        </>
      ) : (
        <>
          <FormInput
            label="Recovery code"
            value={recoveryCode}
            onChange={(event) => setRecoveryCode(event.target.value)}
          />
          <Button
            type="button"
            className="w-full"
            disabled={!recoveryCode || recoveryMutation.isPending}
            onClick={() => recoveryMutation.mutate()}
          >
            {recoveryMutation.isPending ? "Verifying…" : "Verify recovery code"}
          </Button>
          <button
            type="button"
            className="text-xs text-primary underline-offset-4 hover:underline"
            onClick={() => setUseRecovery(false)}
          >
            Use authenticator code
          </button>
        </>
      )}
      <Button type="button" variant="outline" className="w-full" onClick={onBack}>
        Back
      </Button>
    </div>
  );
}
