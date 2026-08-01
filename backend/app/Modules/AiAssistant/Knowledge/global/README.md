# Global help content pack

Markdown articles for the tenant-user AI Assistant (how-to / workflows).

## Conventions

- One `.md` file per article under this folder.
- Required YAML-like frontmatter between `---` fences:
  - `title`, `slug`, `module`, `audience`, `permissions`, `status`, `version`, `related_routes`, `last_reviewed`
- `module` must match TowerOS module keys (`core`, `sites`, `e_approval`, …).
- `audience` should be `tenant_user` for this pack.
- `status: published` is required for sync registration.
- No platform-admin or engineering/architecture secrets.

## Sync

```bash
php artisan ai-assistant:sync-global-knowledge --tenant={uuid}
php artisan ai-assistant:sync-global-knowledge --all
php artisan ai-assistant:sync-global-knowledge --all --prune
```

Upserts rows into each tenant’s `ai_knowledge_sources` (`scope=global`) by `slug`.
Embeddings / chunks are handled in a later phase.
