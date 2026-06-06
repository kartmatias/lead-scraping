<?php

namespace App\Jobs;

use App\Models\CanonicalEntity;
use App\Models\Lead;
use App\Models\LeadLink;
use App\Models\ScrapeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class LinkLeadsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public ScrapeRequest $scrapeRequest) {}

    public function handle(): void
    {
        $this->scrapeRequest->leads()
            ->whereDoesntHave('link')
            ->each(fn (Lead $lead) => $this->linkLead($lead));
    }

    private function linkLead(Lead $lead): void
    {
        [$entity, $method, $score] = $this->findMatch($lead);

        if (! $entity) {
            $entity = CanonicalEntity::create([]);
            $method = 'new';
            $score  = 100;
        }

        $entity->mergeFromLead($lead);

        LeadLink::updateOrCreate(
            ['lead_id' => $lead->id],
            ['canonical_entity_id' => $entity->id, 'match_method' => $method, 'match_score' => $score]
        );
    }

    /** @return array{CanonicalEntity|null, string, int} */
    private function findMatch(Lead $lead): array
    {
        // 1. CNPJ — exact, certain
        if ($lead->cnpj) {
            $e = CanonicalEntity::where('cnpj', $lead->cnpj)->first();
            if ($e) return [$e, 'cnpj', 100];
        }

        // 2. Phone — normalize E.164, exact
        if ($lead->phone) {
            $phone = self::normalizePhone($lead->phone);
            $e = CanonicalEntity::where('phone', $phone)->first();
            if ($e) return [$e, 'phone', 100];
        }

        // 3. Email — exact
        if ($lead->email) {
            $e = CanonicalEntity::where('email', $lead->email)->first();
            if ($e) return [$e, 'email', 100];
        }

        // 4. Website — normalized, exact
        if ($lead->website) {
            $url = self::normalizeUrl($lead->website);
            $e = CanonicalEntity::where('website', $url)->first();
            if ($e) return [$e, 'website', 100];
        }

        // 5. Name fuzzy — only when name is present, threshold ≥ 85%
        if ($lead->name && strlen($lead->name) > 3) {
            $name = self::normalizeName($lead->name);
            $match = null;
            $bestScore = 0;

            CanonicalEntity::whereNotNull('name')
                ->each(function (CanonicalEntity $e) use ($name, &$match, &$bestScore) {
                    similar_text($name, self::normalizeName($e->name ?? ''), $pct);
                    if ($pct >= 85 && $pct > $bestScore) {
                        $bestScore = (int) $pct;
                        $match = $e;
                    }
                });

            if ($match) return [$match, 'name_fuzzy', $bestScore];
        }

        return [null, 'new', 100];
    }

    public static function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);

        // Brazil: add country code if missing
        if (strlen($digits) === 10 || strlen($digits) === 11) {
            $digits = '55' . $digits;
        }

        return '+' . $digits;
    }

    public static function normalizeUrl(string $url): string
    {
        $url = strtolower(trim($url));
        $url = preg_replace('#^https?://(www\.)?#', '', $url);
        return rtrim($url, '/');
    }

    public static function normalizeName(string $name): string
    {
        // uppercase, remove accents, collapse spaces
        $name = mb_strtoupper($name);
        $name = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
        return preg_replace('/\s+/', ' ', trim($name));
    }
}
