import {
  Pagination,
  PaginationContent,
  PaginationEllipsis,
  PaginationItem,
  PaginationLink,
  PaginationNext,
  PaginationPrevious,
} from "@/components/base-ui/pagination";
import { cn } from "@/lib/utils";

const linkClass =
  "h-6 min-w-6 w-auto px-1.5 text-xs shadow-none tabular-nums transition-colors hover:bg-neutral-50 active:scale-95 dark:hover:bg-neutral-900";
const activeClass = "h-6 min-w-6 w-auto px-1.5 text-xs !border-primary/50 shadow-none";
const navClass =
  "h-6 gap-0.5 px-1.5 text-xs shadow-none transition-colors hover:bg-neutral-100 dark:hover:bg-neutral-800 [&_svg]:size-3";

export type Pagination1Props = {
  page?: number;
  pageCount?: number;
  onPageChange?: (page: number) => void;
  disabled?: boolean;
  className?: string;
};

function pageItems(page: number, pageCount: number): Array<number | "gap"> {
  if (pageCount <= 7) {
    return Array.from({ length: pageCount }, (_, index) => index + 1);
  }
  const items: Array<number | "gap"> = [1];
  const start = Math.max(2, page - 1);
  const end = Math.min(pageCount - 1, page + 1);
  if (start > 2) items.push("gap");
  for (let current = start; current <= end; current += 1) items.push(current);
  if (end < pageCount - 1) items.push("gap");
  items.push(pageCount);
  return items;
}

const Pagination1 = ({
  page = 2,
  pageCount = 3,
  onPageChange,
  disabled = false,
  className,
}: Pagination1Props) => {
  const total = Math.max(1, pageCount);
  const current = Math.min(total, Math.max(1, page));
  const go = (next: number) => {
    if (disabled || next < 1 || next > total || next === current) return;
    onPageChange?.(next);
  };

  return (
    <Pagination className={cn("py-1", className)}>
      <PaginationContent className="gap-1">
        <PaginationItem>
          <PaginationPrevious
            text="Back"
            size="xs"
            className={navClass}
            disabled={disabled || current <= 1}
            onClick={() => go(current - 1)}
          />
        </PaginationItem>
        {pageItems(current, total).map((item, index) =>
          item === "gap" ? (
            <PaginationItem key={`gap-${index}`}>
              <PaginationEllipsis className="size-6" />
            </PaginationItem>
          ) : (
            <PaginationItem key={item}>
              <PaginationLink
                isActive={item === current}
                size="xs"
                className={item === current ? activeClass : linkClass}
                aria-label={`Page ${item}`}
                disabled={disabled}
                onClick={() => go(item)}
              >
                {item}
              </PaginationLink>
            </PaginationItem>
          ),
        )}
        <PaginationItem>
          <PaginationNext
            text="Next"
            size="xs"
            className={navClass}
            disabled={disabled || current >= total}
            onClick={() => go(current + 1)}
          />
        </PaginationItem>
      </PaginationContent>
    </Pagination>
  );
};

export default Pagination1;
