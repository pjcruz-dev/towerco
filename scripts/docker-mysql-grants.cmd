@echo off
setlocal
cd /d "%~dp0\.."

if not exist ".env.docker" if exist "env.docker.example" copy /Y "env.docker.example" ".env.docker" >nul

echo Applying MySQL grants for tenant database provisioning...
docker compose --env-file .env.docker exec -T mysql mysql -uroot -ptoweros < "docker\mysql\init\01-tenant-grants.sql"
if errorlevel 1 (
  echo Failed. Is MySQL running? Try: docker compose --env-file .env.docker up -d mysql
  exit /b 1
)
echo Done. Re-run: docker compose exec api php artisan toweros:repair-tenant-databases --create
exit /b 0
