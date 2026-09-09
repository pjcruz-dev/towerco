from __future__ import annotations

import io
import logging
from typing import Any

import fitz  # PyMuPDF
import pytesseract
from fastapi import FastAPI, File, Form, UploadFile
from PIL import Image

from .extract import discover_fields, map_fields, parse_fields_json

logger = logging.getLogger("doc_extract")
logging.basicConfig(level=logging.INFO)

app = FastAPI(title="TowerOS DocExtract", version="0.2.0")

SPARSE_CHAR_THRESHOLD = 40


@app.get("/health")
def health() -> dict[str, str]:
    return {"status": "ok"}


@app.post("/v1/scan")
async def scan(
    file: UploadFile = File(...),
    mime_type: str | None = Form(default=None),
    fields_json: str | None = Form(default=None),
) -> dict[str, Any]:
    raw = await file.read()
    filename = file.filename or "document"
    content_type = (mime_type or file.content_type or "").lower()
    warnings: list[str] = []

    if content_type.startswith("image/") or _looks_like_image(filename):
        pages, engine = _ocr_image_bytes(raw, warnings)
    else:
        pages, engine = _scan_pdf_bytes(raw, warnings)

    page_texts = [str(page.get("text") or "").strip() for page in pages]
    extracted_text = "\n\n".join(text for text in page_texts if text)
    fields = parse_fields_json(fields_json)
    discovered_fields: list[dict[str, Any]] = []

    if fields:
        field_values = map_fields(fields, extracted_text)
    else:
        discovered_fields, field_values = discover_fields(extracted_text)

    return {
        "engine": engine,
        "pages": pages,
        "warnings": warnings,
        "field_values": field_values,
        "discovered_fields": discovered_fields,
        "mode": "template" if fields else "auto",
    }


def _looks_like_image(filename: str) -> bool:
    lower = filename.lower()
    return lower.endswith((".png", ".jpg", ".jpeg", ".webp", ".tif", ".tiff"))


def _scan_pdf_bytes(raw: bytes, warnings: list[str]) -> tuple[list[dict[str, Any]], str]:
    doc = fitz.open(stream=raw, filetype="pdf")
    pages: list[dict[str, Any]] = []
    used_ocr = False

    try:
        for index, page in enumerate(doc, start=1):
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
