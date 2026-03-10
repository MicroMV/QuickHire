@echo off
REM QuickHire Skills Migration Script for Windows
REM This script updates existing databases with skills and employment type features

echo ================================
echo QuickHire Skills Migration
echo ================================
echo.

REM Check if MySQL is available
where mysql >nul 2>nul
if %ERRORLEVEL% NEQ 0 (
    echo Error: MySQL is not installed or not in PATH
    echo Please install MySQL and add it to your system PATH
    pause
    exit /b 1
)

echo Updating database with skills features...
echo.

REM Database credentials (from config.php)
set DB_HOST=localhost
set DB_USER=root
set DB_PASS=
set DB_NAME=quick_hire

REM Run migration
echo Applying skills migration...
mysql -h %DB_HOST% -u %DB_USER% %DB_NAME% < update_database_skills.sql

if %ERRORLEVEL% NEQ 0 (
    echo Error: Failed to apply migration
    pause
    exit /b 1
)

echo.
echo ================================
echo Skills migration completed!
echo ================================
echo.
echo New features added:
echo   - Employment type field for jobseekers
echo   - Skills selection for jobseekers and employers
echo   - Enhanced matching algorithm with 80%% threshold
echo.
echo You can now use the enhanced matching features!
echo.
pause