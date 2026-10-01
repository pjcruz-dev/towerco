from __future__ import annotations

import json
import re
from datetime import datetime
from typing import Any


_CURRENCY_RE = re.compile(
    r"(?:PHP|PhP|Php|₱|P)\s*([0-9]{1,3}(?:,[0-9]{3})*(?:\.[0-9]{2})?|[0-9]+(?:\.[0-9]{2})?)"
    r"|([0-9]{1,3}(?:,[0-9]{3})+(?:\.[0-9]{2})?|[0-9]+\.[0-9]{2})\s*(?:PHP|PhP|₱)?"
)

_DATE_RE = re.compile(
    r"\b("
    r"\d{4}[-/]\d{1,2}[-/]\d{1,2}"
    r"|\d{1,2}[-/]\d{1,2}[-/]\d{2,4}"
    r"|(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\s+\d{1,2},?\s+\d{4}"
    r")\b",
    re.IGNORECASE,
)


def parse_fields_json(raw: str | None) -> list[dict[str, Any]]:
    if not raw or not raw.strip():
        return []
    try:
        data = json.loads(raw)
    except json.JSONDecodeError:
        return []
    if not isinstance(data, list):
        return []
    fields: list[dict[str, Any]] = []
    for item in data:
        if not isinstance(item, dict):
            continue
        key = str(item.get("key") or "").strip()
        if not key:
            continue
        row: dict[str, Any] = {
            "key": key,
            "label": str(item.get("label") or key).strip(),
            "type": str(item.get("type") or "text").strip().lower(),
            "hint": str(item.get("hint") or "").strip(),
        }
        columns = item.get("columns")
        if isinstance(columns, list) and columns:
            row["columns"] = columns
        fields.append(row)
    return fields


def map_fields(fields: list[dict[str, Any]], text: str) -> dict[str, str | None]:
    normalized = _normalize_text(text)
    values: dict[str, str | None] = {}
    for field in fields:
        key = field["key"]
        field_type = (field.get("type") or "text").lower()
        if field_type == "table":
            table = _find_named_table(normalized, field)
            values[key] = json.dumps(table, ensure_ascii=False) if table else None
            continue
        raw = _find_value(normalized, field)
        coerced = _coerce(raw, field_type)
        values[key] = _sanitize_mapped_value(field, coerced)
    return values


def _sanitize_mapped_value(field: dict[str, Any], value: str | None) -> str | None:
    if value is None:
        return None
    cleaned = str(value).strip()
    if cleaned == "":
        return None
    key = (field.get("key") or "").lower()
    label = (field.get("label") or "").lower()
    field_type = (field.get("type") or "text").lower()
    blob = f"{key} {label}"

    if field_type == "date" or "date" in blob:
        if _looks_like_identifier_not_date(cleaned) or not _looks_like_date_value(cleaned):
            # Allow already-normalized ISO dates from _coerce.
            if not re.fullmatch(r"\d{4}-\d{2}-\d{2}", cleaned):
                return None
    if "reference" in blob:
        if not _looks_like_reference_no(cleaned) and not re.fullmatch(r"\d{6,}", cleaned):
            # Keep numeric-only refs; reject "Purchase" etc.
            if not re.search(r"\d", cleaned) or cleaned.lower().startswith("purchase"):
                return None
    if _looks_like_label_fragment(cleaned) or cleaned.lower() in {"purchase", "purchase order"}:
        return None
    return cleaned


def discover_fields(text: str, max_fields: int = 80) -> tuple[list[dict[str, Any]], dict[str, str | None]]:
    """
    Build fields from OCR:
    1) Layout-aware recommendations (CitiDirect payment advice, etc.)
    2) Detect repeated row blocks as type=table
    3) Label: value scalars (always merged; skips keys already recommended)
    """
    normalized = _normalize_text(text)
    fields: list[dict[str, Any]] = []
    values: dict[str, str | None] = {}
    seen_keys: set[str] = set()

    def add_field(
        label: str,
        value: str | None,
        field_type: str = "text",
        hint: str | None = None,
        columns: list[dict[str, Any]] | None = None,
        key: str | None = None,
    ) -> None:
        nonlocal fields, values
        if len(fields) >= max_fields:
            return
        field_key = key or _slugify(label)
        if not field_key or field_key in seen_keys:
            return
        if value is None or str(value).strip() == "":
            return
        if _looks_like_label_fragment(str(value)):
            return
        stored = value if field_type == "table" else _coerce(value, field_type)
        if stored is None or str(stored).strip() == "":
            return
        # Allow closely related keys (present_reading vs present_rdg) — only block exact duplicates.
        seen_keys.add(field_key)
        row: dict[str, Any] = {
            "key": field_key,
            "label": label.strip()[:180],
            "type": field_type,
            "hint": hint,
            "description": None,
        }
        if field_type == "table" and columns:
            row["columns"] = columns
        fields.append(row)
        values[field_key] = stored

    # Prefer curated layout recommendations (CitiDirect, etc.) over noisy OCR pairs.
    recommended = _recommend_layout_fields(normalized)
    for item in recommended:
        add_field(
            item["label"],
            item.get("value"),
            item.get("type") or "text",
            hint=item.get("hint"),
            columns=item.get("columns"),
            key=item.get("key"),
        )

    # Tables — skip if a layout already recommended the same table key.
    for table_field, table_json in _discover_tables(normalized):
        add_field(
            table_field["label"],
            table_json,
            "table",
            hint=table_field.get("hint"),
            columns=table_field.get("columns"),
            key=table_field.get("key"),
        )

    # Always merge generic Label: value pairs for fuller capture (skip keys already taken).
    # Skip noisy auto-pairs on structured OT worksheets / WCN forms — layouts already recommended.
    skip_generic_pairs = _looks_like_ot_calculation_sheet(normalized) or _looks_like_wcn(normalized)
    if not skip_generic_pairs:
        for label, raw in _iter_label_value_pairs(normalized):
            if _is_month_token(label):
                continue
            if _looks_like_table_row(f"{label} - {raw}"):
                continue
            if _looks_like_label_fragment(raw, label):
                continue
            # Skip ID-like "labels" created from codes such as OT26-0163.
            if re.fullmatch(r"[A-Z]{1,4}\d{2,}", label.strip(), flags=re.IGNORECASE):
                continue
            field_type = _guess_type(label, raw)
            add_field(label, raw, field_type)

    return fields, values


_CITIDIRECT_RECOMMENDED: list[dict[str, str]] = [
    {"key": "printed_on", "label": "Printed On", "type": "text", "hint": "Printed on"},
    {
        "key": "transaction_reference_number",
        "label": "Transaction Reference Number",
        "type": "text",
        "hint": "Transaction Reference Number",
    },
    {
        "key": "debit_account",
        "label": "Debit Account",
        "type": "text",
        "hint": "Account Number / Account Currency / Account Name",
    },
    {"key": "account_number", "label": "Account Number", "type": "text", "hint": "Account Number"},
    {"key": "account_currency", "label": "Account Currency", "type": "text", "hint": "Account Currency"},
    {"key": "account_name", "label": "Account Name", "type": "text", "hint": "Account Name"},
    {"key": "payment_currency", "label": "Payment Currency", "type": "text", "hint": "Payment Currency"},
    {
        "key": "payment_amount",
        "label": "Payment Amount",
        "type": "currency",
        "hint": "Payment Currency / Payment Amount",
    },
    {"key": "payment_type", "label": "Payment Type", "type": "text", "hint": "Payment Type"},
    {"key": "payment_method", "label": "Payment Method", "type": "text", "hint": "Payment Method"},
    {"key": "creation_method", "label": "Creation Method", "type": "text", "hint": "Creation Method"},
    {"key": "ordering_party", "label": "Ordering Party", "type": "text", "hint": "Ordering Party"},
    {"key": "value_date", "label": "Value Date", "type": "date", "hint": "Value Date"},
    {
        "key": "beneficiary_account_number",
        "label": "Beneficiary Account Number",
        "type": "text",
        "hint": "Beneficiary Account Number",
    },
    {"key": "beneficiary_name", "label": "Beneficiary Name", "type": "text", "hint": "Beneficiary Name"},
    {
        "key": "beneficiary_bank_routing_method",
        "label": "Beneficiary Bank Routing Method",
        "type": "text",
        "hint": "Beneficiary Bank Routing Method",
    },
    {
        "key": "beneficiary_bank_routing_code",
        "label": "Beneficiary Bank Routing Code",
        "type": "text",
        "hint": "Beneficiary Bank Routing Code",
    },
    {
        "key": "beneficiary_bank_name",
        "label": "Beneficiary Bank Name / Address",
        "type": "text",
        "hint": "Beneficiary Bank Name / Address",
    },
    {"key": "charges_indicator", "label": "Charges Indicator", "type": "text", "hint": "Charges Indicator"},
    {"key": "charges_account", "label": "Charges Account", "type": "text", "hint": "Charges Account"},
    {"key": "payment_details", "label": "Payment Details", "type": "multiline", "hint": "Payment Details"},
    {"key": "submitted_by", "label": "Submitted By", "type": "text", "hint": "Submitted By"},
    {
        "key": "submission_date_time",
        "label": "Submission Date/Time",
        "type": "text",
        "hint": "Submission Date/Time",
    },
    {"key": "status", "label": "Status", "type": "text", "hint": "Status"},
    {"key": "sub_status", "label": "Sub-Status", "type": "text", "hint": "Sub-Status"},
]


def _recommend_layout_fields(text: str) -> list[dict[str, Any]]:
    """Return curated field recommendations for known document layouts."""
    recommended: list[dict[str, Any]] = []
    seen: set[str] = set()

    def push(item: dict[str, Any]) -> None:
        key = str(item.get("key") or "")
        value = item.get("value")
        if not key or key in seen or value in (None, ""):
            return
        if item.get("type") != "table" and _looks_like_label_fragment(str(value)):
            return
        seen.add(key)
        recommended.append(item)

    if _looks_like_citidirect(text):
        pairs = _citidirect_pairs(text)
        for spec in _CITIDIRECT_RECOMMENDED:
            value = pairs.get(spec["key"])
            if not value:
                continue
            push(
                {
                    "key": spec["key"],
                    "label": spec["label"],
                    "type": spec["type"],
                    "hint": spec["hint"],
                    "value": value,
                }
            )
        lines = [line.strip() for line in text.split("\n") if line.strip()]
        site_rows = _collect_site_payment_rows(lines)
        if len(site_rows) >= 2:
            columns = list(_DEFAULT_SITE_PAYMENT_COLUMNS)
            payload = {
                "columns": columns,
                "rows": [
                    {
                        "site_id_no": entry["site_id_no"],
                        "account_number": entry["account_number"],
                        "amount_paid": entry["amount_paid"],
                    }
                    for entry in site_rows
                ],
            }
            push(
                {
                    "key": "payment_breakdown",
                    "label": "Payment Breakdown",
                    "type": "table",
                    "hint": "SITE ID NO",
                    "columns": columns,
                    "value": json.dumps(payload, ensure_ascii=False),
                }
            )

    if _looks_like_wcn(text):
        for item in _wcn_recommended(text):
            push(item)

    if _looks_like_ot_calculation_sheet(text):
        for item in _ot_calc_recommended(text):
            push(item)

    if _looks_like_meralco(text):
        for item in _meralco_recommended(text):
            push(item)

    if _looks_like_neeco(text):
        for item in _neeco_recommended(text):
            push(item)

    if _looks_like_iseco_invoice(text):
        for item in _iseco_recommended(text):
            push(item)

    return recommended


# Long names first so regex alternation does not match JAN inside JANUARY.
_MONTHS = (
    "JANUARY",
    "FEBRUARY",
    "MARCH",
    "APRIL",
    "JUNE",
    "JULY",
    "AUGUST",
    "SEPTEMBER",
    "OCTOBER",
    "NOVEMBER",
    "DECEMBER",
    "SEPT",
    "JAN",
    "FEB",
    "MAR",
    "APR",
    "MAY",
    "JUN",
    "JUL",
    "AUG",
    "SEP",
    "OCT",
    "NOV",
    "DEC",
)

_MONTH_TOKEN_RE = re.compile(
    r"^(?:" + "|".join(_MONTHS) + r")(?:\s*[-–/]\s*\d{2,4})?$",
    re.IGNORECASE,
)

_MONTH_ROW_RE = re.compile(
    r"^(?P<month>"
    + "|".join(_MONTHS)
    + r")\s*[-–]?\s*(?P<year>\d{4})?\s+(?P<rest>[\d,.\s]+)$",
    re.IGNORECASE,
)

_TABLE_TITLE_RE = re.compile(
    r"(?im)^(?P<title>.{0,80}?(?:consumption\s+history|payment\s+history|billing\s+history|"
    r"transaction\s+history|monthly\s+consumption|payment\s+details).{0,40})$"
)

_CONSUMPTION_HEADER_RE = re.compile(
    r"(?i)bill\s*month.*(?:present|previous|rdg|kwh|amount)|present\s*rdg|kwh\s*used|bill\s*amount"
)

_PAYMENT_HEADER_RE = re.compile(
    r"(?i)(?:posting\s*date|payment\s*channel(?:s)?|amount\s*paid|payment\s*history)"
)

_DEFAULT_CONSUMPTION_COLUMNS = [
    {"key": "bill_month", "label": "Bill Month", "type": "text", "description": None},
    {"key": "present_rdg", "label": "Present Rdg.", "type": "number", "description": None},
    {"key": "previous_rdg", "label": "Previous Rdg.", "type": "number", "description": None},
    {"key": "kwh_used", "label": "kWh Used", "type": "number", "description": None},
    {"key": "bill_amount", "label": "Bill Amount", "type": "currency", "description": None},
]

_DEFAULT_PAYMENT_COLUMNS = [
    {"key": "billing_period", "label": "Billing Period", "type": "text", "description": None},
    {"key": "posting_date", "label": "Posting Date", "type": "date", "description": None},
    {"key": "payment_channels", "label": "Payment Channels", "type": "text", "description": None},
    {"key": "amount_paid", "label": "Amount Paid", "type": "currency", "description": None},
]

_DEFAULT_SITE_PAYMENT_COLUMNS = [
    {"key": "site_id_no", "label": "Site ID No.", "type": "text", "description": None},
    {"key": "account_number", "label": "Account Number", "type": "text", "description": None},
    {"key": "amount_paid", "label": "Amount Paid", "type": "currency", "description": None},
]


