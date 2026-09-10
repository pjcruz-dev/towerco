export type PaginatedMeta = {
  total: number;
  per_page: number;
  current_page: number;
  last_page: number;
  status_counts?: {
    all?: number;
    processing?: number;
    ready?: number;
    failed?: number;
  };
};

export type PaginatedEnvelope<T> = {
  data: T[];
  meta: PaginatedMeta;
};
