"use client";

import { Mail } from "lucide-react";

import { buttonVariants } from "@/components/ui/button";
import { cn } from "@/lib/utils";

type Props = {
  message: string;
  contact?: string | null;
  organizationLabel?: string | null;
};

function contactHref(contact: string): string | null {
  const trimmed = contact.trim();
  if (trimmed === "") return null;
  if (/^https?:\/\//i.test(trimmed)) return trimmed;
  if (trimmed.includes("@")) return `mailto:${trimmed}`;
  return null;
}

/**
 * Calm operational Coming Soon panel — replaces login form when tenant gate is on.
 * No countdown, no marketing subscribe chrome.
 */
export function ComingSoonLoginPanel({ message, contact, organizationLabel }: Props) {
  const href = contact ? contactHref(contact) : null;

  return (
    <div className="w-full rounded-2xl border border-border bg-card p-6 shadow-sm sm:p-8">
      <p className="text-xs font-medium text-muted-foreground">Workspace unavailable</p>
      <h1 className="mt-2 text-2xl font-semibold leading-tight tracking-tight text-foreground">
        Coming soon
      </h1>
      {organizationLabel ? (
        <p className="mt-1 text-sm font-medium text-foreground/80">{organizationLabel}</p>
      ) : null}
      <p className="mt-3 text-sm leading-relaxed text-muted-foreground">{message}</p>

      {href ? (
        <div className="mt-6">
          <a href={href} className={cn(buttonVariants({ variant: "outline", size: "sm" }))}>
            <Mail className="size-4" aria-hidden />
            Contact administrator
          </a>
        </div>
      ) : null}

      <p className="mt-8 text-xs text-muted-foreground">
        Sign-in will appear here when this environment is opened.
      </p>
    </div>
  );
}
