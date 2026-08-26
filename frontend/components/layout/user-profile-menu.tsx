"use client";

import { useMutation, useQuery } from "@tanstack/react-query";
import {
  Building2,
  ChevronDown,
  ExternalLink,
  Layers,
  Loader2,
  LogOut,
  Monitor,
  Moon,
  Settings,
  Shield,
  Sun,
  UserCircle,
} from "lucide-react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useTheme } from "next-themes";
import { forwardRef, useEffect, useMemo, useState } from "react";

import { Avatar, AvatarFallback } from "@/components/ui/avatar";
import { Button } from "@/components/ui/button";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { Separator } from "@/components/ui/separator";
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
  SheetTrigger,
} from "@/components/ui/sheet";
import { useIsMobile } from "@/hooks/use-mobile";
import { usePermission } from "@/hooks/use-permission";
import { logout } from "@/lib/api/modules/auth-api";
import { getErrorMessage } from "@/lib/api/error";
import {
  fetchWorkspaceEnvironments,
  mintWorkspaceEnvironmentHandoff,
  type WorkspaceEnvironmentLink,
} from "@/lib/api/modules/workspace-environments-api";
import { clearSessionCookie } from "@/lib/auth/session-cookie";
import { hasPermission, permissions } from "@/lib/rbac/permissions";
import { isEchoEnabled } from "@/lib/socket/echo-client";
import {
  buildEnvironmentFallbackUrl,
  rememberEnvSwitchActorEmail,
} from "@/lib/tenant/environment-switch";
import { cn } from "@/lib/utils";
import { useAuthStore } from "@/stores/auth-store";
import { useNotificationStore } from "@/stores/notification-store";

type ThemeChoice = "light" | "dark" | "system";

function userInitials(name: string | undefined, email: string | undefined): string {
  const source = name ?? email ?? "?";
  return source
    .split(" ")
    .map((part) => part[0])
    .join("")
    .slice(0, 2)
    .toUpperCase();
}

const ProfileTrigger = forwardRef<
  HTMLButtonElement,
  React.ComponentProps<"button"> & { compact?: boolean }
>(function ProfileTrigger({ compact, className, ...props }, ref) {
  const user = useAuthStore((state) => state.user);
  const initials = userInitials(user?.name, user?.email);

  return (
    <button
      ref={ref}
      type="button"
      className={cn(
        "inline-flex items-center gap-2 rounded-full border border-border bg-muted/40 p-0.5 pr-2 text-left transition-colors hover:bg-muted aria-expanded:bg-muted",
        compact && "pr-0.5",
        className,
      )}
      aria-label="Open account menu"
      {...props}
    >
      <Avatar size="sm">
        <AvatarFallback className="text-[11px] font-medium">{initials}</AvatarFallback>
      </Avatar>
      {!compact ? (
        <>
          <span className="hidden max-w-[8rem] truncate text-sm font-medium text-foreground sm:inline">
            {user?.name?.split(" ")[0] ?? "Account"}
          </span>
          <ChevronDown className="hidden size-3.5 text-muted-foreground sm:inline" aria-hidden />
        </>
      ) : null}
    </button>
  );
});

function ThemeSegment({ onSelect }: { onSelect?: () => void }) {
  const { theme, setTheme } = useTheme();
  const [mounted, setMounted] = useState(false);

  useEffect(() => {
    setMounted(true);
  }, []);

  const active = (mounted ? theme : "system") as ThemeChoice;

  const options: { value: ThemeChoice; label: string; icon: typeof Sun }[] = [
    { value: "light", label: "Light", icon: Sun },
    { value: "dark", label: "Dark", icon: Moon },
    { value: "system", label: "System", icon: Monitor },
  ];

  return (
    <div className="grid grid-cols-3 gap-1 rounded-lg border border-border bg-muted/30 p-1">
      {options.map((option) => {
        const Icon = option.icon;
        const isActive = active === option.value;

        return (
          <button
            key={option.value}
            type="button"
            disabled={!mounted}
            onClick={() => {
              setTheme(option.value);
              onSelect?.();
            }}
            className={cn(
              "inline-flex flex-col items-center gap-1 rounded-md px-2 py-2 text-[11px] font-medium transition-colors",
              isActive
                ? "bg-background text-foreground shadow-sm"
                : "text-muted-foreground hover:bg-background/70 hover:text-foreground",
            )}
          >
            <Icon className="size-3.5" aria-hidden />
            {option.label}
          </button>
        );
      })}
    </div>
  );
}