def _discover_tables(text: str) -> list[tuple[dict[str, Any], str]]:
    lines = [line.strip() for line in text.split("\n") if line.strip()]
    found: list[tuple[dict[str, Any], str]] = []
    ot_sheet = _looks_like_ot_calculation_sheet(text)

    # OT calculation worksheets — summary + daily logs (never treat as utility consumption).
    if ot_sheet:
        for table_field, table_json in _discover_ot_calc_tables(text):
            found.append((table_field, table_json))
        return found

    # 1) Month + numbers blocks (Monthly Consumption History style)
    month_rows = _collect_month_numeric_rows(lines)
    if _is_credible_consumption_table(lines, month_rows):
        title = _nearest_table_title(lines, month_rows[0]["line_index"]) or "Monthly Consumption History"
        columns = list(_DEFAULT_CONSUMPTION_COLUMNS)
        rows = [_consumption_cells_from_entry(entry) for entry in month_rows]
        # Drop rows that still have almost no numeric cells after mapping.
        rows = [row for row in rows if _consumption_row_has_signal(row)]
        if len(rows) >= 2:
            payload = {"columns": columns, "rows": rows}
            field = {
                "key": _slugify(title),
                "label": title,
                "type": "table",
                "hint": "Consumption History",
                "columns": columns,
            }
            found.append((field, json.dumps(payload, ensure_ascii=False)))

    # 2) Payment history style: period + date + channel + amount on one line
    payment_rows = _collect_payment_rows(lines)
    if len(payment_rows) >= 2:
        title = _nearest_table_title(lines, payment_rows[0]["line_index"]) or "Payment History"
        # Avoid duplicate if title already used.
        if not any(item[0]["label"].lower() == title.lower() for item in found):
            columns = list(_DEFAULT_PAYMENT_COLUMNS)
            rows = [
                {
                    "billing_period": entry["billing_period"],
                    "posting_date": entry["posting_date"],
                    "payment_channels": entry["payment_channels"],
                    "amount_paid": entry["amount_paid"],
                }
                for entry in payment_rows
            ]
            payload = {"columns": columns, "rows": rows}
            field = {
                "key": _slugify(title),
                "label": title,
                "type": "table",
                "hint": "Payment History",
                "columns": columns,
            }
            found.append((field, json.dumps(payload, ensure_ascii=False)))

    # 3) Multi-site payment breakdown (SITE ID / ACCOUNT NUMBER / AMOUNT PAID)
    site_rows = _collect_site_payment_rows(lines)
    if len(site_rows) >= 2:
        title = "Payment Breakdown"
        if not any(item[0]["label"].lower() == title.lower() for item in found):
            columns = list(_DEFAULT_SITE_PAYMENT_COLUMNS)
            rows = [
                {
                    "site_id_no": entry["site_id_no"],
                    "account_number": entry["account_number"],
                    "amount_paid": entry["amount_paid"],
                }
                for entry in site_rows
            ]
            payload = {"columns": columns, "rows": rows}
            field = {
                "key": "payment_breakdown",
                "label": title,
                "type": "table",
                "hint": "SITE ID NO",
                "columns": columns,
            }
            found.append((field, json.dumps(payload, ensure_ascii=False)))

    return found


def _looks_like_year_token(value: str) -> bool:
    return bool(re.fullmatch(r"(?:19|20)\d{2}", value.strip()))


def _peel_years_into_bill_month(bill_month: str, numbers: list[str]) -> tuple[str, list[str]]:
    """Move bare YYYY tokens out of the number list into Bill Month."""
    years: list[str] = []
    kept: list[str] = []
    for number in numbers:
        if _looks_like_year_token(number):
            years.append(number)
        else:
            kept.append(number)
    month = bill_month.strip()
    if years and not re.search(r"\b(?:19|20)\d{2}\b", month):
        month = f"{month} - {years[0]}" if month else years[0]
    return month, kept


def _map_consumption_numbers(numbers: list[str]) -> dict[str, str]:
    """
    Map OCR numbers onto Present / Previous / kWh / Amount.

    Heuristics:
    - trailing value with a decimal is Bill Amount
    - remaining left-to-right: Present, Previous, kWh
    - if four+ integers remain and no amount yet, last is Amount
    - when only 1–2 numbers exist, prefer Present / Previous (do not invent kWh)
    """
    nums = [n for n in numbers if n != ""]
    amount = ""
    if nums and ("." in nums[-1]):
        amount = nums.pop()
    elif len(nums) >= 4:
        # Last of four+ readings is usually the peso amount (no decimal OCR).
        amount = nums.pop()

    present = nums[0] if len(nums) > 0 else ""
    previous = nums[1] if len(nums) > 1 else ""
    kwh = nums[2] if len(nums) > 2 else ""
    if not amount and len(nums) > 3:
        amount = nums[3]

    # If OCR only captured Present + Amount (decimal), keep previous/kwh empty.
    if amount and previous and not kwh and "." in amount and len(nums) == 2:
        pass

    return {
        "present_rdg": present,
        "previous_rdg": previous,
        "kwh_used": kwh,
        "bill_amount": amount,
    }


def _consumption_cells_from_entry(entry: dict[str, Any]) -> dict[str, str]:
    bill_month, numbers = _peel_years_into_bill_month(
        str(entry.get("bill_month") or ""),
        list(entry.get("numbers") or []),
    )
    cells = _map_consumption_numbers(numbers)
    return {"bill_month": bill_month, **cells}


def _consumption_row_has_signal(row: dict[str, str]) -> bool:
    filled = sum(1 for key in ("present_rdg", "previous_rdg", "kwh_used", "bill_amount") if (row.get(key) or "").strip())
    return filled >= 2 or bool((row.get("bill_amount") or "").strip())


def _has_consumption_context(lines: list[str], near_index: int) -> bool:
    title = _nearest_table_title(lines, near_index)
    if title and any(token in title.lower() for token in ("consumption", "bill month", "kwh")):
        return True
    start = max(0, near_index - 20)
    blob = "\n".join(lines[start : near_index + 4]).lower()
    return bool(
        re.search(
            r"present\s*rdg|previous\s*rdg|kwh\s*used|bill\s*amount|consumption\s*history|monthly\s*consumption",
            blob,
        )
    )


def _is_credible_consumption_table(lines: list[str], month_rows: list[dict[str, Any]]) -> bool:
    if len(month_rows) < 2:
        return False
    blob = "\n".join(lines)
    if _looks_like_ot_calculation_sheet(blob) or _looks_like_wcn(blob):
        return False
    # Always require bill/consumption context — OT sheets have month+number noise.
    if not _has_consumption_context(lines, month_rows[0]["line_index"]):
        return False
    strong = [row for row in month_rows if len(row.get("numbers") or []) >= 3]
    if len(strong) >= 2:
        return True
    total_numbers = sum(len(row.get("numbers") or []) for row in month_rows)
    return total_numbers >= 6 and len(month_rows) >= 2


def _is_numeric_only_line(line: str) -> bool:
    stripped = line.strip()
    if not stripped:
        return False
    if _is_month_token(stripped.split()[0] if stripped.split() else ""):
        return False
    numbers = _extract_numbers(stripped)
    if not numbers:
        return False
    residual = re.sub(
        r"(?:PHP|PhP|₱|P)?\s*[\d,.\s\-–]+",
        "",
        stripped,
        flags=re.IGNORECASE,
    ).strip()
    return residual == ""


def _amount_only_from_line(line: str) -> str | None:
    match = _AMOUNT_ONLY_LINE_RE.match(line.strip())
    if match:
        return match.group("amount").replace(",", "")
    return None


_AMOUNT_ONLY_LINE_RE = re.compile(
    r"^(?:PHP|PhP|₱|P)?\s*(?P<amount>\d{1,3}(?:,\d{3})*(?:\.\d{2})|\d+\.\d{2})\s*$"
)


def _absorb_following_numeric_lines(
    lines: list[str],
    start_index: int,
    numbers: list[str],
    *,
    target: int = 4,
) -> tuple[list[str], int]:
    """
    OCR often stacks Present / Previous / kWh / Amount on separate lines under the month.
    Pull those into the current row until we have enough numbers or hit a non-numeric line.
    """
    merged = list(numbers)
    cursor = start_index
    while cursor < len(lines) and len(merged) < target:
        nxt = lines[cursor].strip()
        if not nxt:
            cursor += 1
            continue
        if _MONTH_ROW_RE.match(nxt) or (_is_month_token(nxt.split()[0]) if nxt.split() else False):
            break
        amount = _amount_only_from_line(nxt)
        if amount and not any("." in n for n in merged):
            merged.append(amount)
            cursor += 1
            continue
        if _is_numeric_only_line(nxt):
            extras = _extract_numbers(nxt)
            # Avoid swallowing a following month's year-only line.
            if len(extras) == 1 and _looks_like_year_token(extras[0]) and not merged:
                break
            merged.extend(extras)
            cursor += 1
            continue
        break
    return merged, cursor


def _collect_month_numeric_rows(lines: list[str]) -> list[dict[str, Any]]:
    rows: list[dict[str, Any]] = []
    index = 0
    while index < len(lines):
        line = lines[index]
        match = _MONTH_ROW_RE.match(line)
        if match:
            month = match.group("month")
            year = match.group("year") or ""
            numbers = _extract_numbers(match.group("rest"))
            bill_month = f"{month.upper()[:3]} - {year}" if year else month.upper()[:3]
            bill_month, numbers = _peel_years_into_bill_month(bill_month, numbers)
            numbers, consumed_through = _absorb_following_numeric_lines(lines, index + 1, numbers)
            if len(numbers) < 1:
                index += 1
                continue
            rows.append({"line_index": index, "bill_month": bill_month, "numbers": numbers})
            index = max(index + 1, consumed_through)
            continue

        # Fallback for OCR spacing quirks around "FEB - 2026 …"
        parts = line.split()
        if not parts or not _is_month_token(parts[0]) or not _looks_like_table_row(line):
            # Bare month line with numbers on following lines (common OCR split).
            if parts and _is_month_token(parts[0]) and len(parts) <= 3:
                month = parts[0]
                year = ""
                nums_start = 1
                if len(parts) > 1 and re.fullmatch(r"\d{4}", parts[1].strip("-–")):
                    year = parts[1].strip("-–")
                    nums_start = 2
                elif len(parts) > 2 and parts[1] in {"-", "–"} and re.fullmatch(r"\d{4}", parts[2]):
                    year = parts[2]
                    nums_start = 3
                inline_numbers = _extract_numbers(" ".join(parts[nums_start:]))
                bill_month = f"{month.upper()[:3]} - {year}" if year else month.upper()[:3]
                bill_month, inline_numbers = _peel_years_into_bill_month(bill_month, inline_numbers)
                numbers, consumed_through = _absorb_following_numeric_lines(
                    lines, index + 1, inline_numbers
                )
                if len(numbers) >= 2:
                    rows.append({"line_index": index, "bill_month": bill_month, "numbers": numbers})
                    index = max(index + 1, consumed_through)
                    continue
            index += 1
            continue

        month = parts[0]
        year = ""
        nums_start = 1
        if len(parts) > 1 and re.fullmatch(r"\d{4}", parts[1].strip("-–")):
            year = parts[1].strip("-–")
            nums_start = 2
        elif len(parts) > 2 and parts[1] in {"-", "–"} and re.fullmatch(r"\d{4}", parts[2]):
            year = parts[2]
            nums_start = 3
        numbers = _extract_numbers(" ".join(parts[nums_start:]))
        bill_month = f"{month.upper()[:3]} - {year}" if year else month.upper()[:3]
        bill_month, numbers = _peel_years_into_bill_month(bill_month, numbers)
        numbers, consumed_through = _absorb_following_numeric_lines(lines, index + 1, numbers)
        if len(numbers) < 1:
            index += 1
            continue
        rows.append({"line_index": index, "bill_month": bill_month, "numbers": numbers})
        index = max(index + 1, consumed_through)
    return rows


def _collect_payment_rows(lines: list[str]) -> list[dict[str, Any]]:
    """
    Meralco / Bayad-style payment history:
      19 Jun-18 Jul 2026  27 Jul 2026  Bayad Partner - OTC  2,676.93
      or split across lines (amount on its own line).
    """
    period_re = re.compile(
        r"(?i)^(?P<period>\d{1,2}\s+[A-Za-z]{3,9}\s*[-–]\s*\d{1,2}\s+[A-Za-z]{3,9}\s+\d{4})\s*$"
    )
    period_with_rest_re = re.compile(
        r"(?i)^(?P<period>\d{1,2}\s+[A-Za-z]{3,9}\s*[-–]\s*\d{1,2}\s+[A-Za-z]{3,9}\s+\d{4})"
        r"\s+(?P<post>\d{1,2}\s+[A-Za-z]{3,9}\s+\d{4})"
        r"\s+(?P<channel>.+?)"
        r"(?:\s+(?P<amount>\d{1,3}(?:,\d{3})*(?:\.\d{2})|\d+\.\d{2}))?\s*$"
    )
    post_channel_re = re.compile(
        r"(?i)^(?P<post>\d{1,2}\s+[A-Za-z]{3,9}\s+\d{4})\s+(?P<channel>.+?)"
        r"(?:\s+(?P<amount>\d{1,3}(?:,\d{3})*(?:\.\d{2})|\d+\.\d{2}))?\s*$"
    )
    amount_only_re = re.compile(
        r"^(?:PHP|PhP|₱|P)?\s*(?P<amount>\d{1,3}(?:,\d{3})*(?:\.\d{2})|\d+\.\d{2})\s*$"
    )
    one_line_re = re.compile(
        r"(?i)^(?P<period>\d{1,2}\s+[A-Za-z]{3,9}\s*[-–]\s*\d{1,2}\s+[A-Za-z]{3,9}\s+\d{4})"
        r"\s+(?P<post>\d{1,2}\s+[A-Za-z]{3,9}\s+\d{4})"
        r"\s+(?P<channel>.+?)"
        r"\s+(?P<amount>\d{1,3}(?:,\d{3})*(?:\.\d{2})?|\d+\.\d{2})\s*$"
    )

    rows: list[dict[str, Any]] = []
    index = 0
    while index < len(lines):
        line = lines[index].replace("\x00", "").strip()
        if not line:
            index += 1
            continue

        one = one_line_re.match(line)
        if one and one.group("amount"):
            rows.append(
                {
                    "line_index": index,
                    "billing_period": one.group("period").strip(),
                    "posting_date": one.group("post").strip(),
                    "payment_channels": one.group("channel").strip(),
                    "amount_paid": one.group("amount").replace(",", ""),
                }
            )
            index += 1
            continue

        with_rest = period_with_rest_re.match(line)
        if with_rest:
            amount = with_rest.group("amount")
            next_index = index + 1
            if not amount and next_index < len(lines):
                amount_match = amount_only_re.match(lines[next_index].replace("\x00", "").strip())
                if amount_match:
                    amount = amount_match.group("amount")
                    next_index += 1
            if amount:
                rows.append(
                    {
                        "line_index": index,
                        "billing_period": with_rest.group("period").strip(),
                        "posting_date": with_rest.group("post").strip(),
                        "payment_channels": with_rest.group("channel").strip(),
                        "amount_paid": amount.replace(",", ""),
                    }
                )
                index = next_index
                continue

        period_only = period_re.match(line)
        if period_only and index + 1 < len(lines):
            post_line = lines[index + 1].replace("\x00", "").strip()
            post_match = post_channel_re.match(post_line)
            if post_match:
                amount = post_match.group("amount")
                next_index = index + 2
                if not amount and next_index < len(lines):
                    amount_match = amount_only_re.match(lines[next_index].replace("\x00", "").strip())
                    if amount_match:
                        amount = amount_match.group("amount")
                        next_index += 1
                if amount:
                    rows.append(
                        {
                            "line_index": index,
                            "billing_period": period_only.group("period").strip(),
                            "posting_date": post_match.group("post").strip(),
                            "payment_channels": post_match.group("channel").strip(),
                            "amount_paid": amount.replace(",", ""),
                        }
                    )
                    index = next_index
                    continue

        index += 1
    return rows


