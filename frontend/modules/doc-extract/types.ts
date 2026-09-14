export type DocExtractFieldType =
  | "text"
  | "multiline"
  | "number"
  | "currency"
  | "percentage"
  | "date"
  | "email"
  | "phone"
  | "boolean"
  | "table";

export type DocExtractTableColumn = {
  key: string;
  label: string;
  type: Exclude<DocExtractFieldType, "table" | "multiline" | "email" | "phone">;
  description?: string | null;
};

export type DocExtractField = {
  key: string;
  label: string;
  type: DocExtractFieldType;
  description?: string | null;
  hint?: string | null;
  columns?: DocExtractTableColumn[];
  /** When false, key stays synced from label. Defaults to true once user edits the key. */
  keyManual?: boolean;
};

export type DocExtractTemplateStatus = "draft" | "published";

export type DocExtractTemplate = {
  id: string;
  name: string;
  description?: string | null;
  /** Omitted only for legacy clients; treat missing as published after migrate. */
  status?: DocExtractTemplateStatus;
  fields: DocExtractField[];
  created_at?: string | null;
  updated_at?: string | null;
};

export type DocExtractBatchListRow = {
  id: string;
  status: string;
  mode?: "auto" | "template" | string;
  document_count: number;
  ready_count: number;
  failed_count: number;
  message?: string | null;
  template_id?: string | null;
  template_name?: string | null;
  /** First distinct uploaded/source file name for the batch. */
  primary_filename?: string | null;
  /** Distinct source file count (UI shows " +++" when > 1). */
  file_count?: number | null;
  created_at?: string | null;
  updated_at?: string | null;
};

export type DocExtractDocument = {
  id: string;
  batch_id: string;
  template_id?: string | null;
  original_filename: string;
  mime_type?: string | null;
  size_bytes?: number | null;
  status: string;
  scan_engine?: string | null;
  mode?: "auto" | "template" | string;
  page_count?: number | null;
  field_values: Record<string, string | null>;
  discovered_fields?: DocExtractField[];
  /** 1-based page when this row was created via page split / consolidate. */
  source_page?: number | null;
  /** Exact pages included in this record after consolidate. */
  source_pages?: number[] | null;
  error_message?: string | null;
  purged_at?: string | null;
  has_file: boolean;
  created_at?: string | null;
  updated_at?: string | null;
};

export type DocExtractBatchDetail = DocExtractBatchListRow & {
  template: DocExtractTemplate | null;
  effective_fields?: DocExtractField[];
  documents: DocExtractDocument[];
};

export type DocExtractPreviewPage = {
  page: number;
  thumbnail: string | null;
  text_chars: number;
  likely_blank: boolean;
};

export type DocExtractPreviewFile = {
  index: number;
  filename: string;
  mime_type: string | null;
  size_bytes: number;
  page_count: number;
  pages: DocExtractPreviewPage[];
};

export type DocExtractConsolidateRecord = {
  id: string;
  fileIndex: number;
  pages: number[];
  label: string;
};
