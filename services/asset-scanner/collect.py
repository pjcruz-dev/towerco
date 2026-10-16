"""Read this PC into the asset inventory payload.

Windows is queried through CIM. Other systems return the signed-in name and
hostname only, so the screen can still open during development.
"""

from __future__ import annotations

import json
import os
import platform
import subprocess
import uuid
from typing import Any

_PLACEHOLDER_SERIALS = {
    "",
    "NONE",
    "DEFAULT STRING",
    "TO BE FILLED BY O.E.M.",
    "SYSTEM SERIAL NUMBER",
    "NOT SPECIFIED",
    "UNKNOWN",
}

_POWERSHELL = r"""
$ErrorActionPreference = 'Stop'
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8
function SizeLabel([object]$bytes) {
  if ($null -eq $bytes) { return $null }
  $n = [double]$bytes
  if ($n -le 0) { return $null }
  $gb = [math]::Round($n / 1GB, 0)
  if ($gb -lt 1) { return ('{0:N1} GB' -f ($n / 1GB)) }
  return ('{0} GB' -f $gb)
}
$cs = Get-CimInstance Win32_ComputerSystem
$bios = Get-CimInstance Win32_BIOS
$prod = Get-CimInstance Win32_ComputerSystemProduct
$os = Get-CimInstance Win32_OperatingSystem
$cpu = @(Get-CimInstance Win32_Processor) | Select-Object -First 1
$cfgs = @{}
Get-CimInstance Win32_NetworkAdapterConfiguration | ForEach-Object { $cfgs[[int]$_.Index] = $_ }
$adapters = @(Get-CimInstance Win32_NetworkAdapter | Where-Object { $_.PhysicalAdapter -and $_.MACAddress })
$adapterRows = foreach ($a in $adapters) {
  $cfg = $cfgs[[int]$a.Index]
  $ip = $null
  if ($cfg -and $cfg.IPAddress) { $ip = [string]@($cfg.IPAddress)[0] }
  $name = [string]$a.Name
  $kind = 'Other'
  if ($name -match 'Wi-?Fi|Wireless|WLAN') { $kind = 'Wi-Fi' }
  elseif ($name -match 'Ethernet|LAN|Realtek|I219|I225') { $kind = 'Ethernet' }
  [pscustomobject]@{
    adapter_name = $name
    adapter_type = $kind
    mac_address = [string]$a.MACAddress
    ip_address = $ip
    is_active = [bool]$a.NetEnabled
  }
}
$disks = @(Get-CimInstance Win32_DiskDrive | ForEach-Object {
  [pscustomobject]@{
    model = $_.Model
    serial_number = ($(if ($_.SerialNumber) { ([string]$_.SerialNumber).Trim() } else { $null }))
    size_label = (SizeLabel $_.Size)
    interface = $_.InterfaceType
  }
})
$video = @(Get-CimInstance Win32_VideoController | ForEach-Object {
  [pscustomobject]@{
    name = $_.Name
    driver_version = $_.DriverVersion
  }
})
$roots = @(
  'HKLM:\SOFTWARE\Microsoft\Windows\CurrentVersion\Uninstall\*',
  'HKLM:\SOFTWARE\WOW6432Node\Microsoft\Windows\CurrentVersion\Uninstall\*'
)
if (Test-Path 'HKCU:\SOFTWARE\Microsoft\Windows\CurrentVersion\Uninstall') {
  $roots += 'HKCU:\SOFTWARE\Microsoft\Windows\CurrentVersion\Uninstall\*'
}
$seen = @{}
$apps = foreach ($item in (Get-ItemProperty $roots -ErrorAction SilentlyContinue)) {
  $label = [string]$item.DisplayName
  if (-not $label) { continue }
  if ($item.SystemComponent -eq 1) { continue }
  if ($item.ReleaseType -in @('Update', 'Security Update', 'Hotfix')) { continue }
  $key = $label.ToLower()
  if ($seen.ContainsKey($key)) { continue }
  $seen[$key] = $true
  $installed = $null
  $rawDate = [string]$item.InstallDate
  if ($rawDate -match '^\d{8}$') {
    $installed = '{0}-{1}-{2}' -f $rawDate.Substring(0,4), $rawDate.Substring(4,2), $rawDate.Substring(6,2)
  }
  [pscustomobject]@{
    application_name = $label
    version = $item.DisplayVersion
    publisher = $item.Publisher
    installed_date = $installed
  }
}
$ram = $null
if ($cs.TotalPhysicalMemory) { $ram = SizeLabel $cs.TotalPhysicalMemory }
[pscustomobject]@{
  pc_system_type = [int]$cs.PCSystemType
  serial_number = [string]$bios.SerialNumber
  product_serial = [string]$prod.IdentifyingNumber
  manufacturer = [string]$cs.Manufacturer
  product_name = [string]$cs.Model
  product_version = [string]$prod.Version
  hostname = [string]$cs.Name
  processor = $(if ($cpu) { [string]$cpu.Name } else { $null })
  installed_ram = $ram
  operating_system = [string]$os.Caption
  system_type = [string]$cs.SystemType
  bios_version = [string]$bios.SMBIOSBIOSVersion
  network_adapters = @($adapterRows)
  disks = @($disks)
  video_cards = @($video)
  applications = @($apps)
} | ConvertTo-Json -Depth 6 -Compress
"""


