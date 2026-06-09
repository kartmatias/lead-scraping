<?php

namespace Tests\Unit;

use App\Models\Lead;
use App\Models\ScrapeRequest;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadTest extends TestCase
{
    use RefreshDatabase;

    public function test_lead_can_be_created(): void
    {
        $scrapeRequest = ScrapeRequest::create([
            'source' => 'google_maps',
            'status' => 'pending',
            'filters' => ['searchStringsArray' => ['Test']],
        ]);

        $lead = Lead::create([
            'scrape_request_id' => $scrapeRequest->id,
            'source_type' => 'google_maps',
            'source_id' => 'unique-id-123',
            'name' => 'Test Company',
            'email' => 'test@example.com',
            'phone' => '+5511999999999',
            'company' => 'Test Corp',
            'address' => 'São Paulo, SP',
        ]);

        $this->assertDatabaseHas('leads', [
            'name' => 'Test Company',
            'email' => 'test@example.com',
        ]);
    }

    public function test_lead_belongs_to_scrape_request(): void
    {
        $scrapeRequest = ScrapeRequest::create([
            'source' => 'instagram',
            'status' => 'pending',
        ]);

        $lead = Lead::create([
            'scrape_request_id' => $scrapeRequest->id,
            'source_type' => 'instagram',
            'source_id' => 'insta-123',
            'name' => 'Test Profile',
        ]);

        $this->assertEquals($scrapeRequest->id, $lead->scrapeRequest->id);
    }

    public function test_lead_upsert_creates_new_record(): void
    {
        $scrapeRequest = ScrapeRequest::create([
            'source' => 'linkedin',
            'status' => 'pending',
        ]);

        $data = [
            'source_type' => 'linkedin',
            'source_id' => 'li-123',
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'position' => 'CEO',
        ];

        $lead = Lead::upsertFromScrapeData($data, $scrapeRequest->id);

        $this->assertNotNull($lead->id);
        $this->assertEquals('John Doe', $lead->name);
    }

    public function test_lead_upsert_updates_existing_record(): void
    {
        $scrapeRequest = ScrapeRequest::create([
            'source' => 'cnpj',
            'status' => 'pending',
        ]);

        $data = [
            'source_type' => 'cnpj',
            'source_id' => 'cnpj-123',
            'name' => 'Company A',
            'cnpj' => '12345678000100',
        ];

        Lead::upsertFromScrapeData($data, $scrapeRequest->id);

        $updatedData = [
            'source_type' => 'cnpj',
            'source_id' => 'cnpj-123',
            'name' => 'Company A Updated',
            'cnpj' => '12345678000100',
        ];

        $lead = Lead::upsertFromScrapeData($updatedData, $scrapeRequest->id);

        $this->assertEquals(1, Lead::count());
        $this->assertEquals('Company A Updated', $lead->name);
    }

    public function test_lead_has_unique_constraint(): void
    {
        $scrapeRequest = ScrapeRequest::create([
            'source' => 'google_maps',
            'status' => 'pending',
        ]);

        Lead::create([
            'scrape_request_id' => $scrapeRequest->id,
            'source_type' => 'google_maps',
            'source_id' => 'gm-123',
            'name' => 'Business 1',
        ]);

        $this->expectException(QueryException::class);

        Lead::create([
            'scrape_request_id' => $scrapeRequest->id,
            'source_type' => 'google_maps',
            'source_id' => 'gm-123',
            'name' => 'Business 2',
        ]);
    }
}
