/** Document register removed — stub keeps legacy E-Forms compose imports compiling. */
export type ControlledDocumentLookupResult = {
  id: string;
  document_code: string;
  title: string | null;
  document_type: string | null;
  department: string | null;
  current_revision: string | null;
  status: string | null;
};

export async function lookupControlledDocument(_code: string): Promise<ControlledDocumentLookupResult> {
  throw new Error("Document register has been removed from this workspace.");
}

export async function fetchControlledDocuments(): Promise<never> {
  throw new Error("Document register has been removed from this workspace.");
}
