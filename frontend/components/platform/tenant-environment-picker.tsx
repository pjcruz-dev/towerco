"use client";

import { cn } from "@/lib/utils";
import type { TenantEnvironment } from "@/lib/tenant/recommended-tenant-domain";

export const TENANT_ENVIRONMENT_OPTIONS: Array<{
  value: TenantEnvironment;
  label: string;
  description: string;
}> = [
  {
    value: "local",
    label: "Local",
    description: "Developer workstation",
  },
  {
    value: "test",
    label: "Test",
    description: "UAT / integration",
  },
  {
    value: "staging",
    label: "Staging",
    description: "Pre-production",
  },
  {
    value: "production",
    label: "Production",
    description: "Live tenant app",
  },
];

type Props = {
  value: TenantEnvironment;
  onChange: (value: TenantEnvironment) => void;
  disabled?: boolean;
};

export function TenantEnvironmentPicker({ value, onChange, disabled }: Props) {
  return (
    <div className="grid gap-2 sm:grid-cols-2">
      {TENANT_ENVIRONMENT_OPTIONS.map((option) => {
        const selected = value === option.value;

        return (
          <button
            key={option.value}
            type="button"
            disabled={disabled}
            aria-pressed={selected}
            onClick={() => onChange(option.value)}
            className={cn(
              "rounded-lg border px-3 py-2.5 text-left transition-colors",
              selected
                ? "border-primary bg-primary/5 ring-1 ring-primary/30"
                : "border-border bg-background hover:border-primary/30 hover:bg-muted/30",
              disabled && "pointer-events-none opacity-60",
            )}
          >
            <span className="block text-sm font-medium text-foreground">{option.label}</span>
            <span className="mt-0.5 block text-xs text-muted-foreground">{option.description}</span>
          </button>
        );
      })}
    </div>
  );
}
