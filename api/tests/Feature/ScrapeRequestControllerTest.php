<?php

namespace Tests\Feature;

use App\Jobs\RunApifyActor;
use App\Models\ScrapeRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScrapeRequestControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_scrape_request(): void
    {
        $response = $this->postJson('/api/scrape-requests', [
            'source' => 'google_maps',
            'filters' => ['searchStringsArray' => ['Restaurants in Sao Paulo']],
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'source',
                    'status',
                    'filters',
                    'created_at',
                ],
            ]);
    }

    public function test_create_scrape_request_validates_source(): void
    {
        $response = $this->postJson('/api/scrape-requests', [
            'source' => 'invalid_source',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['source']);
    }

    public function test_create_scrape_request_requires_source(): void
    {
        $response = $this->postJson('/api/scrape-requests', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['source']);
    }

    public function test_can_list_scrape_requests(): void
    {
        ScrapeRequest::create(['source' => 'google_maps', 'status' => 'pending']);
        ScrapeRequest::create(['source' => 'instagram', 'status' => 'running']);

        $response = $this->getJson('/api/scrape-requests');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'current_page',
                'total',
            ]);
    }

    public function test_can_filter_scrape_requests_by_source(): void
    {
        ScrapeRequest::create(['source' => 'google_maps', 'status' => 'pending']);
        ScrapeRequest::create(['source' => 'instagram', 'status' => 'pending']);

        $response = $this->getJson('/api/scrape-requests?source=google_maps');

        $response->assertStatus(200);
        $this->assertEquals(1, count($response->json('data')));
    }

    public function test_can_filter_scrape_requests_by_status(): void
    {
        ScrapeRequest::create(['source' => 'google_maps', 'status' => 'pending']);
        ScrapeRequest::create(['source' => 'instagram', 'status' => 'completed']);

        $response = $this->getJson('/api/scrape-requests?status=completed');

        $response->assertStatus(200);
        $this->assertEquals(1, count($response->json('data')));
    }

    public function test_can_get_scrape_request_details(): void
    {
        $scrapeRequest = ScrapeRequest::create([
            'source' => 'linkedin',
            'status' => 'pending',
            'filters' => ['query' => 'developers'],
        ]);

        $response = $this->getJson("/api/scrape-requests/{$scrapeRequest->id}");

        $response->assertStatus(200)
            ->assertJson([
                'id' => $scrapeRequest->id,
                'source' => 'linkedin',
                'status' => 'pending',
            ]);
    }

    public function test_returns_404_for_nonexistent_scrape_request(): void
    {
        $response = $this->getJson('/api/scrape-requests/999');

        $response->assertStatus(404);
    }

    public function test_can_get_scrape_request_status(): void
    {
        $scrapeRequest = ScrapeRequest::create([
            'source' => 'cnpj',
            'status' => 'completed',
            'total_leads' => 100,
            'completed_at' => now(),
        ]);

        $response = $this->getJson("/api/scrape-requests/{$scrapeRequest->id}/status");

        $response->assertStatus(200)
            ->assertJson([
                'id' => $scrapeRequest->id,
                'status' => 'completed',
                'total_leads' => 100,
            ]);
    }

    public function test_can_cancel_scrape_request(): void
    {
        $scrapeRequest = ScrapeRequest::create([
            'source' => 'google_maps',
            'status' => 'running',
            'started_at' => now(),
        ]);

        $response = $this->postJson("/api/scrape-requests/{$scrapeRequest->id}/cancel");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Scrape request cancelled',
            ]);

        $this->assertEquals('cancelled', $scrapeRequest->fresh()->status);
    }

    public function test_cannot_cancel_completed_scrape_request(): void
    {
        $scrapeRequest = ScrapeRequest::create([
            'source' => 'google_maps',
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $response = $this->postJson("/api/scrape-requests/{$scrapeRequest->id}/cancel");

        $response->assertStatus(400);
    }

    public function test_can_delete_scrape_request(): void
    {
        $scrapeRequest = ScrapeRequest::create([
            'source' => 'instagram',
            'status' => 'pending',
        ]);

        $response = $this->deleteJson("/api/scrape-requests/{$scrapeRequest->id}");

        $response->assertStatus(200);

        $this->assertNull(ScrapeRequest::find($scrapeRequest->id));
    }
}