_SITE_ID_RE = re.compile(
    r"^(?P<site>(?:NS|NTG|OWO)[A-Z0-9\-]{3,}|[A-Z]{2,}-[A-Z0-9\-]{4,})\s*$",
    re.IGNORECASE,
)
_SITE_ACCOUNT_RE = re.compile(r"^(?P<account>\d{2,4}[- ]?\d{3,4}[- ]?\d{3,4}|\d{8,12})\s*$")
_SITE_AMOUNT_RE = re.compile(r"^(?P<amount>\d{1,3}(?:,\d{3})*(?:\.\d{2})|\d+\.\d{2})\s*$")
_SITE_INLINE_RE = re.compile(
    r"^(?P<site>(?:NS|NTG|OWO)[A-Z0-9\-]+)\s+(?P<account>\d{2,4}[- ]?\d{3,4}[- ]?\d{3,4}|\d{8,12})"
    r"\s+(?P<amount>\d{1,3}(?:,\d{3})*(?:\.\d{2})|\d+\.\d{2})\s*$",
    re.IGNORECASE,
)


def _collect_site_payment_rows(lines: list[str]) -> list[dict[str, Any]]:
    """
    Bank payment breakdown pages:
      SITE ID NO. / ACCOUNT NUMBER / AMOUNT PAID
      NS-BIZ20-A92
      29-0102-0302
      25,940.08
    """
    rows: list[dict[str, Any]] = []
    index = 0
    while index < len(lines):
        line = lines[index]
        if line.upper() in {"TOTAL", "PAYMENT BREAKDOWN", "SITE ID NO.", "SITE ID NO", "ACCOUNT NUMBER", "AMOUNT PAID"}:
            index += 1
            continue

        inline = _SITE_INLINE_RE.match(line)
        if inline:
            rows.append(
                {
                    "line_index": index,
                    "site_id_no": inline.group("site").upper(),
                    "account_number": inline.group("account").replace(" ", ""),
                    "amount_paid": inline.group("amount").replace(",", ""),
                }
            )
            index += 1
            continue

        site_match = _SITE_ID_RE.match(line)
        if site_match and index + 2 < len(lines):
            account_match = _SITE_ACCOUNT_RE.match(lines[index + 1])
            amount_match = _SITE_AMOUNT_RE.match(lines[index + 2])
            if account_match and amount_match:
                rows.append(
                    {
                        "line_index": index,
                        "site_id_no": site_match.group("site").upper(),
                        "account_number": account_match.group("account").replace(" ", ""),
                        "amount_paid": amount_match.group("amount").replace(",", ""),
                    }
                )
                index += 3
                continue
        index += 1
    return rows


def _find_named_table(text: str, field: dict[str, Any]) -> dict[str, Any] | None:
    label = (field.get("label") or "").lower()
    hint = (field.get("hint") or "").lower()
    blob = f"{label} {hint}"
    discovered = _discover_tables(text)
    if not discovered:
        return None

    chosen: dict[str, Any] | None = None
    for table_field, table_json in discovered:
        title = (table_field.get("label") or "").lower()
        if any(token in title for token in ("payment", "consumption", "history")) and any(
            token in blob for token in ("payment", "consumption", "history", title.split()[0])
        ):
            chosen = json.loads(table_json)
            break
        if label and label in title:
            chosen = json.loads(table_json)
            break

    columns = field.get("columns") if isinstance(field.get("columns"), list) else []
    if chosen is None:
        chosen = json.loads(discovered[0][1])
        if columns:
            for _, table_json in discovered:
                parsed = json.loads(table_json)
                if len(parsed.get("columns") or []) == len(columns):
                    chosen = parsed
                    break

    if not chosen:
        return None

    # Prefer the caller's column schema while keeping discovered row values.
    if columns:
        return _align_table_to_columns(chosen, columns)
    return chosen


def _align_table_to_columns(table: dict[str, Any], columns: list[dict[str, Any]]) -> dict[str, Any]:
    source_columns = table.get("columns") if isinstance(table.get("columns"), list) else []
    source_rows = table.get("rows") if isinstance(table.get("rows"), list) else []
    target_columns: list[dict[str, Any]] = []
    for index, column in enumerate(columns):
        if not isinstance(column, dict):
            continue
        target_columns.append(
            {
                "key": str(column.get("key") or f"col_{index + 1}"),
                "label": str(column.get("label") or column.get("key") or f"Column {index + 1}"),
                "type": str(column.get("type") or "text"),
                "description": column.get("description"),
            }
        )

    aligned_rows: list[dict[str, str]] = []
    for row in source_rows:
        if not isinstance(row, dict):
            continue
        next_row: dict[str, str] = {}
        for index, target in enumerate(target_columns):
            key = target["key"]
            label = str(target.get("label") or "")
            value = row.get(key)
            if value is None and index < len(source_columns) and isinstance(source_columns[index], dict):
                source_key = str(source_columns[index].get("key") or "")
                value = row.get(source_key)
            if value is None and label:
                value = row.get(label)
            next_row[key] = "" if value is None else str(value)
        aligned_rows.append(next_row)

    return {"columns": target_columns, "rows": aligned_rows}


def _nearest_table_title(lines: list[str], near_index: int) -> str | None:
    start = max(0, near_index - 8)
    window = lines[start : near_index + 1]
    for line in reversed(window):
        # OCR often splits "Monthly Consumption History" — collapse spaces for match.
        collapsed = re.sub(r"\s+", "", line).lower()
        if "consumptionhistory" in collapsed or "monthlyconsumption" in collapsed:
            return "Monthly Consumption History"
        if "paymenthistory" in collapsed or "whatyouvepaid" in collapsed or "whatyou'vepaid" in collapsed.replace("'", ""):
            return "Payment History"
        match = _TABLE_TITLE_RE.match(line)
        if match:
            return re.sub(r"\s+", " ", match.group("title")).strip()[:120]
        if _CONSUMPTION_HEADER_RE.search(line) and "month" in line.lower():
            return "Monthly Consumption History"
        if _PAYMENT_HEADER_RE.search(line):
            return "Payment History"
        if re.search(r"(?i)posting\s*date|payment\s*channels|amount\s*paid", line):
            return "Payment History"
    return None


def _is_month_token(value: str) -> bool:
    token = value.strip().upper().rstrip(".")
    return bool(_MONTH_TOKEN_RE.match(token)) or token in {m.upper() for m in _MONTHS}


def _looks_like_table_row(line: str) -> bool:
    if _MONTH_ROW_RE.match(line):
        return True
    parts = line.split()
    if not parts:
        return False
    if _is_month_token(parts[0]) and len(_extract_numbers(line)) >= 2:
        return True
    return False


def _extract_numbers(text: str) -> list[str]:
    return [
        match.group(0).replace(",", "")
        for match in re.finditer(r"\d{1,3}(?:,\d{3})+(?:\.\d+)?|\d+\.\d+|\d+", text)
    ]


_LABEL_VALUE_RE = re.compile(
    r"(?m)^(?P<label>[A-Za-z][A-Za-z0-9 /#().&\-]{1,60}?)\s*(?::|\s-\s)\s*(?P<value>.+?)\s*$"
)

_SKIP_LABELS = {
    "page",
    "pages",
    "date",
    "time",
    "www",
    "http",
    "https",
    "tel",
    "fax",
    "email",
}


def _iter_label_value_pairs(text: str) -> list[tuple[str, str]]:
    pairs: list[tuple[str, str]] = []
    for match in _LABEL_VALUE_RE.finditer(text):
        label = match.group("label").strip()
        value = _clean_value(match.group("value"))
        if not label or not value:
            continue
        if label.lower() in _SKIP_LABELS:
            continue
        if _is_month_token(label):
            continue
        if _looks_like_table_row(match.group(0)):
            continue
        if len(value) > 120:
            continue
        pairs.append((label, value))
    return pairs


def _guess_type(label: str, value: str) -> str:
    blob = f"{label} {value}".lower()
    if "email" in blob or "@" in value:
        return "email"
    if "phone" in blob or "mobile" in blob or "tel" in blob:
        return "phone"
    if "date" in blob or _DATE_RE.search(value):
        return "date"
    if any(token in blob for token in ("amount", "total", "due", "php", "₱", "price", "cost")):
        return "currency"
    if "%" in value or "percent" in blob:
        return "percentage"
    if re.fullmatch(r"[\d,]+(?:\.\d+)?", value.strip()):
        return "number"
    return "text"


def _slugify(label: str) -> str:
    cleaned = re.sub(r"[^a-z0-9]+", "_", label.strip().lower())
    cleaned = re.sub(r"_+", "_", cleaned).strip("_")
    return cleaned[:80]


def _normalize_text(value: str) -> str:
    cleaned = value.replace("\r\n", "\n").replace("\r", "\n")
    # Keep line structure for table detection; only collapse spaces within lines.
    lines = [re.sub(r"[ \t]+", " ", line).strip() for line in cleaned.split("\n")]
    return "\n".join(line for line in lines if line)

def _find_value(text: str, field: dict[str, Any]) -> str | None:
    # More specific utility layouts before CitiDirect (hybrid payment packs).
    for lookup in (_ot_calc_lookup, _wcn_lookup, _iseco_lookup, _neeco_lookup, _meralco_lookup, _citidirect_lookup):
        hit = lookup(text, field)
        if hit is not None and str(hit).strip() != "" and not _looks_like_label_fragment(str(hit)):
            return hit

    needles = _needles_for(field)
    for needle in needles:
        hit = _match_label_value(text, needle)
        if hit and not _looks_like_label_fragment(hit, needle):
            return hit

    field_type = field.get("type") or "text"
    key = (field.get("key") or "").lower()
    label = (field.get("label") or "").lower()
    blob = f"{key} {label}"

    if field_type == "currency" or "amount" in blob or "total" in blob:
        return _find_currency(text, prefer_total="total" in blob or "due" in blob)
    if field_type == "date" or "date" in blob:
        return _find_date_near(text, needles)
    if "can" in blob or ("account" in blob and "debit" not in blob and "beneficiary" not in blob):
        return _find_account_number(text)
    if "invoice" in blob or "bill no" in blob or "billing invoice" in blob:
        return _find_invoice_number(text)

    return None


_CITIDIRECT_LABELS: list[tuple[str, tuple[str, ...]]] = [
    ("printed_on", ("printed on",)),
    ("transaction_reference_number", ("transaction reference number",)),
    (
        "debit_account",
        (
            "account number / account currency / account name",
            "account number / account currency / account",
        ),
    ),
    ("debit_iban", ("debit iban account number",)),
    ("payment_currency_amount", ("payment currency / payment amount",)),
    ("payment_type", ("payment type",)),
    ("subsidiary", ("subsidiary identifier / subsidiary name",)),
    ("cheque_number", ("cheque number",)),
    ("payment_method", ("payment method",)),
    ("creation_method", ("creation method",)),
    ("additional_info", ("additional info",)),
    ("confidential", ("confidential",)),
    ("transaction_type", ("transaction type",)),
    ("ordering_party", ("ordering party",)),
    ("value_date", ("value date",)),
    ("beneficiary_account_number", ("beneficiary account number",)),
    ("beneficiary_name", ("beneficiary name",)),
    ("beneficiary_bank_routing_method", ("beneficiary bank routing method",)),
    ("beneficiary_bank_routing_code", ("beneficiary bank routing code",)),
    ("beneficiary_bank_name", ("beneficiary bank name / address", "beneficiary bank name")),
    ("charges_indicator", ("charges indicator",)),
    ("charges_account", ("charges account",)),
    ("payment_details", ("payment details",)),
    ("submitted_by", ("submitted by",)),
    ("submission_date_time", ("submission date/time", "submission date / time")),
    ("status", ("status",)),
    ("sub_status", ("sub-status", "sub status")),
]


def _looks_like_citidirect(text: str) -> bool:
    blob = text.lower()
    return (
        "transaction reference number" in blob
        and ("paylink" in blob or "payment method" in blob)
        and ("beneficiary" in blob or "value date" in blob)
    )


