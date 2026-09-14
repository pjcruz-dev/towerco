# DocExtract OCR sidecar (local)

Scan PDFs/images for TowerOS DocExtract. Laravel calls this service; do not expose publicly.

## Run with Compose

From the repo root:

```bash
docker compose up -d --build doc-extract
```

Health: `http://localhost:8082/health` (host port) or `http://doc-extract:8081/health` from the API container.

## Env (API)

```
DOC_EXTRACT_URL=http://doc-extract:8081
```

When the API runs on the host (not in Docker), use `DOC_EXTRACT_URL=http://127.0.0.1:8082`.

## Endpoints

- `GET /health`
- `POST /v1/page-count` multipart `file` (+ optional `mime_type`) → `{ "page_count": N }`
- `POST /v1/preview` multipart `file` (+ optional `mime_type`) → page thumbnails for Consolidate (no OCR)
- `POST /v1/scan` multipart `file` (+ optional `mime_type`, `fields_json`, `page`, `pages`) — `pages` is comma/range list (e.g. `1,2,5-7`)
- `POST /v1/map` form `text` + `fields_json` — map OCR text onto fields without re-OCR
