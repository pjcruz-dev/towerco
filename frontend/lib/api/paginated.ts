export type PaginatedMeta = {
  total: number;
  per_page: number;
  current_page: number;
  last_page: number;
  /** Dynamic Entities: sums for fields with calculate_totals over the filtered set. */
  column_totals?: Record<string, number>;
};

export type PaginatedEnvelope<T> = {
  data: T[];
  meta: PaginatedMeta;
};