def _citidirect_pairs(text: str) -> dict[str, str]:
    """
    CitiDirect PDF text is label-then-value on consecutive lines, and OCR often wraps:
      Account Number / Account Currency / Account
      Name
      759687019 - PHP - SBA TOWERS...
    """
    if not _looks_like_citidirect(text):
        return {}

    lines = [line.strip() for line in text.split("\n") if line.strip()]
    # Join wrapped compound labels ending with "Account" + next line "Name"
    joined: list[str] = []
    index = 0
    while index < len(lines):
        line = lines[index]
        if (
            index + 1 < len(lines)
            and line.lower().endswith("account")
            and lines[index + 1].lower() in {"name", "address"}
        ):
            joined.append(f"{line} {lines[index + 1]}")
            index += 2
            continue
        joined.append(line)
        index += 1

    known = []
    for key, aliases in _CITIDIRECT_LABELS:
        for alias in aliases:
            known.append((alias, key))
    known.sort(key=lambda item: len(item[0]), reverse=True)

    pairs: dict[str, str] = {}
    index = 0
    while index < len(joined):
        line = joined[index]
        lower = line.lower().rstrip(":")
        matched_key = None
        for alias, key in known:
            if lower == alias:
                matched_key = key
                break
            # Allow startswith only for non-ambiguous labels (not Payment Details header).
            if alias not in {"payment details", "status"} and lower.startswith(alias + " "):
                matched_key = key
                break
        if matched_key is None:
            index += 1
            continue

        # Header line "Payment Details: Transaction Ref No …" is not the remittance block.
        if matched_key == "payment_details" and "transaction ref" in lower:
            index += 1
            continue

        # "Printed on <timestamp>" keeps the value on the same OCR line.
        if matched_key == "printed_on" and lower.startswith("printed on"):
            same_line = re.sub(r"(?i)^printed on\s*", "", line).strip()
            if same_line and matched_key not in pairs:
                pairs[matched_key] = same_line
            index += 1
            continue

        # Value is next non-label line (skip blank-like and other labels).
        value_parts: list[str] = []
        cursor = index + 1
        while cursor < len(joined):
            candidate = joined[cursor]
            cand_lower = candidate.lower().rstrip(":")
            if any(cand_lower == alias or cand_lower.startswith(alias + " ") for alias, _ in known):
                break
            # Stop at payment-breakdown header.
            if cand_lower in {"site id no.", "site id no", "account number", "amount paid", "payment breakdown"}:
                break
            if cand_lower.startswith("page "):
                break
            value_parts.append(candidate)
            # Most CitiDirect cells are single-line; allow a few lines for remittance narrative.
            if matched_key != "payment_details" or len(value_parts) >= 3:
                cursor += 1
                break
            cursor += 1

        raw = " ".join(value_parts).strip()
        if raw and raw != "-" and matched_key not in pairs:
            pairs[matched_key] = raw
        index = max(cursor, index + 1)

    # Split compound account / amount fields.
    debit = pairs.get("debit_account")
    if debit:
        parts = [part.strip() for part in re.split(r"\s*-\s*", debit, maxsplit=2)]
        if len(parts) >= 1 and re.fullmatch(r"\d{6,}", parts[0]):
            pairs.setdefault("account_number", parts[0])
        if len(parts) >= 2 and re.fullmatch(r"[A-Z]{3}", parts[1], re.IGNORECASE):
            pairs.setdefault("account_currency", parts[1].upper())
        if len(parts) >= 3:
            pairs.setdefault("account_name", parts[2])

    pay = pairs.get("payment_currency_amount")
    if pay:
        pay_parts = [part.strip() for part in re.split(r"\s*-\s*", pay, maxsplit=1)]
        if len(pay_parts) == 2 and re.fullmatch(r"[A-Z]{3}", pay_parts[0], re.IGNORECASE):
            pairs.setdefault("payment_currency", pay_parts[0].upper())
            pairs["payment_amount"] = pay_parts[1].replace(",", "")
        elif re.search(r"[\d,]+\.\d{2}", pay):
            amount = re.search(r"([\d,]+\.\d{2})", pay)
            if amount:
                pairs["payment_amount"] = amount.group(1).replace(",", "")

    # Header "Payment Details: Transaction Ref No XXX"
    header = re.search(
        r"(?i)Payment Details:\s*Transaction Ref No\s+([A-Z0-9]+)",
        text,
    )
    if header:
        pairs.setdefault("transaction_reference_number", header.group(1).strip())

    printed = re.search(r"(?i)^Printed on\s+(.+)$", text, flags=re.MULTILINE)
    if printed:
        pairs["printed_on"] = printed.group(1).strip()

    return pairs


def _citidirect_lookup(text: str, field: dict[str, Any]) -> str | None:
    pairs = _citidirect_pairs(text)
    if not pairs:
        return None

    key = (field.get("key") or "").strip().lower()
    hint = (field.get("hint") or "").lower()
    label = (field.get("label") or "").lower()
    blob = f"{key} {hint} {label}"

    # Ambiguous keys also appear on electric invoices inside the same PDF pack.
    # Only use CitiDirect values when the field clearly targets the payment advice.
    citi_only_keys = {
        "printed_on",
        "transaction_reference_number",
        "debit_account",
        "debit_iban",
        "payment_type",
        "payment_method",
        "creation_method",
        "ordering_party",
        "beneficiary_account_number",
        "beneficiary_name",
        "beneficiary_bank_routing_method",
        "beneficiary_bank_routing_code",
        "beneficiary_bank_name",
        "charges_indicator",
        "charges_account",
        "payment_details",
        "submitted_by",
        "submission_date_time",
        "status",
        "sub_status",
        "account_currency",
        "account_name",
        "payment_currency",
        "value_date",
    }
    if key == "account_number":
        value = pairs.get("account_number")
        if value and re.fullmatch(r"\d{6,}", value):
            return value
        return None
    if key == "payment_amount":
        if "payment currency" not in blob and "payment amount" not in blob and "paylink" not in text.lower():
            return None
    elif key not in citi_only_keys:
        return None

    if key in pairs and pairs[key].strip():
        return pairs[key].strip()

    aliases = {
        "debit_account": ("debit_account",),
        "payment_amount": ("payment_amount", "payment_currency_amount"),
        "beneficiary_bank_name": ("beneficiary_bank_name",),
    }
    for candidate in aliases.get(key, ()):
        if candidate in pairs and pairs[candidate].strip():
            return pairs[candidate].strip()
    return None


_WCN_RECOMMENDED: list[dict[str, str]] = [
    {"key": "reference_no", "label": "Reference No.", "type": "text", "hint": "Reference No / WCN Reference No"},
    {"key": "document_date", "label": "Date", "type": "date", "hint": "Date"},
    {"key": "work_order_id", "label": "Work Order ID", "type": "text", "hint": "Work Order ID"},
    {"key": "name", "label": "Name", "type": "text", "hint": "Name / Resource Name"},
    {
        "key": "purchase_order_no",
        "label": "Purchase Order No",
        "type": "text",
        "hint": "Purchase Order No / PO No",
    },
    {"key": "po_value", "label": "PO Value", "type": "currency", "hint": "PO Value"},
    {"key": "currency", "label": "Currency", "type": "text", "hint": "Currency"},
    {"key": "supplier_name", "label": "Supplier Name", "type": "text", "hint": "Supplier Name / Agency Name"},
    {"key": "terms_of_payment", "label": "Terms of payment", "type": "text", "hint": "Terms of payment"},
    {
        "key": "service_start_date",
        "label": "Service Start Date",
        "type": "date",
        "hint": "Service Start Date",
    },
    {"key": "service_end_date", "label": "Service End Date", "type": "date", "hint": "Service End Date"},
    {
        "key": "delivery_address",
        "label": "Delivery Address",
        "type": "multiline",
        "hint": "Delivery Address",
    },
]

# Ordered longest-first so "Service Start Date" wins over "Date".
_WCN_LABEL_ALIASES: list[tuple[str, tuple[str, ...]]] = [
    ("service_start_date", ("service start date",)),
    ("service_end_date", ("service end date",)),
    ("delivery_address", ("delivery address",)),
    ("terms_of_payment", ("terms of payment",)),
    ("purchase_order_no", ("purchase order no", "purchase order no.", "purchase order number", "po no", "po no.")),
    ("work_order_id", ("work order id", "work order id.", "work order no", "work order number")),
    ("supplier_name", ("supplier name", "supplier name :")),
    ("reference_no", ("reference no", "reference no.", "wcn reference no", "wcn reference no.")),
    ("po_value", ("po value", "po value :")),
    ("document_date", ("date",)),
    ("currency", ("currency",)),
    ("name", ("name",)),
]


_OT_CALC_RECOMMENDED: list[dict[str, str]] = [
    {"key": "name", "label": "Name", "type": "text", "hint": "Name / Resource Name"},
    {"key": "basic_salary", "label": "Basic Salary", "type": "currency", "hint": "Basic Salary"},
    {"key": "daily_rate", "label": "Daily Rate", "type": "currency", "hint": "Daily Rate"},
    {"key": "hourly_rate", "label": "Hourly Rate", "type": "currency", "hint": "Hourly Rate"},
    {"key": "period_covered", "label": "Period Covered", "type": "text", "hint": "Period Covered"},
    {"key": "subtotal_amount", "label": "Subtotal Amount", "type": "currency", "hint": "Subtotal Amount"},
    {
        "key": "management_fee",
        "label": "Management Fee (10%)",
        "type": "currency",
        "hint": "Management Fee / Add: Management Fee",
    },
    {"key": "ot_total", "label": "TOTAL", "type": "currency", "hint": "TOTAL / Total Amount"},
]

_OT_SUMMARY_COLUMNS = [
    {"key": "ot_type", "label": "OT Premium Rates", "type": "text", "description": None},
    {"key": "rate", "label": "Rate", "type": "currency", "description": None},
    {"key": "hours_01_15", "label": "Hours 01-15", "type": "number", "description": None},
    {"key": "hours_16_30", "label": "Hours 16-30", "type": "number", "description": None},
    {"key": "hours_total", "label": "Hours Total", "type": "number", "description": None},
    {"key": "amount_01_15", "label": "Amount 01-15", "type": "currency", "description": None},
    {"key": "amount_16_30", "label": "Amount 16-30", "type": "currency", "description": None},
    {"key": "amount_total", "label": "Amount Total", "type": "currency", "description": None},
]

_OT_LOG_COLUMNS = [
    {"key": "date", "label": "Date", "type": "text", "description": None},
    {"key": "ot_type", "label": "OT Type", "type": "text", "description": None},
    {"key": "hours", "label": "Hours", "type": "number", "description": None},
]

_OT_ND_COLUMNS = [
    {"key": "date", "label": "Date", "type": "text", "description": None},
    {"key": "start_time", "label": "Start Time", "type": "text", "description": None},
    {"key": "end_time", "label": "End Time", "type": "text", "description": None},
    {"key": "hours", "label": "Total Hours", "type": "number", "description": None},
]


def _looks_like_ot_calculation_sheet(text: str) -> bool:
    blob = text.lower()
    has_rates = "hourly rate" in blob and ("daily rate" in blob or "basic salary" in blob)
    has_premium = "ot premium" in blob or "regular ot" in blob or "rest day ot" in blob
    has_period = "period covered" in blob or re.search(r"\b\d{2}-\d{2}\b", blob) is not None
    has_nd = "night differential" in blob or re.search(r"\bnd\b", blob) is not None
    return has_rates and (has_premium or (has_period and has_nd))


def _ot_calc_pairs(text: str) -> dict[str, str]:
    if not _looks_like_ot_calculation_sheet(text):
        return {}
    pairs: dict[str, str] = {}

    def grab(key: str, *patterns: str) -> None:
        for pattern in patterns:
            match = re.search(pattern, text, flags=re.IGNORECASE | re.MULTILINE)
            if not match:
                continue
            value = _clean_value(match.group(1))
            if value and not _looks_like_label_fragment(value):
                # Never store the next label (e.g. Daily Rate -> "Hourly Rate").
                if _match_ot_calc_label(value):
                    continue
                pairs[key] = value
                return

    grab(
        "name",
        r"(?im)^Name\s*[:\-]\s*([A-Z][A-Z ,.'\-]+)",
        r"(?im)^Name\s*\n\s*([A-Z][A-Z ,.'\-]{5,})",
    )
    grab(
        "basic_salary",
        r"(?im)^Basic\s*Salary\s*[:\-]?\s*([\d,]+\.\d{2})",
        r"(?im)^Basic\s*Salary\s*\n\s*([\d,]+\.\d{2})",
    )
    grab(
        "daily_rate",
        r"(?im)^Daily\s*Rate\s*[:\-]?\s*([\d,]+\.\d{2})",
        r"(?im)^Daily\s*Rate\s*\n\s*([\d,]+\.\d{2})",
    )
    grab(
        "hourly_rate",
        r"(?im)^Hourly\s*Rate\s*[:\-]?\s*([\d,]+\.\d{2})",
        r"(?im)^Hourly\s*Rate\s*\n\s*([\d,]+\.\d{2})",
    )
    grab(
        "period_covered",
        r"(?im)^Period\s*Covered\s*[:\-]?\s*([A-Za-z]+\s+\d{1,2}\s*[-–]\s*\d{1,2},?\s*\d{4})",
        r"(?im)^Period\s*Covered\s*\n\s*([A-Za-z]+\s+\d{1,2}\s*[-–]\s*\d{1,2},?\s*\d{4})",
    )
    grab(
        "subtotal_amount",
        r"(?im)Subtotal\s*Amount[^\n]*?([\d,]+\.\d{2})",
    )
    grab(
        "management_fee",
        r"(?im)Management\s*Fee[^\n]*?([\d,]+\.\d{2})",
    )
    grab(
        "ot_total",
        r"(?im)^TOTAL\s*[:\-]?\s*([\d,]+\.\d{2})",
        r"(?im)TOTAL\s*\n\s*([\d,]+\.\d{2})",
    )

    return pairs


def _match_ot_calc_label(value: str) -> bool:
    lower = value.strip().lower().rstrip(":")
    return lower in {
        "name",
        "basic salary",
        "daily rate",
        "hourly rate",
        "period covered",
        "ot premium rates",
        "no. of hours",
        "amount",
        "subtotal amount",
        "management fee",
        "total",
        "night differential",
        "regular ot",
        "rest day ot",
        "legal holiday ot",
    }


def _ot_calc_recommended(text: str) -> list[dict[str, Any]]:
    pairs = _ot_calc_pairs(text)
    items: list[dict[str, Any]] = []
    for spec in _OT_CALC_RECOMMENDED:
        value = pairs.get(spec["key"])
        if not value:
            continue
        items.append(
            {
                "key": spec["key"],
                "label": spec["label"],
                "type": spec["type"],
                "hint": spec["hint"],
                "value": value,
            }
        )
    for table_field, table_json in _discover_ot_calc_tables(text):
        items.append(
            {
                "key": table_field["key"],
                "label": table_field["label"],
                "type": "table",
                "hint": table_field.get("hint"),
                "columns": table_field.get("columns"),
                "value": table_json,
            }
        )
    return items


def _ot_calc_lookup(text: str, field: dict[str, Any]) -> str | None:
    pairs = _ot_calc_pairs(text)
    if not pairs:
        return None
    key = (field.get("key") or "").strip().lower()
    label = (field.get("label") or "").strip().lower()
    hint = (field.get("hint") or "").strip().lower()
    blob = f"{key} {label} {hint}"

    aliases: dict[str, tuple[str, ...]] = {
        "name": ("name",),
        "resource_name": ("name",),
        "basic_salary": ("basic_salary",),
        "daily_rate": ("daily_rate",),
        "hourly_rate": ("hourly_rate",),
        "period_covered": ("period_covered",),
        "ot_period_from": ("period_covered",),
        "ot_period_to": ("period_covered",),
        "subtotal_amount": ("subtotal_amount",),
        "management_fee": ("management_fee",),
        "ot_total": ("ot_total",),
        "amount_php": ("ot_total",),
        "total": ("ot_total",),
    }
    for candidate in aliases.get(key, ()):
        if candidate in pairs and pairs[candidate].strip():
            return pairs[candidate].strip()

    for pair_key, value in pairs.items():
        human = pair_key.replace("_", " ")
        if pair_key == key or human == label:
            return value
        if pair_key == "daily_rate" and "daily rate" in blob:
            return value
        if pair_key == "hourly_rate" and "hourly rate" in blob:
            return value
        if pair_key == "ot_total" and ("total" in blob or "amount" in blob):
            return value
        if pair_key == "name" and re.search(r"(^|\s)name(\s|$)", label):
            return value
    return None


