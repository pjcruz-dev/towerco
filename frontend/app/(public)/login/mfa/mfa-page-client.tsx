"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation } from "@tanstack/react-query";
import { ArrowLeft } from "lucide-react";
import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import { useForm } from "react-hook-form";
import { z } from "zod";

import { AuthOtpInput } from "@/components/auth/auth-otp-input";
import { Button } from "@/components/ui/button";
import { getErrorMessage } from "@/lib/api/error";
import { setSessionCookie } from "@/lib/auth/session-cookie";
import { tenantPostLoginPath } from "@/lib/auth/tenant-post-login-path";
import {
  requestMfaChallenge,
  verifyMfaChallenge,
  verifyMfaRecoveryCode,
} from "@/lib/api/modules/auth-api";
import { useAuthStore } from "@/stores/auth-store";
import { useNotificationStore } from "@/stores/notification-store";

const schema = z.object({
  code: z.string().min(6).max(10),
});

type FormValues = z.infer<typeof schema>;

export function MfaPageClient() {
  const router = useRouter();
  const pendingMfa = useAuthStore((state) => state.pendingMfa);
  const setPendingMfa = useAuthStore((state) => state.setPendingMfa);
  const setSession = useAuthStore((state) => state.setSession);
  const notify = useNotificationStore((state) => state.push);
  const [recoveryCode, setRecoveryCode] = useState("");

  const form = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: { code: "" },
  });

  const codeValue = form.watch("code");

  const verifyMutation = useMutation({
    mutationFn: verifyMfaChallenge,
    onSuccess: () => {
      if (!pendingMfa) return;
      const nextSession = {
        ...pendingMfa,
        mfaRequired: false,
      };
      setSession(nextSession);
      setPendingMfa(null);
      setSessionCookie();
      router.replace(tenantPostLoginPath(nextSession));
    },
    onError: (error) =>
      notify({
        level: "error",
        title: "MFA verification failed",
        message: getErrorMessage(error),
      }),
  });

  const challengeMutation = useMutation({
    mutationFn: requestMfaChallenge,
    onSuccess: (challenge) => {
      setPendingMfa(
        pendingMfa
          ? {
              ...pendingMfa,
              mfaChallenge: challenge,
            }
          : null,
      );
      notify({
        level: "info",
        title: "New challenge issued",
        message: "Check your authenticator app for a new code.",
      });
    },
  });

  const recoveryMutation = useMutation({
    mutationFn: verifyMfaRecoveryCode,
    onSuccess: () => {
      if (!pendingMfa) return;
      const nextSession = {
        ...pendingMfa,
        mfaRequired: false,
      };
      setSession(nextSession);
      setPendingMfa(null);
      setSessionCookie();
      router.replace(tenantPostLoginPath(nextSession));
    },
    onError: (error) =>
      notify({
        level: "error",
        title: "Recovery code failed",
        message: getErrorMessage(error),
      }),
  });

  useEffect(() => {
    if (!pendingMfa?.sessionId || !pendingMfa?.mfaChallenge) {
      if (pendingMfa?.mfaEnrollmentRequired) {
        router.replace("/login/mfa/enroll");
      }
    }
  }, [pendingMfa, router]);

  if (!pendingMfa?.sessionId || !pendingMfa?.mfaChallenge) {
    if (pendingMfa?.mfaEnrollmentRequired) {
      return null;
    }
    return (
      <div className="w-full rounded-2xl border border-border bg-card p-6 shadow-sm sm:p-8">
        <p className="text-sm text-muted-foreground">MFA session not found. Please sign in again.</p>
        <Button className="mt-4" onClick={() => router.replace("/login")}>
          Back to sign in
        </Button>
      </div>
    );
  }

  return (
    <div className="w-full rounded-2xl border border-border bg-card p-6 shadow-sm sm:p-8">
      <Button
        type="button"
        variant="ghost"
        size="sm"
        className="-ml-2 mb-4 h-8 gap-1.5 text-muted-foreground"
        onClick={() => router.replace("/login")}
      >
        <ArrowLeft className="size-3.5" aria-hidden />
        Back
      </Button>

      <h1 className="text-2xl font-semibold leading-tight tracking-tight text-foreground">
        Two-factor authentication
      </h1>
      <p className="mt-1.5 text-sm text-muted-foreground">
        Enter the 6-digit code from your authenticator app to continue.
      </p>

      <form
        className="mt-8 space-y-6"
        onSubmit={form.handleSubmit(({ code }) =>
          verifyMutation.mutate({
            challengeId: pendingMfa.mfaChallenge!.id,
            sessionId: pendingMfa.sessionId!,
            code,
          }),
        )}
      >
        <AuthOtpInput
          value={codeValue}
          onChange={(next) => form.setValue("code", next, { shouldValidate: true })}
          disabled={verifyMutation.isPending}
          autoFocus
          aria-invalid={Boolean(form.formState.errors.code)}
        />
        {form.formState.errors.code ? (
          <p className="text-center text-xs text-destructive">Enter a valid 6-digit code.</p>
        ) : null}

        <Button className="w-full" disabled={verifyMutation.isPending || codeValue.length < 6} type="submit">
          {verifyMutation.isPending ? "Verifying…" : "Confirm verification"}
        </Button>

        <p className="text-center text-sm text-muted-foreground">
          Didn&apos;t get a code?{" "}
          <button
            type="button"
            className="font-medium text-sky-700 hover:underline dark:text-sky-400"
            disabled={challengeMutation.isPending}
            onClick={() => challengeMutation.mutate(pendingMfa.sessionId!)}
          >
            {challengeMutation.isPending ? "Requesting…" : "Tap to resend"}
          </button>
        </p>
      </form>

      <div className="mt-8 border-t border-border pt-5">
        <p className="text-sm font-medium text-foreground">Use a recovery code</p>
        <div className="mt-2 flex gap-2">
          <input
            className="h-10 flex-1 rounded-md border border-border bg-background px-3 text-sm"
            value={recoveryCode}
            onChange={(event) => setRecoveryCode(event.target.value)}
            placeholder="ABCD-EFGH"
          />
          <Button
            variant="outline"
            disabled={recoveryMutation.isPending || !recoveryCode.trim()}
            onClick={() =>
              recoveryMutation.mutate({
                sessionId: pendingMfa.sessionId!,
                recoveryCode: recoveryCode.trim(),
              })
            }
          >
            Recover
          </Button>
        </div>
      </div>
    </div>
  );
}
