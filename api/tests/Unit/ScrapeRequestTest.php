<?php

namespace Tests\Unit;

use App\Models\Lead;
use App\Models\ScrapeRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScrapeRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_scrape_request_can_be_created(): void
    {
        $scrapeRequest = ScrapeRequest::create([
            'source' => 'google_maps',
            'status' => 'pending',
            'filters' => ['searchStringsArray' => ['Restaurants']],
        ]);

        $this->assertDatabaseHas('scrape_requests', [
            'source' => 'google_maps',
            'status' => 'pending',
        ]);
    }

    public function test_scrape_request_has_many_leads(): void
    {
        $scrapeRequest = ScrapeRequest::create([
            'source' => 'instagram',
            'status' => 'pending',
        ]);

        Lead::create([
            'scrape_request_id' => $scrapeRequest->id,
            'source_type' => 'instagram',
            'source_id' => 'ig-1',
            'name' => 'Profile 1',
        ]);

        Lead::create([
            'scrape_request_id' => $scrapeRequest->id,
            'source_type' => 'instagram',
            'source_id' => 'ig-2',
            'name' => 'Profile 2',
        ]);

        $this->assertEquals(2, $scrapeRequest->leads()->count());
    }

    public function test_start_scrape_updates_status(): void
    {
        $scrapeRequest = ScrapeRequest::create([
            'source' => 'linkedin',
            'status' => 'pending',
        ]);

        $scrapeRequest->startScrape();

        $this->assertEquals('running', $scrapeRequest->fresh()->status);
        $this->assertNotNull($scrapeRequest->fresh()->started_at);
    }

    public function test_complete_updates_status(): void
    {
        $scrapeRequest = ScrapeRequest::create([
            'source' => 'cnpj',
            'status' => 'running',
            'started_at' => now(),
        ]);

        $scrapeRequest->complete(50);

        $this->assertEquals('completed', $scrapeRequest->fresh()->status);
        $this->assertNotNull($scrapeRequest->fresh()->completed_at);
        $this->assertEquals(50, $scrapeRequest->fresh()->total_leads);
    }

    public function test_fail_updates_status(): void
    {
        $scrapeRequest = ScrapeRequest::create([
            'source' => 'google_maps',
            'status' => 'running',
            'started_at' => now(),
        ]);

        $scrapeRequest->fail('API rate limit exceeded');

        $this->assertEquals('failed', $scrapeRequest->fresh()->status);
        $this->assertNotNull($scrapeRequest->fresh()->completed_at);
        $this->assertEquals('API rate limit exceeded', $scrapeRequest->fresh()->error_message);
    }

    public function test_cancel_updates_status(): void
    {
        $scrapeRequest = ScrapeRequest::create([
            'source' => 'instagram',
            'status' => 'running',
            'started_at' => now(),
        ]);

        $scrapeRequest->cancel();

        $this->assertEquals('cancelled', $scrapeRequest->fresh()->status);
        $this->assertNotNull($scrapeRequest->fresh()->completed_at);
    }

    public function test_is_pending_returns_correct_value(): void
    {
        $pending = ScrapeRequest::create(['source' => 'google_maps', 'status' => 'pending']);
        $running = ScrapeRequest::create(['source' => 'google_maps', 'status' => 'running']);

        $this->assertTrue($pending->isPending());
        $this->assertFalse($running->isPending());
    }

    public function test_is_running_returns_correct_value(): void
    {
        $pending = ScrapeRequest::create(['source' => 'google_maps', 'status' => 'pending']);
        $running = ScrapeRequest::create(['source' => 'google_maps', 'status' => 'running']);

        $this->assertTrue($running->isRunning());
        $this->assertFalse($pending->isRunning());
    }

    public function test_filters_are_cast_to_array(): void
    {
        $scrapeRequest = ScrapeRequest::create([
            'source' => 'google_maps',
            'status' => 'pending',
            'filters' => ['searchStringsArray' => ['Restaurants'], 'maxItems' => 100],
        ]);

        $this->assertIsArray($scrapeRequest->filters);
        $this->assertEquals(['Restaurants'], $scrapeRequest->filters['searchStringsArray']);
    }

    public function test_source_enum_validation(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        ScrapeRequest::create([
            'source' => 'invalid_source',
            'status' => 'pending',
        ]);
    }

    public function test_status_enum_validation(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        ScrapeRequest::create([
            'source' => 'google_maps',
            'status' => 'invalid_status',
        ]);
    }
}