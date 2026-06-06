<?php

namespace Tests\Unit;

use App\Jobs\RunApifyActor;
use App\Models\ScrapeRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RunApifyActorTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_can_be_instantiated(): void
    {
        $scrapeRequest = ScrapeRequest::create([
            'source' => 'google_maps',
            'status' => 'pending',
        ]);

        $job = new RunApifyActor($scrapeRequest);

        $this->assertInstanceOf(RunApifyActor::class, $job);
        $this->assertEquals($scrapeRequest->id, $job->scrapeRequest->id);
    }

    public function test_job_has_tries_set(): void
    {
        $scrapeRequest = ScrapeRequest::create([
            'source' => 'instagram',
            'status' => 'pending',
        ]);

        $job = new RunApifyActor($scrapeRequest);

        $this->assertEquals(3, $job->tries);
    }

    public function test_job_has_backoff_set(): void
    {
        $scrapeRequest = ScrapeRequest::create([
            'source' => 'linkedin',
            'status' => 'pending',
        ]);

        $job = new RunApifyActor($scrapeRequest);

        $this->assertEquals(60, $job->backoff);
    }

    public function test_job_validates_source_types(): void
    {
        $validSources = ['google_maps', 'instagram', 'linkedin', 'cnpj'];

        foreach ($validSources as $source) {
            $scrapeRequest = ScrapeRequest::create([
                'source' => $source,
                'status' => 'pending',
            ]);

            $this->assertEquals($source, $scrapeRequest->source);
        }
    }
}
