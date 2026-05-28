# CLAUDE.md

Lead scraping system that collects business leads from multiple sources using Apify's web scraping platform. Built with Laravel 11.

## Architecture

Queue-based system with the following flow:
1. **API Endpoint** receives scrape requests (type, filters, location)
2. **Jobs** orchestrate the scraping process:
   - `ProcessScrapeRequest` - orchestrates the scraping workflow
   - `RunApifyActor` - executes individual scraper runs
3. **ApifyService** - handles all Apify API interactions via [Guzzle](https://docs.guzzlephp.org/)
4. **Lead Model** - stores enriched lead data (upsert by source_id + source_type)

## Database Schema

| Table | Purpose |
|-------|---------|
| `scrape_requests` | Tracks scrape jobs (type, status, apify_run_id, dataset_id) |
| `leads` | Stores lead data (name, email, phone, address, cnpj, social profiles) |

## Apify Data Sources

| Source | Actor ID | Documentation |
|--------|----------|---------------|
| Google Maps | `compass/crawler-google-places` | [Apify Google Places](https://apify.com/compass/crawler-google-places) |
| Instagram | `apify/instagram-scraper` | [Apify Instagram](https://apify.com/apify/instagram-scraper) |
| LinkedIn | `dev_fusion/linkedin-profile-scraper` | [LinkedIn Scraper](https://apify.com/dev_fusion/linkedin-profile-scraper) |
| CNPJ | `parseforge/brazil-cnpj-scraper` | [Brazil CNPJ](https://apify.com/parseforge/brazil-cnpj-scraper) |

## Source Code

- PHP Laravel source: `./api`

## Development Commands

```bash
# Install dependencies
composer install --working-dir=api

# Run migrations
php artisan migrate

# Start queue worker
php artisan queue:work

# Create a scrape request via tinker
php artisan tinker --execute="App\Models\ScrapeRequest::create(['type' => 'google_maps', 'filters' => json_encode(['searchStringsArray' => ['Restaurantes em São Paulo SP'], 'maxItems' => 50]), 'status' => 'pending'])"
```

## Configuration

Environment variables (store in `api/.env`):
- `APIFY_TOKEN` - Apify API bearer token
- `QUEUE_CONNECTION` - Queue driver (sync, redis, database)
- `services.apify.ssl_verify` - Set to `false` for Windows local development

## Key Patterns

- Use webhooks over polling for long-running scrape jobs
- Implement upserts on Lead model using `source_id + source_type` as unique key
- Configure rate limiting to control Apify costs
- See [Laravel Queues](https://laravel.com/docs/queues) for job processing
- See [Apify API](https://docs.apify.com/api/v2) for API reference