function EnvironmentSwitcher({ onSelect }: { onSelect?: () => void }) {
  const notify = useNotificationStore((state) => state.push);
  const actorEmail = useAuthStore((state) => state.user?.email ?? null);
  const canSwitchEnvironments = usePermission([permissions.workspaceEnvironmentsSwitch]);
  const [pendingEnvironment, setPendingEnvironment] = useState<string | null>(null);

  const environmentsQuery = useQuery({
    queryKey: ["workspace", "environments"],
    queryFn: fetchWorkspaceEnvironments,
    staleTime: 60_000,
    enabled: canSwitchEnvironments,
  });

  const openFallback = (env: WorkspaceEnvironmentLink, detail?: string) => {
    const href = buildEnvironmentFallbackUrl(env, actorEmail);
    notify({
      level: env.sso_enabled ? "info" : "warning",
      title: env.sso_enabled ? "Continue with Microsoft sign-in" : "Account missing on that environment",
      message:
        detail?.trim() ||
        (env.sso_enabled
          ? "Seamless switch was unavailable. Starting Microsoft sign-in on the other host."
          : `Add ${actorEmail ?? "your user"} on ${env.label} (same email), then try Switch again — or sign in on that host.`),
    });
    onSelect?.();
    window.location.assign(href);
  };

  const handoffMutation = useMutation({
    mutationFn: mintWorkspaceEnvironmentHandoff,
    onSuccess: (payload) => {
      rememberEnvSwitchActorEmail(actorEmail);
      onSelect?.();
      window.location.assign(payload.redeem_url);
    },
    onError: (error, environment) => {
      const env = (environmentsQuery.data?.environments ?? []).find(
        (item) => item.environment === environment,
      );
      if (env) {
        openFallback(env, getErrorMessage(error));
        return;
      }
      notify({
        level: "error",
        title: "Could not switch environment",
        message: getErrorMessage(error),
      });
    },
    onSettled: () => setPendingEnvironment(null),
  });

  const environments = environmentsQuery.data?.environments ?? [];
  const handoffSupported = environmentsQuery.data?.handoff_supported !== false;

  // Only users with workspace:environments:switch see this block.
  if (!canSwitchEnvironments || environments.length <= 1) {
    return null;
  }

  return (
    <div className="space-y-1.5">
      <p className="text-xs font-medium text-muted-foreground">Environment</p>
      <div className="space-y-1">
        {environments.map((env) => {
          if (env.is_current) {
            return (
              <div
                key={env.environment}
                className="flex items-center gap-2 rounded-lg border border-primary/30 bg-primary/5 px-3 py-2 text-sm text-foreground"
              >
                <Layers className="size-4 shrink-0 text-muted-foreground" aria-hidden />
                <span className="min-w-0 flex-1 truncate font-medium">{env.label}</span>
                <span className="shrink-0 text-[11px] text-muted-foreground">Current</span>
              </div>
            );
          }

          const canHandoff = handoffSupported && env.handoff_available !== false;
          const busy = pendingEnvironment === env.environment && handoffMutation.isPending;

          if (canHandoff) {
            return (
              <button
                key={env.environment}
                type="button"
                disabled={handoffMutation.isPending}
                onClick={() => {
                  rememberEnvSwitchActorEmail(actorEmail);
                  setPendingEnvironment(env.environment);
                  handoffMutation.mutate(env.environment);
                }}
                className="flex w-full items-center gap-2 rounded-lg border border-transparent bg-muted/20 px-3 py-2 text-left text-sm text-muted-foreground transition-colors hover:bg-muted/50 hover:text-foreground disabled:opacity-60"
              >
                <Layers className="size-4 shrink-0" aria-hidden />
                <span className="min-w-0 flex-1 truncate font-medium">{env.label}</span>
                {busy ? (
                  <Loader2 className="size-3.5 shrink-0 animate-spin" aria-hidden />
                ) : (
                  <span className="shrink-0 text-[11px] text-muted-foreground">Switch</span>
                )}
              </button>
            );
          }

          const href = env.switch_url ?? env.login_url;

          return (
            <a
              key={env.environment}
              href={href}
              target="_blank"
              rel="noopener noreferrer"
              onClick={() => onSelect?.()}
              className="flex items-center gap-2 rounded-lg border border-transparent bg-muted/20 px-3 py-2 text-sm text-muted-foreground transition-colors hover:bg-muted/50 hover:text-foreground"
            >
              <Layers className="size-4 shrink-0" aria-hidden />
              <span className="min-w-0 flex-1 truncate font-medium">{env.label}</span>
              {env.sso_enabled ? (
                <span className="shrink-0 text-[11px] text-muted-foreground">SSO</span>
              ) : null}
              <ExternalLink className="size-3.5 shrink-0 opacity-70" aria-hidden />
              <span className="sr-only">
                Open {env.label} {env.sso_enabled ? "Microsoft sign-in" : "login"} in a new tab
              </span>
            </a>
          );
        })}
      </div>
      <p className="text-[11px] leading-snug text-muted-foreground">
        Seamless switch needs the same email on the other environment. If that fails, we open that
        host’s login (Microsoft SSO when enabled). Sessions stay separate per host.
      </p>
    </div>
  );
}

