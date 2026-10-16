"""Alliance Towers IT asset scanner.

Collects this PC, shows a preview, and sends only after the operator confirms.
A typed asset tag is checked against the detected serial before anything is written.
Rename runs only from Inventory + PC Rename, and only after the API accepts the name.
"""

from __future__ import annotations

import platform
import subprocess
import uuid
from typing import Any

from textual.app import App, ComposeResult
from textual.containers import Horizontal, Vertical
from textual.content import Content
from textual.screen import Screen
from textual.widgets import Button, Footer, Header, Input, Label, ListItem, ListView, Static

from client import ApiError, AssetApi
from collect import collect, login_name


def _wifi_mac(payload: dict[str, Any]) -> str:
    adapters = payload.get("network_adapters") or []
    preferred = [row for row in adapters if str(row.get("adapter_type")) == "Wi-Fi" and row.get("mac_address")]
    chosen = preferred[0] if preferred else next((row for row in adapters if row.get("mac_address")), None)
    return str(chosen.get("mac_address")) if chosen else "—"


def _plain(text: str) -> Content:
    return Content.from_text(text)


def _line(label: str, value: Any) -> str:
    text = "—" if value is None or value == "" else str(value)
    return f"{label:<16}{text}"


class MenuScreen(Screen):
    def compose(self) -> ComposeResult:
        yield Header(show_clock=True)
        yield Static(id="menu-card")
        with Vertical(id="menu-actions"):
            yield Button("Inventory Scan Only", id="inventory")
            yield Button("Inventory + PC Rename", id="rename")
            yield Button("View Device Information", id="device")
            yield Button("Exit", id="exit")
        yield Footer()

    def on_mount(self) -> None:
        self._paint()

    def on_screen_resume(self) -> None:
        self._paint()

    def _paint(self) -> None:
        app = self.app
        assert isinstance(app, ScannerApp)
        payload = app.payload or {}
        device = " ".join(
            part for part in [str(payload.get("manufacturer") or ""), str(payload.get("product_name") or "")] if part
        ).strip() or "Reading this PC"
        link = "Connected" if app.connected else "Not connected"
        card = "\n".join([
            "ALLIANCE TOWERS IT ASSET SCANNER",
            _line("Device", device),
            _line("Serial", payload.get("serial_number") or "—"),
            _line("User", payload.get("windows_identity") or "—"),
            "",
            f"Asset management: {link}",
        ])
        if payload.get("collect_error"):
            card += f"\n\n{payload['collect_error']}"
        self.query_one("#menu-card", Static).update(_plain(card))
        busy = app.payload is None
        for button_id in ("inventory", "rename", "device"):
            self.query_one(f"#{button_id}", Button).disabled = busy

    def on_button_pressed(self, event: Button.Pressed) -> None:
        app = self.app
        assert isinstance(app, ScannerApp)
        button = event.button.id
        if button == "exit":
            app.exit()
            return
        if button == "device":
            app.push_screen(DeviceScreen())
            return
        if button in {"inventory", "rename"}:
            app.mode = "rename" if button == "rename" else "inventory"
            app.employee = None
            app.hostname = None
            app.hostname_error = None
            app.serial_note = None
            app.push_screen(PreviewScreen())


