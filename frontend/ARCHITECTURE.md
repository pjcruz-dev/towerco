# TowerOS Frontend Architecture (Next.js App Router)

## Folder Structure

```text
frontend/
├── app/
│   ├── (public)/
│   │   └── login/
│   │       └── page.tsx
│   ├── (platform)/
│   │   ├── dashboard/
│   │   │   └── page.tsx
│   │   ├── gis/
│   │   │   └── page.tsx
│   │   └── layout.tsx
│   ├── globals.css
│   ├── layout.tsx
│   └── page.tsx
├── components/
│   ├── data/
│   │   └── app-table.tsx
│   ├── feedback/
│   │   └── notification-center.tsx
│   ├── forms/
│   │   └── form-input.tsx
│   ├── layout/
│   │   ├── app-shell.tsx
│   │   ├── permission-gate.tsx
│   │   └── platform-guard.tsx
│   ├── modals/
│   │   └── confirm-modal.tsx
│   ├── navigation/
│   │   ├── nav-config.ts
│   │   └── tenant-switcher.tsx
│   ├── providers/
│   │   └── app-providers.tsx
│   ├── theme/
│   │   └── theme-toggle.tsx
│   ├── ui/
│   │   └── button.tsx
│   └── widgets/
│       └── widget-grid.tsx
├── hooks/
│   └── use-permission.ts
├── lib/
│   ├── auth/
│   │   └── session-cookie.ts
│   ├── api/
│   │   ├── client.ts
│   │   └── modules/
│   │       └── auth-api.ts
│   ├── query/
│   │   └── query-client.ts
│   ├── rbac/
│   │   └── permissions.ts
│   ├── socket/
│   │   └── socket-client.ts
│   ├── theme/
│   │   └── theme-provider.tsx
│   └── utils.ts
├── modules/
│   └── identity/
│       └── auth-normalizer.ts
├── stores/
│   ├── auth-store.ts
│   └── notification-store.ts
├── types/
│   ├── auth.ts
│   ├── navigation.ts
│   └── ui.ts
└── middleware.ts
```

## Architecture Decisions

- App Router route groups split public and platform concerns.
- Middleware handles authentication-aware routing at edge level.
- Permission-aware navigation is filtered by RBAC capability strings.
- API integration uses Axios with interceptors and typed module clients.
- React Query handles server-state; Zustand handles client-state.
- Dashboard shell and shared UI components are reusable across modules.
- GIS pages are scaffolded as map-ready surfaces with filter panels and real-time hooks.
- Theme system uses `next-themes` with shadcn-compatible tokens.
- Notification, modal, table, and widget layers are isolated for reuse.
