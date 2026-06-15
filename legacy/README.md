# Legacy reference code

This folder holds **decommissioned** applications kept for migration reference only. They are **not** built, deployed, or run as part of TowerOS.

| Path | Status | Replacement |
|------|--------|-------------|
| `atcformbuiilder/` | **Archived (P4)** | TowerOS **E-Approval** module (`backend/app/Modules/EApproval/`, `/e-approval` UI) |

Do not add Docker services, CI jobs, or npm scripts that start legacy apps. For cutover data, use `php artisan e-approval:import-legacy` (see `docs/modules/e-approval.md`).
