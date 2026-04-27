# Railway Deployment Guide for Laravel

## Quick Deploy to Railway

This Laravel application is configured for automatic deployment on Railway.

### Prerequisites
- GitHub repository connected to Railway
- Railway account

### Environment Variables (Set in Railway Dashboard)

```env
APP_NAME="Laravel"
APP_ENV=production
APP_KEY=base64:YOUR_APP_KEY_HERE
APP_DEBUG=false
APP_URL=https://your-app.railway.app

DB_CONNECTION=sqlite
# Or use Railway PostgreSQL:
# DB_CONNECTION=pgsql
# DB_HOST=${PGHOST}
# DB_PORT=${PGPORT}
# DB_DATABASE=${PGDATABASE}
# DB_USERNAME=${PGUSER}
# DB_PASSWORD=${PGPASSWORD}

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

LOG_CHANNEL=stack
LOG_LEVEL=error
```

### Deployment Steps

1. **Push to GitHub**
   ```bash
   git add .
   git commit -m "Configure for Railway deployment"
   git push origin main
   ```

2. **Configure Railway**
   - Go to your Railway project dashboard
   - Connect your GitHub repository
   - Railway will automatically detect the Laravel app
   - Add environment variables (see above)
   - Generate APP_KEY: Run `php artisan key:generate --show` locally and copy the key

3. **Initial Deployment**
   - Railway will automatically build and deploy
   - The build process runs: composer install, npm build, and caches config/routes/views
   - Database migrations run automatically on deployment

4. **Optional: Add PostgreSQL Database**
   - In Railway dashboard, click "New" → "Database" → "PostgreSQL"
   - Railway will automatically inject database environment variables
   - Update your environment variables to use PostgreSQL (see above)

### Files Created for Railway

- `Procfile` - Defines how to run the web server
- `nixpacks.toml` - Nixpacks build configuration
- `railway.json` - Railway deployment configuration
- `deploy.sh` - Deployment script (for reference)

### API Endpoint

Your application has the following endpoint:
- POST `/exercise-1-artwork-version` - Submit artwork for approval

### Troubleshooting

1. **500 Error**: Check APP_KEY is set in environment variables
2. **Database Error**: Ensure database migrations ran successfully
3. **Build Fails**: Check Railway build logs for specific error messages

### Local Development

```bash
# Install dependencies
composer install
npm install

# Setup environment
cp .env.example .env
php artisan key:generate

# Create database
touch database/database.sqlite

# Run migrations
php artisan migrate

# Build assets
npm run build

# Start server
php artisan serve
```

### Support

For issues, check:
- Railway build logs
- Application logs in Railway dashboard
- Ensure all environment variables are set correctly
