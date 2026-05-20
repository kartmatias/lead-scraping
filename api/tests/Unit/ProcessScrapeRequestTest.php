<?php

namespace Tests\Unit;

use App\Jobs\ProcessScrapeRequest;
use App\Models\Lead;
use App\Models\ScrapeRequest;
use App\Services\ApifyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessScrapeRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_can_be_instantiated(): void
    {
        $scrapeRequest = ScrapeRequest::create([
            'source' => 'google_maps',
            'status' => 'running',
            'apify_run_id' => 'run-123',
            'apify_dataset_id' => 'dataset-123',
            'started_at' => now(),
        ]);

        $job = new ProcessScrapeRequest($scrapeRequest);

        $this->assertInstanceOf(ProcessScrapeRequest::class, $job);
        $this->assertEquals($scrapeRequest->id, $job->scrapeRequest->id);
    }

    public function test_job_has_tries_set(): void
    {
        $scrapeRequest = ScrapeRequest::create([
            'source' => 'google_maps',
            'status' => 'running',
        ]);

        $job = new ProcessScrapeRequest($scrapeRequest);

        $this->assertEquals(3, $job->tries);
    }

    public function test_job_has_backoff_set(): void
    {
        $scrapeRequest = ScrapeRequest::create([
            'source' => 'google_maps',
            'status' => 'running',
        ]);

        $job = new ProcessScrapeRequest($scrapeRequest);

        $this->assertEquals(30, $job->backoff);
    }

    public function test_job_does_nothing_if_not_running(): void
    {
        $scrapeRequest = ScrapeRequest::create([
            'source' => 'google_maps',
            'status' => 'pending',
        ]);

        $job = new ProcessScrapeRequest($scrapeRequest);
        $job->handle(new ApifyService());

        // Should complete without errors
        $this->assertTrue(true);
    }

    public function test_job_fails_if_no_run_id(): void
    {
        $scrapeRequest = ScrapeRequest::create([
            'source' => 'google_maps',
            'status' => 'running',
            'started_at' => now(),
        ]);

        $job = new ProcessScrapeRequest($scrapeRequest);
        $job->handle(new ApifyService());

        $this->assertEquals('failed', $scrapeRequest->fresh()->status);
    }

    public function test_job_fails_if_no_dataset_id(): void
    {
        $scrapeRequest = ScrapeRequest::create([
            'source' => 'google_maps',
            'status' => 'running',
            'apify_run_id' => 'run-123',
            'started_at' => now(),
        ]);

        $job = new ProcessScrapeRequest($scrapeRequest);
        $job->handle(new ApifyService());

        $this->assertEquals('failed', $scrapeRequest->fresh()->status);
    }

    public function test_failed_method_sets_status(): void
    {
        $scrapeRequest = ScrapeRequest::create([
            'source' => 'google_maps',
            'status' => 'running',
            'started_at' => now(),
        ]);

        $job = new ProcessScrapeRequest($scrapeRequest);
        $job->failed(new \Exception('Test error'));

        $this->assertEquals('failed', $scrapeRequest->fresh()->status);
        $this->assertEquals('Test error', $scrapeRequest->fresh()->error_message);
    }
}