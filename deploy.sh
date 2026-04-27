#!/bin/bash

echo "Starting deployment..."

# Create database directory if it doesn't exist
mkdir -p database

# Create SQLite database file if it doesn't exist
touch database/database.sqlite

# Set proper permissions for storage and bootstrap/cache
chmod -R 775 storage bootstrap/cache

# Clear any cached config
php artisan config:clear
php artisan cache:clear

# Run migrations
php artisan migrate --force

# Cache configuration
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "Deployment complete!"