def _discover_ot_calc_tables(text: str) -> list[tuple[dict[str, Any], str]]:
    if not _looks_like_ot_calculation_sheet(text):
        return []
    found: list[tuple[dict[str, Any], str]] = []
    summary_rows = _collect_ot_summary_rows(text)
    if len(summary_rows) >= 2:
        columns = list(_OT_SUMMARY_COLUMNS)
        payload = {"columns": columns, "rows": summary_rows}
        found.append(
            (
                {
                    "key": "ot_premium_summary",
                    "label": "OT Premium Summary",
                    "type": "table",
                    "hint": "OT Premium Rates",
                    "columns": columns,
                },
                json.dumps(payload, ensure_ascii=False),
            )
        )

    first_half = _collect_ot_log_rows(text, half="first")
    if first_half:
        columns = list(_OT_LOG_COLUMNS)
        payload = {"columns": columns, "rows": first_half}
        found.append(
            (
                {
                    "key": "ot_log_01_15",
                    "label": "OT Log 01-15",
                    "type": "table",
                    "hint": "01-15",
                    "columns": columns,
                },
                json.dumps(payload, ensure_ascii=False),
            )
        )

    second_half = _collect_ot_log_rows(text, half="second")
    if second_half:
        columns = list(_OT_LOG_COLUMNS)
        payload = {"columns": columns, "rows": second_half}
        found.append(
            (
                {
                    "key": "ot_log_16_31",
                    "label": "OT Log 16-31",
                    "type": "table",
                    "hint": "16-31",
                    "columns": columns,
                },
                json.dumps(payload, ensure_ascii=False),
            )
        )

    nd_rows = _collect_ot_nd_rows(text)
    if nd_rows:
        columns = list(_OT_ND_COLUMNS)
        payload = {"columns": columns, "rows": nd_rows}
        found.append(
            (
                {
                    "key": "night_differential_log",
                    "label": "Night Differential",
                    "type": "table",
                    "hint": "ND",
                    "columns": columns,
                },
                json.dumps(payload, ensure_ascii=False),
            )
        )

    return found


_OT_SUMMARY_ROW_RE = re.compile(
    r"(?im)^(?P<kind>Regular\s*OT|Rest\s*Day\s*OT|Legal\s*Holiday\s*OT|Night\s*Differential|"
    r"Special\s*Holiday\s*OT|RD\s*on\s*SH\s*OT|RD\s*on\s*LH\s*OT)"
    r"\s+(?P<rate>\d+(?:\.\d+)?)"
    r"(?:\s+(?P<h1>\d+(?:\.\d+)?|-))?"
    r"(?:\s+(?P<h2>\d+(?:\.\d+)?|-))?"
    r"(?:\s+(?P<ht>\d+(?:\.\d+)?|-))?"
    r"(?:\s+(?P<a1>[\d,]+(?:\.\d+)?|-))?"
    r"(?:\s+(?P<a2>[\d,]+(?:\.\d+)?|-))?"
    r"(?:\s+(?P<at>[\d,]+(?:\.\d+)?|-))?\s*$"
)

_OT_LOG_ROW_RE = re.compile(
    r"(?im)^(?P<date>\d{1,2}[-/][A-Za-z]{3}[-/]\d{2,4}|\d{1,2}-[A-Za-z]{3}-\d{2})"
    r"\s+(?P<kind>REG\s*OT|RDOT|RD|LH|SH|ND|[A-Z]{2,}(?:\s+OT)?)"
    r"\s+(?P<hours>\d+(?:\.\d+)?)\s*$"
)

_OT_ND_ROW_RE = re.compile(
    r"(?im)^(?P<date>\d{1,2}[-/][A-Za-z]{3}[-/]\d{2,4}|\d{1,2}-[A-Za-z]{3}-\d{2})"
    r"\s+(?P<start>\d{1,2}:\d{2})"
    r"\s+(?P<end>\d{1,2}:\d{2}|24:00)"
    r"\s+(?P<hours>\d+(?:\.\d+)?)\s*$"
)


def _dash_or_num(value: str | None) -> str:
    if value is None or value.strip() in {"", "-"}:
        return ""
    return value.replace(",", "").strip()


def _collect_ot_summary_rows(text: str) -> list[dict[str, str]]:
    rows: list[dict[str, str]] = []
    for match in _OT_SUMMARY_ROW_RE.finditer(text):
        rows.append(
            {
                "ot_type": re.sub(r"\s+", " ", match.group("kind")).strip(),
                "rate": _dash_or_num(match.group("rate")),
                "hours_01_15": _dash_or_num(match.group("h1")),
                "hours_16_30": _dash_or_num(match.group("h2")),
                "hours_total": _dash_or_num(match.group("ht")),
                "amount_01_15": _dash_or_num(match.group("a1")),
                "amount_16_30": _dash_or_num(match.group("a2")),
                "amount_total": _dash_or_num(match.group("at")),
            }
        )
    return rows


def _collect_ot_log_rows(text: str, *, half: str) -> list[dict[str, str]]:
    lines = [line.strip() for line in text.split("\n") if line.strip()]
    rows: list[dict[str, str]] = []
    capture = False
    for line in lines:
        lower = line.lower()
        if half == "first" and re.search(r"01\s*[-–]\s*15|may\s*01\s*[-–]\s*15", lower):
            capture = True
            continue
        if half == "second" and re.search(r"16\s*[-–]\s*3[01]|may\s*16\s*[-–]\s*3[01]", lower):
            capture = True
            continue
        if capture and (
            "night differential" in lower
            or "total hours" in lower
            or (half == "first" and re.search(r"16\s*[-–]\s*3[01]", lower))
            or (half == "second" and "night differential" in lower)
        ):
            if "total hours" in lower and rows:
                break
            if half == "first" and re.search(r"16\s*[-–]\s*3[01]", lower):
                break
            if "night differential" in lower:
                break
        if not capture:
            continue
        match = _OT_LOG_ROW_RE.match(line)
        if match:
            rows.append(
                {
                    "date": match.group("date"),
                    "ot_type": re.sub(r"\s+", " ", match.group("kind")).strip(),
                    "hours": match.group("hours"),
                }
            )
    return rows


def _collect_ot_nd_rows(text: str) -> list[dict[str, str]]:
    lines = [line.strip() for line in text.split("\n") if line.strip()]
    rows: list[dict[str, str]] = []
    capture = False
    for line in lines:
        lower = line.lower()
        if "night differential" in lower or re.search(r"\bnd\b", lower):
            capture = True
            continue
        if not capture:
            continue
        if lower.startswith("total"):
            break
        match = _OT_ND_ROW_RE.match(line)
        if match:
            rows.append(
                {
                    "date": match.group("date"),
                    "start_time": match.group("start"),
                    "end_time": match.group("end"),
                    "hours": match.group("hours"),
                }
            )
    return rows


def _looks_like_wcn(text: str) -> bool:
    blob = text.lower()
    return "work completion note" in blob or (
        "purchase order information" in blob
        and "work order id" in blob
        and ("supplier name" in blob or "po value" in blob)
    )


def _is_wcn_section_header(line: str) -> bool:
    lower = re.sub(r"\s+", " ", line.strip().lower())
    if lower in {"work completion note", "purchase order information"}:
        return True
    collapsed = re.sub(r"[^a-z]", "", lower)
    return collapsed in {
        "workcompletionnote",
        "purchaseorderinformation",
        "purchaseorderinfo",
    }


def _looks_like_reference_no(value: str) -> bool:
    cleaned = value.strip()
    if not cleaned or _looks_like_label_fragment(cleaned):
        return False
    if _is_wcn_section_header(cleaned):
        return False
    lower = cleaned.lower()
    if lower in {"purchase", "purchase order", "order", "information", "reference", "date"}:
        return False
    if lower.startswith("purchase"):
        return False
    # Canonical WCN refs: OT26-0163 / OT-26-0163
    if re.fullmatch(r"OT[- ]?\d{2}[- ]?\d{3,6}", cleaned, flags=re.IGNORECASE):
        return True
    # Generic code with at least one digit and a separator or letter prefix.
    if re.fullmatch(r"[A-Z]{1,6}\d{2,}[-/][A-Z0-9]{2,}", cleaned, flags=re.IGNORECASE):
        return True
    if re.search(r"\d", cleaned) and re.search(r"[A-Za-z]", cleaned) and len(cleaned) >= 6:
        if not re.fullmatch(r"[A-Za-z]+", cleaned):
            return True
    return False


def _looks_like_date_value(value: str) -> bool:
    cleaned = value.strip()
    if not cleaned or _looks_like_label_fragment(cleaned):
        return False
    if _DATE_RE.search(cleaned):
        return True
    if re.fullmatch(
        r"(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\.?\s+\d{1,2},?\s+\d{4}",
        cleaned,
        flags=re.IGNORECASE,
    ):
        return True
    return False


def _looks_like_identifier_not_date(value: str) -> bool:
    cleaned = value.strip()
    if _looks_like_date_value(cleaned):
        return False
    if _looks_like_reference_no(cleaned):
        return True
    if re.fullmatch(r"[A-Z0-9][A-Z0-9\-_/]{5,}", cleaned, flags=re.IGNORECASE) and re.search(
        r"\d", cleaned
    ):
        return True
    return False
    blob = text.lower()
    return "work completion note" in blob or (
        "purchase order information" in blob
        and "work order id" in blob
        and ("supplier name" in blob or "po value" in blob)
    )


def _normalize_wcn_label(line: str) -> str:
    return re.sub(r"\s+", " ", line.strip().lower().rstrip(":").strip())


def _match_wcn_label(token: str) -> str | None:
    cleaned = _normalize_wcn_label(token)
    if not cleaned:
        return None
    for key, aliases in _WCN_LABEL_ALIASES:
        for alias in aliases:
            if cleaned == alias or cleaned.rstrip(".") == alias.rstrip("."):
                return key
    return None


def _split_wcn_label_line(line: str) -> list[str]:
    """
    Split 'Work Order ID : Name :' into label keys.
    """
    parts = re.split(r"\s*:\s*", line.strip())
    keys: list[str] = []
    for part in parts:
        if not part.strip():
            continue
        key = _match_wcn_label(part)
        if key:
            keys.append(key)
    return keys


def _split_wcn_value_line(keys: list[str], value_line: str) -> dict[str, str]:
    raw = value_line.strip()
    if not raw or not keys:
        return {}
    if len(keys) == 1:
        return {keys[0]: raw}

    out: dict[str, str] = {}
    remaining = raw

    # Date pairs: "May 1, 2026 May 31, 2026"
    if len(keys) == 2 and all(k.endswith("_date") or k == "document_date" for k in keys):
        dates = list(_DATE_RE.finditer(raw))
        if len(dates) >= 2:
            out[keys[0]] = dates[0].group(1)
            out[keys[1]] = dates[1].group(1)
            return out

    first_key = keys[0]
    if first_key in {"reference_no", "work_order_id", "purchase_order_no", "currency"}:
        tokens = remaining.split()
        if not tokens:
            return {}
        out[first_key] = tokens[0]
        rest = " ".join(tokens[1:]).strip()
        # Strip OCR noise like trailing "vatex" on PO Value cells.
        rest = re.sub(r"\bvatex\b", "", rest, flags=re.IGNORECASE).strip()
        if len(keys) > 1 and rest:
            out[keys[1]] = rest
        return out

    if first_key == "po_value":
        amount_match = re.search(
            r"(\d{1,3}(?:,\d{3})*(?:\.\d{2})?|\d+\.\d{2})",
            remaining,
        )
        if amount_match:
            out[first_key] = amount_match.group(1)
            rest = (remaining[: amount_match.start()] + remaining[amount_match.end() :]).strip()
            rest = re.sub(r"\bvatex\b", "", rest, flags=re.IGNORECASE).strip()
            if len(keys) > 1 and rest:
                out[keys[1]] = rest
            return out

    # Fallback: split on 2+ spaces, else give first token / remainder.
    chunks = re.split(r"\s{2,}", remaining)
    if len(chunks) >= len(keys):
        for index, key in enumerate(keys):
            out[key] = chunks[index].strip()
        return out

    tokens = remaining.split()
    if len(tokens) >= 2:
        out[keys[0]] = tokens[0]
        out[keys[1]] = " ".join(tokens[1:]).strip()
    else:
        out[keys[0]] = remaining
    return out


def _wcn_pairs(text: str) -> dict[str, str]:
    """
    Work Completion Note PDFs are cell grids. OCR often emits:
      Work Order ID : Name :
      ERCSWO00178100 DE GUZMAN, LORETO JR. P.
    Pair labels on one line with values on the next, and skip section headers.
    """
    if not _looks_like_wcn(text):
        return {}

    lines = [line.strip() for line in text.split("\n") if line.strip()]
    pairs: dict[str, str] = {}
    index = 0
    while index < len(lines):
        line = lines[index]
        if _is_wcn_section_header(line):
            index += 1
            continue

        keys = _split_wcn_label_line(line)
        # Require the line to look like labels (contains ':' or pure known label).
        if not keys or (":" not in line and _match_wcn_label(line) is None):
            index += 1
            continue

        # Same-line values after the last colon: "Currency : PHP"
        if ":" in line:
            after = line.rsplit(":", 1)[-1].strip()
            if after and _match_wcn_label(after) is None and not _looks_like_label_fragment(after):
                if len(keys) == 1 and _wcn_value_acceptable(keys[0], after):
                    pairs.setdefault(keys[0], after)
                    index += 1
                    continue

        # Walk forward past section headers / empty noise to the real value line.
        cursor = index + 1
        value_line = ""
        while cursor < len(lines):
            candidate = lines[cursor]
            if _is_wcn_section_header(candidate):
                cursor += 1
                continue
            if _split_wcn_label_line(candidate) and ":" in candidate:
                break
            value_line = candidate
            break

        if not value_line:
            index += 1
            continue

        extracted = _split_wcn_value_line(keys, value_line)
        for key, value in extracted.items():
            cleaned = value.strip()
            if not cleaned or _looks_like_label_fragment(cleaned):
                continue
            if not _wcn_value_acceptable(key, cleaned):
                continue
            if key == "delivery_address" and cursor + 1 < len(lines):
                nxt = lines[cursor + 1]
                if (
                    not _split_wcn_label_line(nxt)
                    and ":" not in nxt
                    and not _is_wcn_section_header(nxt)
                ):
                    cleaned = f"{cleaned} {nxt.strip()}".strip()
                    cursor += 1
            pairs.setdefault(key, cleaned)
        index = max(cursor + 1, index + 1)

    # Prefer explicit OT reference codes anywhere near the header.
    if "reference_no" not in pairs or not _looks_like_reference_no(pairs.get("reference_no", "")):
        match = re.search(
            r"(?im)(?:reference\s*no\.?\s*:?\s*(?:date\s*:?\s*)?\n?\s*)?(OT[- ]?\d{2}[- ]?\d{3,6})\b",
            text,
        )
        if match and _looks_like_reference_no(match.group(1)):
            pairs["reference_no"] = re.sub(r"\s+", "", match.group(1).upper().replace("OT-", "OT"))
            # Normalize OT26-0163 style
            pairs["reference_no"] = re.sub(
                r"(?i)^OT[- ]?(\d{2})[- ]?(\d{3,6})$",
                lambda m: f"OT{m.group(1)}-{m.group(2)}",
                pairs["reference_no"],
            )

    if "work_order_id" not in pairs:
        match = re.search(r"(?im)work\s*order\s*id\.?\s*:?\s*(?:name\s*:?\s*)?\n?\s*([A-Z0-9]{8,})", text)
        if match and not _looks_like_label_fragment(match.group(1)):
            pairs["work_order_id"] = match.group(1).strip()
    if "purchase_order_no" not in pairs:
        match = re.search(
            r"(?im)purchase\s*order\s*no\.?\s*:?\s*(?:po\s*value\s*:?\s*)?\n?\s*(\d{7,})",
            text,
        )
        if match:
            pairs["purchase_order_no"] = match.group(1).strip()
    if "po_value" not in pairs:
        match = re.search(
            r"(?im)po\s*value\s*:?\s*\n?\s*[^\d\n]*(\d{1,3}(?:,\d{3})+(?:\.\d{2})?|\d+\.\d{2})",
            text,
        )
        if match:
            pairs["po_value"] = match.group(1).strip()
    if "supplier_name" not in pairs:
        match = re.search(
            r"(?im)supplier\s*name\s*:?\s*\n?\s*([A-Z0-9][A-Z0-9 ,.&'\-]{8,})",
            text,
        )
        if match and not _looks_like_label_fragment(match.group(1)):
            pairs["supplier_name"] = match.group(1).strip()

    # Drop invalid date captures (IDs mistaken for dates).
    if "document_date" in pairs and not _looks_like_date_value(pairs["document_date"]):
        pairs.pop("document_date", None)

    if "reference_no" in pairs and not _looks_like_reference_no(pairs["reference_no"]):
        pairs.pop("reference_no", None)

    return pairs


