# ⚠️ READ THIS FIRST — one manual step, still not done as of v11

Your own Cursor agent just confirmed it: `.cursor/rules/toweros.mdc` and `.cursor/rules/uiux-theme.mdc`
are STILL in your repo, STILL `alwaysApply: true`, and STILL loading on every single turn alongside
`global.mdc` and `ui-theme.mdc`. This has been true since v5. No zip can fix this — extracting a zip only
adds or overwrites files, it never deletes anything, so these two files survive every update untouched.

Run this now, before anything else, from the repo root:

```powershell
cd C:\LaravelProject\TowerOS
git rm .cursor/rules/toweros.mdc .cursor/rules/uiux-theme.mdc
git commit -m "Remove superseded always-on rules (replaced by global.mdc and ui-theme.mdc)"
```

Then verify it actually worked:

```powershell
Test-Path .cursor\rules\toweros.mdc      # must print False
Test-Path .cursor\rules\uiux-theme.mdc   # must print False
```

If either prints `True`, the delete did not take — paste the exact error PowerShell gave you.
Until both print `False`, every rule below is competing with a stale copy of itself on every turn.

---

# TowerOS: Cursor config (v13)

**Scope change:** the rules now only cover the Ticketing, EApproval (E-Forms), DocExtract and Documents (Document Control) modules. `ui-map.mdc`, `ui-mobile-field.mdc` and the `map-view` skill were removed (telecom/NOC/GIS content, not used by these modules). `global.mdc` tells the agent not to build in AssetOne, FiberOne, TowerOne, Sites, Rollout, ProcurementOne, ProjectOne, AiAssistant or Help, and not to touch the shared platform plumbing (Identity, Tenancy, Notifications, Platform, AdminOne, Billing, Workspace, `app/Core`) without asking, since the four active modules depend on it.
**I assumed "E-Forms" = the `EApproval` module and "Document Control" = the `Documents` module** (its frontend API client is literally `controlled-documents-api.ts`). Tell me if that's wrong.
**I have not touched any code or removed any modules from the repo itself** — this only changes what the Cursor agent is told to work on. Deleting the actual AssetOne/FiberOne/etc. code is a separate, larger job (routes, permissions, nav entries, tests, migrations) that I'd want to scope with you before running.


Extract over your repo root. It overwrites the files from the first zip and leaves your own `toweros.mdc` and `uiux-theme.mdc` untouched. Then resolve those two (below).

```
.cursorignore                 secrets out of indexing and agent reads
.cursorindexingignore         noisy folders out of the index only
.cursor/
  mcp.json                    Context7, Playwright (Laravel Boost removed: not installed)
  hooks.json + hooks/         guardrails (Node, Windows-safe)
  rules/                      global.mdc (always on) + 12 scoped rules
  skills/                     crud-module, data-table, map-view, tenant-safe-migration
  agents/                     code-reviewer, security-auditor, tenant-isolation-auditor, ui-ux-reviewer
  commands/                   /new-feature /ui-review /tenant-audit /review-branch /fix-issue
```

## Resolve the duplicates
`toweros.mdc` (your global rules) and `uiux-theme.mdc` (your UI theme) are probably `alwaysApply: true`. Together with `global.mdc` and `ui-theme.mdc` that means two overlapping always-on rule sets, which can conflict. If they are your original text, everything in them is already covered, so delete them. If you added project-specific lines since, move those lines into `global.mdc` / `ui-theme.mdc` first.

## What changed in v3 (from your folder listing and globals.css)
- Tables are `components/ui/data-table*.tsx`, `app-table.tsx`, `lib/table/server-sort.ts`, `lib/api/paginated.ts` and the list-export files. `components/data` was empty, so nothing points there any more.
- Modules use `<Module><Thing>Service`, `<Module>PlanFeaturesService` and `<Module>FileStorageService`. Repositories are optional (only an abstract base exists).
- Frontend per feature: route in `app/(platform)`, client in `lib/api/modules/<m>-api.ts`, types in `modules/<m>/types.ts`, helpers in `lib/<area>`, UI in `components/<area>`.
- Three API clients (`client`, `central-client`, `public-tenant-client`) are now a tenancy rule.
- ui-theme keeps your existing `globals.css` tokens and only adds status/alarm tokens. Fonts are system stacks. Radius is 0.5rem.
- Added an SSRF check for tenant-configurable webhooks (Ticketing has test-webhook endpoints).

