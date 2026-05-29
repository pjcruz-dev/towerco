@echo off
setlocal
cd /d "%~dp0"

if not exist ".env.docker" (
  if exist "env.docker.example" copy /Y "env.docker.example" ".env.docker" >nul
)

set "TARGET=%~1"
if "%TARGET%"=="" set "TARGET=api"
if /I "%TARGET%"=="help" goto :help
if /I "%TARGET%"=="-h" goto :help
if /I "%TARGET%"=="?" goto :help
if /I "%TARGET%"=="mysql" goto :mysql
if /I "%TARGET%"=="docker" goto :mysql
if /I "%TARGET%"=="api" goto :api
if /I "%TARGET%"=="laravel" goto :api
if /I "%TARGET%"=="all" goto :all

echo Unknown target: %TARGET%
goto :help

:help
echo TowerOS dev logs
echo.
echo   dev-logs.cmd           Laravel API log ^(default — use in terminal 2^)
echo   dev-logs.cmd api       Same as above
echo   dev-logs.cmd mysql     MySQL container logs ^(use when MySQL won't start^)
echo   dev-logs.cmd all       MySQL + Laravel in one window
echo.
echo   dev-logs.cmd mysql 100 Last 100 MySQL lines ^(no follow^)
echo   dev-logs.cmd api 200   Last 200 Laravel lines
echo.
exit /b 0

:mysql
set "TAIL=%~2"
if "%TAIL%"=="" (
  echo [mysql] Following toweros-mysql logs ^(Ctrl+C to exit^)...
  docker compose --env-file .env.docker logs -f mysql
) else (
  echo [mysql] Last %TAIL% lines:
  docker compose --env-file .env.docker logs --tail %TAIL% mysql
)
exit /b %ERRORLEVEL%

:api
docker ps --filter "name=toweros-api" --format "{{.Names}}" 2>nul | findstr /I "toweros-api" >nul
if not errorlevel 1 (
  set "TAIL=%~2"
  if "%TAIL%"=="" (
    echo [api] Following toweros-api container logs ^(Ctrl+C to exit^)...
    docker compose --env-file .env.docker logs -f api
  ) else (
    echo [api] Last %TAIL% lines:
    docker compose --env-file .env.docker logs --tail %TAIL% api
  )
  exit /b %ERRORLEVEL%
)
set "TAIL=%~2"
if "%TAIL%"=="" set "TAIL=80"
set "LOG=backend\storage\logs\laravel.log"
if not exist "%LOG%" (
  echo [api] No log file yet: %LOG%
  echo       Start the stack ^(npm run dev^) or use dev.cmd host, then try again.
  exit /b 1
)
if "%~2"=="" (
  echo [api] Following %LOG% ^(host mode, Ctrl+C to exit^)...
  powershell -NoProfile -Command "Get-Content -LiteralPath '%LOG%' -Wait -Tail %TAIL%"
) else (
  echo [api] Last %TAIL% lines of %LOG%:
  powershell -NoProfile -Command "Get-Content -LiteralPath '%LOG%' -Tail %TAIL%"
)
exit /b %ERRORLEVEL%

:all
where npm >nul 2>&1
if errorlevel 1 (
  echo npm not found. Use two terminals: dev-logs.cmd mysql  and  dev-logs.cmd api
  exit /b 1
)
call npm run dev:logs
exit /b %ERRORLEVEL%
