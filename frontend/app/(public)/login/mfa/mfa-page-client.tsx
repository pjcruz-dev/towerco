"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation } from "@tanstack/react-query";
import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import { useForm } from "react-hook-form";
import { z } from "zod";

import { FormInput } from "@/components/forms/form-input";
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
        message: "Check your registered MFA channel.",
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
      <div className="w-full max-w-md rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
          <p className="text-sm text-muted-foreground">
            MFA session not found. Please login again.
          </p>
          <Button className="mt-4" onClick={() => router.replace("/login")}>
            Back to login
          </Button>
      </div>
    );
  }

  return (
      <div className="w-full max-w-md rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <h1 className="text-[30px] font-semibold leading-tight tracking-tight text-slate-900 dark:text-slate-50">
          Multi-factor verification
        </h1>
        <p className="mt-2 text-sm text-slate-600 dark:text-slate-400">
          Enter the verification code to complete sign in.
        </p>
        <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
          If you have not enrolled yet, sign in and complete enrollment in Security settings.
        </p>

        <form
          className="mt-6 space-y-4"
          onSubmit={form.handleSubmit(({ code }) =>
            verifyMutation.mutate({
              challengeId: pendingMfa.mfaChallenge!.id,
              sessionId: pendingMfa.sessionId!,
              code,
            }),
          )}
        >
          <FormInput
            label="Verification code"
            autoComplete="one-time-code"
            {...form.register("code")}
            error={form.formState.errors.code}
          />
          <Button className="w-full" disabled={verifyMutation.isPending} type="submit">
            {verifyMutation.isPending ? "Verifying..." : "Verify"}
          </Button>
          <Button
            className="w-full"
            variant="outline"
            type="button"
            disabled={challengeMutation.isPending}
            onClick={() => challengeMutation.mutate(pendingMfa.sessionId!)}
          >
            Request new code
          </Button>
        </form>

        <div className="mt-6 border-t pt-4">
          <p className="text-sm font-medium">Use a recovery code</p>
          <div className="mt-2 flex gap-2">
            <input
              className="h-10 flex-1 rounded-md border bg-background px-3 text-sm"
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