## What changed from v1 (based on your repo)
- No `src/` in frontend: routes in `app/`, UI in `components/`, module code in `modules/`, shared code in `lib/`.
- Backend uses `app/Core` + `app/Modules` and the repository/service pattern. Tenant migrations are in `database/migrations/tenant` (database-per-tenant).
- Tests are PHPUnit (not Pest). Commands come from your root `package.json` scripts. No Larastan, Prettier, Playwright, i18n or URL-state library are assumed.
- Hooks: added blocks for Docker volume removal, `dev:fresh`, PowerShell download-and-run and `git clean -f`. Pint now runs as `php vendor/bin/pint`. The Prettier step was removed.
- ui-theme and the reviewer defer to `frontend/app/globals.css` and `docs/design-system` as the source of truth.

## Verify in Cursor
Settings, then Hooks: confirm the four hooks load and check the Hooks output channel. Try editing `pnpm-lock.yaml`/`package-lock.json` once to confirm `protect-writes` blocks it. Hooks intentionally block the agent from editing `.cursor/hooks*` and `.cursorignore`.


## v5: fix a duplicate-file bug from v1-v4
Extracting these zips over your repo only adds/overwrites files by name — it never deletes anything. So your **original** `toweros.mdc` and `uiux-theme.mdc` were still sitting in `.cursor/rules/` this whole time, alongside the new `global.mdc` and `ui-theme.mdc`, both marked `alwaysApply: true`. That's why `toweros.mdc` still had the stale PostgreSQL/PostGIS/TimescaleDB lines — nothing in these zips ever touched it.

Run this once, from the repo root, to delete both superseded files (back them up first if you want the history):

```powershell
cd C:\LaravelProject\TowerOS
git rm .cursor/rules/toweros.mdc .cursor/rules/uiux-theme.mdc
```

`global.mdc` now carries everything `toweros.mdc` had, plus the deployment/hostname block:
- Redis 7 (was unversioned)
- Production hostnames (`app`, `appmenu`, `console`, `staging`.alliancetowers.com) and the CloudFront → nginx → EC2 API path — no IP address, as instructed
- The PostgreSQL/PostGIS/TimescaleDB lines are gone, since this repo doesn't use them

I also fixed a leftover doubled "## Stack in use" heading in `global.mdc` from an earlier edit pass.


## v6: UI drift ratchet + tenancy corrections
1. Copy `scripts/ui-drift.mjs` to the repo's `scripts/` folder.
2. Add to the ROOT `package.json` scripts: `"ui:drift": "node scripts/ui-drift.mjs"`.
3. Once, run `node scripts/ui-drift.mjs --report`, then `node scripts/ui-drift.mjs --update` and commit `scripts/ui-drift-baseline.json`.
4. Add a step to `.github/workflows/ci.yml` after checkout: `- run: node scripts/ui-drift.mjs`.

The script fails only when a file gets WORSE than its baseline, so the existing drift does not block anyone. The agent is blocked from editing the script or baseline and from running `--update`.

Rule corrections from your `config/tenancy.php`: there is no Redis-prefix or broadcasting bootstrapper, the cache bootstrapper is conditional, and the filesystem bootstrapper covers `local`/`public` only. `multi-tenancy.mdc` now says so, and adds environments, module gating and backups.


## v7: corrections from your tokens.css, badges, module resolver and tenancy services
- `ui-theme.mdc` now follows `tokens.css` (status tokens, radius, shadow and z-index tokens already exist there). My earlier "border-first, no shadow, rounded-lg" card rule contradicted your tokens and is gone.
- New one-time commands: `/consolidate-status-badges` (shared status palette, fixes the failing `destructive` badge contrast) and `/audit-module-gating` (read-only check of whether disabling a module really blocks it).
- `global.mdc` now uses the real module keys. `e_approval` is labelled "E-Forms" in code. Document Control is most likely `document_register`, not `documents` (which is leases/permits/contracts across sites).
- `multi-tenancy.mdc` now describes tenant-linked environments (root tenant plus children via `parent_tenant_id`) and says plainly that no detach feature exists.


## v8: corrections from the second PowerShell audit
- Card shape: measured, not guessed. `rounded-xl` + `shadow-sm` is what the UI really uses. The `rounded-card`/`shadow-card` tokens (which v7 told the agent to use) have 0 uses. `ui-theme.mdc` now says so and points at DESIGN_SYSTEM.md sections 4, 5 and 9.
- DESIGN_SYSTEM.md already has badge and status-colour sections (16, 17). `/consolidate-status-badges` now reads them first and stops if they conflict.
- `global.mdc` records the hard dependencies that block deleting modules (E-Forms -> ProcurementOne, Document Control <-> E-Forms) and the verified gating facts. `architecture.mdc` no longer claims modules never import each other.
- `multi-tenancy.mdc` records verified facts about linked environments: billing columns are per row and do not follow the org root, and offboarding takes no backup and may leave local files behind.


