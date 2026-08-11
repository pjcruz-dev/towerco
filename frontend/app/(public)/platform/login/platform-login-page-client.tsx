"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation } from "@tanstack/react-query";
import { useRouter, useSearchParams } from "next/navigation";
import { useEffect, useState } from "react";
import { useForm, type FieldErrors } from "react-hook-form";
import { z } from "zod";

import { LoginNoticeBanner } from "@/components/feedback/login-notice-banner";
import { FormInput } from "@/components/forms/form-input";
import { Button } from "@/components/ui/button";
import { getErrorMessage } from "@/lib/api/error";
import { PlatformLoginMfaPanel } from "@/components/platform/platform-login-mfa-panel";
import { platformLogin } from "@/lib/api/modules/platform-api";
import {
  consumeLoginNotice,
  type LoginNotice,
} from "@/lib/auth/login-notice";
import { usePlatformAuthStore } from "@/stores/platform-auth-store";
import { useNotificationStore } from "@/stores/notification-store";

const schema = z.object({
  email: z.email(),
  password: z.string().min(8),
});

type FormValues = z.infer<typeof schema>;

export function PlatformLoginPageClient() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const notify = useNotificationStore((state) => state.push);
  const setSession = usePlatformAuthStore((state) => state.setSession);
  const accessToken = usePlatformAuthStore((state) => state.accessToken);
  const isHydrated = usePlatformAuthStore((state) => state.isHydrated);

  const [loginNotice, setLoginNoticeState] = useState<LoginNotice | null>(null);
  const [mfaPending, setMfaPending] = useState<{
    login_session_id: string;
    mfa_enrollment_required: boolean;
    mfa_challenge?: { id: string; expires_at: string };
  } | null>(null);

  const showLoginNotice = (notice: LoginNotice) => {
    setLoginNoticeState(notice);
    notify(notice);
  };

  useEffect(() => {
    const ssoError = searchParams.get("sso_error");
    if (ssoError) {
      showLoginNotice({
        level: "error",
        title: "Microsoft sign-in failed",
        message: ssoError,
      });
    }
  }, [searchParams]);

  useEffect(() => {
    const stored = consumeLoginNotice();
    if (stored) {
      setLoginNoticeState(stored);
      notify(stored);
    }
  }, [notify]);

  useEffect(() => {
    if (!isHydrated) {
      usePlatformAuthStore.getState().hydrate();
      return;
    }
    if (accessToken) {
      router.replace("/platform");
    }
  }, [accessToken, isHydrated, router]);

  const form = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: { email: "", password: "" },
  });

  const mutation = useMutation({
    mutationFn: platformLogin,
    onSuccess: (data) => {
      if (data.mfa_required && data.login_session_id) {
        setMfaPending({
          login_session_id: data.login_session_id,
          mfa_enrollment_required: Boolean(data.mfa_enrollment_required),
          mfa_challenge: data.mfa_challenge,
        });
        return;
      }
      if (!data.access_token || !data.user) {
        showLoginNotice({
          level: "error",
          title: "Sign-in incomplete",
          message: "The server did not return a session token.",
        });
        return;
      }
      setSession({ access_token: data.access_token, user: data.user });
      router.replace("/platform");
    },
    onError: (error) => {
      showLoginNotice({
        level: "error",
        title: "Superadmin sign-in failed",
        message: getErrorMessage(error),
      });
    },
  });

  const onSubmit = (values: FormValues) => {
    setLoginNoticeState(null);
    mutation.mutate(values);
  };

  const onInvalid = (errors: FieldErrors<FormValues>) => {
    showLoginNotice({
      level: "error",
      title: "Check your credentials",
      message:
        errors.email?.message ??
        errors.password?.message ??
        "Enter a valid email and a password with at least 8 characters.",
    });
  };

  return (
    <div className="flex min-h-screen items-center justify-center bg-background p-6">
      <div className="w-full max-w-md rounded-xl border border-border bg-card p-6 shadow-sm">
        <h1 className="text-2xl font-semibold tracking-tight text-foreground">Superadmin console</h1>
        <p className="mt-1 text-sm text-muted-foreground">
          Sign in with your TowerOS superadmin account. Tenant users must use their organization URL.
        </p>

        {loginNotice ? (
          <div className="mt-4">
            <LoginNoticeBanner notice={loginNotice} onDismiss={() => setLoginNoticeState(null)} />
          </div>
        ) : null}

        {mfaPending ? (
          <div className="mt-6">
            <PlatformLoginMfaPanel
              pending={mfaPending}
              onBack={() => setMfaPending(null)}
              onComplete={() => router.replace("/platform")}
            />
          </div>
        ) : (
          <>
            <form className="mt-6 space-y-4" noValidate onSubmit={form.handleSubmit(onSubmit, onInvalid)}>
              <FormInput
                label="Email"
                type="email"
                autoComplete="email"
                {...form.register("email")}
                error={form.formState.errors.email}
              />
              <FormInput
                label="Password"
                type="password"
                autoComplete="current-password"
                {...form.register("password")}
                error={form.formState.errors.password}
              />

              <Button className="w-full" type="submit" disabled={mutation.isPending}>
                {mutation.isPending ? "Signing in..." : "Sign in"}
              </Button>
            </form>
          </>
        )}
      </div>
    </div>
  );
}
