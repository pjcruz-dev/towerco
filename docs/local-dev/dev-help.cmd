@echo off
echo.
echo TowerOS local development
echo =========================
echo.
echo Preferred ^(cross-platform^)
echo   npm run dev              MySQL + API + Soketi
echo   npm run dev:web          Next.js on host ^(http://localhost^)
echo   npm run dev:hybrid       Core + web
echo   npm run dev:down         Stop stack
echo   npm run dev:logs:api     API logs
echo.
echo Optional Windows menu
echo   docs\local-dev\tower.cmd           Interactive menu
echo   docs\local-dev\tower.cmd 2         Jump ^(1=start, 2=restart, ...^)
echo   docs\local-dev\dev.cmd             Same as npm run dev
echo   docs\local-dev\dev-db.cmd          MySQL CLI
echo   docs\local-dev\dev-logs.cmd api    API logs
echo   docs\local-dev\dev-stop.cmd        Stop Docker helpers
echo.
echo URLs ^(browser^)
echo   Web         http://localhost
echo   API         http://127.0.0.1:8000
echo   phpMyAdmin  http://localhost:8080  ^(root / toweros^)
echo.
echo Database
echo   MySQL host  127.0.0.1:3307   DB toweros   User root   Pass toweros
echo.
echo Demo data ^(Alliance tenant^)
echo   cd backend ^&^& php artisan tenants:seed-demo --billing
echo   Logins: admin/manager/project.lead/ops.viewer @alliance.localhost  password: password
echo   URL: http://alliance.localhost/login
echo.
