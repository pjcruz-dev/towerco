"use client";

import { Monitor, Smartphone, Tablet } from "lucide-react";

import {
  formatAuditUserAgent,
  parseAuditUserAgent,
} from "@/lib/workspace/audit-user-agent";

type Props = {
  userAgent: string | null | undefined;
};

export function AuditDeviceDetail({ userAgent }: Props) {
  const info = parseAuditUserAgent(userAgent);

  if (!info) {
    return (
      <div className="space-y-1">
        <p className="text-sm text-foreground">—</p>
        <p className="text-xs text-muted-foreground">
          Browser / device is recorded on new events after deploy. Phones and desktops are detected
          from the browser User-Agent (not IMEI or exact phone SKU when the OS hides it).
        </p>
      </div>
    );
  }

  const Icon =
    info.formFactor === "Phone" ? Smartphone : info.formFactor === "Tablet" ? Tablet : Monitor;

  return (
    <div className="space-y-2">
      <p className="flex items-start gap-1.5 text-sm font-medium text-foreground">
        <Icon className="mt-0.5 h-3.5 w-3.5 shrink-0 text-muted-foreground" aria-hidden />
        <span>{info.summary}</span>
      </p>
      <ul className="space-y-0.5 text-xs text-muted-foreground">
        {info.details.map((line) => (
          <li key={line}>{line}</li>
        ))}
      </ul>
      <p className="break-all font-mono text-[11px] leading-relaxed text-muted-foreground/90">
        {info.raw}
      </p>
      <p className="text-[11px] leading-relaxed text-muted-foreground">
        From the browser User-Agent. On iPhone/iPad the model is often just “iPhone” / “iPad”; Android
        may include a model code when the browser sends it.
      </p>
    </div>
  );
}

type CompactProps = {
  userAgent: string | null | undefined;
  className?: string;
};

export function AuditDeviceCompact({ userAgent, className }: CompactProps) {
  const info = parseAuditUserAgent(userAgent);
  const label = formatAuditUserAgent(userAgent);
  const title = info ? [info.summary, ...info.details, info.raw].join("\n") : label;

  return (
    <span className={className} title={title}>
      {label}
    </span>
  );
}
