<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessScrapeRequest;
use App\Jobs\RunApifyActor;
use App\Models\ScrapeRequest;
use App\Services\ApifyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScrapeRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ScrapeRequest::query()->orderBy('created_at', 'desc');

        if ($request->has('source')) {
            $query->where('source', $request->input('source'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $scrapeRequests = $query->paginate($request->input('per_page', 20));

        return response()->json($scrapeRequests);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source' => 'required|in:google_maps,instagram,linkedin,cnpj',
            'filters' => 'nullable|array',
        ]);

        $scrapeRequest = ScrapeRequest::create([
            'source' => $validated['source'],
            'status' => 'pending',
            'filters' => $validated['filters'] ?? [],
        ]);

        RunApifyActor::dispatch($scrapeRequest);

        return response()->json([
            'message' => 'Scrape request created',
            'data' => $scrapeRequest,
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $scrapeRequest = ScrapeRequest::with('leads')->findOrFail($id);

        return response()->json($scrapeRequest);
    }

    public function status(int $id): JsonResponse
    {
        $scrapeRequest = ScrapeRequest::findOrFail($id);

        $data = [
            'id' => $scrapeRequest->id,
            'source' => $scrapeRequest->source,
            'status' => $scrapeRequest->status,
            'total_leads' => $scrapeRequest->total_leads,
            'completed_leads' => $scrapeRequest->completed_leads,
            'failed_leads' => $scrapeRequest->failed_leads,
            'started_at' => $scrapeRequest->started_at,
            'completed_at' => $scrapeRequest->completed_at,
            'error_message' => $scrapeRequest->error_message,
        ];

        if ($scrapeRequest->isRunning() && $scrapeRequest->apify_run_id) {
            $apifyService = new ApifyService();
            $runStatus = $apifyService->getRunStatus($scrapeRequest->apify_run_id);

            if ($runStatus['success']) {
                $data['apify_status'] = $runStatus['status'];
            }
        }

        return response()->json($data);
    }

    public function cancel(int $id): JsonResponse
    {
        $scrapeRequest = ScrapeRequest::findOrFail($id);

        if ($scrapeRequest->status !== 'pending' && $scrapeRequest->status !== 'running') {
            return response()->json([
                'message' => 'Cannot cancel scrape request with status: ' . $scrapeRequest->status,
            ], 400);
        }

        if ($scrapeRequest->apify_run_id) {
            $apifyService = new ApifyService();
            $apifyService->cancelRun($scrapeRequest->apify_run_id);
        }

        $scrapeRequest->cancel();

        return response()->json([
            'message' => 'Scrape request cancelled',
            'data' => $scrapeRequest,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $scrapeRequest = ScrapeRequest::findOrFail($id);
        $scrapeRequest->delete();

        return response()->json(['message' => 'Scrape request deleted']);
    }
}