def windows_identity() -> str:
    user = os.environ.get("USERNAME", "").strip()
    domain = os.environ.get("USERDOMAIN", "").strip()
    if domain and user and domain.upper() != user.upper():
        return f"{domain}\\{user}"
    return user or os.environ.get("USER", "")


def login_name() -> str:
    identity = windows_identity()
    if "\\" in identity:
        return identity.split("\\", 1)[1]
    return identity


def collect() -> dict[str, Any]:
    if platform.system() != "Windows":
        return _empty(note="Hardware, MAC addresses, and installed applications are read on Windows.")
    completed = subprocess.run(
        ["powershell", "-NoProfile", "-NonInteractive", "-ExecutionPolicy", "Bypass", "-Command", _POWERSHELL],
        check=False,
        capture_output=True,
        text=True,
        encoding="utf-8",
        errors="replace",
        timeout=90,
    )
    if completed.returncode != 0:
        detail = (completed.stderr or completed.stdout or "PowerShell could not read this PC.").strip()
        payload = _empty(note=detail.splitlines()[-1][:240])
        payload["collect_error"] = detail[:1000]
        return payload
    try:
        raw = json.loads(completed.stdout)
    except json.JSONDecodeError as exc:
        payload = _empty(note="The hardware reading was not valid JSON.")
        payload["collect_error"] = str(exc)
        return payload
    if not isinstance(raw, dict):
        return _empty(note="The hardware reading was empty.")
    return normalize(raw)


def normalize(raw: dict[str, Any]) -> dict[str, Any]:
    serial = _serial(raw.get("serial_number")) or _serial(raw.get("product_serial"))
    adapters = [_adapter(row) for row in _rows(raw.get("network_adapters"))]
    disks = [_disk(row) for row in _rows(raw.get("disks"))]
    video = [_video(row) for row in _rows(raw.get("video_cards"))]
    apps = [_app(row) for row in _rows(raw.get("applications"))]
    return {
        "client_key": str(uuid.uuid4()),
        "serial_number": serial,
        "asset_type": _asset_type(raw),
        "manufacturer": _text(raw.get("manufacturer")),
        "product_name": _text(raw.get("product_name")),
        "product_version": _text(raw.get("product_version")),
        "hostname": _text(raw.get("hostname")) or platform.node(),
        "processor": _text(raw.get("processor")),
        "installed_ram": _text(raw.get("installed_ram")),
        "operating_system": _text(raw.get("operating_system")),
        "system_type": _text(raw.get("system_type")),
        "bios_version": _text(raw.get("bios_version")),
        "windows_identity": windows_identity(),
        "network_adapters": [row for row in adapters if row],
        "disks": [row for row in disks if row],
        "video_cards": [row for row in video if row],
        "applications": [row for row in apps if row],
        "collect_error": None,
    }


def _empty(*, note: str) -> dict[str, Any]:
    return {
        "client_key": str(uuid.uuid4()),
        "serial_number": "",
        "asset_type": "laptop",
        "manufacturer": platform.system(),
        "product_name": platform.machine(),
        "product_version": None,
        "hostname": platform.node(),
        "processor": None,
        "installed_ram": None,
        "operating_system": platform.platform(),
        "system_type": None,
        "bios_version": None,
        "windows_identity": windows_identity(),
        "network_adapters": [],
        "disks": [],
        "video_cards": [],
        "applications": [],
        "collect_error": note,
    }


def _rows(value: Any) -> list[Any]:
    if isinstance(value, list):
        return value
    if isinstance(value, dict):
        return [value]
    return []


def _text(value: Any) -> str | None:
    if value is None:
        return None
    text = str(value).strip()
    return text or None


def _asset_type(raw: dict[str, Any]) -> str:
    try:
        kind = int(raw.get("pc_system_type") or 0)
    except (TypeError, ValueError):
        kind = 0
    if kind in {2, 8, 9, 10, 11, 12, 30}:
        return "laptop"
    if kind in {1, 3, 4, 5, 6, 7}:
        return "desktop"
    return "laptop"


def _serial(value: Any) -> str:
    text = (_text(value) or "").upper()
    if text in _PLACEHOLDER_SERIALS:
        return ""
    return text


def _adapter(row: Any) -> dict[str, Any] | None:
    if not isinstance(row, dict):
        return None
    mac = _text(row.get("mac_address"))
    if not mac:
        return None
    return {
        "adapter_name": _text(row.get("adapter_name")),
        "adapter_type": _text(row.get("adapter_type")) or "Other",
        "mac_address": mac,
        "ip_address": _text(row.get("ip_address")),
        "is_active": bool(row.get("is_active", True)),
    }


def _disk(row: Any) -> dict[str, Any] | None:
    if not isinstance(row, dict):
        return None
    model = _text(row.get("model"))
    if not model and not _text(row.get("serial_number")):
        return None
    return {
        "model": model,
        "serial_number": _text(row.get("serial_number")),
        "size_label": _text(row.get("size_label")),
        "interface": _text(row.get("interface")),
    }


def _video(row: Any) -> dict[str, Any] | None:
    if not isinstance(row, dict):
        return None
    name = _text(row.get("name"))
    if not name:
        return None
    return {"name": name, "driver_version": _text(row.get("driver_version"))}


def _app(row: Any) -> dict[str, Any] | None:
    if not isinstance(row, dict):
        return None
    name = _text(row.get("application_name"))
    if not name:
        return None
    return {
        "application_name": name[:255],
        "version": _text(row.get("version")),
        "publisher": _text(row.get("publisher")),
        "installed_date": _text(row.get("installed_date")),
    }
