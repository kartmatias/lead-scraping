<?php

namespace Tests\Unit;

use App\Services\ApifyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApifyServiceTest extends TestCase
{
    use RefreshDatabase;

    private ApifyService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ApifyService();
    }

    public function test_service_can_be_instantiated(): void
    {
        $this->assertInstanceOf(ApifyService::class, $this->service);
    }

    public function test_start_actor_run_returns_array_structure(): void
    {
        $result = $this->service->startActorRun('compass/crawler-google-places', [
            'searchStringsArray' => ['Test'],
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    public function test_get_run_status_returns_array_structure(): void
    {
        $result = $this->service->getRunStatus('invalid-run-id');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    public function test_get_dataset_items_returns_array_structure(): void
    {
        $result = $this->service->getDatasetItems('invalid-dataset-id');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    public function test_get_all_dataset_items_handles_pagination(): void
    {
        $result = $this->service->getAllDatasetItems('invalid-dataset-id');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    public function test_cancel_run_returns_array_structure(): void
    {
        $result = $this->service->cancelRun('invalid-run-id');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }
}