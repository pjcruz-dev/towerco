"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation, useQuery } from "@tanstack/react-query";
import { Fingerprint } from "lucide-react";
import { useRouter, useSearchParams } from "next/navigation";
import { Suspense, useEffect, useMemo, useState, useSyncExternalStore } from "react";
import { useForm, type FieldErrors } from "react-hook-form";
import { z } from "zod";

import { FormInput } from "@/components/forms/form-input";
import { LoginNoticeBanner } from "@/components/feedback/login-notice-banner";
import { Button } from "@/components/ui/button";
import { useOrganizationLabel } from "@/hooks/use-organization-label";
import { setSessionCookie } from "@/lib/auth/session-cookie";
import { tenantPostLoginPath } from "@/lib/auth/tenant-post-login-path";
import { getErrorMessage } from "@/lib/api/error";
import { fetchTenantAuthPublicStatus } from "@/lib/api/modules/admin-api";
import { login, webAuthnLoginOptions, webAuthnLoginVerify } from "@/lib/api/modules/auth-api";
import {
  consumeLoginNotice,
  type LoginNotice,
} from "@/lib/auth/login-notice";
import {
  isCentralHostname,
  rememberDevTenantDomain,
  resolveTenantDomainForApi,
  tenantDomainFromBrowserHostname,
  tenantLoginUrl,
} from "@/lib/tenant/resolve-tenant-domain";
import {
  isWebAuthnSupported,
  serializeAssertion,
  toRequestOptions,
  webAuthnUserMessage,
} from "@/lib/webauthn/browser";
import { useAuthStore } from "@/stores/auth-store";
import { useNotificationStore } from "@/stores/notification-store";

const schema = z.object({
  email: z
    .string()
    .min(1, "Email is required")
    .refine((value) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value), {
      message: "Enter a valid email address",
    }),
  password: z.string().min(8, "Password must be at least 8 characters"),
});

type FormValues = z.infer<typeof schema>;

function useBrowserHostname(): string {
  return useSyncExternalStore(
    () => () => {},
    () => window.location.hostname.toLowerCase(),
    () => "",
  );
}

/** Avoid SSR/client drift on hostname-derived defaults and extension-mutated inputs. */
function useClientReady(): boolean {
  return useSyncExternalStore(
    () => () => {},
    () => true,
    () => false,
  );
}

function LoginFormSkeleton() {
  return (
    <div className="mt-6 space-y-4" aria-hidden>
      <div className="h-16 animate-pulse rounded-md bg-muted/60" />
      <div className="h-16 animate-pulse rounded-md bg-muted/60" />
      <div className="h-10 animate-pulse rounded-md bg-muted/60" />
    </div>
  );
}