## v9: status colours follow DESIGN_SYSTEM.md, not my earlier token idea
- DESIGN_SYSTEM.md 16.2 and 17 already prescribe Tailwind palette classes for statuses (emerald, amber, red, sky). Most of the 817 "drift" matches are that documented pattern, so `scripts/ui-drift.mjs` no longer counts those four families (or sidebar files). It now flags undocumented families (blue, green, orange, slate, gray...), hex and rgb/hsl values. Install it and run `node scripts/ui-drift.mjs --report` to see the real number.
- `proposals/status-badges/` holds three files (status-tone.ts, status-badge.tsx, ticketing-badges.tsx) that implement 17.1 once. They were typechecked against a copy of your real `badge.tsx`. Copy them into `frontend/components/...` by hand (no Cursor credits needed), then run lint, typecheck and tests. `/consolidate-status-badges` now describes this instead of new tokens.
- `global.mdc` and `/audit-module-gating` now state what gating really enforces: only 5 route groups carry `tenant.module`.
- `multi-tenancy.mdc`: deletion callers and audit behaviour, tenant file location, and the staging-topology question.


## v10: staging recommendation + ProcurementOne removal plan
- `proposals/staging-environment.md`: `staging.alliancetowers.com` is confirmed to be a tenant environment on the production servers (created via `console.alliancetowers.com`, DNS at GoDaddy), not a release-testing environment. Recommends a separate pre-release stack instead of relying on it.
- `procurement_one` is confirmed unneeded and being removed, but E-Forms calls into it directly today, so it cannot be deleted yet. `global.mdc` now says this explicitly so the agent does not delete ProcurementOne prematurely nor build new work there.
- `proposals/procurement-one-decoupling-plan.md`: the ordered plan (audit -> decide what E-Forms loses -> remove the calls -> remove the module -> verify).
- `proposals/procurement-removal-audit.ps1`: read-only PowerShell script listing every file outside ProcurementOne that references it, so Step 1 of the plan can be filled in with real file names. I could not execute this script myself (no PowerShell in my environment) — only syntax-balance-checked it. Please run it and confirm it works before relying on its output.


## v12: stale rule index fixed; the toweros.mdc/uiux-theme.mdc deletion still isn't done
Cursor's own agent reviewed the v11 rules and confirmed both files are still present, `alwaysApply: true`, and actively contradicting `global.mdc` (INFRA SUITE vs TowerOS, Postgres/PostGIS/TimescaleDB vs the real MySQL-only stack, the old blue-primary theme vs the real near-black one). It also caught that `global.mdc`'s own rule index at the bottom never got updated after `/audit-module-gating`, `/consolidate-status-badges` and `/decouple-procurement` were added — fixed that too. The file-deletion step is still on you; see the banner at the top of this README.

## v11: procurement audit came back — real scope, plan finalized
The audit script worked and returned real data: ProcurementOne removal touches ~40 frontend pages, 6 E-Forms services, billing entitlements, 4 RBAC roles, an AI Assistant catalog entry, a Ticketing category pack, 8 scheduled commands and 17 tenant migrations. Given that scale, the plan now commits to Option A (drop procurement-linked E-Forms form types entirely, not just their sync). `proposals/procurement-one-decoupling-plan.md` was rewritten with the real file/class names from the audit, in execution order. New command `/decouple-procurement` runs it step by step with checkpoints, and asks before dropping the 17 tenant-database tables or deleting an RBAC role that a real user holds.


## v13: removal scope expanded to six more modules
AssetOne, FiberOne, TowerOne, Sites, Rollout and ProjectOne are now confirmed for removal, same status ProcurementOne had before its audit: no new work, no deletion until audited. Two are already known to be entangled, not just switchable, from earlier audit output: Sites is imported directly by E-Forms (`EApprovalMasterDataService`), and Rollout has module-specific code living inside Document Control (`DocumentRolloutGateEnforcementService` and others) and is not even present in the toggleable-modules list — it looks baked into tenant provisioning itself. `global.mdc` now states this so the agent does not attempt to delete either prematurely.

`proposals/module-removal-audit.ps1` (new, could not be executed here — no PowerShell in this environment, only brace/quote-balance checked) audits all six at once: cross-references from outside each module, RBAC/billing/AI-assistant/Ticketing coupling, tenant-database migrations, and whether each is even toggleable. It also cross-checks whether the four ACTIVE modules reference any of the six. `/audit-modules-for-removal` runs the same read-only investigation from inside Cursor and asks for a per-module verdict before any decoupling plan gets written.
