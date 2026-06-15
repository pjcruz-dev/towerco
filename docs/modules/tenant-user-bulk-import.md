# Team & Access — bulk user import

CSV columns: `email`, `name`, `role` (optional, defaults to `viewer`).

## Microsoft sign-in (no duplicates)

- TowerOS matches users by **email address** (case-insensitive).
- **Import first**, then users sign in with Microsoft using the **same work email** (UPN).
- Microsoft sign-in **reuses** the imported account — it does **not** create a second user.
- Re-importing the same email (any casing) is **skipped** (`skipped` count in the API response).

## Roles

- Roles set in the CSV (or Team & Access) are **kept** when the user signs in with Microsoft.
- Entra **group → role mapping** only **adds** roles when a group matches; it does not remove import assignments.
- Leave group mapping as `{}` if all roles should come from import / Team & Access only.

## Recommended settings

| Setting | Recommendation |
|---------|----------------|
| Auto-provision on Microsoft sign-in | **Off** — only pre-imported users can sign in |
| Allowed email domains | Your organization domain(s) |
| Entra group mapping | `{}` unless you intentionally merge group roles |

## API

`POST /api/v1/admin/users/import` — `multipart/form-data` with `file` (CSV).

Response includes `created`, `skipped`, `errors`, and `hint` explaining duplicate and SSO behavior.
