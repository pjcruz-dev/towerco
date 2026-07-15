@echo off
setlocal EnableExtensions
cd /d "%~dp0"

if /I "%~1"=="help" (
  call "%~dp0dev-help.cmd"
  exit /b 0
)
if /I "%~1"=="host" (
  echo [dev] Host PHP/Node + Docker MySQL only ^(legacy^)...
  call npm run dev:host
  exit /b %ERRORLEVEL%
)
if not "%~1"=="" (
  echo Unknown option: %~1
  echo Usage: dev.cmd [help ^| host]
  echo   dev.cmd       Full stack in Docker ^(same as npm run dev^)
  echo   dev.cmd host  API/Web on host, MySQL in Docker
  exit /b 1
)

echo TowerOS — Podman/Docker dev stack ^(no web container^)
echo   API         http://localhost:8000
echo   phpMyAdmin  http://localhost:8080  ^(COMPOSE_PROFILES=tools^)
echo   MySQL       127.0.0.1:3307
echo.
echo   Web on host:  npm run dev:web   ^(http://localhost^)
echo   Or one shot:  npm run dev:hybrid
echo   Stop: dev-stop.cmd  or  npm run dev:down
echo   Logs: npm run dev:logs:api
echo.

where docker >nul 2>&1
if errorlevel 1 (
  echo ERROR: Docker Desktop is required. Install Docker or use: dev.cmd host
  exit /b 1
)

call npm run dev
exit /b %ERRORLEVEL%
