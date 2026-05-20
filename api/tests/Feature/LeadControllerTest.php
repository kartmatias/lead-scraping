<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\ScrapeRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_leads(): void
    {
        $scrapeRequest = ScrapeRequest::create([
            'source' => 'google_maps',
            'status' => 'completed',
        ]);

        Lead::create([
            'scrape_request_id' => $scrapeRequest->id,
            'source_type' => 'google_maps',
            'source_id' => 'gm-1',
            'name' => 'Business 1',
            'email' => 'test1@example.com',
        ]);

        Lead::create([
            'scrape_request_id' => $scrapeRequest->id,
            'source_type' => 'google_maps',
            'source_id' => 'gm-2',
            'name' => 'Business 2',
            'email' => 'test2@example.com',
        ]);

        $response = $this->getJson('/api/leads');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'current_page',
                'total',
            ]);
    }

    public function test_can_filter_leads_by_source_type(): void
    {
        $scrapeRequest = ScrapeRequest::create([
            'source' => 'google_maps',
            'status' => 'completed',
        ]);

        Lead::create([
            'scrape_request_id' => $scrapeRequest->id,
            'source_type' => 'google_maps',
            'source_id' => 'gm-1',
            'name' => 'Business 1',
        ]);

        Lead::create([
            'scrape_request_id' => $scrapeRequest->id,
            'source_type' => 'instagram',
            'source_id' => 'ig-1',
            'name' => 'Profile 1',
        ]);

        $response = $this->getJson('/api/leads?source_type=google_maps');

        $response->assertStatus(200);
        $this->assertEquals(1, count($response->json('data')));
    }

    public function test_can_filter_leads_by_scrape_request(): void
    {
        $scrapeRequest1 = ScrapeRequest::create(['source' => 'google_maps', 'status' => 'completed']);
        $scrapeRequest2 = ScrapeRequest::create(['source' => 'instagram', 'status' => 'completed']);

        Lead::create([
            'scrape_request_id' => $scrapeRequest1->id,
            'source_type' => 'google_maps',
            'source_id' => 'gm-1',
            'name' => 'Business 1',
        ]);

        Lead::create([
            'scrape_request_id' => $scrapeRequest2->id,
            'source_type' => 'instagram',
            'source_id' => 'ig-1',
            'name' => 'Profile 1',
        ]);

        $response = $this->getJson("/api/leads?scrape_request_id={$scrapeRequest1->id}");

        $response->assertStatus(200);
        $this->assertEquals(1, count($response->json('data')));
    }

    public function test_can_search_leads(): void
    {
        $scrapeRequest = ScrapeRequest::create([
            'source' => 'google_maps',
            'status' => 'completed',
        ]);

        Lead::create([
            'scrape_request_id' => $scrapeRequest->id,
            'source_type' => 'google_maps',
            'source_id' => 'gm-1',
            'name' => 'Restaurant A',
            'email' => 'a@test.com',
        ]);

        Lead::create([
            'scrape_request_id' => $scrapeRequest->id,
            'source_type' => 'google_maps',
            'source_id' => 'gm-2',
            'name' => 'Cafe B',
            'email' => 'b@test.com',
        ]);

        $response = $this->getJson('/api/leads?search=Restaurant');

        $response->assertStatus(200);
        $this->assertEquals(1, count($response->json('data')));
    }

    public function test_can_filter_leads_with_email(): void
    {
        $scrapeRequest = ScrapeRequest::create([
            'source' => 'google_maps',
            'status' => 'completed',
        ]);

        Lead::create([
            'scrape_request_id' => $scrapeRequest->id,
            'source_type' => 'google_maps',
            'source_id' => 'gm-1',
            'name' => 'Business 1',
            'email' => 'test@example.com',
        ]);

        Lead::create([
            'scrape_request_id' => $scrapeRequest->id,
            'source_type' => 'google_maps',
            'source_id' => 'gm-2',
            'name' => 'Business 2',
        ]);

        $response = $this->getJson('/api/leads?has_email=true');

        $response->assertStatus(200);
        $this->assertEquals(1, count($response->json('data')));
    }

    public function test_can_filter_leads_with_phone(): void
    {
        $scrapeRequest = ScrapeRequest::create([
            'source' => 'google_maps',
            'status' => 'completed',
        ]);

        Lead::create([
            'scrape_request_id' => $scrapeRequest->id,
            'source_type' => 'google_maps',
            'source_id' => 'gm-1',
            'name' => 'Business 1',
            'phone' => '+5511999999999',
        ]);

        Lead::create([
            'scrape_request_id' => $scrapeRequest->id,
            'source_type' => 'google_maps',
            'source_id' => 'gm-2',
            'name' => 'Business 2',
        ]);

        $response = $this->getJson('/api/leads?has_phone=true');

        $response->assertStatus(200);
        $this->assertEquals(1, count($response->json('data')));
    }

    public function test_can_get_lead_details(): void
    {
        $scrapeRequest = ScrapeRequest::create([
            'source' => 'linkedin',
            'status' => 'completed',
        ]);

        $lead = Lead::create([
            'scrape_request_id' => $scrapeRequest->id,
            'source_type' => 'linkedin',
            'source_id' => 'li-1',
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'position' => 'CEO',
        ]);

        $response = $this->getJson("/api/leads/{$lead->id}");

        $response->assertStatus(200)
            ->assertJson([
                'id' => $lead->id,
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'position' => 'CEO',
            ]);
    }

    public function test_returns_404_for_nonexistent_lead(): void
    {
        $response = $this->getJson('/api/leads/999');

        $response->assertStatus(404);
    }

    public function test_can_delete_lead(): void
    {
        $scrapeRequest = ScrapeRequest::create([
            'source' => 'google_maps',
            'status' => 'completed',
        ]);

        $lead = Lead::create([
            'scrape_request_id' => $scrapeRequest->id,
            'source_type' => 'google_maps',
            'source_id' => 'gm-1',
            'name' => 'Business 1',
        ]);

        $response = $this->deleteJson("/api/leads/{$lead->id}");

        $response->assertStatus(200);

        $this->assertNull(Lead::find($lead->id));
    }

    public function test_can_export_leads_as_json(): void
    {
        $scrapeRequest = ScrapeRequest::create([
            'source' => 'google_maps',
            'status' => 'completed',
        ]);

        Lead::create([
            'scrape_request_id' => $scrapeRequest->id,
            'source_type' => 'google_maps',
            'source_id' => 'gm-1',
            'name' => 'Business 1',
            'email' => 'test@example.com',
        ]);

        $response = $this->getJson('/api/leads/export?format=json');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'count',
                'leads',
            ]);
    }

    public function test_can_export_leads_as_csv(): void
    {
        $scrapeRequest = ScrapeRequest::create([
            'source' => 'google_maps',
            'status' => 'completed',
        ]);

        Lead::create([
            'scrape_request_id' => $scrapeRequest->id,
            'source_type' => 'google_maps',
            'source_id' => 'gm-1',
            'name' => 'Business 1',
            'email' => 'test@example.com',
        ]);

        $response = $this->get('/api/leads/export?format=csv');

        $response->assertStatus(200)
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_can_get_stats(): void
    {
        $scrapeRequest = ScrapeRequest::create([
            'source' => 'google_maps',
            'status' => 'completed',
        ]);

        Lead::create([
            'scrape_request_id' => $scrapeRequest->id,
            'source_type' => 'google_maps',
            'source_id' => 'gm-1',
            'name' => 'Business 1',
            'email' => 'test@example.com',
            'phone' => '+5511999999999',
        ]);

        $response = $this->getJson('/api/leads/stats');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'leads' => [
                    'total',
                    'by_source',
                    'with_email',
                    'with_phone',
                    'with_cnpj',
                ],
                'scrape_requests' => [
                    'total',
                    'by_status',
                ],
            ]);
    }
}