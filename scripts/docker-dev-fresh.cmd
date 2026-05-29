@echo off

setlocal EnableExtensions

cd /d "%~dp0\.."



echo.

echo TowerOS DEV FRESH RESET

echo ======================

echo This will DELETE the MySQL Docker volume (all tenants + central data).

echo After reset, db:seed restores:

echo   - Platform superadmin (superadmin@toweros.local)

echo   - Published playbooks v1 + v2

echo   - Policy bundles: towerco-default + towerco-full-gate-approval

echo   - Helper center operational acronyms (TowerOS defaults)

echo   - Passport personal access client

echo.

echo Custom policy bundles from the platform UI are NOT restored.

echo.

set /p CONFIRM="Type the word FRESH (all caps) and press Enter: "

if /I not "%CONFIRM%"=="FRESH" (

  echo.

  echo Aborted. You typed: "%CONFIRM%"

  echo Run again and type exactly: FRESH

  exit /b 1

)



echo.

echo [1/6] Stopping stack and removing MySQL volume...

docker compose --env-file .env.docker down -v

if errorlevel 1 exit /b 1



echo [2/6] Starting MySQL...

docker compose --env-file .env.docker up -d mysql

if errorlevel 1 exit /b 1



echo [3/6] Waiting for MySQL...

docker compose --env-file .env.docker up -d --wait mysql

if errorlevel 1 exit /b 1



echo [4/6] MySQL grants for tenant DB provisioning...

call "%~dp0docker-mysql-grants.cmd"

if errorlevel 1 exit /b 1



echo [5/6] Running central migrations + seed (one-off, no API server yet)...

docker compose --env-file .env.docker run --rm --no-deps --entrypoint php api artisan migrate --force --no-interaction

if errorlevel 1 exit /b 1

docker compose --env-file .env.docker run --rm --no-deps --entrypoint php api artisan db:seed --force --no-interaction

if errorlevel 1 exit /b 1



echo [6/6] Starting full stack...

docker compose --env-file .env.docker up -d --build

if errorlevel 1 exit /b 1



echo.

echo Done.
echo.
echo IMPORTANT: Log in again after fresh reset (old browser tokens are invalid).
echo   http://localhost:3001/platform/login
echo   Email:    superadmin@toweros.local
echo   Password: 123123123
echo   Create tenant: http://localhost:3001/platform/tenants/create

echo.

exit /b 0

