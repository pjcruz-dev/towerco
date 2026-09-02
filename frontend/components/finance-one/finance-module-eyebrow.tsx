import Link from "next/link";

type Props = {
  homeHref?: string;
  label?: string;
};

export function FinanceModuleEyebrow({ homeHref = "/finance", label = "Finance" }: Props) {
  return (
    <Link href={homeHref} className="hover:text-primary">
      {label}
    </Link>
  );
}
