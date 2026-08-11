import type { DashboardWidget } from "@/types/ui";

export function WidgetGrid({ widgets }: { widgets: DashboardWidget[] }) {
  return (
    <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
      {widgets.map((widget) => (
        <article key={widget.id} className="rounded-lg border bg-card p-4">
          <p className="text-sm text-muted-foreground">{widget.title}</p>
          <p className="mt-2 text-2xl font-semibold">{widget.value}</p>
          {widget.description ? (
            <p className="mt-2 text-xs text-muted-foreground">{widget.description}</p>
          ) : null}
        </article>
      ))}
    </section>
  );
}
