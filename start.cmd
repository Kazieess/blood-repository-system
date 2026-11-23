@echo off
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\backend.ps1" -Action serve %*
exit /b %errorlevel%
