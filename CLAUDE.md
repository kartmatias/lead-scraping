# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Queue-based lead scraping system that collects business data from multiple sources via Apify, normalizes it into `leads`, and links records across sources into enriched `canonical_entities`.

## Architecture

```
POST /api/scrape-requests
  → RunApifyActor (job)        — starts Apify actor run
  → ProcessScrapeRequest (job) — polls until SUCCEEDED, upserts leads
  → LinkLeadsJob (job)         — links leads to canonical entities
```

### Key Files

| File | Purpose |
|------|---------|
| `app/Services/ApifyService.php` | Guzzle wrapper for Apify API |
| `app/Jobs/RunApifyActor.php` | Starts actor run, dispatches ProcessScrapeRequest |
| `app/Jobs/ProcessScrapeRequest.php` | Polls run status, fetches dataset, upserts leads, dispatches LinkLeadsJob |
| `app/Jobs/LinkLeadsJob.php` | Matches leads to canonical entities across sources |
| `app/Models/Lead.php` | `upsertFromScrapeData()` with per-source field mapping |
| `app/Models/CanonicalEntity.php` | Enriched entity merged from multiple leads |
| `app/Models/LeadLink.php` | Pivot: lead → canonical entity with match_method + match_score |
| `app/Providers/ApifyActorRegistry.php` | Actor list (uses `~` separator) |
| `app/Providers/ApifyActorValidationServiceProvider.php` | Boot-time actor validation (skip with `APIFY_VALIDATION_DISABLED=true`) |
| `routes/api.php` | REST endpoints for scrape-requests and leads |

**scrape_requests**: `id`, `source` (enum), `status` (pending/running/completed/failed/cancelled), `filters` (JSON), `apify_run_id`, `apify_dataset_id`, `total_leads`, `completed_leads`, `failed_leads`, `started_at`, `completed_at`, `error_message`

**leads**: `id`, `scrape_request_id`, `source_type`, `source_id`, `name`, `email`, `phone`, `company`, `position`, `address`, `cnpj`, `website`, `instagram`, `linkedin`, `facebook`, `raw_data` (JSON)
UNIQUE: `(source_type, source_id)`

**canonical_entities**: `id`, `name`, `cnpj`, `phone`, `email`, `website`, `address`, `instagram_url`, `linkedin_url`

**lead_links**: `id`, `canonical_entity_id`, `lead_id`, `match_method` (phone/website/cnpj/email/name_fuzzy/new), `match_score` (0-100)
UNIQUE: `lead_id`

## Apify Actors

| Source | Actor ID | Input example |
|--------|----------|---------------|
| `google_maps` | `compass/crawler-google-places` | `{"searchStringsArray":["restaurants in São Paulo"],"maxItems":10}` |
| `instagram` | `apify/instagram-scraper` | `{"directUrls":["https://www.instagram.com/user/"],"resultsLimit":10}` |
| `linkedin` | `dev_fusion/linkedin-profile-scraper` | requires Apify account full-permission approval |
| `cnpj` | `parseforge/brazil-cnpj-scraper` | `{"cnpjs":["00000000000191"]}` — field is `cnpjs` (plural) |

**Important:** actor IDs use `/` in `RunApifyActor::getActorId()` — converted to `~` before calling the API in `ApifyService` (`str_replace('/', '~', $actorId)`).

## API Endpoints

```
POST   /api/scrape-requests          { source, filters }
GET    /api/scrape-requests
GET    /api/scrape-requests/{id}
GET    /api/scrape-requests/{id}/status
POST   /api/scrape-requests/{id}/cancel
DELETE /api/scrape-requests/{id}

GET    /api/leads
GET    /api/leads/stats
GET    /api/leads/export
GET    /api/leads/{id}
DELETE /api/leads/{id}
```

## Development Commands

```bash
# Install PHP dependencies
composer install

# Install Node.js dependencies (if using frontend assets)
npm install

# Copy environment file and generate key
cp .env.example .env
php artisan key:generate

# Run database migrations
php artisan migrate

# Start the development server
php artisan serve

# Start the queue worker (process jobs)
php artisan queue:work

# Process a single job (for testing)
php artisan queue:work --once

# Run all tests
php artisan test

# Run tests for a specific suite (Unit, Feature, Integration)
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature
php artisan test --testsuite=Integration

# Run a single test class or method
php artisan test --filter=TestClassName
php artisan test --filter=TestClassName::testMethod

# Run tests with coverage (if configured)
php artisan test --coverage

# Lint PHP files with Laravel Pint
vendor/bin/pint

# Check for linting issues without fixing
vendor/bin/pint --test

# Start Vite development server (for frontend assets during development)
npm run dev

# Build frontend assets for production
npm run build
```

## Known Issues / Constraints

- **LinkedIn** — `dev_fusion/linkedin-profile-scraper` requires full-permission approval on Apify; returns 403 until approved.
- **No authentication** on API routes.
- **Polling only** — no webhook support implemented yet.
- **Apify actor validation** fails during `artisan` commands if `APIFY_TOKEN` is not set or actors are inaccessible. Set `APIFY_VALIDATION_DISABLED=true` in your environment to skip validation for local development.

## Lead Linking Logic (`LinkLeadsJob`)

Matches each new lead to an existing `CanonicalEntity` by priority:
1. CNPJ (exact)
2. Phone (normalized to E.164 `+55DDNNNNNNNNN`)
3. Email (exact)
4. Website (normalized: strip https/www, trailing slash)
5. Name fuzzy (`similar_text` ≥ 85%, ASCII-normalized)
6. No match → creates new entity

After matching, `CanonicalEntity::mergeFromLead()` fills any null fields from the new lead.

## Environment Variables

```
APIFY_TOKEN=                  # required
QUEUE_CONNECTION=database     # default; use redis for production
DB_CONNECTION=sqlite          # dev default
APIFY_VALIDATION_DISABLED=    # set to true to skip boot-time actor validation
```