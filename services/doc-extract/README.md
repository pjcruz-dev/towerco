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
- `POST /v1/scan` multipart `file` (+ optional `mime_type`)