def _wcn_value_acceptable(key: str, value: str) -> bool:
    if key == "reference_no":
        return _looks_like_reference_no(value)
    if key in {"document_date", "service_start_date", "service_end_date"}:
        return _looks_like_date_value(value)
    if key == "currency":
        return bool(re.fullmatch(r"[A-Z]{3}", value.strip(), flags=re.IGNORECASE))
    if _is_wcn_section_header(value) or value.strip().lower().startswith("purchase"):
        return False
    return not _looks_like_label_fragment(value)


def _wcn_recommended(text: str) -> list[dict[str, Any]]:
    pairs = _wcn_pairs(text)
    if not pairs:
        return []
    items: list[dict[str, Any]] = []
    for spec in _WCN_RECOMMENDED:
        value = pairs.get(spec["key"])
        if not value:
            continue
        items.append(
            {
                "key": spec["key"],
                "label": spec["label"],
                "type": spec["type"],
                "hint": spec["hint"],
                "value": value,
            }
        )
    return items


def _wcn_lookup(text: str, field: dict[str, Any]) -> str | None:
    pairs = _wcn_pairs(text)
    if not pairs:
        return None
    key = (field.get("key") or "").strip().lower()
    label = (field.get("label") or "").strip().lower()
    hint = (field.get("hint") or "").strip().lower()
    blob = f"{key} {label} {hint}"

    aliases: dict[str, tuple[str, ...]] = {
        "reference_no": ("reference_no",),
        "wcn_reference_no": ("reference_no",),
        "document_date": ("document_date",),
        "date": ("document_date",),
        "work_order_id": ("work_order_id",),
        "name": ("name",),
        "resource_name": ("name",),
        "purchase_order_no": ("purchase_order_no",),
        "po_no": ("purchase_order_no",),
        "po_value": ("po_value",),
        "currency": ("currency",),
        "supplier_name": ("supplier_name",),
        "agency_name": ("supplier_name",),
        "terms_of_payment": ("terms_of_payment",),
        "service_start_date": ("service_start_date",),
        "ot_period_from": ("service_start_date",),
        "service_end_date": ("service_end_date",),
        "ot_period_to": ("service_end_date",),
        "delivery_address": ("delivery_address",),
    }

    for candidate in aliases.get(key, ()):
        if candidate in pairs and pairs[candidate].strip():
            return pairs[candidate].strip()

    # Fuzzy match on label/hint text for customized columns.
    for pair_key, value in pairs.items():
        human = pair_key.replace("_", " ")
        if pair_key == key or human == label:
            return value
        if pair_key == "reference_no" and "reference" in blob:
            return value
        if pair_key == "purchase_order_no" and ("purchase order" in blob or re.search(r"\bpo\b", blob)):
            return value
        if pair_key == "supplier_name" and ("supplier" in blob or "agency" in blob):
            return value
        if pair_key == "po_value" and "po value" in blob:
            return value
        if pair_key == "delivery_address" and "delivery" in blob:
            return value
        if pair_key == "work_order_id" and "work order" in blob:
            return value
        if pair_key == "name" and re.search(r"(^|\s)name(\s|$)", label) and "supplier" not in blob:
            return value
        if pair_key == "document_date" and re.search(r"(^|\s)date(\s|$)", label) and "start" not in blob and "end" not in blob:
            return value
    return None


def _looks_like_label_fragment(value: str, needle: str = "") -> bool:
    cleaned = value.strip().strip(".")
    if not cleaned:
        return True
    lower = cleaned.lower().rstrip(":").strip()
    raw_lower = cleaned.lower().strip()
    if cleaned.startswith("/") or lower.startswith("account currency") or lower.startswith("account name"):
        return True
    # Trailing colon almost always means we captured the next form label.
    if raw_lower.endswith(":"):
        return True
    if lower in {
        "name",
        "address",
        "account",
        "currency",
        "payment amount",
        "account currency / account",
        "/ account currency / account",
        "previous",
        "current",
        "reading",
        "present",
        "bill month",
        "consumer type",
        "meter reading date",
        "no",
        "date",
        "reference no",
        "reference no.",
        "work order id",
        "purchase order no",
        "po value",
        "supplier name",
        "terms of payment",
        "service start date",
        "service end date",
        "delivery address",
        "purchase order information",
        "work completion note",
        "purchase",
        "purchase order",
        "hourly rate",
        "daily rate",
        "basic salary",
        "period covered",
        "ot premium rates",
        "management fee",
        "subtotal amount",
        "night differential",
        "regular ot",
        "rest day ot",
    }:
        return True
    if re.fullmatch(r"(previous|current|present)\s+reading", lower):
        return True
    # Section headers / title-case label rows without a real value payload.
    if re.fullmatch(r"[A-Za-z][A-Za-z0-9 /&().\-]{2,60}", cleaned) and not re.search(r"\d", cleaned):
        if any(
            token in lower
            for token in (
                "information",
                "history",
                "details",
                "address",
                "payment",
                "completion",
                "order",
            )
        ) and len(cleaned.split()) <= 6:
            return True
    # Captured the remainder of a compound CitiDirect label.
    if "account currency" in lower and not re.search(r"\d{6,}", cleaned):
        return True
    if needle and needle.lower() in lower and not re.search(r"\d", cleaned):
        return True
    return False


def _layout_lookup(pairs: dict[str, str], field: dict[str, Any]) -> str | None:
    key = (field.get("key") or "").strip().lower()
    if key in pairs and pairs[key].strip():
        return pairs[key].strip()
    return None


def _looks_like_meralco(text: str) -> bool:
    blob = text.lower()
    return "manila electric" in blob or (
        "customer account number (can)" in blob and "please pay" in blob and "meralco" in blob
    )


def _meralco_pairs(text: str) -> dict[str, str]:
    if not _looks_like_meralco(text):
        return {}
    pairs: dict[str, str] = {}

    def grab(key: str, *patterns: str) -> None:
        for pattern in patterns:
            match = re.search(pattern, text, flags=re.IGNORECASE | re.MULTILINE)
            if match:
                value = _clean_value(match.group(1))
                if value and not _looks_like_label_fragment(value):
                    pairs[key] = value
                    return

    grab("customer_account_number", r"(?m)^Customer Account Number \(CAN\)\s*\n\s*([0-9]{8,})")
    grab("due_date", r"(?m)^Due Date\s*\n\s*([^\n]+)")
    grab("please_pay", r"(?m)^Please Pay\s*\n\s*[^\d\n]*([\d,]+\.\d{2})")
    grab("total_amount_due", r"(?m)^Total Amount Due\s*\n?\s*[^\d\n]*([\d,]+\.\d{2})")
    grab("meter_no", r"Meter No\.?\s*:\s*([A-Za-z0-9\-]+)")
    grab("electric_meter_number", r"Electric Meter Number\s*\n\s*([A-Za-z0-9\-]+)")
    grab("route_seq", r"Route Seq\.?\s*:\s*([^\n]+)")
    grab("print_seq", r"Print Seq\.?\s*:?\s*([0-9]+)")
    grab("billing_invoice_no", r"Billing Invoice\s*\n\s*No\.\s*([0-9]+)", r"Billing Invoice\s*No\.?\s*([0-9]+)")
    grab("vat_reg_tin", r"VAT REG\.?\s*TIN\s*([0-9\-]+)")
    grab("billing_period", r"(?m)^Billing Period\s*\n\s*([0-9].+)")
    grab("bill_date", r"(?m)^Bill Date\s*\n\s*([^\n]+)")
    grab("date_of_meter_reading", r"(?m)^Date of Meter Reading\s*\n\s*([^\n]+)")
    grab("date_of_next_meter_reading", r"(?m)^Date of Next Meter Reading\s*\n\s*([^\n]+)")
    grab("customer_type", r"(?m)^Customer Type\s*\n\s*([^\n]+)")
    grab("rate_this_month", r"Your rate this month\s*\n?\s*[^\d\n]*([\d.]+)\s*per\s*kWh")
    grab("current_reading", r"(?m)^Current Reading\s*\n\s*([0-9]+)")
    grab("previous_reading", r"(?m)^Previous Reading\s*\n\s*([0-9]+)")
    grab("actual_consumption", r"(?m)^Actual Consumption\s*\n\s*([0-9]+)\s*kWh")
    grab("remaining_balance", r"Remaining Balance from previous bill\s*\n\s*([\d,]+\.\d{2}|0\.00)")
    grab("charges_for_billing_period", r"Charges for this billing period\s*\n\s*([\d,]+\.\d{2})")
    grab("generation", r"(?m)^Generation\s*\n\s*([\d,]+\.\d{2})")
    grab("transmission", r"(?m)^Transmission\s*\n\s*([\d,]+\.\d{2})")
    grab("system_loss", r"(?m)^System Loss\s*\n\s*([\d,]+\.\d{2})")
    grab("distribution_meralco", r"Distribution \(Meralco\)\s*\n\s*([\d,]+\.\d{2})")
    grab("government_taxes", r"Government Taxes\s*\n\s*([\d,]+\.\d{2})")
    grab("bill_reference_no", r"Bill Reference No\.?\s*:\s*([0-9]+)")
    grab("local_application_no", r"Local Application No\.?\s*:\s*([0-9]+)")
    grab("service_id_number", r"Service ID Number\s*:\s*([0-9]+)")
    grab("contract_holder", r"Contract Holder\s*:\s*\n?\s*([^\n]+)")
    grab("service_address", r"Service Address\s*:\s*\n?\s*([^\n]+)")
    grab("voltage_level_class", r"Voltage Level Class\s*:\s*([^\n]+)")

    # Customer name: first all-caps name block after Please Pay amount.
    name_match = re.search(
        r"(?m)^Please Pay\s*\n\s*[^\n]*\n([A-Z][A-Z ,.'-]{5,})",
        text,
    )
    if name_match:
        pairs.setdefault("customer_name", name_match.group(1).strip())
    if "contract_holder" in pairs:
        pairs.setdefault("customer_name", pairs["contract_holder"])

    # Payment history table
    lines = [line.strip() for line in text.split("\n") if line.strip()]
    payment_rows = _collect_payment_rows(lines)
    if len(payment_rows) >= 2:
        columns = list(_DEFAULT_PAYMENT_COLUMNS)
        payload = {
            "columns": columns,
            "rows": [
                {
                    "billing_period": row["billing_period"],
                    "posting_date": row["posting_date"],
                    "payment_channels": row["payment_channels"],
                    "amount_paid": row["amount_paid"],
                }
                for row in payment_rows
            ],
        }
        pairs["payment_history"] = json.dumps(payload, ensure_ascii=False)

    return pairs


def _meralco_recommended(text: str) -> list[dict[str, Any]]:
    pairs = _meralco_pairs(text)
    specs = [
        ("customer_account_number", "Customer Account Number (CAN)", "text", "Customer Account Number (CAN)"),
        ("customer_name", "Customer Name", "text", "Contract Holder"),
        ("service_address", "Service Address", "multiline", "Service Address"),
        ("due_date", "Due Date", "date", "Due Date"),
        ("please_pay", "Please Pay", "currency", "Please Pay"),
        ("total_amount_due", "Total Amount Due", "currency", "Total Amount Due"),
        ("meter_no", "Meter No", "text", "Meter No"),
        ("electric_meter_number", "Electric Meter Number", "text", "Electric Meter Number"),
        ("route_seq", "Route Seq", "text", "Route Seq"),
        ("print_seq", "Print Seq", "text", "Print Seq"),
        ("billing_invoice_no", "Billing Invoice No", "text", "Billing Invoice"),
        ("vat_reg_tin", "VAT REG. TIN", "text", "VAT REG. TIN"),
        ("billing_period", "Billing Period", "text", "Billing Period"),
        ("bill_date", "Bill Date", "date", "Bill Date"),
        ("date_of_meter_reading", "Date of Meter Reading", "date", "Date of Meter Reading"),
        ("date_of_next_meter_reading", "Date of Next Meter Reading", "date", "Date of Next Meter Reading"),
        ("customer_type", "Customer Type", "text", "Customer Type"),
        ("rate_this_month", "Rate This Month", "currency", "Your rate this month"),
        ("current_reading", "Current Reading", "number", "Current Reading"),
        ("previous_reading", "Previous Reading", "number", "Previous Reading"),
        ("actual_consumption", "Actual Consumption", "number", "Actual Consumption"),
        ("remaining_balance", "Remaining Balance", "currency", "Remaining Balance from previous bill"),
        ("charges_for_billing_period", "Charges for this Billing Period", "currency", "Charges for this billing period"),
        ("generation", "Generation", "currency", "Generation"),
        ("transmission", "Transmission", "currency", "Transmission"),
        ("system_loss", "System Loss", "currency", "System Loss"),
        ("distribution_meralco", "Distribution (Meralco)", "currency", "Distribution (Meralco)"),
        ("government_taxes", "Government Taxes", "currency", "Government Taxes"),
        ("bill_reference_no", "Bill Reference No", "text", "Bill Reference No"),
        ("local_application_no", "Local Application No", "text", "Local Application No"),
        ("service_id_number", "Service ID Number", "text", "Service ID Number"),
        ("contract_holder", "Contract Holder", "text", "Contract Holder"),
        ("voltage_level_class", "Voltage Level Class", "text", "Voltage Level Class"),
    ]
    out: list[dict[str, Any]] = []
    for key, label, field_type, hint in specs:
        if key not in pairs:
            continue
        out.append({"key": key, "label": label, "type": field_type, "hint": hint, "value": pairs[key]})
    if "payment_history" in pairs:
        out.append(
            {
                "key": "payment_history",
                "label": "Payment History",
                "type": "table",
                "hint": "What you've paid",
                "columns": list(_DEFAULT_PAYMENT_COLUMNS),
                "value": pairs["payment_history"],
            }
        )
    return out


