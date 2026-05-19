<?php

namespace App\Jobs;

use App\Models\Lead;
use App\Models\ScrapeRequest;
use App\Services\ApifyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessScrapeRequest implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        public ScrapeRequest $scrapeRequest
    ) {}

    public function handle(ApifyService $apifyService): void
    {
        if (!$this->scrapeRequest->isRunning()) {
            Log::warning('Scrape request is not running', [
                'scrape_request_id' => $this->scrapeRequest->id,
                'status' => $this->scrapeRequest->status,
            ]);
            return;
        }

        $runId = $this->scrapeRequest->apify_run_id;
        $datasetId = $this->scrapeRequest->apify_dataset_id;

        if (!$runId || !$datasetId) {
            $this->scrapeRequest->fail('No run ID or dataset ID');
            return;
        }

        $statusResult = $apifyService->getRunStatus($runId);

        if (!$statusResult['success']) {
            Log::error('Failed to get run status', [
                'scrape_request_id' => $this->scrapeRequest->id,
                'error' => $statusResult['error'],
            ]);
            return;
        }

        $status = $statusResult['status'];

        if (in_array($status, ['RUNNING', 'READY'])) {
            $this->release(30);
            return;
        }

        if ($status === 'ABORTED' || $status === 'ABORTING') {
            $this->scrapeRequest->cancel();
            return;
        }

        if ($status === 'FAILED') {
            $this->scrapeRequest->fail('Apify run failed');
            return;
        }

        if ($status === 'SUCCEEDED') {
            $this->processResults($apifyService, $datasetId);
            return;
        }

        Log::warning('Unknown run status', [
            'scrape_request_id' => $this->scrapeRequest->id,
            'status' => $status,
        ]);
    }

    private function processResults(ApifyService $apifyService, string $datasetId): void
    {
        $result = $apifyService->getAllDatasetItems($datasetId);

        if (!$result['success']) {
            $this->scrapeRequest->fail($result['error'] ?? 'Failed to fetch dataset items');
            return;
        }

        $items = $result['items'];
        $count = count($items);

        foreach ($items as $item) {
            Lead::upsertFromScrapeData($item, $this->scrapeRequest->id);
        }

        $this->scrapeRequest->complete($count);

        Log::info('Scrape request completed', [
            'scrape_request_id' => $this->scrapeRequest->id,
            'leads_count' => $count,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        $this->scrapeRequest->fail($exception->getMessage());

        Log::error('ProcessScrapeRequest job failed', [
            'scrape_request_id' => $this->scrapeRequest->id,
            'error' => $exception->getMessage(),
        ]);
    }
}