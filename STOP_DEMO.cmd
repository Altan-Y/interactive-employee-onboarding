@echo off
cd /d "%~dp0"
docker compose down
echo The demo containers have been stopped. Your local database remains saved.
pause
