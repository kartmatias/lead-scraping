<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lead extends Model
{
    protected $fillable = [
        'scrape_request_id',
        'source_type',
        'source_id',
        'name',
        'email',
        'phone',
        'company',
        'position',
        'address',
        'cnpj',
        'website',
        'instagram',
        'linkedin',
        'facebook',
        'raw_data',
    ];

    protected $casts = [
        'raw_data' => 'array',
    ];

    public function scrapeRequest(): BelongsTo
    {
        return $this->belongsTo(ScrapeRequest::class);
    }

    public static function upsertFromScrapeData(array $data, int $scrapeRequestId): self
    {
        $sourceType = $data['source_type'] ?? 'unknown';
        $sourceId = $data['source_id'] ?? $data['id'] ?? uniqid();

        $leadData = [
            'scrape_request_id' => $scrapeRequestId,
            'source_type' => $sourceType,
            'source_id' => (string) $sourceId,
            'name' => $data['name'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? $data['phoneNumber'] ?? null,
            'company' => $data['company'] ?? $data['businessName'] ?? null,
            'position' => $data['position'] ?? $data['title'] ?? null,
            'address' => $data['address'] ?? $data['location'] ?? null,
            'cnpj' => $data['cnpj'] ?? null,
            'website' => $data['website'] ?? null,
            'instagram' => $data['instagram'] ?? null,
            'linkedin' => $data['linkedin'] ?? $data['linkedinUrl'] ?? null,
            'facebook' => $data['facebook'] ?? null,
            'raw_data' => $data,
        ];

        self::upsert(
            [$leadData],
            uniqueBy: ['source_type', 'source_id'],
            update: array_keys(array_diff_key($leadData, ['source_type' => '', 'source_id' => '', 'scrape_request_id' => '']))
        );

        return self::where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->first();
    }
}