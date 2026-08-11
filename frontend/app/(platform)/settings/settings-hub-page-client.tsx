"use client";

import Link from "next/link";
import { useMemo } from "react";
import type { LucideIcon } from "lucide-react";
import { Settings2 } from "lucide-react";

import { settingsHubSections } from "@/lib/navigation/settings-hub-config";
import { hasPermission } from "@/lib/rbac/permissions";
import { isTenantModuleEnabled, resolveEnabledModulesForUser } from "@/lib/tenant/enabled-modules";
import { useAuthStore } from "@/stores/auth-store";
import { cn } from "@/lib/utils";

function SettingsHubCard({
  title,
  description,
  href,
  icon: Icon,
}: {
  title: string;
  description: string;
  href: string;
  icon: LucideIcon;
}) {
  return (
    <Link
      href={href}
      prefetch={false}
      className={cn(
        "group flex h-full flex-col rounded-xl border border-border bg-card p-4 shadow-sm transition-colors",
        "hover:border-primary/30 hover:bg-muted/20",
      )}
    >
      <div className="flex items-start gap-3">
        <span className="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-border bg-muted/30 text-muted-foreground group-hover:text-primary">
          <Icon className="h-4 w-4" />
        </span>
        <div className="min-w-0">
          <h2 className="text-base font-medium text-foreground">{title}</h2>
          <p className="mt-1 text-xs leading-relaxed text-muted-foreground">{description}</p>
        </div>
      </div>
    </Link>
  );
}

export function SettingsHubPageClient() {
  const user = useAuthStore((state) => state.user);
  const activeTenantId = useAuthStore((state) => state.activeTenantId);
  const effectivePermissions = useAuthStore((state) => state.effectivePermissions);

  const scopedUser = useMemo(() => {
    if (!user) {
      return null;
    }
    return { ...user, permissions: effectivePermissions() };
  }, [effectivePermissions, user]);

  const enabledModules = useMemo(
    () => resolveEnabledModulesForUser(user, activeTenantId),
    [activeTenantId, user],
  );

  const visibleSections = useMemo(() => {
    return settingsHubSections
      .map((section) => ({
        ...section,
        items: section.items.filter((item) => {
          if (item.module && !isTenantModuleEnabled(enabledModules, item.module)) {
            return false;
          }
          return hasPermission(scopedUser, item.requiredPermissions);
        }),
      }))
      .filter((section) => section.items.length > 0);
  }, [enabledModules, scopedUser]);

  return (
    <div className="space-y-6">
      <header>
        <h1 className="text-2xl font-semibold tracking-tight text-foreground">Settings</h1>
        <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
          Configure platform security, module policies, and operational defaults. Personal MFA and sessions are under
          your profile menu → My security.
        </p>
      </header>

      {visibleSections.length === 0 ? (
        <div className="rounded-xl border border-dashed border-border px-6 py-12 text-center">
          <Settings2 className="mx-auto h-8 w-8 text-muted-foreground" />
          <p className="mt-3 text-sm text-muted-foreground">
            No settings are available for your role. Contact an administrator if you need access.
          </p>
        </div>
      ) : (
        visibleSections.map((section) => (
          <section key={section.id} className="space-y-3">
            <h2 className="text-xl font-semibold text-foreground">{section.title}</h2>
            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
              {section.items.map((item) => (
                <SettingsHubCard
                  key={item.id}
                  title={item.title}
                  description={item.description}
                  href={item.href}
                  icon={item.icon}
                />
              ))}
            </div>
          </section>
        ))
      )}
    </div>
  );
}
