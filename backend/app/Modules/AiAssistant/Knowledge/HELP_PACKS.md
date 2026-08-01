# TowerOS AI Assistant — Module Help Packs

This document defines the **Module Help Pack** convention. Any TowerOS module can make
itself discoverable by the in-product assistant (Ask TowerOS) simply by shipping a help
pack — no changes to the AiAssistant module are required.

## Convention

Place published help articles here:

```
backend/app/Modules/{Module}/Knowledge/help/*.md
```

- `{Module}` is the StudlyCase module directory (e.g. `ProcurementOne`, `EApproval`).
- One markdown file per help article.
- A `README.md` in the folder is ignored by discovery.

The AiAssistant module also ships **core, cross-module** articles under
`backend/app/Modules/AiAssistant/Knowledge/global/*.md` (getting started, permissions,
command palette, troubleshooting). Module-specific how-tos belong in that module's help pack.

## Required frontmatter

Every help article must start with a frontmatter block:

```markdown
---
title: Procurement-One overview
slug: procurement-one-overview        # unique, kebab-case, [a-z0-9-]
module: procurement_one               # module KEY (snake_case), must match the folder
audience: tenant_user
permissions:                          # required permissions to retrieve this doc (RBAC-gated)
  - procurement_one:view
status: published                     # only "published" is synced/ingested
version: 1                            # integer >= 1; bump on meaningful change
related_routes:                       # deep links surfaced with answers
  - /procurement
last_reviewed: 2026-07-17             # optional
---

# Body in markdown …
```

Notes:

- `module` must be a **known module key** (see `TenantEnabledModulesResolver`) and must match
  the folder it lives in (`Str::snake('ProcurementOne') === 'procurement_one'`).
- `status: draft` (or anything other than `published`) is **skipped** by sync/ingest.
- `permissions` are enforced at retrieval time; a user who lacks them never sees the doc.

## How discovery, sync, and retrieval work

1. **Discovery** — `HelpPackDiscoveryService` scans `Modules/*/Knowledge/help/*.md`.
2. **Sync** — `ai-assistant:sync-global-knowledge` upserts published core + module articles
   into each tenant's `ai_knowledge_sources` as **global scope, module-tagged** rows.
3. **Ingest** — `ai-assistant:ingest-knowledge` chunks + embeds published sources.
4. **Retrieval** — at query time the assistant includes a module's docs only if:
   - the module is **enabled** for the current tenant, AND
   - the user holds the doc's **required permissions**.
5. **No guide yet** — if a module is enabled but has no published docs, the assistant
   answers honestly ("guide not published yet") instead of inventing steps.

## Shipping a help pack for a new module

1. Create `backend/app/Modules/{Module}/Knowledge/help/{slug}.md` with valid frontmatter.
2. Run validation:
   ```bash
   php artisan ai-assistant:validate-help-packs
   ```
3. Sync + ingest for tenants:
   ```bash
   php artisan ai-assistant:sync-global-knowledge --all
   php artisan ai-assistant:ingest-knowledge --all
   ```

## Validation (CI)

`ai-assistant:validate-help-packs` fails (non-zero exit) when:

- frontmatter is missing/unclosed or required fields are invalid,
- a `module` key is unknown or does not match its folder,
- a slug is duplicated across packs.

Use `--strict` to also fail on warnings (e.g. missing `permissions`). Add it to CI:

```bash
php artisan ai-assistant:validate-help-packs --strict
```

## Tenant SOPs (tenant knowledge)

Tenant-specific procedures are **not** markdown help packs. Workspace admins publish them under
**Settings → AI Assistant → Knowledge** (`ai_assistant:knowledge:manage`).

- Scope: `tenant` (isolated per tenant DB)
- Set `module_key` when the SOP belongs to a module — Ask TowerOS biases retrieval toward the
  current page module and prefers matching tenant SOPs over unrelated global guides.
- After publish, ingestion embeds the article for RAG automatically.

## Feedback gaps

Thumbs-down feedback is stored on assistant messages. To find missing guides / bad routing:

```bash
php artisan ai-assistant:feedback-gaps --tenant={uuid} --days=30
php artisan ai-assistant:feedback-gaps --all --json
```

## Controlled actions (Phase 10)

Write capabilities are propose-only until the user confirms in the assistant drawer.

- Flag: `AI_ASSISTANT_ACTIONS_ENABLED` (config `ai_assistant.actions.enabled`)
- Permissions: `ai_assistant:tools:use` (propose) + `ai_assistant:actions:execute` (confirm) + the domain permission for the action
- Confirm API: `POST /api/v1/assistant/actions/confirm` — never executes from ask alone
- Allowlisted actions today: `draft_ticket`, `draft_e_approval_submission`, `suggest_document_metadata`
