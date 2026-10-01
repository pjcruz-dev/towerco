import type { ReactNode } from "react";

type Props = {
  eyebrow?: ReactNode;
  title: string;
  description?: ReactNode;
  actions?: ReactNode;
};

/** Shared tenant / workspace page header (aligned with E-Forms settings). */
export function WorkspacePageHeader({ eyebrow, title, description, actions }: Props) {
  return (
    <header className="flex flex-wrap items-start justify-between gap-3">
      <div>
        {eyebrow ? (
          <p className="mb-1 text-xs font-medium uppercase tracking-wide text-muted-foreground">{eyebrow}</p>
        ) : null}
        <h1 className="text-2xl font-semibold tracking-tight text-foreground">{title}</h1>
        {description ? <div className="mt-1 text-sm text-muted-foreground">{description}</div> : null}
      </div>
      {actions ? <div className="flex flex-wrap gap-2">{actions}</div> : null}
    </header>
  );
}
