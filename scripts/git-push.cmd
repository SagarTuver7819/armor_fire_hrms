@echo off
REM Permanent project push helper — use if Cursor Sync shows a false "From https://" error.
REM Push itself is fine; this script only pushes main without fetching tags.
cd /d "%~dp0.."
git push origin HEAD:main
if errorlevel 1 (
  echo PUSH FAILED
  exit /b 1
)
echo PUSH OK
git status -sb
exit /b 0
