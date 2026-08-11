import Link from "next/link";
import type { ReactNode } from "react";

import { buttonVariants } from "@/components/ui/button";
import { cn } from "@/lib/utils";

type Props = {
  title: string;
  description: string;
  actionHref?: string;
  actionLabel?: string;
  secondaryHref?: string;
  secondaryLabel?: string;
  className?: string;
  children?: ReactNode;
};

export function ListEmptyState({
  title,
  description,
  actionHref,
  actionLabel,
  secondaryHref,
  secondaryLabel,
  className,
  children,
}: Props) {
  return (
    <div className={cn("flex flex-col items-center justify-center px-6 py-12 text-center", className)}>
      <h3 className="text-base font-medium text-foreground">{title}</h3>
      <p className="mt-2 max-w-md text-sm text-muted-foreground">{description}</p>
      {children}
      <div className="mt-5 flex flex-wrap items-center justify-center gap-2">
        {actionHref && actionLabel ? (
          <Link href={actionHref} className={buttonVariants({ size: "sm" })}>
            {actionLabel}
          </Link>
        ) : null}
        {secondaryHref && secondaryLabel ? (
          <Link href={secondaryHref} className={buttonVariants({ size: "sm", variant: "outline" })}>
            {secondaryLabel}
          </Link>
        ) : null}
      </div>
    </div>
  );
}
