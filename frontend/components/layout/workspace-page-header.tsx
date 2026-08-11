import type { ReactNode } from "react";

type Props = {
  title: string;
  description?: ReactNode;
  actions?: ReactNode;
};

/** Shared tenant / workspace page header (aligned with E-Approval settings). */
export function WorkspacePageHeader({ title, description, actions }: Props) {
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
