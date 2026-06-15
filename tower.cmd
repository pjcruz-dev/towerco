@echo off
setlocal EnableExtensions
cd /d "%~dp0"

REM Direct dispatch: tower.cmd 2   or   tower.cmd restart
if not "%~1"=="" (
  call :dispatch "%~1"
  exit /b %ERRORLEVEL%
)

:menu
cls
echo.
echo  TowerOS Development Menu
echo  ========================
echo.
echo    1  Start dev stack       MySQL + API + Web
echo    2  Restart API + Web     docker compose restart api web
echo    3  Stop API + Web        Keeps MySQL/phpMyAdmin running
echo    4  MySQL CLI             Interactive SQL shell
echo    5  API logs              Laravel log ^(new window^)
echo    6  MySQL logs            Docker MySQL log ^(new window^)
echo    7  Stop Docker           Stop MySQL container
echo    8  Help / URLs
echo    0  Exit
echo.
echo  Quick URLs:  Web http://localhost   API http://127.0.0.1:8000   phpMyAdmin http://localhost:8080
echo.
set "CHOICE="
set /p CHOICE="Select option [0-8]: "

if "%CHOICE%"=="" goto menu
call :dispatch "%CHOICE%"
if errorlevel 99 goto menu
exit /b %ERRORLEVEL%

:dispatch
set "OPT=%~1"
if /I "%OPT%"=="0" exit /b 0
if /I "%OPT%"=="1" goto act_start
if /I "%OPT%"=="2" goto act_restart
if /I "%OPT%"=="3" goto act_stop_app
if /I "%OPT%"=="4" goto act_db
if /I "%OPT%"=="5" goto act_logs_api
if /I "%OPT%"=="6" goto act_logs_mysql
if /I "%OPT%"=="7" goto act_stop_docker
if /I "%OPT%"=="8" goto act_help
if /I "%OPT%"=="start" goto act_start
if /I "%OPT%"=="dev" goto act_start
if /I "%OPT%"=="restart" goto act_restart
if /I "%OPT%"=="stop" goto act_stop_app
if /I "%OPT%"=="db" goto act_db
if /I "%OPT%"=="mysql" goto act_db
if /I "%OPT%"=="logs-api" goto act_logs_api
if /I "%OPT%"=="logs" goto act_logs_api
if /I "%OPT%"=="logs-mysql" goto act_logs_mysql
if /I "%OPT%"=="stop-docker" goto act_stop_docker
if /I "%OPT%"=="help" goto act_help
echo Unknown option: %OPT%
echo Usage: tower.cmd [1-8 ^| start ^| restart ^| stop ^| db ^| logs-api ^| logs-mysql ^| stop-docker ^| help]
exit /b 1

:act_start
call "%~dp0dev.cmd"
exit /b %ERRORLEVEL%

:act_restart
if not exist ".env.docker" if exist "env.docker.example" copy /Y "env.docker.example" ".env.docker" >nul
docker compose --env-file .env.docker restart api web
exit /b %ERRORLEVEL%

:act_stop_app
if not exist ".env.docker" if exist "env.docker.example" copy /Y "env.docker.example" ".env.docker" >nul
docker compose --env-file .env.docker stop api web
echo.
pause
exit /b 99

:act_db
call "%~dp0dev-db.cmd"
echo.
pause
exit /b 99

:act_logs_api
start "TowerOS API logs" cmd /k "%~dp0dev-logs.cmd" api
echo Opened API logs in a new window.
timeout /t 2 /nobreak >nul
exit /b 99

:act_logs_mysql
start "TowerOS MySQL logs" cmd /k "%~dp0dev-logs.cmd" mysql
echo Opened MySQL logs in a new window.
timeout /t 2 /nobreak >nul
exit /b 99

:act_stop_docker
call "%~dp0dev-stop.cmd"
echo.
pause
exit /b 99

:act_help
call "%~dp0dev-help.cmd"
echo.
pause
exit /b 99
