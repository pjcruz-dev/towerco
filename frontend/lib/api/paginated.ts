export type PaginatedMeta = {
  total: number;
  per_page: number;
  current_page: number;
  last_page: number;
};

export type PaginatedEnvelope<T> = {
  data: T[];
  meta: PaginatedMeta;
};
