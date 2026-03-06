@echo off
REM QuickHire Database Setup Script for Windows
REM This script helps initialize the QuickHire database

echo ================================
echo QuickHire Database Setup
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

echo Checking MySQL connection...
echo.

REM Database credentials (from config.php)
set DB_HOST=localhost
set DB_USER=root
set DB_PASS=
set DB_NAME=quick_hire

REM Create database and import schema
echo Creating database '%DB_NAME%'...
mysql -h %DB_HOST% -u %DB_USER% -e "CREATE DATABASE IF NOT EXISTS %DB_NAME% CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

if %ERRORLEVEL% NEQ 0 (
    echo Error: Failed to create database
    pause
    exit /b 1
)

echo Importing database schema...
mysql -h %DB_HOST% -u %DB_USER% %DB_NAME% < database_schema.sql

if %ERRORLEVEL% NEQ 0 (
    echo Error: Failed to import schema
    pause
    exit /b 1
)

echo.
echo ================================
echo Database setup completed!
echo ================================
echo.
echo Database Information:
echo   Host: %DB_HOST%
echo   Database: %DB_NAME%
echo   User: %DB_USER%
echo.
echo You can now start using QuickHire!
echo.
pause
