# TowerOS documentation (non-production)

All playbooks, phase notes, board rules, local launchers, and archives live under **`docs/`**.

## Not deployed to AWS

Production/staging images are built with Docker context **`./backend`** and **`./frontend` only** (see `.github/workflows/deploy-*.yml`). Nothing in this folder is copied into those images or served as app runtime content.

| Included in AWS images | Not included (this tree + repo root helpers) |
|------------------------|-----------------------------------------------|
| `backend/` app code, configs, AI Knowledge packs | `docs/` (this folder) |
| `frontend/` Next.js app | `docs/local-dev/` Windows helpers (optional) |
| | `scripts/` local/CI helpers (not in image context) |
| | `.cursor/` |

Keep in-app help Markdown under `backend/app/Modules/*/Knowledge/` — those **are** production.

## Layout

```text
docs/
├── README.md           This index
├── Rules/              Board deck, rollout playbook DOCX, AI security HTML
├── archives/           Local-only scratch (gitignored except README)
├── local-dev/          Optional Windows .cmd launchers (npm run … preferred)
├── guides/             Local Docker / Podman / performance
├── roadmaps/           PROJECT-ONE, notifications, etc.
├── rollout/            Playbook phases + gate-approval phases
├── architecture/       Tenant isolation, etc.
├── design-system/      DESIGN_SYSTEM.md (canonical) + token summary
├── frontend/           Frontend engineering notes
├── infrastructure/     AWS EC2/RDS, ECS, CI/CD, hardening, runbooks
├── modules/            Module guides (e-approval, billing phases, …)
├── samples/            E-Approval import JSON samples
└── exports/            Generated spreadsheets
```

## Quick links

| Area | Path |
|------|------|
| Local Docker guide | [guides/local-development-docker-guide.md](./guides/local-development-docker-guide.md) |
| Production (EC2 + RDS) | [infrastructure/aws-ec2-rds-production.md](./infrastructure/aws-ec2-rds-production.md) |
| PROJECT-ONE roadmap | [roadmaps/project-one-roadmap.md](./roadmaps/project-one-roadmap.md) |
| Rollout playbooks | [rollout/](./rollout/) |
| Board presentation | [Rules/TowerOS_Board_Presentation.pdf](./Rules/TowerOS_Board_Presentation.pdf) |
| Design system | [design-system/DESIGN_SYSTEM.md](./design-system/DESIGN_SYSTEM.md) |
| Dev menu (optional) | [local-dev/tower.cmd](./local-dev/tower.cmd) — or `npm run dev` |
