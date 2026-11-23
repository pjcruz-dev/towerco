import Link from "next/link";
import { ReceiptText } from "lucide-react";

import { buttonVariants } from "@/components/ui/button";
import { cn } from "@/lib/utils";

type Props = {
  href: string;
  className?: string;
};

/** The next job on a released cash advance. A filled control, so it is not another text link. */
export function EApprovalLiquidateLink({ href, className }: Props) {
  return (
    <Link href={href} className={cn(buttonVariants({ size: "xs" }), className)}>
      <ReceiptText aria-hidden />
      Liquidate
    </Link>
  );
}
