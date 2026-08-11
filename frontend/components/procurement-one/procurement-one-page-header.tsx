import type { ReactNode } from "react";

type Props = {
  title: string;
  description?: ReactNode;
  eyebrow?: ReactNode;
  actions?: ReactNode;
};

export function ProcurementOnePageHeader({ title, description, eyebrow, actions }: Props) {
  return (
    <header className="flex flex-wrap items-start justify-between gap-3">
      <div>
        {eyebrow ? <div className="mb-1 text-sm text-muted-foreground">{eyebrow}</div> : null}
        <h1 className="text-2xl font-semibold tracking-tight text-foreground">{title}</h1>
        {description ? <div className="mt-1 text-sm text-muted-foreground">{description}</div> : null}
      </div>
      {actions ? <div className="flex flex-wrap gap-2">{actions}</div> : null}
    </header>
  );
}
