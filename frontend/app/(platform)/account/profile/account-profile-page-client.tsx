"use client";

import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { Bell, Fingerprint, Settings, Shield, UserRound } from "lucide-react";
import { useMemo } from "react";

import { AccountRelatedRecords } from "@/components/account/account-related-records";
import { ProductReleaseEditor } from "@/components/product/product-release-editor";
import { PermissionGate } from "@/components/layout/permission-gate";
import { usePermission } from "@/hooks/use-permission";
import { permissions } from "@/lib/rbac/permissions";
import { useProductRelease } from "@/hooks/use-product-release";
import {
  isTenantModuleEnabled,
  resolveEnabledModulesForUser,
} from "@/lib/tenant/enabled-modules";
import { useAuthStore } from "@/stores/auth-store";
import { cn } from "@/lib/utils";

type SectionId = "account" | "security" | "notifications";

const SECTIONS: { id: SectionId; label: string; hint: string; icon: typeof UserRound }[] = [
  { id: "account", label: "Account", hint: "Your name, email, and records", icon: UserRound },
  { id: "security", label: "Security", hint: "Sessions, authenticator, and passkeys", icon: Shield },
  { id: "notifications", label: "Notifications", hint: "Inbox and what's new", icon: Bell },
];

export function AccountProfilePageClient() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const user = useAuthStore((state) => state.user);
  const activeTenantId = useAuthStore((state) => state.activeTenantId);
  const { displayedVersion } = useProductRelease();
  const canEforms = usePermission([permissions.eApprovalView]);
  const canManageTenant = usePermission([permissions.tenantManage]);

  const section = useMemo((): SectionId => {
    const value = searchParams.get("section");
    if (value === "security" || value === "notifications") {
      return value;
    }
    return "account";
  }, [searchParams]);

  const showEforms = useMemo(() => {
    if (!canEforms) {
      return false;
    }
    return isTenantModuleEnabled(resolveEnabledModulesForUser(user, activeTenantId), "e_approval");
  }, [activeTenantId, canEforms, user]);

  const setSection = (next: SectionId) => {
    const params = new URLSearchParams(searchParams.toString());
    if (next === "account") {
      params.delete("section");
    } else {
      params.set("section", next);
    }
    const query = params.toString();
    router.replace(query ? `/account/profile?${query}` : "/account/profile", { scroll: false });
  };

  const initial = (user?.name || user?.email || "?").slice(0, 1).toUpperCase();

  return (
    <PermissionGate requiredPermissions={[permissions.dashboardView]}>
      <div className="space-y-6">
        <header className="flex flex-wrap items-end justify-between gap-3">
          <div>
            <p className="text-xs text-muted-foreground">
              <Link href="/dashboard" className="hover:text-foreground">
                Home
              </Link>
              <span aria-hidden> / </span>
              <span className="text-foreground">Profile</span>
            </p>
            <h1 className="mt-2 text-2xl font-semibold tracking-tight text-foreground">Profile</h1>
          </div>
          <p className="text-xs text-muted-foreground">{displayedVersion}</p>
        </header>

        <div className="grid gap-6 lg:grid-cols-[16rem_minmax(0,1fr)]">
          <nav className="space-y-2" aria-label="Profile sections">
            {SECTIONS.map((item) => {
              const Icon = item.icon;
              const active = section === item.id;
              return (
                <button
                  key={item.id}
                  type="button"
                  onClick={() => setSection(item.id)}
                  className={cn(
                    "flex w-full items-start gap-3 rounded-xl border px-3 py-3 text-left transition-colors",
                    active
                      ? "border-border bg-muted/60 text-foreground"
                      : "border-transparent bg-card text-foreground hover:bg-muted/40",
                  )}
                  aria-current={active ? "page" : undefined}
                >
                  <span className="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg border border-border bg-background">
                    <Icon className="size-4 text-muted-foreground" aria-hidden />
                  </span>
                  <span>
                    <span className="block text-sm font-medium">{item.label}</span>
                    <span className="mt-0.5 block text-xs text-muted-foreground">{item.hint}</span>
                  </span>
                </button>
              );
            })}
          </nav>

          <div className="min-w-0 rounded-xl border border-border bg-card p-5 shadow-sm sm:p-6">
            {section === "account" ? (
              <div className="space-y-6">
                <div>
                  <h2 className="text-lg font-semibold text-foreground">Account</h2>
                  <p className="mt-1 text-sm text-muted-foreground">
                    Name and email are set by an administrator. Your records stay on this page.
                  </p>
                </div>
                <div className="flex items-center gap-3">
                  <span className="flex size-12 items-center justify-center rounded-full bg-muted text-sm font-medium text-foreground">
                    {initial}
                  </span>
                  <div className="min-w-0">
                    <p className="truncate text-sm font-medium text-foreground">{user?.name ?? "Signed in"}</p>
                    <p className="truncate text-sm text-muted-foreground">{user?.email}</p>
                  </div>
                </div>
                {showEforms ? (
                  <SecurityRow
                    icon={UserRound}
                    title="E-Forms signature"
                    description="Signature and delegation used on approvals"
                    href="/e-approval/profile"
                    action="Open"
                  />
                ) : null}
                {canManageTenant ? (
                  <SecurityRow
                    icon={Settings}
                    title="Settings"
                    description="Organization settings for the modules you run"
                    href="/settings"
                    action="Open"
                  />
                ) : null}
                <AccountRelatedRecords />
              </div>
            ) : null}

            {section === "security" ? (
              <div className="space-y-4">
                <div>
                  <h2 className="text-lg font-semibold text-foreground">Security</h2>
                  <p className="mt-1 text-sm text-muted-foreground">
                    Sessions, authenticator, and passkeys. Organization sign-in rules are here too when you administer the tenant.
                  </p>
                </div>
                <SecurityRow
                  icon={Shield}
                  title="Authenticator"
                  description="Turn on a code from your authenticator app"
                  href="/account/security?tab=mfa"
                  action="Open"
                  helpId="ea-profile-authenticator"
                  tourNav="/account/security?tab=mfa"
                />
                <SecurityRow
                  icon={Fingerprint}
                  title="Passkeys"
                  description="Fingerprint or Windows Hello on this device"
                  href="/account/security?tab=passkeys"
                  action="Open"
                  helpId="ea-profile-passkeys"
                  tourNav="/account/security?tab=passkeys"
                />
                <SecurityRow
                  icon={UserRound}
                  title="Active sessions"
                  description="See where you are signed in and end other sessions"
                  href="/account/security"
                  action="View"
                />
                {canManageTenant ? (
                  <SecurityRow
                    icon={Settings}
                    title="Sign-in & security"
                    description="Organization MFA, passkeys, and Microsoft sign-in"
                    href="/admin/settings"
                    action="Open"
                  />
                ) : null}
              </div>
            ) : null}

            {section === "notifications" ? (
              <div className="space-y-6">
                <div>
                  <h2 className="text-lg font-semibold text-foreground">Notifications</h2>
                  <p className="mt-1 text-sm text-muted-foreground">
                    A pulsing dot on the bell means something is unread. Open the bell or the inbox to read it.
                  </p>
                </div>
                <Link
                  href="/notifications"
                  className="flex items-center justify-between gap-3 rounded-lg border border-border px-4 py-3 text-sm hover:bg-muted/40"
                >
                  <span>
                    <span className="block font-medium text-foreground">Inbox</span>
                    <span className="text-muted-foreground">Action required and updates</span>
                  </span>
                  <span className="shrink-0 text-primary">Open</span>
                </Link>
                <div className="border-t border-border pt-6">
                  <h3 className="text-sm font-medium text-foreground">What&apos;s new</h3>
                  <div className="mt-3">
                    <ProductReleaseEditor />
                  </div>
                </div>
              </div>
            ) : null}
          </div>
        </div>
      </div>
    </PermissionGate>
  );
}

function SecurityRow({
  icon: Icon,
  title,
  description,
  href,
  action,
  helpId,
  tourNav,
}: {
  icon: typeof Shield;
  title: string;
  description: string;
  href: string;
  action: string;
  helpId?: string;
  tourNav?: string;
}) {
  return (
    <Link
      href={href}
      data-help={helpId}
      data-tour-nav={tourNav}
      className="flex items-center justify-between gap-3 border-b border-border py-4 last:border-b-0"
    >
      <span className="flex min-w-0 items-start gap-3">
        <span className="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg border border-border bg-muted/40">
          <Icon className="size-4 text-muted-foreground" aria-hidden />
        </span>
        <span>
          <span className="block text-sm font-medium text-foreground">{title}</span>
          <span className="mt-0.5 block text-sm text-muted-foreground">{description}</span>
        </span>
      </span>
      <span className="shrink-0 text-sm font-medium text-primary">{action}</span>
    </Link>
  );
}
