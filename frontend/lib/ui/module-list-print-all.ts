/**
 * Fetch paginated list rows for Print all.
 * Sets larger than MODULE_LIST_PRINT_ALL_CAP should queue a printable HTML export
 * via the module export API (My exports) — see printAllOrQueuePrintableExport.
 */

export const MODULE_LIST_PRINT_ALL_CAP = 5000;
export const MODULE_LIST_PRINT_PAGE_SIZE = 100;

export type ListPageResult<T> = {
  data: T[];
  meta: {
    last_page?: number;
    total?: number;
  };
};

export async function fetchModuleListRowsCapped<T>(options: {
  fetchPage: (page: number, perPage: number) => Promise<ListPageResult<T>>;
  maxRows?: number;
  perPage?: number;
}): Promise<{ rows: T[]; total: number; truncated: boolean }> {
  const maxRows = options.maxRows ?? MODULE_LIST_PRINT_ALL_CAP;
  const perPage = Math.min(options.perPage ?? MODULE_LIST_PRINT_PAGE_SIZE, maxRows);
  const rows: T[] = [];
  let page = 1;
  let total = 0;
  let lastPage = 1;

  while (rows.length < maxRows) {
    const result = await options.fetchPage(page, perPage);
    total = result.meta.total ?? total;
    lastPage = result.meta.last_page ?? page;
    rows.push(...result.data);
    if (page >= lastPage || result.data.length === 0) break;
    page += 1;
  }

  const sliced = rows.slice(0, maxRows);
  const knownTotal = total || rows.length;
  const truncated = sliced.length < knownTotal;

  return {
    rows: sliced,
    total: knownTotal,
    truncated,
  };
}

export type PrintAllOrQueueResult =
  | { mode: "printed"; rows: number; total: number }
  | { mode: "queued"; total: number; message: string };

/**
 * Print in-browser when ≤ cap; otherwise queue uncapped printable HTML via export API.
 */
export async function printAllOrQueuePrintableExport<T>(options: {
  fetchPage: (page: number, perPage: number) => Promise<ListPageResult<T>>;
  print: (rows: T[], meta: { total: number; truncated: boolean }) => void;
  queuePrintableExport: () => Promise<{ message?: string } | void>;
  maxRows?: number;
}): Promise<PrintAllOrQueueResult> {
  const result = await fetchModuleListRowsCapped({
    fetchPage: options.fetchPage,
    maxRows: options.maxRows,
  });

  if (!result.truncated) {
    options.print(result.rows, { total: result.total, truncated: false });
    return { mode: "printed", rows: result.rows.length, total: result.total };
  }

  const queued = await options.queuePrintableExport();
  return {
    mode: "queued",
    total: result.total,
    message:
      queued && typeof queued === "object" && typeof queued.message === "string"
        ? queued.message
        : `Queued printable HTML for ${result.total.toLocaleString()} rows. Open Settings → My exports when ready, then open the file to print.`,
  };
}
