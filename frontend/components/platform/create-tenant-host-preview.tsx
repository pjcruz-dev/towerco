"use client";

import {
  isLocalDevPlatformHost,
  recommendedTenantDomain,
  type TenantEnvironment,
} from "@/lib/tenant/recommended-tenant-domain";
import { previewTenantLoginUrl } from "@/lib/tenant/resolve-tenant-domain";
import { cn } from "@/lib/utils";

type Props = {
  slug: string;
  brandDomain: string;
  selectedEnvironment: TenantEnvironment;
  hostname?: string;
};

const ENV_ROWS: Array<{ key: TenantEnvironment; label: string }> = [
  { key: "local", label: "Local" },
  { key: "test", label: "Test" },
  { key: "staging", label: "Staging" },
  { key: "production", label: "App" },
];

export function CreateTenantHostPreview({
  slug,
  brandDomain,
  selectedEnvironment,
  hostname,
}: Props) {
  const normalizedSlug = slug.trim();
  const hasSlug = normalizedSlug.length > 0;
  const useLocalDevHosts = isLocalDevPlatformHost();

  if (!hasSlug) {
    return (
      <div className="rounded-xl border border-dashed border-border bg-muted/30 p-4 text-sm text-muted-foreground">
        Enter a slug to preview hostnames for this organization.
      </div>
    );
  }

  const activeHostname =
    hostname?.trim() ||
    recommendedTenantDomain(selectedEnvironment, normalizedSlug, brandDomain || null);

  return (
    <div className="rounded-xl border border-border bg-muted/20 p-4">
      <p className="text-sm font-medium text-foreground">Hostname map</p>
      <p className="mt-1 text-xs text-muted-foreground">
        Creating <span className="font-medium capitalize text-foreground">{selectedEnvironment}</span>{" "}
        now. Add other environments later from the tenant directory — same slug and brand.
      </p>
      <ul className="mt-3 space-y-2">
        {ENV_ROWS.map(({ key, label }) => {
          const rowHostname = recommendedTenantDomain(key, normalizedSlug, brandDomain || null, {
            useLocalDevHosts,
          });
          const loginUrl = previewTenantLoginUrl(rowHostname);
          const isSelected = key === selectedEnvironment;

          return (
            <li
              key={key}
              className={cn(
                "rounded-md border px-3 py-2 text-xs",
                isSelected
                  ? "border-primary/40 bg-primary/5"
                  : "border-border/70 bg-background/80",
              )}
            >
              <div className="flex items-center justify-between gap-2">
                <span className="font-medium text-foreground">{label}</span>
                {isSelected ? (
                  <span className="rounded bg-primary/10 px-1.5 py-0.5 text-[10px] font-medium text-primary">
                    Creating
                  </span>
                ) : null}
              </div>
              <p className="mt-0.5 font-mono text-muted-foreground">{rowHostname}</p>
              {isSelected ? (
                <a
                  href={loginUrl}
                  className="mt-1 inline-block text-primary underline-offset-4 hover:underline"
                  target="_blank"
                  rel="noopener noreferrer"
                >
                  {loginUrl}
                </a>
              ) : null}
            </li>
          );
        })}
      </ul>
      {!brandDomain.trim() && !useLocalDevHosts ? (
        <p className="mt-3 text-xs text-amber-700 dark:text-amber-400">
          Set brand domain for deployed test, staging, and production hostnames.
        </p>
      ) : null}
      {activeHostname ? (
        <p className="mt-3 border-t border-border/60 pt-3 text-[11px] text-muted-foreground">
          Primary hostname for this create:{" "}
          <span className="font-mono text-foreground">{activeHostname}</span>
        </p>
      ) : null}
    </div>
  );
}
