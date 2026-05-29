@echo off
setlocal
cd /d "%~dp0"

if not exist ".env.docker" (
  if exist "env.docker.example" copy /Y "env.docker.example" ".env.docker" >nul
)

echo TowerOS MySQL connection
echo   Host:        127.0.0.1
echo   Port:        3307
echo   Database:    toweros
echo   User:        root
echo   Password:    toweros
echo   Web UI:      http://localhost:8080  ^(phpMyAdmin^)
echo.
echo Desktop clients: DBeaver, HeidiSQL, TablePlus, MySQL Workbench
echo.

docker ps --filter "name=toweros-mysql" --format "{{.Status}}" 2>nul | findstr /I "Up" >nul
if errorlevel 1 (
  echo [error] toweros-mysql is not running. Start with: dev.cmd
  exit /b 1
)

if /I "%~1"=="info" exit /b 0

echo Opening MySQL CLI inside Docker ^(type exit to leave^)...
docker exec -it toweros-mysql mysql -u root -ptoweros toweros
