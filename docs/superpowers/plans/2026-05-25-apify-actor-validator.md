# Apify Actor Validator Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Create a Laravel service provider that validates all Apify actors exist at application boot time, failing fast if any actor is missing.

**Architecture:** A service provider runs at boot, fetching actor details from Apify API via a new `getActorDetails()` method in ApifyService. Throws RuntimeException if any actor validation fails.

**Tech Stack:** Laravel, Guzzle HTTP client, Apify API

---

### Task 1: Add getActorDetails method to ApifyService

**Files:**
- Modify: `api/app/Services/ApifyService.php`

- [ ] **Step 1: Add getActorDetails method**

Add this method to `ApifyService.php` after the `cancelRun` method (around line 160):

```php
public function getActorDetails(string $actorId): array
{
    try {
        $response = $this->client->get("/acts/{$actorId}");
        $data = json_decode($response->getBody()->getContents(), true);

        return [
            'success' => true,
            'actor_id' => $data['data']['id'] ?? $actorId,
            'name' => $data['data']['name'] ?? null,
            'description' => $data['data']['description'] ?? null,
            'version' => $data['data']['version'] ?? null,
        ];
    } catch (RequestException $e) {
        Log::error('Apify getActorDetails failed', [
            'actor_id' => $actorId,
            'error' => $e->getMessage(),
        ]);

        return [
            'success' => false,
            'error' => $e->getMessage(),
        ];
    }
}
```

- [ ] **Step 2: Run test to verify code compiles**

Run: `cd /mnt/operacional/projects-2026/lead-scraping/api && php artisan --version`
Expected: Version output (no syntax errors)

- [ ] **Step 3: Commit**

```bash
git add api/app/Services/ApifyService.php
git commit -m "feat: add getActorDetails method to ApifyService"
```

---

### Task 2: Create ApifyActorRegistry

**Files:**
- Create: `api/app/Providers/ApifyActorRegistry.php`

- [ ] **Step 1: Create the registry file**

Create `api/app/Providers/ApifyActorRegistry.php`:

```php
<?php

namespace App\Providers;

class ApifyActorRegistry
{
    /**
     * Get all configured Apify actors.
     *
     * @return array<int, array{id: string, name: string, source: string}>
     */
    public function getActors(): array
    {
        return [
            [
                'id' => 'compass/crawler-google-places',
                'name' => 'Google Maps',
                'source' => 'google_maps',
            ],
            [
                'id' => 'apify/instagram-scraper',
                'name' => 'Instagram',
                'source' => 'instagram',
            ],
            [
                'id' => 'dev_fusion/linkedin-profile-scraper',
                'name' => 'LinkedIn',
                'source' => 'linkedin',
            ],
            [
                'id' => 'parseforge/brazil-cnpj-scraper',
                'name' => 'CNPJ',
                'source' => 'cnpj',
            ],
        ];
    }

    /**
     * Get actor IDs only.
     *
     * @return array<int, string>
     */
    public function getActorIds(): array
    {
        return array_column($this->getActors(), 'id');
    }
}
```

- [ ] **Step 2: Run test to verify code compiles**

Run: `cd /mnt/operacional/projects-2026/lead-scraping/api && php -l app/Providers/ApifyActorRegistry.php`
Expected: No syntax errors

- [ ] **Step 3: Commit**

```bash
git add api/app/Providers/ApifyActorRegistry.php
git commit -m "feat: add ApifyActorRegistry configuration"
```

---

### Task 3: Create ApifyActorValidationServiceProvider

**Files:**
- Create: `api/app/Providers/ApifyActorValidationServiceProvider.php`
- Modify: `api/config/app.php`

- [ ] **Step 1: Create the service provider**

Create `api/app/Providers/ApifyActorValidationServiceProvider.php`:

```php
<?php

namespace App\Providers;

use App\Services\ApifyService;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Log;

class ApifyActorValidationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(ApifyService $apifyService): void
    {
        // Allow disabling validation via environment variable for local dev
        if ($this->app->environment('local') && env('APIFY_VALIDATION_DISABLED', false)) {
            Log::info('Apify actor validation skipped (APIFY_VALIDATION_DISABLED=true)');
            return;
        }

        $registry = new ApifyActorRegistry();
        $actors = $registry->getActors();
        $failedActors = [];

        foreach ($actors as $actor) {
            $result = $apifyService->getActorDetails($actor['id']);

            if (!$result['success']) {
                $failedActors[] = [
                    'id' => $actor['id'],
                    'name' => $actor['name'],
                    'error' => $result['error'],
                ];
                Log::error('Apify actor validation failed', [
                    'actor_id' => $actor['id'],
                    'error' => $result['error'],
                ]);
            } else {
                Log::info('Apify actor validated', [
                    'actor_id' => $actor['id'],
                    'name' => $result['name'] ?? $actor['name'],
                ]);
            }
        }

        if (!empty($failedActors)) {
            $failedList = implode(', ', array_map(fn($a) => "{$a['name']} ({$a['id']})", $failedActors));
            throw new \RuntimeException(
                "Apify actor validation failed. Missing or inaccessible actors: {$failedList}"
            );
        }
    }
}
```

- [ ] **Step 2: Register provider in config/app.php**

Find the providers array in `api/config/app.php` and add:

```php
App\Providers\ApifyActorValidationServiceProvider::class,
```

Add it after the other App\Providers entries.

- [ ] **Step 3: Run test to verify code compiles**

Run: `cd /mnt/operacional/projects-2026/lead-scraping/api && php artisan --version`
Expected: No errors

- [ ] **Step 4: Commit**

```bash
git add api/app/Providers/ApifyActorValidationServiceProvider.php api/config/app.php
git commit -m "feat: add ApifyActorValidationServiceProvider for boot-time validation"
```

---

### Task 4: Add unit tests for getActorDetails

**Files:**
- Modify: `api/tests/Unit/ApifyServiceTest.php`

- [ ] **Step 1: Add test for getActorDetails success**

Add to `ApifyServiceTest.php`:

```php
public function test_get_actor_details_returns_array_structure(): void
{
    $result = $this->service->getActorDetails('compass/crawler-google-places');

    $this->assertIsArray($result);
    $this->assertArrayHasKey('success', $result);
}

public function test_get_actor_details_handles_missing_actor(): void
{
    $result = $this->service->getActorDetails('non-existent/actor');

    $this->assertIsArray($result);
    $this->assertArrayHasKey('success', $result);
    $this->assertFalse($result['success']);
}
```

- [ ] **Step 2: Run tests**

Run: `cd /mnt/operacional/projects-2026/lead-scraping/api && php artisan test tests/Unit/ApifyServiceTest.php`
Expected: All tests pass

- [ ] **Step 3: Commit**

```bash
git add api/tests/Unit/ApifyServiceTest.php
git commit -m "test: add getActorDetails tests"
```

---

### Task 5: Final verification

- [ ] **Step 1: Run full test suite**

Run: `cd /mnt/operacional/projects-2026/lead-scraping/api && php artisan test`
Expected: All tests pass

- [ ] **Step 2: Verify provider registration**

Run: `cd /mnt/operacional/projects-2026/lead-scraping/api && php artisan about`
Look for: ApifyActorValidationServiceProvider in provider list

- [ ] **Step 3: Commit**

```bash
git add -A
git commit -m "feat: complete Apify actor validator bootstrapper"
```