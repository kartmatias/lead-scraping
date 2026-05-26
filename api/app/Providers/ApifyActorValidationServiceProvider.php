<?php

namespace App\Providers;

use App\Services\ApifyService;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Log;

class ApifyActorValidationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(ApifyService $apifyService): void
    {
        // Allow disabling validation via environment variable for local dev
        if ($this->app->environment('local') && env('APIFY_VALIDATION_DISABLED', false)) {
            Log::info('Apify actor validation skipped (APIFY_VALIDATION_DISABLED=true)');
            return;
        }

        $registry = new ApifyActorRegistry();
        $actors = $registry->getActors();
        $failedActors = [];

        foreach ($actors as $actor) {
            $result = $apifyService->getActorDetails($actor['id']);

            if (!$result['success']) {
                $failedActors[] = [
                    'id' => $actor['id'],
                    'name' => $actor['name'],
                    'error' => $result['error'],
                ];
                Log::error('Apify actor validation failed', [
                    'actor_id' => $actor['id'],
                    'error' => $result['error'],
                ]);
            } else {
                Log::info('Apify actor validated', [
                    'actor_id' => $actor['id'],
                    'name' => $result['name'] ?? $actor['name'],
                ]);
            }
        }

        if (!empty($failedActors)) {
            $failedList = implode(', ', array_map(fn($a) => "{$a['name']} ({$a['id']})", $failedActors));
            throw new \RuntimeException(
                "Apify actor validation failed. Missing or inaccessible actors: {$failedList}"
            );
        }
    }
}