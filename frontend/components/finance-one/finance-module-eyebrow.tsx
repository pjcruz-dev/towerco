import Link from "next/link";

import { FINANCE_ONE_HOME } from "@/lib/navigation/finance-one-routes";

type Props = {
  homeHref?: string;
  label?: string;
};

export function FinanceModuleEyebrow({ homeHref = FINANCE_ONE_HOME, label = "Finance-One" }: Props) {
  return (
    <Link href={homeHref} className="hover:text-primary">
      {label}
    </Link>
  );
}
