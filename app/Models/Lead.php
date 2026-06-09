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

    public function link(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(LeadLink::class);
    }

    public static function upsertFromScrapeData(array $data, int $scrapeRequestId): self
    {
        $mapped = self::mapSourceFields($data);

        $leadData = array_merge($mapped, [
            'scrape_request_id' => $scrapeRequestId,
            'raw_data' => json_encode($data),
        ]);

        $updateColumns = ['name', 'email', 'phone', 'company', 'position', 'address', 'cnpj', 'website', 'instagram', 'linkedin', 'facebook', 'raw_data', 'updated_at'];

        self::upsert(
            [$leadData],
            uniqueBy: ['source_type', 'source_id'],
            update: $updateColumns
        );

        return self::where('source_type', $mapped['source_type'])
            ->where('source_id', $mapped['source_id'])
            ->first();
    }

    private static function mapSourceFields(array $data): array
    {
        // google_maps
        if (isset($data['placeId'])) {
            $endereco = $data['endereco'] ?? null;
            return [
                'source_type' => 'google_maps',
                'source_id'   => $data['placeId'],
                'name'        => $data['title'] ?? null,
                'phone'       => $data['phone'] ?? $data['phoneUnformatted'] ?? null,
                'address'     => $data['address'] ?? null,
                'website'     => $data['website'] ?? null,
                'company'     => $data['title'] ?? null,
                'position'    => $data['categoryName'] ?? null,
                'email'       => null,
                'cnpj'        => null,
                'instagram'   => null,
                'linkedin'    => null,
                'facebook'    => str_contains($data['website'] ?? '', 'facebook') ? $data['website'] : null,
            ];
        }

        // cnpj
        if (isset($data['cnpj'], $data['razaoSocial'])) {
            $end = $data['endereco'] ?? [];
            $address = implode(', ', array_filter([
                $end['logradouro'] ?? null,
                $end['numero'] ?? null,
                $end['bairro'] ?? null,
                $end['municipio'] ?? null,
                $end['uf'] ?? null,
            ]));

            return [
                'source_type' => 'cnpj',
                'source_id'   => $data['cnpj'],
                'name'        => $data['razaoSocial'] ?? null,
                'company'     => $data['nomeFantasia'] ?? $data['razaoSocial'] ?? null,
                'phone'       => $data['telefone'] ?? null,
                'address'     => $address ?: null,
                'cnpj'        => $data['cnpj'],
                'email'       => $data['email'] ?? null,
                'website'     => $data['website'] ?? null,
                'position'    => null,
                'instagram'   => null,
                'linkedin'    => null,
                'facebook'    => null,
            ];
        }

        // instagram
        if (isset($data['username']) || isset($data['ownerUsername'])) {
            $username = $data['username'] ?? $data['ownerUsername'];
            return [
                'source_type' => 'instagram',
                'source_id'   => $data['id'] ?? $username,
                'name'        => $data['fullName'] ?? $data['ownerFullName'] ?? $username,
                'company'     => null,
                'phone'       => $data['businessPhoneNumber'] ?? null,
                'address'     => $data['businessAddress'] ?? null,
                'email'       => $data['businessEmail'] ?? null,
                'website'     => $data['externalUrl'] ?? null,
                'instagram'   => "https://www.instagram.com/{$username}/",
                'position'    => null,
                'cnpj'        => null,
                'linkedin'    => null,
                'facebook'    => null,
            ];
        }

        // linkedin
        if (isset($data['linkedinUrl']) || isset($data['profileUrl'])) {
            return [
                'source_type' => 'linkedin',
                'source_id'   => $data['linkedinUrl'] ?? $data['profileUrl'],
                'name'        => trim(($data['firstName'] ?? '').' '.($data['lastName'] ?? '')) ?: ($data['name'] ?? null),
                'company'     => $data['currentCompany'] ?? $data['company'] ?? null,
                'position'    => $data['title'] ?? $data['headline'] ?? null,
                'phone'       => $data['phone'] ?? null,
                'email'       => $data['email'] ?? null,
                'address'     => $data['location'] ?? null,
                'website'     => null,
                'linkedin'    => $data['linkedinUrl'] ?? $data['profileUrl'],
                'cnpj'        => null,
                'instagram'   => null,
                'facebook'    => null,
            ];
        }

        // fallback
        return [
            'source_type' => $data['source_type'] ?? 'unknown',
            'source_id'   => (string) ($data['source_id'] ?? $data['id'] ?? uniqid()),
            'name'        => $data['name'] ?? null,
            'email'       => $data['email'] ?? null,
            'phone'       => $data['phone'] ?? null,
            'company'     => $data['company'] ?? null,
            'position'    => $data['position'] ?? null,
            'address'     => $data['address'] ?? null,
            'cnpj'        => isset($data['cnpj']) ? (string) $data['cnpj'] : null,
            'website'     => $data['website'] ?? null,
            'instagram'   => $data['instagram'] ?? null,
            'linkedin'    => $data['linkedin'] ?? null,
            'facebook'    => $data['facebook'] ?? null,
        ];
    }
}
