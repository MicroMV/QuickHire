#!/bin/bash
# QuickHire Skills Migration Script for Linux/Mac
# This script updates existing databases with skills and employment type features

echo "================================"
echo "QuickHire Skills Migration"
echo "================================"
echo

# Check if MySQL is available
if ! command -v mysql &> /dev/null; then
    echo "Error: MySQL is not installed or not in PATH"
    echo "Please install MySQL first"
    exit 1
fi

echo "Updating database with skills features..."
echo

# Database credentials (from config.php)
DB_HOST="localhost"
DB_USER="root"
DB_PASS=""
DB_NAME="quick_hire"

# Run migration
echo "Applying skills migration..."
mysql -h "$DB_HOST" -u "$DB_USER" "$DB_NAME" < update_database_skills.sql

if [ $? -ne 0 ]; then
    echo "Error: Failed to apply migration"
    exit 1
fi

echo
echo "================================"
echo "Skills migration completed!"
echo "================================"
echo
echo "New features added:"
echo "  - Employment type field for jobseekers"
echo "  - Skills selection for jobseekers and employers"
echo "  - Enhanced matching algorithm with 80% threshold"
echo
echo "You can now use the enhanced matching features!"
echo