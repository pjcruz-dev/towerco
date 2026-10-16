"""TowerOS asset API used by the scanner. Sends nothing until the operator confirms."""

from __future__ import annotations

import json
import os
from typing import Any
from urllib import error, parse, request


class ApiError(Exception):
    def __init__(self, message: str, status: int = 0) -> None:
        super().__init__(message)
        self.status = status


class AssetApi:
    def __init__(self) -> None:
        self.base = os.environ.get("TOWEROS_API", "http://localhost:8000/api/v1").rstrip("/")
        self.token = os.environ.get("TOWEROS_TOKEN", "").strip()
        self.tenant = os.environ.get("TOWEROS_TENANT", "").strip()
        self.domain = os.environ.get("TOWEROS_TENANT_DOMAIN", "").strip()

    @property
    def configured(self) -> bool:
        return bool(self.token and (self.tenant or self.domain))

    def ping(self) -> None:
        self.get("/assets/directory", {"q": ""})

    def find_people(self, term: str) -> list[dict[str, Any]]:
        body = self.get("/assets/directory", {"q": term})
        rows = body.get("data")
        return rows if isinstance(rows, list) else []

    def find_asset_by_serial(self, serial: str) -> dict[str, Any] | None:
        body = self.get("/assets", {"q": serial, "per_page": 20})
        rows = body.get("data")
        if not isinstance(rows, list):
            return None
        wanted = serial.strip().upper()
        for row in rows:
            if isinstance(row, dict) and str(row.get("serial_number", "")).upper() == wanted:
                return row
        return None

    def verify_serial(self, asset_tag: str, detected_serial: str) -> dict[str, Any]:
        return self.post("/assets/inventory/verify", {
            "asset_tag": asset_tag,
            "detected_serial": detected_serial,
        })

    def check_hostname(self, employee_id: str, asset_id: str | None) -> dict[str, Any]:
        body: dict[str, Any] = {"employee_id": employee_id}
        if asset_id:
            body["asset_id"] = asset_id
        return self.post("/assets/hostnames/check", body)

    def submit(self, payload: dict[str, Any]) -> dict[str, Any]:
        return self.post("/assets/inventory", payload)

    def record_hostname(self, asset_id: str, hostname: str) -> dict[str, Any]:
        return self.post(f"/assets/{asset_id}/hostname", {
            "hostname": hostname,
            "reason": "Initial asset setup",
            "os_confirmed": True,
        })

    def get(self, path: str, params: dict[str, str] | None = None) -> dict[str, Any]:
        query = ""
        if params:
            parts = [f"{key}={parse.quote(value)}" for key, value in params.items() if value != ""]
            if parts:
                query = "?" + "&".join(parts)
        return self._send(path + query, method="GET")

    def post(self, path: str, body: dict[str, Any]) -> dict[str, Any]:
        return self._send(path, method="POST", body=body)

    def _send(self, path: str, *, method: str, body: dict[str, Any] | None = None) -> dict[str, Any]:
        if not self.configured:
            raise ApiError("Set TOWEROS_TOKEN and TOWEROS_TENANT before sending inventory.")
        data = None if body is None else json.dumps(body).encode()
        headers = {
            "Authorization": f"Bearer {self.token}",
            "Accept": "application/json",
            "Content-Type": "application/json",
        }
        if self.tenant:
            headers["X-Tenant-Id"] = self.tenant
        if self.domain:
            headers["X-Tenant-Domain"] = self.domain
        req = request.Request(self.base + path, data=data, headers=headers, method=method)
        try:
            with request.urlopen(req, timeout=30) as response:
                parsed = json.loads(response.read().decode())
        except error.HTTPError as exc:
            detail = exc.read().decode(errors="replace")
            raise ApiError(_message(detail), exc.code) from exc
        except error.URLError as exc:
            raise ApiError(f"Asset management is not reachable. {exc.reason}") from exc
        if not isinstance(parsed, dict):
            raise ApiError("The asset service returned an unexpected response.")
        data_node = parsed.get("data", parsed)
        return parsed if body is None or method == "GET" else (data_node if isinstance(data_node, dict) else parsed)


def _message(body: str) -> str:
    try:
        parsed = json.loads(body)
    except json.JSONDecodeError:
        return body.strip()[:500] or "The asset service rejected the request."
    if not isinstance(parsed, dict):
        return body.strip()[:500]
    errors = parsed.get("errors")
    lines: list[str] = []
    if isinstance(errors, dict):
        for messages in errors.values():
            if isinstance(messages, list):
                lines.extend(str(item) for item in messages)
    if lines:
        return " ".join(lines)
    message = parsed.get("message")
    return str(message) if message else "The asset service rejected the request."
