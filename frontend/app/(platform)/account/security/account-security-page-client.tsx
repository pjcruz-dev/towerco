"use client";

import { useRouter, useSearchParams } from "next/navigation";
import { useMemo } from "react";

import { MfaSettingsPageClient } from "@/app/(platform)/settings/security/mfa/mfa-settings-page-client";
import { SessionsPageClient } from "@/app/(platform)/settings/sessions/sessions-page-client";
import { cn } from "@/lib/utils";

type TabId = "sessions" | "mfa";

const TABS: { id: TabId; label: string }[] = [
  { id: "sessions", label: "Sessions" },
  { id: "mfa", label: "Authenticator" },
];

export function AccountSecurityPageClient() {
  const router = useRouter();
  const searchParams = useSearchParams();

  const activeTab = useMemo((): TabId => {
    const tab = searchParams.get("tab");
    return tab === "mfa" ? "mfa" : "sessions";
  }, [searchParams]);

  const setTab = (tab: TabId) => {
    const params = new URLSearchParams(searchParams.toString());
    if (tab === "sessions") {
      params.delete("tab");
    } else {
      params.set("tab", tab);
    }
    const query = params.toString();
    router.replace(query ? `/account/security?${query}` : "/account/security", { scroll: false });
  };

  return (
    <div className="space-y-6">
      <header>
        <h1 className="text-2xl font-semibold tracking-tight text-foreground">My security</h1>
        <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
          Manage your sign-in sessions and multi-factor authentication. Organization-wide policies are configured under
          Administration → Settings.
        </p>
      </header>

      <div className="inline-flex rounded-lg border border-border bg-muted/20 p-1">
        {TABS.map((tab) => (
          <button
            key={tab.id}
            type="button"
            onClick={() => setTab(tab.id)}
            className={cn(
              "rounded-md px-3 py-1.5 text-sm font-medium transition-colors",
              activeTab === tab.id
                ? "bg-background text-foreground shadow-sm"
                : "text-muted-foreground hover:text-foreground",
            )}
          >
            {tab.label}
          </button>
        ))}
      </div>

      {activeTab === "sessions" ? <SessionsPageClient embedded /> : <MfaSettingsPageClient embedded />}
    </div>
  );
}
