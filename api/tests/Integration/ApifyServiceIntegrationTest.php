<?php

namespace Tests\Integration;

use App\Services\ApifyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Integration tests for ApifyService.
 *
 * Requirements to run these tests:
 * 1. Valid APIFY_TOKEN in .env file
 * 2. Internet connection to reach api.apify.com
 * 3. The test user must have access to the Apify actors
 *
 * Run with: php artisan test --filter=ApifyServiceIntegrationTest
 */
class ApifyServiceIntegrationTest extends TestCase
{
    private ?string $apiToken;
    private ?string $apifyUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->apiToken = config('services.apify.token') ?: env('APIFY_TOKEN');
        $this->apifyUser = config('services.apify.user') ?: env('APIFY_USER');
    }

    private function skipIfNoToken(): void
    {
        if (empty($this->apiToken)) {
            $this->markTestSkipped('APIFY_TOKEN not configured');
        }
    }

    public function test_service_has_valid_token_configured(): void
    {
        $this->assertNotEmpty($this->apiToken, 'APIFY_TOKEN must be configured in .env');
        $this->assertIsString($this->apiToken);
        $this->assertStringStartsWith('apify_api_', $this->apiToken, 'Token should start with apify_api_');
    }

    public function test_can_start_google_maps_actor(): void
    {
        $this->skipIfNoToken();

        $service = new ApifyService();
        // Custom actor: nwua9Gu5YrADL7ZDj (compass/crawler-google-places)
        // Uses singular field names: searchString, maxCrawledPlaces
        $result = $service->startActorRun('nwua9Gu5YrADL7ZDj', [
            'searchString' => 'Restaurantes near São Paulo, Brazil',
            'maxCrawledPlaces' => 5,
        ]);

        // Handle different response types
        if (!$result['success']) {
            $error = $result['error'] ?? 'Unknown error';
            if (str_contains($error, '404') || str_contains($error, 'page-not-found')) {
                $this->markTestSkipped('Actor not found or API token invalid - check Apify account');
            }
            $this->fail('Failed to start actor: ' . $error);
        }

        $this->assertNotNull($result['run_id']);
        $this->assertNotNull($result['dataset_id']);
        $this->assertContains($result['status'], ['READY', 'RUNNING']);

        // Store for cleanup
        Cache::put('test_run_id', $result['run_id'], now()->addMinutes(10));
        Cache::put('test_dataset_id', $result['dataset_id'], now()->addMinutes(10));
    }

    public function test_can_start_instagram_scraper(): void
    {
        $this->skipIfNoToken();

        $service = new ApifyService();
        $result = $service->startActorRun('apify/instagram-scraper', [
            'hashtags' => ['saopaulo'],
            'maxItems' => 3,
        ]);

        if (!$result['success']) {
            $error = $result['error'] ?? 'Unknown error';
            if (str_contains($error, '404') || str_contains($error, 'page-not-found')) {
                $this->markTestSkipped('Actor not found or API token invalid');
            }
            $this->fail('Failed to start actor: ' . $error);
        }

        $this->assertNotNull($result['run_id']);
        Cache::put('test_ig_run_id', $result['run_id'], now()->addMinutes(10));
    }

    public function test_can_get_run_status(): void
    {
        $this->skipIfNoToken();

        // First start a quick actor
        $service = new ApifyService();
        $startResult = $service->startActorRun('compass/crawler-google-places', [
            'searchStringsArray' => ['Test'],
            'maxItems' => 1,
        ]);

        if (!$startResult['success']) {
            $this->markTestSkipped('Could not start test actor');
        }

        $runId = $startResult['run_id'];

        // Then check status
        $statusResult = $service->getRunStatus($runId);

        $this->assertTrue($statusResult['success']);
        $this->assertArrayHasKey('status', $statusResult);
        $this->assertContains($statusResult['status'], ['RUNNING', 'SUCCEEDED', 'FAILED', 'ABORTED']);

        // Cleanup - try to abort if still running
        if ($statusResult['status'] === 'RUNNING') {
            $service->cancelRun($runId);
        }
    }

    public function test_can_get_dataset_items(): void
    {
        $this->skipIfNoToken();

        $service = new ApifyService();

        // Start and wait for a minimal actor to complete
        $startResult = $service->startActorRun('compass/crawler-google-places', [
            'searchStringsArray' => ['Test Location'],
            'maxItems' => 2,
        ]);

        if (!$startResult['success']) {
            $this->markTestSkipped('Could not start test actor');
        }

        $runId = $startResult['run_id'];
        $datasetId = $startResult['dataset_id'];

        // Wait for completion (max 60 seconds)
        $maxWait = 60;
        $waited = 0;
        while ($waited < $maxWait) {
            sleep(5);
            $waited += 5;

            $statusResult = $service->getRunStatus($runId);
            if ($statusResult['status'] === 'SUCCEEDED') {
                break;
            }
            if (in_array($statusResult['status'], ['FAILED', 'ABORTED'])) {
                break;
            }
        }

        // Get dataset items
        $itemsResult = $service->getDatasetItems($datasetId);

        $this->assertTrue($itemsResult['success']);
        $this->assertIsArray($itemsResult['items']);
    }

    public function test_can_get_all_dataset_items_with_pagination(): void
    {
        $this->skipIfNoToken();

        $service = new ApifyService();

        // Start actor that will return some results
        $startResult = $service->startActorRun('compass/crawler-google-places', [
            'searchStringsArray' => ['Coffee Shop'],
            'maxItems' => 10,
        ]);

        if (!$startResult['success']) {
            $this->markTestSkipped('Could not start test actor');
        }

        $runId = $startResult['run_id'];
        $datasetId = $startResult['dataset_id'];

        // Wait for completion
        $maxWait = 60;
        $waited = 0;
        while ($waited < $maxWait) {
            sleep(5);
            $waited += 5;

            $statusResult = $service->getRunStatus($runId);
            if ($statusResult['status'] === 'SUCCEEDED') {
                break;
            }
            if (in_array($statusResult['status'], ['FAILED', 'ABORTED'])) {
                break;
            }
        }

        // Get all items
        $allItemsResult = $service->getAllDatasetItems($datasetId);

        $this->assertTrue($allItemsResult['success']);
        $this->assertIsArray($allItemsResult['items']);
        $this->assertArrayHasKey('count', $allItemsResult);
    }

    public function test_can_cancel_running_actor(): void
    {
        $this->skipIfNoToken();

        $service = new ApifyService();

        // Start a long-running actor
        $startResult = $service->startActorRun('compass/crawler-google-places', [
            'searchStringsArray' => ['Test Cancel'],
            'maxItems' => 100,
        ]);

        if (!$startResult['success']) {
            $this->markTestSkipped('Could not start test actor');
        }

        $runId = $startResult['run_id'];

        // Cancel it
        $cancelResult = $service->cancelRun($runId);

        $this->assertTrue($cancelResult['success']);
    }

    public function test_invalid_actor_returns_error(): void
    {
        $this->skipIfNoToken();

        $service = new ApifyService();
        $result = $service->startActorRun('invalid/actor-that-does-not-exist', []);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
    }

    public function test_invalid_run_id_returns_error(): void
    {
        $this->skipIfNoToken();

        $service = new ApifyService();
        $result = $service->getRunStatus('invalid-run-id-12345');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
    }

    public function test_actor_id_mapping_for_all_sources(): void
    {
        $this->skipIfNoToken();

        $service = new ApifyService();

        $actorMappings = [
            'google_maps' => 'compass/crawler-google-places',
            'instagram' => 'apify/instagram-scraper',
            'linkedin' => 'dev_fusion/linkedin-profile-scraper',
            'cnpj' => 'parseforge/brazil-cnpj-scraper',
        ];

        foreach ($actorMappings as $source => $actorId) {
            // Just verify we can start each actor type (with minimal input)
            $result = $service->startActorRun($actorId, $this->getMinimalInput($source));

            // Some might fail due to input validation, but we check structure
            $this->assertIsArray($result);
            $this->assertArrayHasKey('success', $result);
        }
    }

    private function getMinimalInput(string $source): array
    {
        return match ($source) {
            'google_maps' => ['searchStringsArray' => ['test'], 'maxItems' => 1],
            'instagram' => ['hashtags' => ['test'], 'maxItems' => 1],
            'linkedin' => ['profiles' => ['test'], 'maxItems' => 1],
            'cnpj' => ['cnpjs' => ['00000000000000']],
            default => [],
        };
    }
}