function TenantPicker({ onSelect }: { onSelect?: () => void }) {
  const user = useAuthStore((state) => state.user);
  const activeTenantId = useAuthStore((state) => state.activeTenantId);
  const setActiveTenantId = useAuthStore((state) => state.setActiveTenantId);

  const options = useMemo(() => user?.tenantAccesses ?? [], [user?.tenantAccesses]);

  if (options.length <= 1) {
    const active = options.find((tenant) => tenant.tenantId === activeTenantId) ?? options[0];
    if (!active) {
      return null;
    }

    return (
      <div className="space-y-3">
        <div className="flex items-center gap-2 rounded-lg border border-border bg-muted/30 px-3 py-2 text-sm">
          <Building2 className="size-4 shrink-0 text-muted-foreground" aria-hidden />
          <span className="truncate font-medium text-foreground">{active.tenantName}</span>
        </div>
        <EnvironmentSwitcher onSelect={onSelect} />
      </div>
    );
  }

  return (
    <div className="space-y-3">
      <div className="space-y-1.5">
        <p className="text-xs font-medium text-muted-foreground">Switch organization</p>
        <div className="max-h-40 space-y-1 overflow-y-auto">
          {options.map((tenant) => {
            const isActive = tenant.tenantId === activeTenantId;

            return (
              <button
                key={tenant.tenantId}
                type="button"
                onClick={() => {
                  setActiveTenantId(tenant.tenantId);
                  onSelect?.();
                }}
                className={cn(
                  "flex w-full items-center gap-2 rounded-lg border px-3 py-2 text-left text-sm transition-colors",
                  isActive
                    ? "border-primary/30 bg-primary/5 text-foreground"
                    : "border-transparent bg-muted/20 text-muted-foreground hover:bg-muted/50 hover:text-foreground",
                )}
              >
                <Building2 className="size-4 shrink-0" aria-hidden />
                <span className="truncate font-medium">{tenant.tenantName}</span>
              </button>
            );
          })}
        </div>
      </div>
      <EnvironmentSwitcher onSelect={onSelect} />
    </div>
  );
}