def _meralco_lookup(text: str, field: dict[str, Any]) -> str | None:
    return _layout_lookup(_meralco_pairs(text), field)


def _looks_like_neeco(text: str) -> bool:
    blob = text.lower()
    return "nueva ecija" in blob and ("electric cooperative" in blob or "neeco" in blob)


def _neeco_pairs(text: str) -> dict[str, str]:
    if not _looks_like_neeco(text):
        return {}
    pairs: dict[str, str] = {}

    def grab(key: str, *patterns: str) -> None:
        for pattern in patterns:
            match = re.search(pattern, text, flags=re.IGNORECASE | re.MULTILINE)
            if match:
                value = _clean_value(match.group(1))
                if value and not _looks_like_label_fragment(value):
                    pairs[key] = value
                    return

    grab("customer_name", r"(?m)^NAME\s+(.+)$")
    grab("address", r"(?m)^ADDRESS\s+(.+)$")
    grab("tin", r"VAT REG\.?\s*TIN\s*:?\s*([0-9\-]+)")
    grab("contacts", r"Contacts\s*:?\s*(\(?\d{3}\)?[\s\-]*\d{3}[\s\-]*\d{4})")
    grab(
        "meter_no",
        r"METER NO\.?\s*([0-9][0-9 ]{5,})",
        r"METER NO\.?\s*([A-Za-z0-9\-]+)",
    )
    if "meter_no" in pairs:
        pairs["meter_no"] = re.sub(r"\s+", "", pairs["meter_no"])
    grab("statement_of_account_no", r"Statement of Account No\.?\s*:?\s*([0-9]+)")
    grab("email", r"([A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,})")
    grab(
        "billing_period",
        r"(?m)^Billing Period Bill Month\s*\n\s*([0-9].+?)\s+([A-Z]+ \d{4})",
    )
    # billing period line: "Jan 25, 2026 - Feb 25, 2026 FEBRUARY 2026"
    period_line = re.search(
        r"(?m)^([A-Za-z]{3,9}\s+\d{1,2},\s+\d{4}\s*[-–]\s*[A-Za-z]{3,9}\s+\d{1,2},\s+\d{4})\s+([A-Z]+ \d{4})\s*$",
        text,
    )
    if period_line:
        pairs["billing_period"] = period_line.group(1).strip()
        pairs["bill_month"] = period_line.group(2).strip()
    grab("consumer_type", r"(?m)^(?:Consumer Type Meter Reading Dat[e]?)\s*\n\s*([A-Z ]+?)\s+([A-Za-z]{3}\s+\d{1,2},\s+\d{4})")
    type_line = re.search(
        r"(?m)^(LARGE COMMERCIAL|RESIDENTIAL|COMMERCIAL)[^\n]*?([A-Za-z]{3}\s+\d{1,2},\s+\d{4})",
        text,
    )
    if type_line:
        pairs["consumer_type"] = type_line.group(1).strip()
        pairs["meter_reading_date"] = type_line.group(2).strip()
    grab("present_reading", r"Present Reading[^\n]*\n\s*[-–]?\s*([\d,]+\.?\d*)")
    # Demand Used / kWh Used values often on next line: "9.120 1,807.0"
    usage = re.search(
        r"(?i)Demand Used\s+kWh Used[^\n]*\n\s*([\d,]+\.?\d*)\s+([\d,]+\.?\d*)",
        text,
    )
    if usage:
        pairs["demand_used"] = usage.group(1).replace(",", "")
        pairs["kwh_used"] = usage.group(2).replace(",", "")
    grab("total_bill_amount", r"Total Bill Amount\s*[\[\]]?\s*([\d,]+\.\d{2})")
    grab("total_amount_due", r"Total Amount Due\s*([\d,]+\.\d{2})")
    grab("total_amount_after_due", r"Total Amount After Due\s*([\d,]+\.\d{2})")

    lines = [line.strip() for line in text.split("\n") if line.strip()]
    month_rows = _collect_month_numeric_rows(lines)
    if len(month_rows) >= 3:
        columns = list(_DEFAULT_CONSUMPTION_COLUMNS)
        payload = {
            "columns": columns,
            "rows": [_consumption_cells_from_entry(entry) for entry in month_rows],
        }
        pairs["monthly_consumption_history"] = json.dumps(payload, ensure_ascii=False)

    return pairs


def _neeco_recommended(text: str) -> list[dict[str, Any]]:
    pairs = _neeco_pairs(text)
    specs = [
        ("customer_name", "Customer Name", "text", "NAME"),
        ("address", "Address", "text", "ADDRESS"),
        ("tin", "TIN", "text", "VAT REG. TIN"),
        ("contacts", "Contacts", "phone", "TIN Contacts"),
        ("meter_no", "Meter No", "text", "METER NO"),
        ("statement_of_account_no", "Statement of Account No", "text", "Statement of Account No"),
        ("billing_period", "Billing Period", "text", "Billing Period"),
        ("bill_month", "Bill Month", "text", "Bill Month"),
        ("consumer_type", "Consumer Type", "text", "Consumer Type"),
        ("meter_reading_date", "Meter Reading Date", "date", "Meter Reading Date"),
        ("present_reading", "Present Reading", "number", "Present Reading"),
        ("kwh_used", "kWh Used", "number", "kWh Used"),
        ("demand_used", "Demand Used", "number", "Demand Used"),
        ("total_bill_amount", "Total Bill Amount", "currency", "Total Bill Amount"),
        ("total_amount_due", "Total Amount Due", "currency", "Total Amount Due"),
        ("total_amount_after_due", "Total Amount After Due", "currency", "Total Amount After Due"),
        ("email", "Email", "email", "Online"),
    ]
    out: list[dict[str, Any]] = []
    for key, label, field_type, hint in specs:
        if key not in pairs:
            continue
        out.append({"key": key, "label": label, "type": field_type, "hint": hint, "value": pairs[key]})
    if "monthly_consumption_history" in pairs:
        out.append(
            {
                "key": "monthly_consumption_history",
                "label": "Monthly Consumption History",
                "type": "table",
                "hint": "Consumption History",
                "columns": list(_DEFAULT_CONSUMPTION_COLUMNS),
                "value": pairs["monthly_consumption_history"],
            }
        )
    return out


def _neeco_lookup(text: str, field: dict[str, Any]) -> str | None:
    return _layout_lookup(_neeco_pairs(text), field)


def _looks_like_iseco_invoice(text: str) -> bool:
    blob = text.lower()
    return "ilocos sur electric" in blob or (
        "electric billing invoice" in blob and "billno" in blob.replace(" ", "")
    )


def _iseco_pairs(text: str) -> dict[str, str]:
    if not _looks_like_iseco_invoice(text):
        return {}
    pairs: dict[str, str] = {}

    def grab(key: str, *patterns: str) -> None:
        for pattern in patterns:
            match = re.search(pattern, text, flags=re.IGNORECASE | re.MULTILINE)
            if match:
                value = _clean_value(match.group(1))
                if value and not _looks_like_label_fragment(value):
                    pairs[key] = value
                    return

    tin_site = re.search(
        r"VAT REG\.?\s*TIN\s*([0-9\-]+)\s+(NS-[A-Z0-9\-]+)",
        text,
        flags=re.IGNORECASE,
    )
    if tin_site:
        pairs["vat_reg_tin"] = tin_site.group(1)
        pairs["site_id"] = tin_site.group(2).upper()
    else:
        grab("vat_reg_tin", r"VAT REG\.?\s*TIN\s*([0-9\-]+)")
        grab("site_id", r"\b(NS-[A-Z0-9\-]+)\b")

    grab("invoice_date", r"Invoice Date\s*:\s*([0-9/\-]+)")
    grab(
        "account_number",
        r"(?m)^ACCOUNT NUMBER\s*([0-9]{2}-[0-9]{4}-[0-9]{4})",
        r"(?m)^ACCOUNT NUMBER\s*([0-9]{2,4}-[0-9]{3,4}-[0-9]{3,4})",
    )
    grab("bill_no", r"BILLNO\.?\s*:?\s*([0-9]+)")
    grab("invoice_no", r"(?:Electric Billing\s+)?INVOICE No\.?\s*:?\s*([0-9]+)")
    grab("billing_name", r"BILLING NAME\s+(.+)")
    grab("billing_address", r"BILLING ADDRESS\s+(.+)")
    grab("bir_reg_name", r"BIR REG\.?\s*NAME\s+(.+)")
    grab("bir_reg_address", r"BIR REG\.?\s*ADDRESS\s+(.+)")
    # Period / readings line cluster
    period = re.search(
        r"(?i)FROM\s+To[^\n]*\n\s*([0-9/\-]+)\s+([0-9/\-]+)",
        text,
    )
    if period:
        pairs["period_from"] = period.group(1)
        pairs["period_to"] = period.group(2)
    due_block = re.search(
        r"(?i)DUE DATE\s*([0-9/\-]+)\s+([\d,]+)\s+([\d,]+)\s+([\d,]+)\s+([\d,]+)",
        text,
    )
    if due_block:
        pairs["due_date"] = due_block.group(1)
        pairs["present_reading"] = due_block.group(2).replace(",", "")
        pairs["previous_reading"] = due_block.group(3).replace(",", "")
        pairs["multiplier"] = due_block.group(4).replace(",", "")
        pairs["kwh_used"] = due_block.group(5).replace(",", "")
    # Customer TIN / meter often appear on: "FROM To E 800019400 502-362-797-00000"
    meter_tin = re.search(
        r"(?i)FROM\s+To\s+[A-Z]?\s*([0-9]{6,})\s+(\d{3}-\d{3}-\d{3}-\d{5})",
        text,
    )
    if meter_tin:
        pairs["meter_number"] = meter_tin.group(1)
        pairs["tax_identification_number"] = meter_tin.group(2)
    grab("current_month_bill", r"CURRENT MONTH BILL\s*([\d,]+\.\d{2})")
    grab("total_amount_due", r"Total amount Due\s*([\d,]+\.\d{2})", r"TOTAL CURRENT BILL[^\n]*\s*([\d,]+\.\d{2})")

    return pairs


def _iseco_recommended(text: str) -> list[dict[str, Any]]:
    pairs = _iseco_pairs(text)
    specs = [
        ("site_id", "Site ID", "text", "NS-BIZ"),
        ("vat_reg_tin", "VAT REG. TIN", "text", "VAT REG. TIN"),
        ("invoice_date", "Invoice Date", "date", "Invoice Date"),
        ("account_number", "Account Number", "text", "ACCOUNT NUMBER"),
        ("bill_no", "Bill No", "text", "BILLNO"),
        ("invoice_no", "Invoice No", "text", "Electric Billing INVOICE No"),
        ("billing_name", "Billing Name", "text", "BILLING NAME"),
        ("billing_address", "Billing Address", "text", "BILLING ADDRESS"),
        ("bir_reg_name", "BIR Reg. Name", "text", "BIR REG. NAME"),
        ("bir_reg_address", "BIR Reg. Address", "text", "BIR REG. ADDRESS"),
        ("period_from", "Period From", "date", "FROM"),
        ("period_to", "Period To", "date", "To"),
        ("consumer_type", "Consumer Type", "text", "CONSUMER TYPE"),
        ("meter_number", "Meter Number", "text", "METER NUMBER"),
        ("tax_identification_number", "Tax Identification Number", "text", "TAX IDENTIFICATION NUMBER"),
        ("due_date", "Due Date", "date", "DUE DATE"),
        ("present_reading", "Present Reading", "number", "PRESENT"),
        ("previous_reading", "Previous Reading", "number", "PREVIOUS"),
        ("multiplier", "Multiplier", "number", "MULTIPLIER"),
        ("kwh_used", "kWh Used", "number", "kWh USED"),
        ("current_month_bill", "Current Month Bill", "currency", "CURRENT MONTH BILL"),
        ("total_amount_due", "Total Amount Due", "currency", "Total amount Due"),
    ]
    out: list[dict[str, Any]] = []
    for key, label, field_type, hint in specs:
        if key not in pairs:
            continue
        out.append({"key": key, "label": label, "type": field_type, "hint": hint, "value": pairs[key]})
    return out


def _iseco_lookup(text: str, field: dict[str, Any]) -> str | None:
    key = (field.get("key") or "").strip().lower()
    # Prefer invoice account number over CitiDirect debit account for ISECO fields.
    pairs = _iseco_pairs(text)
    if key in pairs:
        return pairs[key]
    return None


