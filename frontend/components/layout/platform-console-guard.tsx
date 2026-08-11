"use client";

import { useRouter } from "next/navigation";
import { useEffect } from "react";

import { usePlatformAuthStore } from "@/stores/platform-auth-store";

export function PlatformConsoleGuard({ children }: { children: React.ReactNode }) {
  const router = useRouter();
  const isHydrated = usePlatformAuthStore((state) => state.isHydrated);
  const accessToken = usePlatformAuthStore((state) => state.accessToken);

  useEffect(() => {
    if (!isHydrated) {
      usePlatformAuthStore.getState().hydrate();
    }
  }, [isHydrated]);

  useEffect(() => {
    if (isHydrated && !accessToken) {
      router.replace("/platform/login");
    }
  }, [accessToken, isHydrated, router]);

  if (!isHydrated || !accessToken) {
    return (
      <div className="flex min-h-[50vh] items-center justify-center text-sm text-muted-foreground">
        Loading superadmin console…
      </div>
    );
  }

  return <>{children}</>;
}
