"use client";

import { useRouter, useSearchParams } from "next/navigation";
import { useMemo } from "react";

import { MfaSettingsPageClient } from "@/app/(platform)/settings/security/mfa/mfa-settings-page-client";
import { SessionsPageClient } from "@/app/(platform)/settings/sessions/sessions-page-client";
import { PasskeysSettingsPanel } from "@/components/auth/passkeys-settings-panel";
import { LiveProductTourHost } from "@/components/help/live-product-tour-host";
import { cn } from "@/lib/utils";

type TabId = "sessions" | "mfa" | "passkeys";

const TABS: { id: TabId; label: string; helpId?: string }[] = [
  { id: "sessions", label: "Sessions" },
  { id: "mfa", label: "Authenticator", helpId: "ea-security-tab-mfa" },
  { id: "passkeys", label: "Passkeys", helpId: "ea-security-tab-passkeys" },
];

export function AccountSecurityPageClient() {
  const router = useRouter();
  const searchParams = useSearchParams();

  const activeTab = useMemo((): TabId => {
    const tab = searchParams.get("tab");
    if (tab === "mfa") return "mfa";
    if (tab === "passkeys") return "passkeys";
    return "sessions";
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
    <div className="space-y-6" data-help="ea-security-page">
      <LiveProductTourHost />
      <header>
        <h1 className="text-2xl font-semibold tracking-tight text-foreground">My security</h1>
        <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
          Manage sessions, authenticator MFA, and passkeys (fingerprint / Windows Hello). Organization-wide
          policies are configured under Administration → Settings.
        </p>
      </header>

      <div className="inline-flex flex-wrap rounded-lg border border-border bg-muted/20 p-1">
        {TABS.map((tab) => (
          <button
            key={tab.id}
            type="button"
            onClick={() => setTab(tab.id)}
            data-help={tab.helpId}
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

      {activeTab === "sessions" ? (
        <SessionsPageClient embedded />
      ) : activeTab === "mfa" ? (
        <MfaSettingsPageClient embedded />
      ) : (
        <PasskeysSettingsPanel embedded />
      )}
    </div>
  );
}
