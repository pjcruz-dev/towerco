from __future__ import annotations

import base64
import io
import logging
from typing import Any

import fitz  # PyMuPDF
import pytesseract
from fastapi import FastAPI, File, Form, HTTPException, UploadFile
from PIL import Image

from .extract import discover_fields, map_fields, parse_fields_json

logger = logging.getLogger("doc_extract")
logging.basicConfig(level=logging.INFO)

app = FastAPI(title="TowerOS DocExtract", version="0.3.0")

SPARSE_CHAR_THRESHOLD = 40
PREVIEW_MAX_PAGES = 50
PREVIEW_JPEG_QUALITY = 55
PREVIEW_SCALE = 0.35


@app.get("/health")
def health() -> dict[str, str]:
    return {"status": "ok"}


@app.post("/v1/page-count")
async def page_count(
    file: UploadFile = File(...),
    mime_type: str | None = Form(default=None),
) -> dict[str, Any]:
    raw = await file.read()
    filename = file.filename or "document"
    content_type = (mime_type or file.content_type or "").lower()

    if content_type.startswith("image/") or _looks_like_image(filename):
        return {"page_count": 1}

    doc = fitz.open(stream=raw, filetype="pdf")
    try:
        return {"page_count": max(1, int(doc.page_count))}
    finally:
        doc.close()


@app.post("/v1/preview")
async def preview(
    file: UploadFile = File(...),
    mime_type: str | None = Form(default=None),
) -> dict[str, Any]:
    """Return page count + small JPEG thumbnails for consolidate UI (no OCR)."""
    raw = await file.read()
    filename = file.filename or "document"
    content_type = (mime_type or file.content_type or "").lower()

    if content_type.startswith("image/") or _looks_like_image(filename):
        thumb = _image_thumbnail_data_url(raw)
        return {
            "page_count": 1,
            "pages": [
                {
                    "page": 1,
                    "thumbnail": thumb,
                    "text_chars": 0,
                    "likely_blank": False,
                }
            ],
        }

    doc = fitz.open(stream=raw, filetype="pdf")
    try:
        total = max(1, int(doc.page_count))
        if total > PREVIEW_MAX_PAGES:
            raise HTTPException(
                status_code=422,
                detail=f"PDF has {total} pages; max for consolidate preview is {PREVIEW_MAX_PAGES}.",
            )
        pages: list[dict[str, Any]] = []
        for index in range(1, total + 1):
            page = doc.load_page(index - 1)
            text = (page.get_text("text") or "").strip()
            text_chars = len(text)
            likely_blank = text_chars < SPARSE_CHAR_THRESHOLD
            thumb = None
            try:
                pix = page.get_pixmap(matrix=fitz.Matrix(PREVIEW_SCALE, PREVIEW_SCALE), alpha=False)
                image = Image.open(io.BytesIO(pix.tobytes("png"))).convert("RGB")
                buffer = io.BytesIO()
                image.save(buffer, format="JPEG", quality=PREVIEW_JPEG_QUALITY, optimize=True)
                thumb = "data:image/jpeg;base64," + base64.b64encode(buffer.getvalue()).decode("ascii")
            except Exception:  # noqa: BLE001
                logger.exception("preview thumbnail failed for page %s", index)
            pages.append(
                {
                    "page": index,
                    "thumbnail": thumb,
                    "text_chars": text_chars,
                    "likely_blank": likely_blank,
                }
            )
        return {"page_count": total, "pages": pages}
    finally:
        doc.close()


@app.post("/v1/map")
async def map_text(
    text: str = Form(...),
    fields_json: str | None = Form(default=None),
) -> dict[str, Any]:
    """Map already-OCR'd text onto a field schema without re-running OCR."""
    fields = parse_fields_json(fields_json)
    if not fields:
        raise HTTPException(status_code=422, detail="fields_json is required and must list at least one field.")
    extracted = (text or "").strip()
    return {
        "field_values": map_fields(fields, extracted),
        "mode": "template",
    }


@app.post("/v1/scan")
async def scan(
    file: UploadFile = File(...),
    mime_type: str | None = Form(default=None),
    fields_json: str | None = Form(default=None),
    page: str | None = Form(default=None),
    pages: str | None = Form(default=None),
) -> dict[str, Any]:
    raw = await file.read()
    filename = file.filename or "document"
    content_type = (mime_type or file.content_type or "").lower()
    warnings: list[str] = []
    try:
        page_list = _parse_page_list(page=page, pages=pages)
    except ValueError as exc:
        raise HTTPException(status_code=422, detail=str(exc)) from exc

    if content_type.startswith("image/") or _looks_like_image(filename):
        if page_list is not None and page_list != [1]:
            warnings.append("Images have only one page; scanning page 1 only.")
        scanned_pages, engine = _ocr_image_bytes(raw, warnings)
    else:
        try:
            scanned_pages, engine = _scan_pdf_bytes(raw, warnings, page_numbers=page_list)
        except ValueError as exc:
            raise HTTPException(status_code=422, detail=str(exc)) from exc

    page_texts = [str(item.get("text") or "").strip() for item in scanned_pages]
    extracted_text = "\n\n".join(text for text in page_texts if text)
    fields = parse_fields_json(fields_json)
    discovered_fields: list[dict[str, Any]] = []

    if fields:
        field_values = map_fields(fields, extracted_text)
    else:
        discovered_fields, field_values = discover_fields(extracted_text)

    return {
        "engine": engine,
        "pages": scanned_pages,
        "warnings": warnings,
        "field_values": field_values,
        "discovered_fields": discovered_fields,
        "mode": "template" if fields else "auto",
    }


