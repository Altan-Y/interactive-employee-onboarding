@echo off
cd /d "%~dp0"
echo This removes the local demo database and WordPress installation.
choice /M "Continue"
if errorlevel 2 exit /b 0
docker compose down -v
echo The demo has been reset. Run START_HERE.cmd to create a fresh installation.
pause
