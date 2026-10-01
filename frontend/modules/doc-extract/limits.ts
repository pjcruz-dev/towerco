/** Keep in sync with backend `config/doc_extract.php` → `max_files_per_batch`. */
export const DOC_EXTRACT_MAX_FILES_PER_BATCH = 500;

/** Preview OCR in chunks so large selections do not trip PHP/nginx body limits. */
export const DOC_EXTRACT_PREVIEW_CHUNK_SIZE = 8;
