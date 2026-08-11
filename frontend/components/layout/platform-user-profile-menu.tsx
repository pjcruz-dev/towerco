"use client";

import {
  BookOpen,
  ChevronDown,
  CircleHelp,
  CreditCard,
  Layers,
  KeyRound,
  LogIn,
  LogOut,
  Monitor,
  Moon,
  PlusCircle,
  Sun,
  Users,
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
import {
  platformHasPermission,
  platformRoleLabel,
  PLATFORM_PERMS,
} from "@/lib/platform/platform-permissions";
import { cn } from "@/lib/utils";
import { usePlatformAuthStore } from "@/stores/platform-auth-store";

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
  const user = usePlatformAuthStore((state) => state.user);
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

function PlatformRoleBadge({ role }: { role: string | undefined }) {
  return (
    <div className="flex items-center gap-2 rounded-lg border border-border bg-muted/30 px-3 py-2 text-sm">
      <Layers className="size-4 shrink-0 text-muted-foreground" aria-hidden />
      <div className="min-w-0">
        <p className="truncate font-medium text-foreground">TowerOS Central</p>
        <p className="truncate text-xs text-muted-foreground">{platformRoleLabel(role)}</p>
      </div>
    </div>
  );
}

function ProfileMenuContent({ onNavigate }: { onNavigate?: () => void }) {
  const router = useRouter();
  const user = usePlatformAuthStore((state) => state.user);
  const clearSession = usePlatformAuthStore((state) => state.clearSession);

  const consoleLinks = useMemo(
    () =>
      [
        {
          href: "/platform",
          label: "Dashboard",
          icon: Layers,
          visible: true,
        },
        {
          href: "/platform/billing",
          label: "Billing & revenue",
          icon: CreditCard,
          visible: platformHasPermission(user, PLATFORM_PERMS.billingView),
        },
        {
          href: "/platform/playbooks",
          label: "Rollout playbooks",
          icon: BookOpen,
          visible: true,
        },
        {
          href: "/platform/operators",
          label: "Platform operators",
          icon: Users,
          visible: platformHasPermission(user, PLATFORM_PERMS.operatorsView),
        },
        {
          href: "/platform/tenants/create",
          label: "Create tenant",
          icon: PlusCircle,
          visible: platformHasPermission(user, PLATFORM_PERMS.tenantsManage),
        },
        {
          href: "/platform/settings/mfa",
          label: "MFA security",
          icon: KeyRound,
          visible: true,
        },
        {
          href: "/platform/helper-center",
          label: "Helper center",
          icon: CircleHelp,
          visible: true,
        },
        {
          href: "/login",
          label: "Tenant application",
          icon: LogIn,
          visible: true,
        },
      ].filter((link) => link.visible),
    [user],
  );

  const signOut = () => {
    onNavigate?.();
    clearSession();
    router.replace("/platform/login");
  };

  return (
    <div className="flex flex-col">
      <div className="space-y-1 px-4 py-3">
        <p className="truncate text-sm font-medium text-foreground">{user?.name ?? "Signed in"}</p>
        <p className="truncate text-xs text-muted-foreground">{user?.email ?? ""}</p>
        <p className="truncate text-xs text-muted-foreground">
          {platformRoleLabel(user?.platform_role)}
        </p>
      </div>

      <Separator />

      <div className="space-y-4 px-4 py-3">
        <PlatformRoleBadge role={user?.platform_role} />

        <div className="space-y-2">
          <p className="text-xs font-medium text-muted-foreground">Appearance</p>
          <ThemeSegment onSelect={onNavigate} />
        </div>

        {consoleLinks.length > 0 ? (
          <div className="space-y-1">
            <p className="text-xs font-medium text-muted-foreground">Console</p>
            <nav className="space-y-0.5">
              {consoleLinks.map((link) => {
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
          <span className="size-2 shrink-0 rounded-full bg-emerald-500" aria-hidden />
          Platform session active
        </div>
        <Button type="button" variant="destructive" className="w-full" onClick={signOut}>
          <LogOut className="size-4" aria-hidden />
          Sign out
        </Button>
      </div>
    </div>
  );
}

export function PlatformUserProfileMenu() {
  const isMobile = useIsMobile();
  const [open, setOpen] = useState(false);

  if (isMobile) {
    return (
      <Sheet open={open} onOpenChange={setOpen}>
        <SheetTrigger render={<ProfileTrigger compact />} />
        <SheetContent side="bottom" className="max-h-[85vh] rounded-t-xl px-0 pb-6" showCloseButton>
          <SheetHeader className="px-4 text-left">
            <SheetTitle>Account</SheetTitle>
            <SheetDescription>Central console, appearance, and session controls.</SheetDescription>
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
