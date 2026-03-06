#!/bin/bash
# QuickHire Database Setup Script
# This script helps initialize the QuickHire database

echo "================================"
echo "QuickHire Database Setup"
echo "================================"
echo ""

# Check if MySQL is available
if ! command -v mysql &> /dev/null; then
    echo "❌ MySQL is not installed or not in PATH"
    echo "Please install MySQL and try again"
    exit 1
fi

echo "📦 Setting up QuickHire database..."
echo ""

# Database credentials (from config.php)
DB_HOST="localhost"
DB_USER="root"
DB_PASS=""
DB_NAME="quick_hire"

# Check if database exists
echo "🔍 Checking if database exists..."
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" -e "USE $DB_NAME;" 2>/dev/null

if [ $? -eq 0 ]; then
    echo "⚠️  Database '$DB_NAME' already exists"
    read -p "Do you want to drop and recreate it? (y/n) " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        echo "🗑️  Dropping existing database..."
        mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" -e "DROP DATABASE $DB_NAME;"
    else
        echo "✅ Keeping existing database"
        exit 0
    fi
fi

# Create database
echo "📝 Creating database '$DB_NAME'..."
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" -e "CREATE DATABASE $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

if [ $? -ne 0 ]; then
    echo "❌ Failed to create database"
    exit 1
fi

# Import schema
echo "📥 Importing database schema..."
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < database_schema.sql

if [ $? -ne 0 ]; then
    echo "❌ Failed to import schema"
    exit 1
fi

echo ""
echo "✅ Database setup completed successfully!"
echo ""
echo "📊 Database Information:"
echo "   Host: $DB_HOST"
echo "   Database: $DB_NAME"
echo "   User: $DB_USER"
echo ""
echo "🚀 You can now start using QuickHire!"
echo ""
