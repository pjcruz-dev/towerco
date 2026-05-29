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

echo TowerOS — Docker dev stack
echo   Web         http://localhost:3001
echo   API         http://localhost:8000
echo   phpMyAdmin  http://localhost:8080
echo   MySQL       127.0.0.1:3307
echo.
echo   Stop: dev-stop.cmd  or  npm run dev:down
echo   Logs: npm run dev:logs
echo.

where docker >nul 2>&1
if errorlevel 1 (
  echo ERROR: Docker Desktop is required. Install Docker or use: dev.cmd host
  exit /b 1
)

call npm run dev
exit /b %ERRORLEVEL%
