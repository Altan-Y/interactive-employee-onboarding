@echo off
setlocal
cd /d "%~dp0"
echo Starting the WordPress onboarding demo...
docker compose up -d --build
if errorlevel 1 (
  echo.
  echo Docker could not start the project. Make sure Docker Desktop is running.
  pause
  exit /b 1
)
echo.
echo Waiting for WordPress setup. This can take a minute on the first start...
for /L %%i in (1,1,60) do (
  curl.exe --silent --fail http://localhost:8081/access/ >nul 2>&1 && goto ready
  timeout /t 2 /nobreak >nul
)
:ready
start "" http://localhost:8081/access/
echo.
echo Demo URL: http://localhost:8081/access/
echo Employee password: demo123
echo Guest password: guest123
echo WordPress admin: http://localhost:8081/wp-admin/
echo Admin login: demo_admin / demo_admin
pause
