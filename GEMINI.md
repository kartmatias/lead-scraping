# GEMINI.md

This file provides context, architectural constraints, and specific development guidelines for Gemini Code Assist when working with this Lead Scraping codebase.

## 1. Project Intent & Persona
You are an expert Backend Architect and Senior Laravel Engineer helping build and maintain a highly scalable, queue-driven lead scraping and enrichment system. 

The project focuses on collecting B2B and B2C business leads from multiple public sources via the **Apify platform**, managing them inside a **Laravel** backend, enriching them using AI models (like Claude or Ollama), and exposing them as an add-on service for a CRM platform.

---

## 2. Technical Stack & Context
- **Backend Framework:** Laravel (located in the root directory, with integration tests in the `./api` directory).
- **Architecture Pattern:** Queue-based asynchronous processing using Redis and Laravel jobs/workers.
- **Primary Database:** PostgreSQL 16 (with deep `JSONB` support for schema-less raw data) in production; SQLite for local development.
- **Cache & Queue Driver:** Redis (production), database or sync (development).
- **HTTP Client:** Guzzle HTTP Client for API interactions.
- **Core Integrations:**
  - **Apify API:** Core engine for data collection.
  - **Claude API / Ollama:** Used for structured JSON extraction, ICP scoring (1-10), and text categorization.
  - **ZeroBounce / NeverBounce:** Email validation providers.

---

## 3. Directory Structure
When generating code, creating new classes, or extending components, adhere strictly to this Laravel architectural structure (relative to the root directory):
```text
app/
├── Enrichers/
│     ├── CnpjEnricher.php         # Handles corporate validation & QSA additions
│     └── GoogleMapsEnricher.php   # Handles post-processing for Maps data
├── Http/
│     ├── Controllers/
│     │     ├── LeadController.php
│     │     └── ScrapeRequestController.php
│     └── Resources/
│           └── LeadResource.php
├── Jobs/
│     ├── LinkLeadsJob.php         # Matches leads to canonical entities across sources
│     ├── ProcessScrapeRequest.php # Polls Apify run status, fetches dataset, upserts leads, dispatches LinkLeadsJob
│     └── RunApifyActor.php        # Triggers actor runs, dispatches ProcessScrapeRequest
├── Models/
│     ├── CanonicalEntity.php      # Enriched entity merged from multiple leads
│     ├── Lead.php                # Stores enriched lead data (upserted by source_id + source_type)
│     ├── LeadLink.php            # Pivot linking lead to canonical entity
│     ├── ScrapeRequest.php       # Tracks scrape requests (status, run_id, dataset_id, progress)
│     └── User.php                # Default Laravel user model
├── Providers/
│     ├── AppServiceProvider.php
│     ├── ApifyActorRegistry.php   # Actor list (uses `~` separator internally)
│     └── ApifyActorValidationServiceProvider.php # Validates all actors at boot
api/
└── tests/
      └── Integration/
            └── ApifyServiceIntegrationTest.php # Apify service integration tests
```

---

## 4. Database Schema
- **scrape_requests**:
  - `id` (unsigned big integer, PK)
  - `source` (enum: `google_maps`, `instagram`, `linkedin`, `cnpj`)
  - `status` (enum: `pending`, `running`, `completed`, `failed`, `cancelled`)
  - `filters` (JSON, nullable)
  - `apify_run_id` (string, nullable)
  - `apify_dataset_id` (string, nullable)
  - `total_leads` (unsigned integer, default 0)
  - `completed_leads` (unsigned integer, default 0)
  - `failed_leads` (unsigned integer, default 0)
  - `started_at` (timestamp, nullable)
  - `completed_at` (timestamp, nullable)
  - `error_message` (text, nullable)
  - `timestamps`
- **leads**:
  - `id` (unsigned big integer, PK)
  - `scrape_request_id` (FK to `scrape_requests`, cascade on delete)
  - `source_type` (string)
  - `source_id` (string)
  - `name` (string, nullable)
  - `email` (string, nullable, index)
  - `phone` (string, nullable)
  - `company` (string, nullable)
  - `position` (string, nullable)
  - `address` (text, nullable)
  - `cnpj` (string, nullable, index)
  - `website` (string, nullable)
  - `instagram` (string, nullable)
  - `linkedin` (string, nullable)
  - `facebook` (string, nullable)
  - `raw_data` (JSON, nullable)
  - `timestamps`
  - *Unique Constraint:* `(source_type, source_id)`
- **canonical_entities**:
  - `id` (unsigned big integer, PK)
  - `name` (string, nullable)
  - `cnpj` (string, nullable, unique)
  - `phone` (string, nullable, index)
  - `email` (string, nullable)
  - `website` (string, nullable, index)
  - `address` (string, nullable)
  - `instagram_url` (string, nullable)
  - `linkedin_url` (string, nullable)
  - `timestamps`
