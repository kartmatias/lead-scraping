# Deployment Runbook — Lead Scraping API

Target: shared PHP hosting or VPS with SSH access.

---

## Requirements

- PHP 8.3+ with extensions: `pdo_mysql` (or `pdo_sqlite`), `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `curl`, `fileinfo`
- Composer 2+
- MySQL 8+ or SQLite (dev only)
- A process manager: **Supervisor** (recommended) or cron-based queue runner
- `APIFY_TOKEN` from [apify.com](https://apify.com)

---

## 1. Upload files

```bash
# Clone or upload to server
git clone <repo-url> /var/www/lead-scraping
cd /var/www/lead-scraping
```

Set correct ownership (replace `www-data` with your web server user):

```bash
chown -R www-data:www-data /var/www/lead-scraping
chmod -R 755 /var/www/lead-scraping
chmod -R 775 storage bootstrap/cache
```

---

## 2. Install dependencies

```bash
composer install --no-dev --optimize-autoloader
```

---

## 3. Configure environment

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env`:

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com

# Database — use MySQL in production
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=lead_scraping
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password

# Queue — database driver works out of the box; switch to redis if available
QUEUE_CONNECTION=database

# Cache
CACHE_STORE=database

# Apify
APIFY_TOKEN=apify_api_xxxxxxxxxxxxxxxxxxxx
APIFY_VALIDATION_DISABLED=false

# Logging
LOG_CHANNEL=stack
LOG_LEVEL=warning
```

---

## 4. Database setup

```bash
# Create the MySQL database first, then:
php artisan migrate --force
```

---

## 5. Optimize for production

```bash
php artisan config:cache
php artisan route:cache
php artisan event:cache
```

> Re-run these three commands on every deploy.

---

## 6. Web server — point document root to `/public`

### Apache `.htaccess` (already included in `/public`)

Virtual host:

```apache
<VirtualHost *:80>
    ServerName yourdomain.com
    DocumentRoot /var/www/lead-scraping/public

    <Directory /var/www/lead-scraping/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

### Nginx

```nginx
server {
    listen 80;
    server_name yourdomain.com;
    root /var/www/lead-scraping/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

---

## 7. Queue worker — Supervisor (recommended)

Install Supervisor:

```bash
apt install supervisor   # Ubuntu/Debian
yum install supervisor   # CentOS
```

Create `/etc/supervisor/conf.d/lead-scraping-worker.conf`:

```ini
[program:lead-scraping-worker]
command=php /var/www/lead-scraping/artisan queue:work --sleep=3 --tries=3 --max-time=3600
directory=/var/www/lead-scraping
user=www-data
numprocs=2
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
redirect_stderr=true
stdout_logfile=/var/www/lead-scraping/storage/logs/worker.log
stdout_logfile_maxbytes=10MB
```

Activate:

```bash
supervisorctl reread
supervisorctl update
supervisorctl start lead-scraping-worker:*
```

### Alternative: cron (shared hosting without Supervisor)

Add to crontab (`crontab -e`):

```cron
* * * * * cd /var/www/lead-scraping && php artisan schedule:run >> /dev/null 2>&1
```

Then add a scheduled queue runner in `routes/console.php`:

```php
Schedule::command('queue:work --once --tries=3')->everyMinute()->withoutOverlapping();
```

> ⚠️ Cron-based workers introduce up to 1 minute latency per job and do not keep a persistent process. For production with real load, use Supervisor.

---

## 8. Verify deployment

```bash
# Check app responds
curl -s https://yourdomain.com/api/scrape-requests | head -c 100

# Submit a test CNPJ scrape
curl -s -X POST https://yourdomain.com/api/scrape-requests \
  -H "Content-Type: application/json" \
  -d '{"source":"cnpj","filters":{"cnpjs":["00000000000191"]}}' | python3 -m json.tool

# Watch logs
tail -f storage/logs/laravel.log
```

---

## 9. Deploy script (subsequent deploys)

```bash
#!/bin/bash
set -e
cd /var/www/lead-scraping

git pull origin main
composer install --no-dev --optimize-autoloader

php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan event:cache

supervisorctl restart lead-scraping-worker:*
```

---

## Troubleshooting

| Symptom | Fix |
|---------|-----|
| Jobs stuck in `pending` | Queue worker not running — check `supervisorctl status` |
| `500` on all routes | `APP_KEY` not set — run `php artisan key:generate` |
| `Permission denied` on storage | `chmod -R 775 storage bootstrap/cache` |
| DB connection refused | Check `DB_*` vars and that MySQL is running |
| Apify 404 on actor runs | Actor ID mapping issue — check `ApifyService::startActorRun` |
| Config changes not reflected | Run `php artisan config:cache` after every `.env` change |
