@echo off
setlocal
cd /d "%~dp0\.."

if not exist ".env.docker" if exist "env.docker.example" copy /Y "env.docker.example" ".env.docker" >nul

echo Applying MySQL grants for tenant database provisioning...
node scripts\compose-run.js --env-file .env.docker exec -T mysql mysql -uroot -ptoweros < "docker\mysql\init\01-tenant-grants.sql"
if errorlevel 1 (
  echo Failed. Is MySQL running? Try: npm run dev:docker:infra
  exit /b 1
)
echo Done. Re-run: node scripts\compose-run.js --env-file .env.docker exec api php artisan toweros:repair-tenant-databases --create
exit /b 0