class DeviceScreen(Screen):
    def compose(self) -> ComposeResult:
        yield Header(show_clock=True)
        yield Static(id="device-body")
        yield Button("Back", id="back")
        yield Footer()

    def on_mount(self) -> None:
        app = self.app
        assert isinstance(app, ScannerApp)
        payload = app.payload or {}
        adapters = payload.get("network_adapters") or []
        disks = payload.get("disks") or []
        video = payload.get("video_cards") or []
        apps = payload.get("applications") or []
        lines = [
            "DEVICE INFORMATION",
            _line("Computer", payload.get("hostname")),
            _line("Serial", payload.get("serial_number")),
            _line("Manufacturer", payload.get("manufacturer")),
            _line("Product", payload.get("product_name")),
            _line("Version", payload.get("product_version")),
            _line("Processor", payload.get("processor")),
            _line("RAM", payload.get("installed_ram")),
            _line("Operating system", payload.get("operating_system")),
            _line("System type", payload.get("system_type")),
            _line("BIOS", payload.get("bios_version")),
            "",
            "Network adapters",
        ]
        if adapters:
            lines.extend(
                f"  {row.get('adapter_type')}: {row.get('adapter_name')}  {row.get('mac_address')}"
                for row in adapters
            )
        else:
            lines.append("  None detected")
        lines.append("")
        lines.append("Disks")
        if disks:
            lines.extend(
                f"  {row.get('model')}  {row.get('size_label') or ''}  {row.get('interface') or ''}".rstrip()
                for row in disks
            )
        else:
            lines.append("  None detected")
        lines.append("")
        lines.append("Video")
        if video:
            lines.extend(f"  {row.get('name')}" for row in video)
        else:
            lines.append("  None detected")
        lines.append("")
        lines.append(f"Applications ({len(apps)})")
        lines.extend(f"  {row.get('application_name')}" for row in apps[:12])
        if len(apps) > 12:
            lines.append(f"  … {len(apps) - 12} more")
        self.query_one("#device-body", Static).update(_plain("\n".join(lines)))

    def on_button_pressed(self, event: Button.Pressed) -> None:
        if event.button.id == "back":
            self.app.pop_screen()


class PeopleScreen(Screen):
    def compose(self) -> ComposeResult:
        yield Header(show_clock=True)
        yield Label("The Windows login was not a single directory match. Pick the employee.", markup=False)
        yield Input(placeholder="Name or email", id="person-search")
        yield ListView(id="people")
        with Horizontal():
            yield Button("Search", id="search")
            yield Button("Back", id="back")
        yield Footer()

    def on_mount(self) -> None:
        app = self.app
        assert isinstance(app, ScannerApp)
        self.query_one("#person-search", Input).value = login_name()
        self._fill(app.people)

    def _fill(self, people: list[dict[str, Any]]) -> None:
        view = self.query_one("#people", ListView)
        view.clear()
        for person in people:
            label = f"{person.get('name')} · {person.get('department') or 'No department'} · {person.get('email')}"
            view.append(ListItem(Label(label, markup=False), name=str(person.get("id")), markup=False))

    def on_button_pressed(self, event: Button.Pressed) -> None:
        if event.button.id == "back":
            self.app.pop_screen()
            return
        if event.button.id == "search":
            app = self.app
            assert isinstance(app, ScannerApp)
            term = self.query_one("#person-search", Input).value.strip()
            try:
                people = app.api.find_people(term)
            except ApiError as exc:
                self.notify(str(exc), severity="error")
                return
            app.people = people
            self._fill(people)

    def on_list_view_selected(self, event: ListView.Selected) -> None:
        app = self.app
        assert isinstance(app, ScannerApp)
        person_id = event.item.name
        match = next((row for row in app.people if str(row.get("id")) == person_id), None)
        if match is None:
            return
        app.employee = match
        self.app.pop_screen()


