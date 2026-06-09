<?php

namespace App\Jobs;

use App\Models\ScrapeRequest;
use App\Services\ApifyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunApifyActor implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public ScrapeRequest $scrapeRequest
    ) {}

    public function handle(ApifyService $apifyService): void
    {
        $actorId = $this->getActorId();
        $input = $this->scrapeRequest->filters ?? [];

        Log::info('Starting Apify actor', [
            'scrape_request_id' => $this->scrapeRequest->id,
            'actor_id' => $actorId,
        ]);

        $result = $apifyService->startActorRun($actorId, $input);

        if ($result['success']) {
            $this->scrapeRequest->update([
                'apify_run_id' => $result['run_id'],
                'apify_dataset_id' => $result['dataset_id'],
            ]);

            $this->scrapeRequest->startScrape();

            ProcessScrapeRequest::dispatch($this->scrapeRequest)->delay(30);
        } else {
            $this->scrapeRequest->fail($result['error'] ?? 'Failed to start actor');
        }
    }

    private function getActorId(): string
    {
        return match ($this->scrapeRequest->source) {
            'google_maps' => 'compass/crawler-google-places',
            'instagram' => 'apify/instagram-scraper',
            'linkedin' => 'dev_fusion/linkedin-profile-scraper',
            'cnpj' => 'parseforge/brazil-cnpj-scraper',
            default => throw new \InvalidArgumentException("Unknown source: {$this->scrapeRequest->source}"),
        };
    }
}