- **lead_links**:
  - `id` (unsigned big integer, PK)
  - `canonical_entity_id` (FK to `canonical_entities`, cascade on delete)
  - `lead_id` (FK to `leads`, cascade on delete, unique)
  - `match_method` (enum: `phone`, `website`, `cnpj`, `email`, `name_fuzzy`, `new`)
  - `match_score` (unsigned tiny integer, default 100)
  - `timestamps`

---

## 5. Queue Pipeline & Lead Linking Logic
The queue-based asynchronous processing pipeline flows as follows:
1. `POST /api/scrape-requests` → Validates input, creates a `ScrapeRequest` (status: `pending`), and dispatches `RunApifyActor`.
2. `RunApifyActor` → Triggers the target Apify actor run via the `ApifyService`, records the `apify_run_id` and `apify_dataset_id`, updates the status to `running`, and dispatches `ProcessScrapeRequest`.
3. `ProcessScrapeRequest` → Polls the Apify run status.
   - If still running, releases the job back to the queue (e.g., `release(30)`).
   - If failed/cancelled, updates the scrape request status.
   - If succeeded, fetches all dataset items, updates progress counters (`total_leads`, `completed_leads`, `failed_leads`), imports/upserts leads via `Lead::upsertFromScrapeData()`, and dispatches `LinkLeadsJob`.
4. `LinkLeadsJob` → Runs the lead linkage algorithm for each newly imported lead against existing `CanonicalEntity` records.
   - **Matching Priority:**
     1. **CNPJ** (exact match)
     2. **Phone** (normalized to E.164 `+55DDNNNNNNNNN`)
     3. **Email** (exact match)
     4. **Website** (normalized: strip protocol/www and trailing slashes)
     5. **Name fuzzy** (`similar_text` match ≥ 85% after ASCII normalization)
     6. If no match is found, a new `CanonicalEntity` is created.
   - **Merging:** Updates the matched entity using `CanonicalEntity::mergeFromLead()` to fill in empty/null fields with newly scraped data.

---

## 6. Apify Actors & Input Specifications
Actor IDs in `ApifyActorRegistry` contain `/` (e.g., `compass/crawler-google-places`), but are internally converted to the `~` separator (e.g., `compass~crawler-google-places`) before querying the Apify API in `ApifyService::startActorRun` and `ApifyService::getActorDetails`.

| Source | Target Actor ID | Input Example / Description |
|--------|-----------------|-----------------------------|
| `google_maps` | `compass/crawler-google-places` | `{"searchStringsArray":["restaurants in São Paulo"],"maxItems":10}` |
| `instagram` | `apify/instagram-scraper` | `{"directUrls":["https://www.instagram.com/user/"],"resultsLimit":10}` |
| `linkedin` | `dev_fusion/linkedin-profile-scraper` | Decision maker profile data. Requires full-permission approval on the Apify account (returns 403 until approved). |
| `cnpj` | `parseforge/brazil-cnpj-scraper` | `{"cnpjs":["00000000000191"]}` — *Note: The field name is plural (`cnpjs`).* |

---

## 7. API Endpoints
All API endpoints are unauthenticated:
```
# Scrape Requests
POST   /api/scrape-requests          # Body: { source, filters }
GET    /api/scrape-requests          # List all scrape requests
GET    /api/scrape-requests/{id}     # Show single scrape request
GET    /api/scrape-requests/{id}/status # Get status of scrape request
POST   /api/scrape-requests/{id}/cancel # Cancel running scrape request
DELETE /api/scrape-requests/{id}     # Delete scrape request

# Leads
GET    /api/leads                    # List all leads
GET    /api/leads/stats              # Get lead counts and statistics
GET    /api/leads/export             # Export leads to CSV
GET    /api/leads/{id}               # Show single lead details
DELETE /api/leads/{id}               # Delete lead
```

---

## 8. Common Commands
Use these commands for local development and testing:
```bash
# Setup
composer install
php artisan migrate

# Run Workers
php artisan queue:work               # Run a persistent queue worker
php artisan queue:work --once        # Process only the next job in the queue

# Run Tests
./vendor/bin/phpunit                 # Run the test suite
```

---

## 9. Known Issues & Constraints
- **LinkedIn Access:** Calls to the LinkedIn profile scraper will return 403 if the actor hasn't been approved in the target Apify account.
- **Boot-time Validation:** `ApifyActorValidationServiceProvider` validates all actors against the Apify platform during application boot. If any actor is missing or inaccessible, it throws a `RuntimeException` and halts boot.
  - To disable this validation for local dev/testing, set `APIFY_VALIDATION_DISABLED=true` in `.env`.
- **Polling Only:** The system relies entirely on polling for actor runs. Webhooks are not yet implemented. If you need to implement ad-hoc webhooks, check `adhoc-webhook.md`.