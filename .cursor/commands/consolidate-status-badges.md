# Adopt the shared StatusBadge (one-time task)

Goal: replace hand-written status colour maps in the active modules (Ticketing, E-Forms, DocExtract, Document Control) with one shared implementation of `docs/design-system/DESIGN_SYSTEM.md` section 17.1. Do NOT add new tokens or new colours.

Facts already established (verify before editing):
- DESIGN_SYSTEM.md 16.2 and 17 prescribe the palette (success emerald, warning amber, danger red, info sky, neutral muted). The app mostly follows it, but every module re-implements the map by hand (`components/ticketing/ticketing-badges.tsx`, billing, module-list exports).
- The shared files are `frontend/components/ui/status-tone.ts` and `frontend/components/ui/status-badge.tsx`. Final versions are in the config package under `proposals/status-badges/`, typechecked against the real `Badge`. If they are not in the repo, ask me to add them instead of rewriting them.
- The `destructive` Badge variant is about 4.1:1 in light mode (below AA). Statuses use tone `danger` instead.

Steps:
1. Confirm both shared files exist. Read `badge.tsx` and `ticketing-badges.tsx`.
2. Replace `ticketing-badges.tsx` with the proposal version. Keep the exported names and props (`TicketingStatusBadge`, `TicketingPriorityBadge`).
3. Grep the active modules for other maps (`statusStyles|statusColor|STATUS_|statusClass`). Migrate them one file at a time, choosing a tone per state. Ask me before touching billing, module-list exports or any out-of-scope module.
4. Verify: `cd frontend; npm run lint; npm run typecheck; npm run test:frontend`, then from the repo root `node scripts/ui-drift.mjs`. Drift must not increase. Do NOT run `--update`.
5. Report every visible change. Expect: rectangle chips become pills with a border, "open" moves from blue to sky, "high" priority moves from orange to amber (same as "in progress"), "closed" moves from slate to the muted neutral, "resolved" moves from green to emerald.