class PreviewScreen(Screen):
    def compose(self) -> ComposeResult:
        yield Header(show_clock=True)
        yield Static(id="preview-card")
        yield Label("Asset tag from the QR, if you have it")
        yield Input(placeholder="ATC-LAP-000142", id="asset-tag")
        yield Static(id="preview-status")
        with Horizontal(id="preview-actions"):
            yield Button("Send Inventory Only", id="send", variant="success")
            yield Button("Send + Rename PC", id="send-rename", variant="primary")
            yield Button("Choose employee", id="people")
            yield Button("Rescan", id="rescan")
            yield Button("Cancel", id="cancel")
        yield Footer()

    def on_mount(self) -> None:
        self._prepare()

    def on_screen_resume(self) -> None:
        self._paint()

    def _prepare(self) -> None:
        app = self.app
        assert isinstance(app, ScannerApp)
        self._paint("Checking the directory…")
        app.run_worker(self._resolve, thread=True, exclusive=True)

    def _resolve(self) -> None:
        app = self.app
        assert isinstance(app, ScannerApp)
        note = None
        try:
            if app.connected and app.employee is None:
                app.employee, app.people = _match_employee(app.api, login_name())
            payload = app.payload or {}
            serial = str(payload.get("serial_number") or "")
            if app.connected and serial and not app.asset_tag:
                existing = app.api.find_asset_by_serial(serial)
                if existing:
                    app.known_asset = existing
                    app.asset_tag = str(existing.get("asset_tag") or "")
            if app.mode == "rename" and app.employee and app.connected:
                asset_id = str((app.known_asset or {}).get("id") or "") or None
                try:
                    checked = app.api.check_hostname(str(app.employee["id"]), asset_id)
                    app.hostname = str(checked.get("hostname") or checked.get("suggested") or "")
                    app.hostname_error = None
                except ApiError as exc:
                    app.hostname = None
                    app.hostname_error = str(exc)
        except ApiError as exc:
            note = str(exc)
        self.app.call_from_thread(self._paint, note)

    def _paint(self, note: str | None = None) -> None:
        if not self.is_mounted:
            return
        app = self.app
        assert isinstance(app, ScannerApp)
        payload = app.payload or {}
        employee = app.employee
        current = str(payload.get("hostname") or "—")
        proposed = app.hostname if app.mode == "rename" else None
        who = "Not resolved"
        department = "—"
        if employee:
            who = str(employee.get("name") or "—")
            department = str(employee.get("department") or "—")
        lines = [
            "INVENTORY PREVIEW",
            _line("Employee", who),
            _line("Department", department),
            _line("Windows login", payload.get("windows_identity")),
            "",
            _line("Current PC", current),
            _line("New PC", proposed or "—"),
            "",
            _line("Serial", payload.get("serial_number")),
            _line("Product", " ".join(part for part in [str(payload.get("manufacturer") or ""), str(payload.get("product_name") or "")] if part)),
            _line("RAM", payload.get("installed_ram")),
            _line("Operating system", payload.get("operating_system")),
            _line("BIOS", payload.get("bios_version")),
            _line("Wi-Fi MAC", _wifi_mac(payload)),
            _line("Applications", len(payload.get("applications") or [])),
        ]
        if app.known_asset:
            lines.append(_line("Existing asset", app.known_asset.get("asset_tag")))
        self.query_one("#preview-card", Static).update(_plain("\n".join(lines)))
        tag = self.query_one("#asset-tag", Input)
        if app.asset_tag and not tag.value:
            tag.value = app.asset_tag
        messages = []
        if note:
            messages.append(note)
        if app.serial_note:
            messages.append(app.serial_note)
        if app.hostname_error:
            messages.append(app.hostname_error)
        elif app.mode == "rename" and app.hostname:
            messages.append(f"Computer name {app.hostname} is available.")
        if not payload.get("serial_number"):
            messages.append("No serial number was read. Inventory cannot be sent.")
        if not app.connected:
            messages.append("Not connected. Set TOWEROS_TOKEN and TOWEROS_TENANT, then open the scan again.")
        if not messages:
            messages.append("Nothing has been sent. Confirm before the inventory is written.")
        self.query_one("#preview-status", Static).update(_plain("\n".join(messages)))
        can_send = bool(payload.get("serial_number")) and app.connected and not payload.get("collect_error")
        self.query_one("#send", Button).disabled = not can_send
        self.query_one("#send-rename", Button).disabled = not (
            can_send and app.mode == "rename" and app.employee and app.hostname and not app.hostname_error
        )

    def on_button_pressed(self, event: Button.Pressed) -> None:
        app = self.app
        assert isinstance(app, ScannerApp)
        button = event.button.id
        if button == "cancel":
            app.pop_screen()
            return
        if button == "people":
            app.push_screen(PeopleScreen())
            return
        if button == "rescan":
            app.payload = None
            app.known_asset = None
            app.asset_tag = ""
            app.serial_note = None
            self.query_one("#preview-status", Static).update(_plain("Reading this PC…"))
            app.run_worker(self._rescan, thread=True, exclusive=True)
            return
        if button in {"send", "send-rename"}:
            self.query_one("#send", Button).disabled = True
            self.query_one("#send-rename", Button).disabled = True
            rename = button == "send-rename"
            tag = self.query_one("#asset-tag", Input).value.strip()
            app.run_worker(lambda: self._send(tag, rename), thread=True, exclusive=True)

    def _rescan(self) -> None:
        app = self.app
        assert isinstance(app, ScannerApp)
        app.payload = collect()
        app.employee = None
        app.hostname = None
        app.hostname_error = None
        self._resolve()

    def _send(self, asset_tag: str, rename: bool) -> None:
        app = self.app
        assert isinstance(app, ScannerApp)
        payload = {key: value for key, value in (app.payload or {}).items() if key != "collect_error"}
        try:
            if asset_tag:
                verified = app.api.verify_serial(asset_tag, str(payload.get("serial_number") or ""))
                if not verified.get("serial_match"):
                    raise ApiError("Serial mismatch. Inventory submission is blocked.")
                payload["asset_tag"] = asset_tag
                app.serial_note = (
                    f"Serial match. Expected {verified.get('expected_serial')}. "
                    f"Detected {verified.get('detected_serial')}."
                )
            else:
                app.serial_note = "No asset tag. The serial number decides whether this updates an existing asset."
            if app.employee:
                payload["matched_user_id"] = app.employee["id"]
            result = app.api.submit(payload)
            asset = result.get("asset") if isinstance(result.get("asset"), dict) else {}
            tag = str(asset.get("asset_tag") or "")
            message = f"Stored {tag}."
            if result.get("idempotent"):
                message = f"{tag} was already stored for this scan."
            if rename and app.hostname:
                if _rename_windows(app.hostname):
                    asset_id = str(asset.get("id") or "")
                    if asset_id:
                        app.api.record_hostname(asset_id, app.hostname)
                    message = f"Stored {tag}. Renamed to {app.hostname}."
                else:
                    message = f"Stored {tag}. Windows did not rename to {app.hostname}."
            if isinstance(app.payload, dict):
                app.payload["client_key"] = str(uuid.uuid4())
            self.app.call_from_thread(self._sent, message)
        except ApiError as exc:
            app.serial_note = str(exc)
            self.app.call_from_thread(self._paint, str(exc))

    def _sent(self, message: str) -> None:
        self.notify(message, timeout=8)
        self.app.pop_screen()


