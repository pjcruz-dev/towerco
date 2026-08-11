import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";

/** Layout-stable placeholder shown while a recharts chart chunk loads. */
export function DashboardChartSkeleton({
  title,
  description,
  height = 220,
  className,
}: {
  title?: string;
  description?: string;
  height?: number;
  className?: string;
}) {
  return (
    <Card className={className ?? "shadow-sm"}>
      <CardHeader className="border-b pb-3">
        <CardTitle className="text-base font-medium">
          {title ? title : <span className="inline-block h-4 w-32 animate-pulse rounded bg-muted/50" />}
        </CardTitle>
        {description ? (
          <p className="text-xs font-normal text-muted-foreground">{description}</p>
        ) : null}
      </CardHeader>
      <CardContent className="pt-4">
        <div
          className="w-full animate-pulse rounded-md bg-muted/40"
          style={{ height }}
          aria-hidden
        />
      </CardContent>
    </Card>
  );
}
