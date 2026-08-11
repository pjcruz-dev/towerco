"use client";

import { Button } from "@/components/ui/button";
import type { CreateTenantInitialAdmin } from "@/lib/api/modules/platform-api";
import { tenantLoginUrl } from "@/lib/tenant/resolve-tenant-domain";

type Props = {
  initialAdmin: CreateTenantInitialAdmin;
  loginDomain?: string | null;
  loginUrl?: string | null;
  title?: string;
};

export function TenantCredentialsPanel({
  initialAdmin,
  loginDomain,
  loginUrl,
  title = "Initial tenant administrator",
}: Props) {
  const resolvedLoginUrl =
    loginUrl ?? (loginDomain ? tenantLoginUrl(loginDomain) : null);
  const hasPassword =
    typeof initialAdmin.password === "string" && initialAdmin.password.length > 0;

  return (
    <div className="space-y-2 rounded-md border border-amber-200/80 bg-amber-50 p-3 text-amber-950 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-50">
      <p className="text-xs font-semibold uppercase tracking-wide text-amber-800 dark:text-amber-200">
        {title}
      </p>

      {hasPassword && initialAdmin.password_generated ? (
        <p className="text-xs text-amber-900/90 dark:text-amber-100/90">
          This password was generated once. Copy it now; it cannot be retrieved later from this console.
        </p>
      ) : null}

      {!hasPassword && initialAdmin.hint ? (
        <p className="text-xs leading-relaxed text-amber-900/90 dark:text-amber-100/90">{initialAdmin.hint}</p>
      ) : null}

      {!hasPassword && initialAdmin.password_from_environment ? (
        <p className="text-xs text-amber-900/90 dark:text-amber-100/90">
          Password is set from{" "}
          <span className="font-mono">TOWEROS_TENANT_BOOTSTRAP_ADMIN_PASSWORD</span> on the API server (typically{" "}
          <span className="font-mono">password</span> in local dev).
        </p>
      ) : null}

      <p>
        <span className="font-medium text-foreground">Email:</span>{" "}
        <span className="font-mono">{initialAdmin.email}</span>
      </p>

      {hasPassword ? (
        <p>
          <span className="font-medium text-foreground">Password:</span>{" "}
          <span className="break-all font-mono">{initialAdmin.password}</span>
        </p>
      ) : null}

      {resolvedLoginUrl ? (
        <p className="text-xs">
          <span className="font-medium text-foreground">Sign-in URL:</span>{" "}
          <a
            href={resolvedLoginUrl}
            className="font-mono text-primary underline-offset-4 hover:underline"
            target="_blank"
            rel="noopener noreferrer"
          >
            {resolvedLoginUrl}
          </a>
        </p>
      ) : null}

      <div className="flex flex-wrap gap-2 pt-1">
        <Button type="button" size="sm" variant="outline" onClick={() => void navigator.clipboard.writeText(initialAdmin.email)}>
          Copy email
        </Button>
        {hasPassword ? (
          <Button
            type="button"
            size="sm"
            variant="outline"
            onClick={() => void navigator.clipboard.writeText(initialAdmin.password!)}
          >
            Copy password
          </Button>
        ) : null}
        {resolvedLoginUrl ? (
          <Button type="button" size="sm" onClick={() => { window.location.href = resolvedLoginUrl; }}>
            Open tenant sign-in
          </Button>
        ) : null}
      </div>
    </div>
  );
}
