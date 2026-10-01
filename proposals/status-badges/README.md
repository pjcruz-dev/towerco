# Shared status badge (drop-in)

Copy the three files, keeping the paths, into the repo:

- `frontend/components/ui/status-tone.ts` (new)
- `frontend/components/ui/status-badge.tsx` (new)
- `frontend/components/ticketing/ticketing-badges.tsx` (replaces the current file; same exports)

They implement `docs/design-system/DESIGN_SYSTEM.md` section 17.1 once. They typecheck against a copy of the real `components/ui/badge.tsx` (TypeScript strict). Then run `cd frontend; npm run lint; npm run typecheck; npm run test:frontend` and look at the ticketing list in light and dark.

Visible changes in Ticketing: chips become pills with a border (the shared Badge shape), open moves from blue to sky, high priority moves from orange to amber, closed moves from slate to the muted neutral, resolved moves from green to emerald.

If your repo already has files with these names, stop and compare first.
