<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\ScrapeRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class LeadController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Lead::query()->orderBy('created_at', 'desc');

        if ($request->has('source_type')) {
            $query->where('source_type', $request->input('source_type'));
        }

        if ($request->has('scrape_request_id')) {
            $query->where('scrape_request_id', $request->input('scrape_request_id'));
        }

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('company', 'like', "%{$search}%");
            });
        }

        if ($request->has('has_email')) {
            $query->whereNotNull('email')->where('email', '!=', '');
        }

        if ($request->has('has_phone')) {
            $query->whereNotNull('phone')->where('phone', '!=', '');
        }

        if ($request->has('has_cnpj')) {
            $query->whereNotNull('cnpj')->where('cnpj', '!=', '');
        }

        $leads = $query->paginate($request->input('per_page', 50));

        return response()->json($leads);
    }

    public function show(int $id): JsonResponse
    {
        $lead = Lead::with('scrapeRequest')->findOrFail($id);

        return response()->json($lead);
    }

    public function destroy(int $id): JsonResponse
    {
        $lead = Lead::findOrFail($id);
        $lead->delete();

        return response()->json(['message' => 'Lead deleted']);
    }

    public function export(Request $request): Response|JsonResponse
    {
        $request->validate([
            'format' => 'nullable|in:json,csv',
            'source_type' => 'nullable|string',
            'scrape_request_id' => 'nullable|integer',
        ]);

        $query = Lead::query();

        if ($request->has('source_type')) {
            $query->where('source_type', $request->input('source_type'));
        }

        if ($request->has('scrape_request_id')) {
            $query->where('scrape_request_id', $request->input('scrape_request_id'));
        }

        $leads = $query->get();
        $format = $request->input('format', 'json');

        if ($format === 'csv') {
            $headers = ['Name', 'Email', 'Phone', 'Company', 'Position', 'Address', 'CNPJ', 'Website', 'Instagram', 'LinkedIn', 'Facebook'];
            $rows = $leads->map(function ($lead) {
                return [
                    $lead->name,
                    $lead->email,
                    $lead->phone,
                    $lead->company,
                    $lead->position,
                    $lead->address,
                    $lead->cnpj,
                    $lead->website,
                    $lead->instagram,
                    $lead->linkedin,
                    $lead->facebook,
                ];
            });

            $csv = implode(',', $headers)."\n";
            foreach ($rows as $row) {
                $csv .= implode(',', array_map(fn ($v) => '"'.str_replace('"', '""', $v ?? '').'"', $row))."\n";
            }

            return response($csv, 200, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="leads-'.date('Y-m-d').'.csv"',
            ]);
        }

        return response()->json([
            'count' => $leads->count(),
            'leads' => $leads,
        ]);
    }

    public function stats(): JsonResponse
    {
        $totalLeads = Lead::count();
        $leadsBySource = Lead::select('source_type', DB::raw('count(*) as count'))
            ->groupBy('source_type')
            ->pluck('count', 'source_type');

        $leadsWithEmail = Lead::whereNotNull('email')->where('email', '!=', '')->count();
        $leadsWithPhone = Lead::whereNotNull('phone')->where('phone', '!=', '')->count();
        $leadsWithCnpj = Lead::whereNotNull('cnpj')->where('cnpj', '!=', '')->count();

        $totalRequests = ScrapeRequest::count();
        $requestsByStatus = ScrapeRequest::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        return response()->json([
            'leads' => [
                'total' => $totalLeads,
                'by_source' => $leadsBySource,
                'with_email' => $leadsWithEmail,
                'with_phone' => $leadsWithPhone,
                'with_cnpj' => $leadsWithCnpj,
            ],
            'scrape_requests' => [
                'total' => $totalRequests,
                'by_status' => $requestsByStatus,
            ],
        ]);
    }
}
