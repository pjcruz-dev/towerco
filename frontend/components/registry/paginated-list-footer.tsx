"use client";

import { Button } from "@/components/ui/button";
import type { PaginatedMeta } from "@/lib/api/paginated";

export function PaginatedListFooter({
  meta,
  onPageChange,
  isPending,
}: {
  meta: PaginatedMeta;
  onPageChange: (page: number) => void;
  isPending: boolean;
}) {
  return (
    <div className="flex flex-wrap items-center justify-between gap-2 border-t border-border px-4 py-2.5 text-xs text-muted-foreground">
      <span>
        Page {meta.current_page} of {meta.last_page} · {meta.total} total
      </span>
      <div className="flex gap-2">
        <Button
          type="button"
          size="sm"
          variant="outline"
          disabled={meta.current_page <= 1 || isPending}
          onClick={() => onPageChange(meta.current_page - 1)}
        >
          Previous
        </Button>
        <Button
          type="button"
          size="sm"
          variant="outline"
          disabled={meta.current_page >= meta.last_page || isPending}
          onClick={() => onPageChange(meta.current_page + 1)}
        >
          Next
        </Button>
      </div>
    </div>
  );
}
