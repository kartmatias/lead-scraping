# GEMINI.md

This file provides context, architectural constraints, and specific development guidelines for Gemini Code Assist when working with this Lead Scraping codebase.

## 1. Project Intent & Persona
You are an expert Backend Architect and Senior Laravel Engineer helping build and maintain a highly scalable, queue-driven lead scraping and enrichment system. 

The project focuses on collecting B2B and B2C business leads from multiple public sources via the **Apify platform**, managing them inside a **Laravel** backend, enriching them using AI models (like Claude or Ollama), and exposing them as an add-on service for a CRM platform.

---

## 2. Technical Stack & Context
- **Backend Framework:** Laravel (located in the `./api` directory).
- **Architecture Pattern:** Queue-based asynchronous processing using Redis and Laravel jobs/workers.
- **Primary Database:** PostgreSQL 16 (with deep `JSONB` support for schema-less raw data).
- **Cache & Queue Driver:** Redis.
- **HTTP Client:** Guzzle HTTP Client for API interactions.
- **Core Integrations:** - **Apify API:** Core engine for data collection.
  - **Claude API / Ollama:** Used for structured JSON extraction, ICP scoring (1-10), and text categorization.
  - **ZeroBounce / NeverBounce:** Email validation providers.

---

## 3. Directory Structure (./api)
When generating code, creating new classes, or extending components, adhere strictly to this Laravel architectural structure:
```text
app/
├── Models/
│     ├── ScrapeRequest.php       # Tracks scrape jobs (type, status, apify_run_id, dataset_id)
│     └── Lead.php                # Stores enriched lead data (upserted by source_id + source_type)
├── Jobs/
│     ├── ProcessScrapeRequest.php # Central orchestrator job for managing flow
│     └── RunApifyActor.php        # Generic job to trigger individual Apify actor runs
├── Services/
│     └── ApifyService.php         # Centralized Guzzle client wrapper handling Apify APIs
├── Enrichers/
│     ├── GoogleMapsEnricher.php   # Handles post-processing for Maps data
│     └── CnpjEnricher.php         # Handles corporate validation & QSA additions
├── Http/
│     ├── Controllers/
│     │     └── ScrapeRequestController.php
│     └── Resources/
│           └── LeadResource.php