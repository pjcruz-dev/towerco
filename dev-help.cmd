@echo off
echo.
echo TowerOS local development
echo =========================
echo.
echo Primary launcher
echo   tower.cmd              Interactive menu ^(recommended^)
echo   tower.cmd 2            Jump directly ^(1=start, 2=restart, 3=stop, 4=db, ...^)
echo.
echo Daily workflow
echo   Terminal 1:  npm run dev           Full Docker stack ^(API + Web + MySQL^)
echo                dev.cmd              Same as npm run dev
echo   After changes: tower.cmd ^> 2       Restart api + web containers
echo   Terminal 2:  npm run dev:logs:api  or  dev-logs.cmd api
echo.
echo Menu options
echo   1  Start dev stack       dev.cmd / npm run dev
echo   2  Restart API + Web     docker compose restart api web
echo   3  Stop API + Web        docker compose stop api web
echo   4  MySQL CLI             dev-db.cmd
echo   5  API logs              dev-logs.cmd api
echo   6  MySQL logs            dev-logs.cmd mysql
echo   7  Stop Docker           dev-stop.cmd
echo   8  Help                  this screen
echo.
echo URLs ^(browser^)
echo   Web         http://localhost:3001
echo   API         http://127.0.0.1:8000
echo   phpMyAdmin  http://localhost:8080   MySQL web UI ^(root / toweros^)
echo.
echo Database
echo   MySQL host  127.0.0.1:3307   DB toweros   User root   Pass toweros
echo   Web UI      http://localhost:8080
echo   Quick SQL:  dev-db.cmd  or  tower.cmd ^> 4
echo   Desktop:     DBeaver, HeidiSQL, TablePlus, MySQL Workbench
echo.
echo Logs
echo   dev-logs.cmd api       API container ^(or laravel.log in host mode^)
echo   dev-logs.cmd mysql     MySQL container
echo   npm run dev:logs       All services
echo.
echo MySQL will not start
echo   1. dev-logs.cmd mysql  ^(or tower.cmd ^> 6^)
echo   2. Check port 3307 free or change TOWEROS_MYSQL_PORT in .env.docker
echo.
echo Other
echo   dev.cmd host          PHP/Node on host, MySQL in Docker only
echo   npm run dev:seed      Platform super-admin ^(once^)
echo.
echo Demo data (Alliance tenant)
echo   cd backend ^&^& php artisan tenants:seed-demo --billing
echo   Logins: admin/manager/project.lead/ops.viewer @alliance.localhost  password: password
echo   URL: http://alliance.localhost:3001/login
echo.
