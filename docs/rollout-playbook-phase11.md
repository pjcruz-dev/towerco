# Rollout Playbook — Phase 11 (Projects integration) ✅

Unify **Projects** (QMS programs) with **Rollouts** (TowerCo playbook execution).

## Delivered

- **`rollout_programs.project_id`** FK + index
- **Project CRUD APIs** — GET show, POST create, PATCH update (index already paginated)
- **Project detail payload** — milestones, recent approvals, linked rollouts
- **Rollout ↔ project link** — optional on create; reassign via PATCH metadata; site mismatch validation
- **Dashboard** — `active_rollouts_by_project` in rollout metrics
- **Frontend** — project detail/new pages, list links, rollout project dropdown + detail link
- **Alliance demo seed** — `RP-2026-GLO-DEMO` linked to Quezon Structural Upgrade project
- **Tests** — `ProjectCrudApiTest`, `RolloutProjectLinkTest`

## Goals

- Full **Project CRUD** (not list-only)
- Each rollout can belong to a **project**
- Project detail is the hub: milestones, approvals, rollouts, site

## Backend

### 1. Schema

Migration (tenant):

```sql
ALTER TABLE rollout_programs ADD project_id UUID NULL REFERENCES projects(id) ON DELETE SET NULL;
```

Index: `rollout_programs(project_id)`.

### 2. Project APIs

| Method | Path | Permission |
|--------|------|------------|
| GET | `/api/v1/project-one/projects` | `project_one:view` | *(exists — keep paginated)* |
| GET | `/api/v1/project-one/projects/{project}` | `project_one:view` |
| POST | `/api/v1/project-one/projects` | `project_one:manage` |
| PATCH | `/api/v1/project-one/projects/{project}` | `project_one:manage` |

Create/update fields: `name`, `site_id`, `project_manager_id`, `status`, `start_date`, `end_date`.

Show payload includes: `milestones[]`, `approvals[]` (recent), `rollouts[]` (summary rows).

### 3. Rollout create/update

- `POST /rollouts` accepts optional `project_id`
- `PATCH /rollouts/{id}` (Phase 10) may reassign `project_id`

Validation: project `site_id` should match rollout site when both set (warn or enforce).

### 4. Dashboard

`ProjectOneDashboardService`: optional KPI `active_rollouts_by_project`.

## Frontend

| Surface | Change |
|---------|--------|
| Projects list | Row links to `/project-one/projects/{id}` |
| Project detail | **New page**: header, site, manager, milestones, linked rollouts table, approvals snippet |
| New project | Form at `/project-one/projects/new` |
| Rollout create | Optional project dropdown (filtered by site if site preselected) |
| Rollout detail | Link back to parent project |

## Demo seed

Update `AllianceDemoSeeder` to attach demo rollouts to existing demo projects.

## Tests

```bat
php artisan test --filter=ProjectCrudApi
php artisan test --filter=RolloutProjectLink
```

## Verify locally

1. Create project on a site
2. Create rollout linked to that project
3. Project detail shows rollout; rollout detail links to project

See also: [project-one-roadmap.md](./project-one-roadmap.md) · [rollout-playbook-phase10.md](./rollout-playbook-phase10.md)