function ProfileMenuContent({ onNavigate }: { onNavigate?: () => void }) {
  const router = useRouter();
  const user = useAuthStore((state) => state.user);
  const activeTenantId = useAuthStore((state) => state.activeTenantId);
  const effectivePermissions = useAuthStore((state) => state.effectivePermissions);
  const clearSession = useAuthStore((state) => state.clearSession);

  const scopedUser = useMemo(() => {
    if (!user || !activeTenantId) {
      return user;
    }
    return { ...user, permissions: effectivePermissions() };
  }, [activeTenantId, effectivePermissions, user]);

  const activeTenant = useMemo(
    () => user?.tenantAccesses.find((tenant) => tenant.tenantId === activeTenantId) ?? user?.tenantAccesses[0],
    [activeTenantId, user?.tenantAccesses],
  );

  const realtimeEnabled = isEchoEnabled();

  const settingsLinks = useMemo(
    () =>
      [
        {
          href: "/account/security",
          label: "My security",
          icon: Shield,
          visible: hasPermission(scopedUser, [permissions.dashboardView]),
        },
        {
          href: "/admin/settings",
          label: "Sign-in & security",
          icon: Settings,
          visible: hasPermission(scopedUser, [permissions.tenantManage]),
        },
        {
          href: "/settings",
          label: "Settings",
          icon: Settings,
          visible: hasPermission(scopedUser, [permissions.tenantManage]),
        },
        {
          href: "/e-approval/profile",
          label: "E-Approval profile",
          icon: UserCircle,
          visible: hasPermission(scopedUser, [permissions.eApprovalView]),
        },
      ].filter((link) => link.visible),
    [scopedUser],
  );

  const signOut = async () => {
    onNavigate?.();
    try {
      await logout();
    } catch {
      // proceed with local sign out
    }
    clearSession();
    clearSessionCookie();
    router.replace("/login");
  };

  return (
    <div className="flex flex-col">
      <div className="space-y-1 px-4 py-3">
        <p className="truncate text-sm font-medium text-foreground">{user?.name ?? "Signed in"}</p>
        <p className="truncate text-xs text-muted-foreground">{user?.email ?? ""}</p>
        {activeTenant ? (
          <p className="truncate text-xs text-muted-foreground">{activeTenant.tenantName}</p>
        ) : null}
      </div>

      <Separator />

      <div className="space-y-4 px-4 py-3">
        <TenantPicker onSelect={onNavigate} />

        <div className="space-y-2">
          <p className="text-xs font-medium text-muted-foreground">Appearance</p>
          <ThemeSegment onSelect={onNavigate} />
        </div>

        {settingsLinks.length > 0 ? (
          <div className="space-y-1">
            <p className="text-xs font-medium text-muted-foreground">Account</p>
            <nav className="space-y-0.5">
              {settingsLinks.map((link) => {
                const Icon = link.icon;
                return (
                  <Link
                    key={link.href}
                    href={link.href}
                    onClick={onNavigate}
                    className="flex items-center gap-2 rounded-lg px-2 py-2 text-sm text-foreground transition-colors hover:bg-muted"
                  >
                    <Icon className="size-4 text-muted-foreground" aria-hidden />
                    {link.label}
                  </Link>
                );
              })}
            </nav>
          </div>
        ) : null}
      </div>

      <Separator />

      <div className="space-y-2 px-4 py-3">
        <div className="flex items-center gap-2 text-xs text-muted-foreground">
          <span
            className={cn(
              "size-2 shrink-0 rounded-full",
              realtimeEnabled ? "bg-emerald-500" : "bg-muted-foreground/40",
            )}
            aria-hidden
          />
          {realtimeEnabled ? "Realtime connected" : "Polling mode"}
        </div>
        <Button type="button" variant="destructive" className="w-full" onClick={signOut}>
          <LogOut className="size-4" aria-hidden />
          Sign out
        </Button>
      </div>
    </div>
  );
}

export function UserProfileMenu() {
  const isMobile = useIsMobile();
  const [open, setOpen] = useState(false);

  if (isMobile) {
    return (
      <Sheet open={open} onOpenChange={setOpen}>
        <SheetTrigger render={<ProfileTrigger compact />} />
        <SheetContent side="bottom" className="max-h-[85vh] rounded-t-xl px-0 pb-6" showCloseButton>
          <SheetHeader className="px-4 text-left">
            <SheetTitle>Account</SheetTitle>
            <SheetDescription>Workspace, appearance, and session controls.</SheetDescription>
          </SheetHeader>
          <ProfileMenuContent onNavigate={() => setOpen(false)} />
        </SheetContent>
      </Sheet>
    );
  }

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger render={<ProfileTrigger />} />
      <PopoverContent align="end" sideOffset={8} className="w-80">
        <ProfileMenuContent onNavigate={() => setOpen(false)} />
      </PopoverContent>
    </Popover>
  );
}