def _match_employee(api: AssetApi, username: str) -> tuple[dict[str, Any] | None, list[dict[str, Any]]]:
    if not username:
        return None, []
    people = api.find_people(username)
    exact = [
        person for person in people
        if str(person.get("email") or "").split("@", 1)[0].lower() == username.lower()
    ]
    if len(exact) == 1:
        return exact[0], people
    if len(people) == 1:
        return people[0], people
    return None, people


def _rename_windows(hostname: str) -> bool:
    if platform.system() != "Windows":
        return False
    completed = subprocess.run(
        ["powershell", "-NoProfile", "-NonInteractive", "-Command", f"Rename-Computer -NewName '{hostname}' -Force"],
        check=False,
        capture_output=True,
        text=True,
    )
    return completed.returncode == 0


class ScannerApp(App):
    CSS = """
    Screen { padding: 1 2; }
    #menu-card, #preview-card, #device-body, #preview-status {
      height: auto;
      padding: 1 2;
      border: round $primary;
      margin-bottom: 1;
    }
    #preview-status { border: round $accent; }
    #menu-actions Button, #preview-actions Button { margin-right: 1; margin-bottom: 1; }
    #people { height: 12; }
    """

    BINDINGS = [("q", "quit", "Quit")]

    def __init__(self) -> None:
        super().__init__()
        self.api = AssetApi()
        self.payload: dict[str, Any] | None = None
        self.connected = False
        self.mode = "inventory"
        self.employee: dict[str, Any] | None = None
        self.people: list[dict[str, Any]] = []
        self.hostname: str | None = None
        self.hostname_error: str | None = None
        self.known_asset: dict[str, Any] | None = None
        self.asset_tag = ""
        self.serial_note: str | None = None

    def on_mount(self) -> None:
        self.title = "Alliance Towers IT Asset Scanner"
        self.push_screen(MenuScreen())
        self.run_worker(self._boot, thread=True)

    def _boot(self) -> None:
        payload = collect()
        connected = False
        if self.api.configured:
            try:
                self.api.ping()
                connected = True
            except ApiError:
                connected = False
        self.call_from_thread(self._booted, payload, connected)

    def _booted(self, payload: dict[str, Any], connected: bool) -> None:
        self.payload = payload
        self.connected = connected
        if isinstance(self.screen, MenuScreen):
            self.screen._paint()


if __name__ == "__main__":
    ScannerApp().run()
