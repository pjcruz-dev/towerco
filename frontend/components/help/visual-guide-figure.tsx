import type { VisualCallout, VisualGuideSection } from "@/lib/help/e-approval-visual-guide";
import { cn } from "@/lib/utils";

type VisualGuideFigureProps = {
  section: VisualGuideSection;
  className?: string;
};

function CalloutBadge({ n }: { n: number }) {
  return (
    <span
      className="flex h-6 w-6 items-center justify-center rounded-full bg-red-600 text-xs font-semibold text-white shadow-sm ring-2 ring-white dark:ring-slate-900"
      aria-hidden
    >
      {n}
    </span>
  );
}

function CalloutMarkers({ callouts }: { callouts: VisualCallout[] }) {
  return (
    <div className="pointer-events-none absolute inset-0" aria-hidden>
      {callouts.map((callout) => (
        <span
          key={callout.n}
          className="absolute -translate-x-1/2 -translate-y-1/2"
          style={{ left: `${callout.x}%`, top: `${callout.y}%` }}
        >
          <CalloutBadge n={callout.n} />
        </span>
      ))}
    </div>
  );
}

export function VisualGuideFigure({ section, className }: VisualGuideFigureProps) {
  return (
    <article
      className={cn(
        "break-inside-avoid rounded-xl border border-border bg-card p-5 shadow-sm md:p-6",
        className,
      )}
    >
      <header className="mb-4 space-y-1">
        <h3 className="text-base font-medium text-foreground">{section.title}</h3>
        <p className="text-sm text-muted-foreground">{section.description}</p>
      </header>

      <div className="relative overflow-hidden rounded-lg border border-border bg-muted/20">
        {/* eslint-disable-next-line @next/next/no-img-element -- guide screenshots; keep layout 1:1 with callout % */}
        <img
          src={section.imageSrc}
          alt={section.imageAlt}
          className="block h-auto w-full select-none"
          draggable={false}
        />
        <CalloutMarkers callouts={section.callouts} />
      </div>

      <ol className="mt-5 grid list-none gap-x-6 gap-y-3 p-0 sm:grid-cols-2">
        {section.callouts.map((callout) => (
          <li key={callout.n} className="flex items-start gap-3 text-sm">
            <span className="mt-0.5 shrink-0">
              <CalloutBadge n={callout.n} />
            </span>
            <div className="min-w-0">
              <p className="font-medium text-foreground">{callout.title}</p>
              <p className="mt-0.5 text-muted-foreground">{callout.body}</p>
            </div>
          </li>
        ))}
      </ol>

      {section.tip ? (
        <p className="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-950 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-100">
          {section.tip}
        </p>
      ) : null}
    </article>
  );
}