def _looks_like_image(filename: str) -> bool:
    lower = filename.lower()
    return lower.endswith((".png", ".jpg", ".jpeg", ".webp", ".tif", ".tiff"))


def _image_thumbnail_data_url(raw: bytes) -> str | None:
    try:
        image = Image.open(io.BytesIO(raw)).convert("RGB")
        image.thumbnail((180, 240))
        buffer = io.BytesIO()
        image.save(buffer, format="JPEG", quality=PREVIEW_JPEG_QUALITY, optimize=True)
        return "data:image/jpeg;base64," + base64.b64encode(buffer.getvalue()).decode("ascii")
    except Exception:  # noqa: BLE001
        logger.exception("image thumbnail failed")
        return None


def _parse_optional_page(raw: str | None) -> int | None:
    if raw is None or str(raw).strip() == "":
        return None
    try:
        value = int(str(raw).strip())
    except ValueError as exc:
        raise ValueError("page must be a positive integer") from exc
    if value < 1:
        raise ValueError("page must be a positive integer")
    return value


def _parse_page_list(*, page: str | None, pages: str | None) -> list[int] | None:
    """Accept single `page` or comma/range list in `pages` (e.g. 1,2,5-7)."""
    if pages is not None and str(pages).strip() != "":
        result: list[int] = []
        for part in str(pages).split(","):
            token = part.strip()
            if token == "":
                continue
            if "-" in token:
                ends = token.split("-", 1)
                try:
                    start = int(ends[0].strip())
                    end = int(ends[1].strip())
                except ValueError as exc:
                    raise ValueError("pages must be integers or ranges like 1-3") from exc
                if start < 1 or end < start:
                    raise ValueError("pages ranges must be ascending positive integers")
                result.extend(range(start, end + 1))
            else:
                try:
                    value = int(token)
                except ValueError as exc:
                    raise ValueError("pages must be positive integers") from exc
                if value < 1:
                    raise ValueError("pages must be positive integers")
                result.append(value)
        # Preserve order, drop duplicates.
        seen: set[int] = set()
        ordered: list[int] = []
        for value in result:
            if value in seen:
                continue
            seen.add(value)
            ordered.append(value)
        return ordered if ordered else None

    single = _parse_optional_page(page)
    return [single] if single is not None else None


def _scan_pdf_bytes(
    raw: bytes,
    warnings: list[str],
    page_numbers: list[int] | None = None,
) -> tuple[list[dict[str, Any]], str]:
    doc = fitz.open(stream=raw, filetype="pdf")
    pages: list[dict[str, Any]] = []
    used_ocr = False

    try:
        total = int(doc.page_count)
        if page_numbers is not None:
            for index in page_numbers:
                if index > total:
                    raise ValueError(f"page {index} is out of range (PDF has {total} page(s))")
            indices = page_numbers
        else:
            indices = list(range(1, total + 1))

        for index in indices:
            page = doc.load_page(index - 1)
            text = (page.get_text("text") or "").strip()
            if len(text) < SPARSE_CHAR_THRESHOLD:
                used_ocr = True
                pix = page.get_pixmap(matrix=fitz.Matrix(2.5, 2.5), alpha=False)
                image = Image.open(io.BytesIO(pix.tobytes("png")))
                ocr_text = pytesseract.image_to_string(image, config="--psm 6") or ""
                text = ocr_text.strip()
                if not text:
                    warnings.append(f"Page {index} produced little or no text after OCR.")
            pages.append({"page": index, "text": text})
    finally:
        doc.close()

    engine = "pymupdf+tesseract" if used_ocr else "pymupdf"
    return pages, engine


def _ocr_image_bytes(raw: bytes, warnings: list[str]) -> tuple[list[dict[str, Any]], str]:
    try:
        image = Image.open(io.BytesIO(raw))
        text = (pytesseract.image_to_string(image, config="--psm 6") or "").strip()
        if not text:
            warnings.append("Image OCR returned empty text.")
        return [{"page": 1, "text": text}], "tesseract"
    except Exception as exc:  # noqa: BLE001
        logger.exception("image OCR failed")
        warnings.append(str(exc))
        return [{"page": 1, "text": ""}], "tesseract"