def _needles_for(field: dict[str, Any]) -> list[str]:
    label = field.get("label") or ""
    hint = field.get("hint") or ""
    key = field.get("key") or ""
    human = key.replace("_", " ").replace("-", " ")
    needles = [hint, label, human]

    blob = f"{label} {hint} {key}".lower()
    if "can" in blob or ("customer account" in blob):
        needles.extend(
            [
                "Customer Account Number (CAN)",
                "Customer Account No. (CAN)",
                "Customer Account Number",
                "Customer Account No",
                "Account Number",
                "Account No",
                "CAN",
            ]
        )
    if "please pay" in blob:
        needles.extend(["Please Pay"])
    if "actual consumption" in blob:
        needles.extend(["Actual Consumption"])
    if "current reading" in blob:
        needles.extend(["Current Reading"])
    if "remaining balance" in blob:
        needles.extend(["Remaining Balance from previous bill", "Remaining Balance"])
    if "charges for this billing" in blob:
        needles.extend(["Charges for this billing period"])
    if "bill reference" in blob:
        needles.extend(["Bill Reference No", "Bill Reference No."])
    if "local application" in blob:
        needles.extend(["Local Application No", "Local Application No."])
    if "service id" in blob or "sin" in blob:
        needles.extend(["Service ID Number", "Previous Service ID Number (SIN)"])
    if "contract holder" in blob:
        needles.extend(["Contract Holder"])
    if "voltage" in blob:
        needles.extend(["Voltage Level Class"])
    if "rate this month" in blob:
        needles.extend(["Your rate this month"])
    if "bill date" in blob:
        needles.extend(["Bill Date"])
    if "next meter reading" in blob:
        needles.extend(["Date of Next Meter Reading"])
    if "date of meter reading" in blob:
        needles.extend(["Date of Meter Reading"])
    if "customer type" in blob:
        needles.extend(["Customer Type"])
    if "billing invoice" in blob:
        needles.extend(["Billing Invoice", "No."])
    if "can" in blob or "account" in blob:
        needles.extend(
            [
                "Customer Account Number",
                "Customer Account No",
                "Account Number",
                "Account No",
                "CAN",
            ]
        )
    if "invoice" in blob:
        needles.extend(
            [
                "Billing Invoice",
                "Electric Billing INVOICE No",
                "INVOICE No",
                "Invoice Number",
                "Invoice No",
                "Invoice #",
                "SOA No",
            ]
        )
    if "bill date" in blob or "billing date" in blob or "invoice date" in blob or (
        field.get("type") == "date" and "date" in blob
    ):
        needles.extend(["Bill Date", "Billing Date", "Statement Date", "Invoice Date", "Due Date"])
    if "ordering party" in blob:
        needles.extend(["Ordering Party"])
    if "creation method" in blob:
        needles.extend(["Creation Method"])
    if "payment type" in blob:
        needles.extend(["Payment Type"])
    if "charges indicator" in blob:
        needles.extend(["Charges Indicator"])
    if "charges account" in blob:
        needles.extend(["Charges Account"])
    if "routing method" in blob:
        needles.extend(["Beneficiary Bank Routing Method"])
    if "routing code" in blob:
        needles.extend(["Beneficiary Bank Routing Code"])
    if "bank name" in blob:
        needles.extend(["Beneficiary Bank Name / Address", "Beneficiary Bank Name"])
    if "submission" in blob:
        needles.extend(["Submission Date/Time", "Submitted By"])
    if blob.strip() in {"status", "sub status", "sub-status"} or "sub-status" in blob or "sub status" in blob:
        needles.extend(["Sub-Status", "Status"])
    if "printed on" in blob:
        needles.extend(["Printed on"])
    if "debit account" in blob or "account currency" in blob or key == "debit_account":
        needles.extend(
            [
                "Account Number / Account Currency / Account Name",
                "Account Number / Account Currency / Account",
            ]
        )
    if "payment details" in blob and "transaction" not in blob and key != "transaction_reference_number":
        needles.extend(["Payment Details"])
    if "site" in blob and ("id" in blob or "no" in blob):
        needles.extend(["SITE ID NO", "Site ID", "Site Id No"])
    if "bill no" in blob or "billno" in blob:
        needles.extend(["BILLNO", "Bill No", "Bill Number"])
    if "billing name" in blob:
        needles.extend(["BILLING NAME", "Billing Name"])
    if "billing address" in blob:
        needles.extend(["BILLING ADDRESS", "Billing Address"])
    if "bir reg" in blob:
        needles.extend(["BIR REG. NAME", "BIR REG. ADDRESS", "BIR Reg. Name"])
    if "period covered" in blob or "period from" in blob or "period to" in blob:
        needles.extend(["PERIOD COVERED", "FROM", "Period From", "Period To"])
    if "due date" in blob:
        needles.extend(["DUE DATE", "Due Date"])
    if "multiplier" in blob:
        needles.extend(["MULTIPLIER", "Multiplier"])
    if "current month bill" in blob or "total current bill" in blob:
        needles.extend(["CURRENT MONTH BILL", "TOTAL CURRENT BILL", "TOTAL CURRENT BILL MONTH"])
    if "transaction reference" in blob or "transaction ref" in blob:
        needles.extend(
            [
                "Transaction Reference Number",
                "Transaction Ref No",
                "Payment Details: Transaction Ref No",
            ]
        )
    if "payment amount" in blob or "payment currency" in blob:
        needles.extend(["Payment Currency / Payment Amount", "Payment Amount"])
    if "beneficiary name" in blob:
        needles.extend(["Beneficiary Name"])
    if "beneficiary account" in blob:
        needles.extend(["Beneficiary Account Number"])
    if "value date" in blob:
        needles.extend(["Value Date"])
    if "payment method" in blob:
        needles.extend(["Payment Method"])
    if "submitted by" in blob:
        needles.extend(["Submitted By"])
    if "meter" in blob:
        needles.extend(["METER NO", "Meter No", "Meter Number", "Meter #", "METER NUMBER"])
    # Avoid short "Account Number" needles for CitiDirect compound debit labels —
    # they match mid-label and capture "/ Account Currency / Account".
    if (
        ("statement" in blob or "soa" in blob or "account no" in blob or "account number" in blob)
        and "currency" not in blob
        and "debit" not in blob
        and key not in {"debit_account", "account_number", "account_name", "account_currency"}
    ):
        needles.extend(
            [
                "Statement of Account No",
                "Statement of Account Number",
                "ACCOUNT NUMBER",
                "Account No.",
                "Account Number",
                "SOA No",
                "Account No",
            ]
        )
    if "billing period" in blob:
        needles.extend(["Billing Period"])
    if "bill month" in blob:
        needles.extend(["Bill Month"])
    if "consumer type" in blob:
        needles.extend(["Consumer Type", "CONSUMER TYPE", "Cons. Type"])
    if "meter reading date" in blob:
        needles.extend(["Meter Reading Date", "Reading Date"])
    if "present reading" in blob or "present rdg" in blob:
        needles.extend(["Present Reading", "Present Rdg", "PRESENT"])
    if "previous reading" in blob or "previous rdg" in blob or "prev reading" in blob:
        needles.extend(["Previous Reading", "Previous Rdg", "PREVIOUS", "Prev Reading"])
    if "demand used" in blob or "peak" in blob:
        needles.extend(["Demand Used", "DEMAND PEAK", "Pres Demand"])
    if "kwh used" in blob or "kwh" in blob:
        needles.extend(["kWh Used", "kWh", "kWh USED"])
    if "customer name" in blob or blob.strip() in {"name", "customer"}:
        needles.extend(["NAME", "Customer Name", "Account Name", "BILLING NAME"])
    if "address" in blob:
        needles.extend(["ADDRESS", "Service Address", "BILLING ADDRESS"])
    if "tin" in blob:
        needles.extend(
            [
                "VAT REG. TIN",
                "VAT Reg. TIN",
                "TIN",
                "TAX IDENTIFICATION NUMBER",
                "Tax Identification Number",
            ]
        )
    if "contact" in blob or "phone" in blob:
        needles.extend(["TIN Contacts", "Contacts", "Contact No", "Tel"])
    if "email" in blob:
        needles.extend(["Online", "Email", "E-mail", "Email Address"])
    if "after due" in blob:
        needles.extend(["Total Amount After Due", "Amount After Due"])
    if "total bill" in blob:
        needles.extend(["Total Bill Amount", "Total Bill", "TOTAL CURRENT BILL", "CURRENT MONTH BILL"])
    if "amount" in blob or "total" in blob or field.get("type") == "currency":
        needles.extend(
            [
                "Total Amount Due",
                "Total amount Due",
                "Amount Due",
                "TOTAL AMOUNT DUE",
                "Total Amount",
                "Total Due",
                "Amount Payable",
                "Payment Currency / Payment Amount",
                "Payment Amount",
            ]
        )
    seen: set[str] = set()
    ordered: list[str] = []
    for needle in needles:
        cleaned = needle.strip()
        if not cleaned:
            continue
        marker = cleaned.lower()
        if marker in seen:
            continue
        seen.add(marker)
        ordered.append(cleaned)
    return ordered


def _match_label_value(text: str, needle: str) -> str | None:
    escaped = re.escape(needle)
    # Prefer start-of-line matches so "Account Number" does not hit
    # "Beneficiary Account Number".
    patterns = [
        rf"(?m)^{escaped}\.?\s*[:\-#]\s*([^\n]+)",
        rf"(?m)^{escaped}\.?\s*\n\s*([^\n]+)",
    ]
    if " / " not in needle and len(needle.split()) <= 4:
        patterns.append(rf"(?m)^{escaped}\.?\s+([^\n]+)")

    for pattern in patterns:
        for match in re.finditer(pattern, text, flags=re.IGNORECASE):
            value = _clean_value(match.group(1))
            # Same-line capture often grabs the neighboring grid label ("Name :").
            if value and _looks_like_label_fragment(value, needle):
                # Prefer the next non-label line under this match.
                tail = text[match.end() :]
                next_line_match = re.match(r"\s*\n\s*([^\n]+)", tail)
                if next_line_match:
                    value = _clean_value(next_line_match.group(1))
                else:
                    continue
            if not value or _looks_like_label_fragment(value, needle):
                continue
            # Dual-label grid: "Work Order ID : Name :" then "ERCSWO… DE GUZMAN…"
            # If the captured "value" still starts another label, reject.
            if re.match(r"^[A-Za-z][A-Za-z0-9 /#().&\-]{1,40}\s*:", value):
                continue
            if "meter" in needle.lower():
                meter = re.search(r"([A-Za-z0-9][A-Za-z0-9\-]{4,})", value)
                if not meter:
                    continue
                token = meter.group(1)
                if token.lower() in {"previous", "current", "present", "reading", "number"}:
                    continue
                return token
            # For ID-like needles, prefer the first token when the line holds two cell values.
            if _needle_is_id_like(needle):
                first = value.split()[0] if value.split() else value
                if first and not _looks_like_label_fragment(first, needle):
                    return first
            return value
    return None


def _needle_is_id_like(needle: str) -> bool:
    lower = needle.lower()
    return any(
        token in lower
        for token in (
            "reference",
            "work order",
            "wo id",
            "wo no",
            "purchase order",
            "po no",
            "po number",
            "invoice",
            "account",
            "signum",
            "docusign id",
        )
    )


def _clean_value(value: str) -> str:
    cleaned = value.strip().strip(" .;|")
    # Stop at common next-label separators on the same OCR line.
    cleaned = re.split(r"\s{2,}|\t|(?<=\d)\s+(?=[A-Z][a-z]+\s+[A-Z])", cleaned, maxsplit=1)[0]
    cleaned = cleaned.strip(" .;|")
    if len(cleaned) > 200:
        cleaned = cleaned[:200].rstrip()
    return cleaned


def _find_currency(text: str, prefer_total: bool = False) -> str | None:
    search_regions = [text]
    if prefer_total:
        for label in ("Total Amount Due", "Amount Due", "Total Amount", "Total Due"):
            match = re.search(rf"{re.escape(label)}\s*[:\-]?\s*([^\n]+)", text, flags=re.IGNORECASE)
            if match:
                search_regions.insert(0, match.group(0))

    for region in search_regions:
        match = _CURRENCY_RE.search(region)
        if match:
            amount = match.group(1) or match.group(2)
            if amount:
                return amount.replace(",", "")
    return None


def _find_date_near(text: str, needles: list[str]) -> str | None:
    for needle in needles:
        match = re.search(rf"{re.escape(needle)}\s*[:\-]?\s*([^\n]+)", text, flags=re.IGNORECASE)
        if match:
            date_match = _DATE_RE.search(match.group(1))
            if date_match:
                return date_match.group(1)
            cleaned = _clean_value(match.group(1))
            if cleaned and _looks_like_date_value(cleaned) and not _looks_like_identifier_not_date(cleaned):
                return cleaned
        # Label on its own line, value on the next.
        match = re.search(rf"(?m)^{re.escape(needle)}\.?\s*:?\s*\n\s*([^\n]+)", text, flags=re.IGNORECASE)
        if match:
            date_match = _DATE_RE.search(match.group(1))
            if date_match:
                return date_match.group(1)
            cleaned = _clean_value(match.group(1))
            if cleaned and _looks_like_date_value(cleaned) and not _looks_like_identifier_not_date(cleaned):
                return cleaned
    match = _DATE_RE.search(text)
    return match.group(1) if match else None


def _find_account_number(text: str) -> str | None:
    patterns = [
        r"(?:Customer Account Number|Customer Account No\.?|Account Number|Account No\.?|CAN)\s*[:\-]?\s*([0-9]{8,20})",
        r"\b([0-9]{10,15})\b",
    ]
    for pattern in patterns:
        match = re.search(pattern, text, flags=re.IGNORECASE)
        if match:
            return match.group(1)
    return None


def _find_invoice_number(text: str) -> str | None:
    patterns = [
        r"(?:Billing Invoice|Invoice Number|Invoice No\.?|Invoice #|SOA No\.?)\s*[:\-]?\s*([A-Z0-9][A-Z0-9\-]{4,24})",
        r"\b([0-9]{2}[A-Z0-9]{6,16})\b",
    ]
    for pattern in patterns:
        match = re.search(pattern, text, flags=re.IGNORECASE)
        if match:
            return match.group(1)
    return None


def _coerce(value: str | None, field_type: str) -> str | None:
    if value is None:
        return None
    cleaned = value.strip()
    if cleaned == "":
        return None

    if field_type == "currency":
        amount = re.sub(r"[^\d.]", "", cleaned.replace(",", ""))
        return amount or cleaned
    if field_type == "number" or field_type == "percentage":
        amount = re.sub(r"[^\d.\-]", "", cleaned.replace(",", ""))
        return amount or cleaned
    if field_type == "date":
        return _to_iso_date(cleaned) or cleaned
    if field_type == "boolean":
        lower = cleaned.lower()
        if lower in {"y", "yes", "true", "1", "x"}:
            return "Yes"
        if lower in {"n", "no", "false", "0"}:
            return "No"
        return cleaned
    if field_type == "email":
        match = re.search(r"[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}", cleaned, flags=re.IGNORECASE)
        return match.group(0) if match else cleaned
    if field_type == "phone":
        digits = re.sub(r"[^\d+]", "", cleaned)
        return digits or cleaned

    # Common OCR confusion: isolated letter O between digits (12O34 → 12034).
    # Do not rewrite intentional letter prefixes such as OT26-0163 / ERCSWO….
    if re.search(r"\d[Oo]\d|\d[Oo]$|^[Oo]\d", cleaned) and re.fullmatch(r"[A-Z0-9\-]+", cleaned, flags=re.I):
        fixed = re.sub(r"(?<=\d)[Oo](?=\d)", "0", cleaned)
        fixed = re.sub(r"(?<=\d)[Oo]$", "0", fixed)
        fixed = re.sub(r"^[Oo](?=\d)", "0", fixed)
        return fixed

    return cleaned


def _to_iso_date(value: str) -> str | None:
    candidates = [
        "%Y-%m-%d",
        "%Y/%m/%d",
        "%m/%d/%Y",
        "%m-%d-%Y",
        "%d/%m/%Y",
        "%d-%m-%Y",
        "%m/%d/%y",
        "%d/%m/%y",
        "%b %d %Y",
        "%B %d %Y",
        "%b %d, %Y",
        "%B %d, %Y",
    ]
    cleaned = value.strip()
    for fmt in candidates:
        try:
            return datetime.strptime(cleaned, fmt).date().isoformat()
        except ValueError:
            continue
    match = _DATE_RE.search(cleaned)
    if not match:
        return None
    fragment = match.group(1)
    for fmt in candidates:
        try:
            return datetime.strptime(fragment, fmt).date().isoformat()
        except ValueError:
            continue
    return None
