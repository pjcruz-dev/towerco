export const CONTROLLED_REGISTER_PATH = "/documents/controlled/register";

export const controlledRegisterParams = {
  q: "q",
  sort: "sort",
  department: "filter[department]",
  documentType: "filter[document_type]",
  status: "filter[status]",
  review: "filter[review]",
  file: "filter[file]",
  effectiveFrom: "filter[effective_from]",
  effectiveTo: "filter[effective_to]",
  publishedFrom: "filter[published_from]",
  publishedTo: "filter[published_to]",
  document: "document",
  page: "page",
} as const;

const LIST_QUERY_KEYS = new Set<string>([
  controlledRegisterParams.q,
  controlledRegisterParams.sort,
  controlledRegisterParams.department,
  controlledRegisterParams.documentType,
  controlledRegisterParams.status,
  controlledRegisterParams.review,
  controlledRegisterParams.file,
  controlledRegisterParams.effectiveFrom,
  controlledRegisterParams.effectiveTo,
  controlledRegisterParams.publishedFrom,
  controlledRegisterParams.publishedTo,
]);

export type ControlledRegisterQuery = {
  q: string;
  sort: string;
  department: string;
  documentType: string;
  status: string;
  review: string;
  file: string;
  effectiveFrom: string;
  effectiveTo: string;
  publishedFrom: string;
  publishedTo: string;
  documentId: string | null;
  page: number;
};

export function readControlledRegisterPage(raw: string | null): number {
  if (!raw || !/^[1-9]\d*$/.test(raw)) {
    return 1;
  }

  const page = Number(raw);

  return page > 10_000 ? 1 : page;
}

const REVIEW_LABELS: Record<string, string> = {
  overdue: "Overdue",
  due_30: "Due in 30 days",
  due_90: "Due in 90 days",
  none: "No review date",
};

const FILE_LABELS: Record<string, string> = {
  has: "Has file",
  missing: "Missing file",
};

const STATUS_LABELS: Record<string, string> = {
  pending: "Pending",
  published: "Published",
  obsolete: "Obsolete",
  incoming: "Incoming",
};

export type ControlledRegisterChip = {
  id: string;
  label: string;
  clear: Record<string, null>;
};

export function readControlledRegisterQuery(
  params: URLSearchParams,
  defaultSort: string,
): ControlledRegisterQuery {
  const keys = controlledRegisterParams;

  return {
    q: params.get(keys.q) ?? "",
    sort: params.get(keys.sort) || defaultSort,
    department: params.get(keys.department) ?? "",
    documentType: params.get(keys.documentType) ?? "",
    status: params.get(keys.status) ?? "",
    review: params.get(keys.review) ?? "",
    file: params.get(keys.file) ?? "",
    effectiveFrom: params.get(keys.effectiveFrom) ?? "",
    effectiveTo: params.get(keys.effectiveTo) ?? "",
    publishedFrom: params.get(keys.publishedFrom) ?? "",
    publishedTo: params.get(keys.publishedTo) ?? "",
    documentId: params.get(keys.document),
    page: readControlledRegisterPage(params.get(keys.page)),
  };
}

export function controlledRegisterHref(
  current: { toString(): string },
  patch: Record<string, string | null>,
  defaultSort: string,
): string {
  const next = new URLSearchParams(current.toString());

  for (const [key, value] of Object.entries(patch)) {
    if (value === null || value === "") {
      next.delete(key);
    } else {
      next.set(key, value);
    }
  }

  if ((next.get(controlledRegisterParams.sort) ?? "") === defaultSort) {
    next.delete(controlledRegisterParams.sort);
  }

  const listChanged = Object.keys(patch).some((key) => LIST_QUERY_KEYS.has(key));
  if (listChanged && !Object.prototype.hasOwnProperty.call(patch, controlledRegisterParams.page)) {
    next.delete(controlledRegisterParams.page);
  }

  const pageValue = next.get(controlledRegisterParams.page);
  if (pageValue !== null && (pageValue === "1" || !/^[1-9]\d*$/.test(pageValue))) {
    next.delete(controlledRegisterParams.page);
  }

  const qs = next.toString();

  return qs ? `${CONTROLLED_REGISTER_PATH}?${qs}` : CONTROLLED_REGISTER_PATH;
}

