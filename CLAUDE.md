# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Lead scraping system that collects business leads from multiple sources using Apify's web scraping platform. Built with Laravel framework.

## Architecture

The system follows a queue-based architecture:

1. **API Endpoint** receives scrape requests (type, filters, location)
2. **Jobs** orchestrate the scraping process:
   - `ProcessScrapeRequest` - orchestrates the scraping workflow
   - `RunApifyActor` - executes individual scraper runs
3. **ApifyService** - handles all Apify API interactions via Guzzle HTTP client
4. **Lead Model** - stores enriched lead data (upsert by source_id + source_type)

### Database Schema

- **scrape_requests**: Tracks scrape jobs (type, status, apify_run_id, dataset_id)
- **leads**: Stores lead data (name, email, phone, address, cnpj, social profiles)

### Apify Actors (Data Sources)

| Source | Actor ID | Purpose |
|--------|----------|---------|
| Google Maps | `compass/crawler-google-places` | Local business leads by city/segment |
| Instagram | `apify/instagram-scraper` | Profile data by hashtag/location |
| LinkedIn | `dev_fusion/linkedin-profile-scraper` | Decision maker profiles |
| CNPJ | `parseforge/brazil-cnpj-scraper` | Business enrichment by CNPJ |

## Source Code
 -  The php laravel source code is located on : `./api`

## Development Commands

```bash
# Install dependencies
composer install

# Run migrations
php artisan migrate

# Start queue worker
php artisan queue:work

# Run scheduler (for polling jobs)
php artisan schedule:work

# Create a scrape request via tinker
php artisan tinker --execute="App\Models\ScrapeRequest::create(['type' => 'google_maps', 'filters' => json_encode(['searchStringsArray' => ['Restaurantes em São Paulo SP'], 'maxItems' => 50]), 'status' => 'pending'])"
```

## Configuration

Required environment variables:
- `APIFY_TOKEN` - Apify API bearer token

## Key Patterns

- Use webhooks over polling for long-running scrape jobs
- Store tokens in `.env` using `vlucas/phpdotenv`
- Use Laravel's queue system with Redis for scalability
- Implement upserts on Lead model using source_id + source_type as unique key
- Configure rate limiting to control Apify costs