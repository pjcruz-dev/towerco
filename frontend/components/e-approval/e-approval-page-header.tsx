import Link from "next/link";
import { useRouter } from "next/navigation";
import type { MouseEvent, ReactNode } from "react";

type Props = {
  title: string;
  description?: ReactNode;
  actions?: ReactNode;
};

export function EApprovalPageHeader({ title, description, actions }: Props) {
  return (
    <header className="flex flex-wrap items-start justify-between gap-3">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight text-foreground">{title}</h1>
        {description ? <div className="mt-1 text-sm text-muted-foreground">{description}</div> : null}
      </div>
      {actions ? <div className="flex flex-wrap gap-2">{actions}</div> : null}
    </header>
  );
}

export function EApprovalBackLink({
  href,
  children,
  confirmWhen,
}: {
  href: string;
  children: ReactNode;
  confirmWhen?: boolean;
}) {
  const router = useRouter();

  if (!confirmWhen) {
    return (
      <Link href={href} className="text-primary hover:underline">
        {children}
      </Link>
    );
  }

  const onClick = (event: MouseEvent<HTMLAnchorElement>) => {
    event.preventDefault();
    if (window.confirm("You have unsaved changes. Leave without saving?")) {
      router.push(href);
    }
  };

  return (
    <a href={href} onClick={onClick} className="cursor-pointer text-primary hover:underline">
      {children}
    </a>
  );
}