function LoginPageContent() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const clearSession = useAuthStore((state) => state.clearSession);
  const beginMfaLogin = useAuthStore((state) => state.beginMfaLogin);
  const setSession = useAuthStore((state) => state.setSession);
  const notify = useNotificationStore((state) => state.push);

  const browserHostname = useBrowserHostname();
  const clientReady = useClientReady();
  const organizationLabel = useOrganizationLabel();
  const [loginNotice, setLoginNoticeState] = useState<LoginNotice | null>(null);

  const showLoginNotice = (notice: LoginNotice) => {
    setLoginNoticeState(notice);
    notify(notice);
  };

  useEffect(() => {
    const tenantFromBrowser = tenantDomainFromBrowserHostname(browserHostname);
    if (tenantFromBrowser) {
      rememberDevTenantDomain(tenantFromBrowser);
    }

    const stored = consumeLoginNotice();
    if (stored) {
      setLoginNoticeState(stored);
      notify(stored);
    }
  }, [browserHostname, notify]);

  const queryTenantDomain = searchParams.get("tenant_domain")?.trim().toLowerCase() ?? "";
  const queryEmail = searchParams.get("email")?.trim() ?? "";
  const queryPassword = searchParams.get("password") ?? "";

  useEffect(() => {
    if (queryTenantDomain) {
      rememberDevTenantDomain(queryTenantDomain);
    }

    if (queryEmail || queryPassword) {
      const url = new URL(window.location.href);
      url.searchParams.delete("email");
      url.searchParams.delete("password");
      window.history.replaceState({}, "", `${url.pathname}${url.search}${url.hash}`);
    }
  }, [queryEmail, queryPassword, queryTenantDomain]);

  const tenantDomain = browserHostname ? resolveTenantDomainForApi() : null;
  const onCentralHost = browserHostname ? isCentralHostname(browserHostname) : false;

  const authStatusQuery = useQuery({
    queryKey: ["auth", "public", "status", tenantDomain],
    queryFn: fetchTenantAuthPublicStatus,
    enabled: Boolean(tenantDomain) && !onCentralHost,
    staleTime: 60_000,
    retry: false,
  });

  const microsoftSignIn = authStatusQuery.data?.microsoft_sign_in;
  const passwordLoginAvailable = authStatusQuery.data?.password_login?.available ?? true;
  const passkeysAvailable = authStatusQuery.data?.passkeys?.enabled ?? false;
  const passkeysPolicy = authStatusQuery.data?.passkeys?.policy ?? "allow";
  const authStatusReady =
    onCentralHost || !tenantDomain || authStatusQuery.isFetched;
  const passwordLoginRestricted =
    authStatusReady &&
    !passwordLoginAvailable &&
    Boolean(microsoftSignIn?.enabled);
  const autoStartSso = searchParams.get("sso") === "1";

  const [showBreakGlassLogin, setShowBreakGlassLogin] = useState(false);

  useEffect(() => {
    if (passwordLoginAvailable) {
      setShowBreakGlassLogin(false);
    }
  }, [passwordLoginAvailable]);

  const recommendedLoginUrl = useMemo(() => {
    if (!tenantDomain || !onCentralHost) {
      return null;
    }
    return tenantLoginUrl(tenantDomain);
  }, [onCentralHost, tenantDomain]);

  const defaultEmail = useMemo(() => {
    // Only prefill when the URL intentionally includes ?email= (e.g. env switch soft-handoff).
    // Do not invent admin@{host} — that looks like a default password account.
    return queryEmail;
  }, [queryEmail]);

  const form = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: { email: defaultEmail, password: queryPassword },
  });

  useEffect(() => {
    form.setValue("email", defaultEmail);
    if (queryPassword) {
      form.setValue("password", queryPassword);
    }
  }, [defaultEmail, form, queryPassword]);

  const {
    errors: formErrors,
    isSubmitted,
    isSubmitting,
  } = form.formState;

  const applyAuthSession = (session: Parameters<typeof setSession>[0]) => {
    if (session.mfaRequired) {
      beginMfaLogin(session);
      notify({
        level: "info",
        title: "MFA required",
        message: session.mfaEnrollmentRequired
          ? "Complete authenticator setup to finish signing in."
          : "Enter your verification code to finish signing in.",
      });
      if (session.mfaEnrollmentRequired) {
        router.replace("/login/mfa/enroll");
        return;
      }
      router.replace("/login/mfa");
      return;
    }
    setSession(session);
    setSessionCookie();
    window.location.assign(tenantPostLoginPath(session));
  };

  const mutation = useMutation({
    mutationFn: login,
    onSuccess: (session) => {
      applyAuthSession(session);
    },
    onError: (error) => {
      showLoginNotice({
        level: "error",
        title: "Login failed",
        message: getErrorMessage(error),
      });
    },
  });

  const passkeyMutation = useMutation({
    mutationFn: async () => {
      if (!isWebAuthnSupported()) {
        throw new Error("This browser does not support passkeys.");
      }
      const email = form.getValues("email")?.trim();
      const options = await webAuthnLoginOptions(email || undefined);
      const requestOptions = toRequestOptions(options.publicKey);
      const credential = (await navigator.credentials.get({
        publicKey: requestOptions,
      })) as PublicKeyCredential | null;
      if (!credential) {
        throw new Error("No passkey was selected.");
      }
      return webAuthnLoginVerify({
        challengeId: options.challenge_id,
        credential: serializeAssertion(credential),
      });
    },
    onSuccess: (session) => {
      applyAuthSession(session);
    },
    onError: (error) => {
      showLoginNotice({
        level: "error",
        title: "Passkey sign-in failed",
        message: webAuthnUserMessage(error) || getErrorMessage(error),
      });
    },
  });

  const onSubmit = (values: FormValues) => {
    setLoginNoticeState(null);

    const host = window.location.hostname.toLowerCase();
    const central = isCentralHostname(host);
    const apiTenantDomain = resolveTenantDomainForApi();

    if (central && !apiTenantDomain) {
      showLoginNotice({
        level: "error",
        title: "Organization hostname required",
        message:
          "Open your organization URL (for example http://staging.quantum.localhost/login) instead of localhost.",
      });
      return;
    }

    clearSession();
    mutation.mutate(values);
  };

  const onPasskeySignIn = () => {
    setLoginNoticeState(null);
    const host = window.location.hostname.toLowerCase();
    if (isCentralHostname(host) && !resolveTenantDomainForApi()) {
      showLoginNotice({
        level: "error",
        title: "Organization hostname required",
        message: "Open your organization URL before using passkey sign-in.",
      });
      return;
    }
    clearSession();
    passkeyMutation.mutate();
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

  const redirectToMicrosoftSignIn = () => {
    const redirectPath =
      microsoftSignIn?.redirect_path ??
      process.env.NEXT_PUBLIC_AUTH_AZURE_REDIRECT_PATH ??
      "/auth/sso/azure/redirect";
    const apiBase = (process.env.NEXT_PUBLIC_API_BASE_URL ?? "http://localhost:8000/api/v1").replace(
      /\/$/,
      "",
    );
    const path = redirectPath.startsWith("/") ? redirectPath : `/${redirectPath}`;
    // Relative API bases (e.g. `/api/v1`) need a document origin — `new URL("/api/v1/…")` alone throws.
    const url = new URL(`${apiBase}${path}`, window.location.origin);
    const tenantHost = resolveTenantDomainForApi();
    if (tenantHost) {
      url.searchParams.set("tenant_domain", tenantHost);
    }
    window.location.href = url.toString();
  };

  // Phase 2 env switcher: /login?sso=1 auto-starts Microsoft when SSO is enabled on this host.
  useEffect(() => {
    if (!autoStartSso || !authStatusReady || !microsoftSignIn?.enabled || onCentralHost) {
      return;
    }

    const guardKey = "toweros.sso_env_switch_auto";
    try {
      if (sessionStorage.getItem(guardKey) === browserHostname) {
        return;
      }
      sessionStorage.setItem(guardKey, browserHostname);
    } catch {
      // private mode / blocked storage — still attempt once this render cycle
    }

    const url = new URL(window.location.href);
    url.searchParams.delete("sso");
    window.history.replaceState({}, "", `${url.pathname}${url.search}${url.hash}`);
    redirectToMicrosoftSignIn();
  }, [
    authStatusReady,
    autoStartSso,
    browserHostname,
    microsoftSignIn?.enabled,
    onCentralHost,
  ]);

  const showPasswordFields =
    !authStatusReady || passwordLoginAvailable || showBreakGlassLogin;
  const showMicrosoftPrimary =
    passwordLoginRestricted && !showBreakGlassLogin;
  const passkeyBusy = passkeyMutation.isPending;
  const authBusy = mutation.isPending || isSubmitting || passkeyBusy;

  const passkeySignInButton = passkeysAvailable ? (
    <Button
      className="w-full gap-1.5"
      type="button"
      variant="outline"
      disabled={authBusy || authStatusQuery.isLoading || onCentralHost}
      onClick={onPasskeySignIn}
    >
      <Fingerprint className="h-4 w-4" aria-hidden />
      {passkeyBusy ? "Waiting for passkey…" : "Sign in with passkey"}
    </Button>
  ) : null;

  return (
    <div className="w-full max-w-md rounded-xl border border-border bg-card p-6 shadow-sm">
      <h1 className="text-2xl font-semibold leading-tight tracking-tight text-foreground">
        Sign in to {organizationLabel}
      </h1>

      {loginNotice ? (
        <div className="mt-4">
          <LoginNoticeBanner notice={loginNotice} onDismiss={() => setLoginNoticeState(null)} />
        </div>
      ) : null}

      {passkeysAvailable && passkeysPolicy === "prefer" ? (
        <p className="mt-4 rounded-md border border-border bg-muted/30 px-3 py-2 text-xs text-muted-foreground">
          Your organization recommends signing in with a passkey when available.
        </p>
      ) : null}
      {passkeysAvailable && passkeysPolicy === "require" ? (
        <p className="mt-4 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-950 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-100">
          Passkeys are required. You can still use password or Microsoft once, then enroll a passkey
          under My security.
        </p>
      ) : null}

      {clientReady && onCentralHost ? (
        <div className="mt-4 rounded-md border border-amber-200 bg-amber-50 p-3 text-xs leading-relaxed text-amber-950 dark:border-amber-900/60 dark:bg-amber-950/50 dark:text-amber-100">
          {tenantDomain ? (
            <>
              Using organization domain <span className="font-mono font-medium">{tenantDomain}</span> from dev context.
              {recommendedLoginUrl ? (
                <>
                  {" "}
                  Prefer{" "}
                  <a className="font-medium underline underline-offset-2" href={recommendedLoginUrl}>
                    {recommendedLoginUrl}
                  </a>
                  .
                </>
              ) : null}
            </>
          ) : (
            <>
              You are on <span className="font-mono font-medium">{browserHostname || "this host"}</span>, which is
              not an organization workspace host. Sign in at your organization URL, for example{" "}
              <span className="font-mono">http://test.atc.localhost/login</span>, or append{" "}
              <span className="font-mono">?tenant_domain=test.atc.localhost</span> for local dev.
            </>
          )}
        </div>
      ) : null}

      {passwordLoginRestricted && !showBreakGlassLogin ? (
        <p className="mt-4 rounded-md border border-border bg-muted/40 px-3 py-2 text-sm text-muted-foreground">
          This organization requires Microsoft sign-in. Password sign-in is only for designated
          break-glass administrator accounts.
        </p>
      ) : null}

      {showBreakGlassLogin && passwordLoginRestricted ? (
        <p className="mt-4 text-xs text-muted-foreground">
          Break-glass sign-in: use the bootstrap administrator account (for example{" "}
          <span className="font-mono text-foreground">
            admin@{tenantDomain ?? browserHostname ?? "your-org.localhost"}
          </span>
          ). Other accounts must use Microsoft.
        </p>
      ) : null}

      {!clientReady ? (
        <LoginFormSkeleton />
      ) : showMicrosoftPrimary ? (
        <div className="mt-6 space-y-3">
          {authStatusQuery.isLoading ? (
            <Button className="w-full" type="button" disabled>
              Loading sign-in options…
            </Button>
          ) : (
            <Button className="w-full" type="button" onClick={redirectToMicrosoftSignIn}>
              {microsoftSignIn?.label ?? "Sign in with Microsoft"}
            </Button>
          )}
          {passkeySignInButton}
          <Button
            className="w-full"
            type="button"
            variant="ghost"
            size="sm"
            onClick={() => setShowBreakGlassLogin(true)}
          >
            Break-glass administrator sign-in
          </Button>
        </div>
      ) : (
        <form
          className="mt-6 space-y-4"
          noValidate
          onSubmit={form.handleSubmit(onSubmit, onInvalid)}
        >
          {isSubmitted && (formErrors.email || formErrors.password) ? (
            <div
              className="rounded-md border border-red-300 bg-red-50 px-3 py-2 text-xs text-red-900 dark:border-red-900/60 dark:bg-red-950/50 dark:text-red-100"
              role="alert"
            >
              {formErrors.email?.message ?? formErrors.password?.message}
            </div>
          ) : null}

          {mutation.isError && !loginNotice ? (
            <div
              className="rounded-md border border-red-300 bg-red-50 px-3 py-2 text-xs text-red-900 dark:border-red-900/60 dark:bg-red-950/50 dark:text-red-100"
              role="alert"
            >
              {getErrorMessage(mutation.error)}
            </div>
          ) : null}

          {showPasswordFields ? (
            <>
              <FormInput
                label="Email"
                type="text"
                inputMode="email"
                autoComplete="email"
                spellCheck={false}
                placeholder="Email"
                {...form.register("email")}
                error={formErrors.email}
              />
              <FormInput
                label="Password"
                type="password"
                autoComplete="current-password"
                {...form.register("password")}
                error={formErrors.password}
              />

              <Button
                className="w-full"
                type="submit"
                disabled={authBusy || authStatusQuery.isLoading}
              >
                {mutation.isPending || isSubmitting ? "Signing in..." : "Sign in"}
              </Button>
            </>
          ) : authStatusQuery.isLoading ? (
            <p className="text-center text-sm text-muted-foreground">Loading sign-in options…</p>
          ) : null}

          {passkeySignInButton}
          <p className="text-center text-xs text-muted-foreground">
            Passkeys must be enrolled under My security after you sign in once.
          </p>

          {microsoftSignIn?.enabled ? (
            <Button
              className="w-full"
              variant={showPasswordFields ? "outline" : "default"}
              type="button"
              disabled={authBusy || authStatusQuery.isLoading}
              onClick={redirectToMicrosoftSignIn}
            >
              {microsoftSignIn.label ?? "Sign in with Microsoft"}
            </Button>
          ) : null}

          {passwordLoginRestricted && showBreakGlassLogin ? (
            <Button
              className="w-full"
              type="button"
              variant="ghost"
              size="sm"
              onClick={() => {
                setShowBreakGlassLogin(false);
                setLoginNoticeState(null);
              }}
            >
              Back to Microsoft sign-in
            </Button>
          ) : null}
        </form>
      )}
    </div>
  );
}

export function LoginPageClient() {
  return (
    <Suspense fallback={null}>
      <LoginPageContent />
    </Suspense>
  );
}
