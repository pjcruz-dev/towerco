export type RelatedRecordAttachment = {
  id: string;
  file_name: string;
  mime_type: string | null;
  size_bytes: number | null;
  source: "ticketing" | "e_approval" | "document_register" | "doc_extract";
  record_id: string;
};

export type RelatedRecord = {
  id: string;
  title: string;
  subtitle: string;
  href: string;
  status: string;
  attachments: RelatedRecordAttachment[];
};

export type RelatedRecordModule = {
  key: string;
  label: string;
  records: RelatedRecord[];
};

export type RelatedRecordsResponse = {
  modules: RelatedRecordModule[];
};