export function controlledRegisterHasFilters(query: ControlledRegisterQuery): boolean {
  return Boolean(
    query.q ||
      query.department ||
      query.documentType ||
      query.status ||
      query.review ||
      query.file ||
      query.effectiveFrom ||
      query.effectiveTo ||
      query.publishedFrom ||
      query.publishedTo,
  );
}

export function controlledRegisterFilterChips(query: ControlledRegisterQuery): ControlledRegisterChip[] {
  const keys = controlledRegisterParams;
  const chips: ControlledRegisterChip[] = [];

  if (query.q) {
    chips.push({ id: "q", label: `Search: ${query.q}`, clear: { [keys.q]: null } });
  }
  if (query.department) {
    chips.push({
      id: "department",
      label: `Department: ${query.department}`,
      clear: { [keys.department]: null },
    });
  }
  if (query.documentType) {
    chips.push({
      id: "type",
      label: `Type: ${query.documentType}`,
      clear: { [keys.documentType]: null },
    });
  }
  if (query.status) {
    chips.push({
      id: "status",
      label: `Status: ${STATUS_LABELS[query.status] ?? query.status}`,
      clear: { [keys.status]: null },
    });
  }
  if (query.review) {
    chips.push({
      id: "review",
      label: `Review: ${REVIEW_LABELS[query.review] ?? query.review}`,
      clear: { [keys.review]: null },
    });
  }
  if (query.file) {
    chips.push({
      id: "file",
      label: FILE_LABELS[query.file] ?? query.file,
      clear: { [keys.file]: null },
    });
  }
  if (query.publishedFrom || query.publishedTo) {
    const from = query.publishedFrom || "…";
    const to = query.publishedTo || "…";
    chips.push({
      id: "published",
      label: query.publishedTo ? `Published ${from} – ${to}` : `Published since ${from}`,
      clear: { [keys.publishedFrom]: null, [keys.publishedTo]: null },
    });
  }
  if (query.effectiveFrom || query.effectiveTo) {
    const from = query.effectiveFrom || "…";
    const to = query.effectiveTo || "…";
    chips.push({
      id: "effective",
      label: `Effective ${from} – ${to}`,
      clear: { [keys.effectiveFrom]: null, [keys.effectiveTo]: null },
    });
  }

  return chips;
}

export function controlledRegisterCountLabel(
  documents:
    | {
        current_page: number;
        per_page: number;
        total: number;
        data: readonly unknown[];
      }
    | undefined,
  isLoading: boolean,
): string {
  if (isLoading && !documents) {
    return "Loading documents";
  }

  const total = documents?.total ?? 0;
  if (!documents || total === 0 || documents.data.length === 0) {
    return `${total} documents`;
  }

  const from = (documents.current_page - 1) * documents.per_page + 1;
  const to = from + documents.data.length - 1;
  if (from === 1 && to === total) {
    return total === 1 ? "1 document" : `${total} documents`;
  }
  if (from === to) {
    return `Showing ${from} of ${total}`;
  }

  return `Showing ${from}–${to} of ${total}`;
}

export function controlledRegisterClearFilters(): Record<string, null> {
  const keys = controlledRegisterParams;

  return {
    [keys.q]: null,
    [keys.department]: null,
    [keys.documentType]: null,
    [keys.status]: null,
    [keys.review]: null,
    [keys.file]: null,
    [keys.effectiveFrom]: null,
    [keys.effectiveTo]: null,
    [keys.publishedFrom]: null,
    [keys.publishedTo]: null,
  };
}
