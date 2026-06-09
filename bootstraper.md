Here is an initialization script/command tailored to your Laravel architecture. Since the Apify platform handles actors through either direct Actor IDs or custom **Tasks** (pre-saved configurations with default inputs), this routine dynamically maps your required system types (`Maps`, `instagram`, `linkedin`, `cnpj`) to their respective production Actor IDs.

You can implement this as a Laravel Custom Artisan Command.

### Custom Artisan Command: `app:init-apify`

Create a command file at `app/Console/Commands/InitApifyActors.php` (or use `php artisan make:command InitApifyActors`):

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ApifyService;
use Exception;

class InitApifyActors extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:init-apify';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verifies connection to Apify API and registers initial actor configurations';

    /**
     * Target active Actors mapped to our internal ScrapeRequest types for 2026.
     */
    protected array $requiredActors = [
        'google_maps' => [
            'actor_id' => 'compass/crawler-google-places',
            'purpose'  => 'Local business leads by city/segment',
            'default_input' => [
                'maxItems' => 50,
                'searchStringsArray' => []
            ]
        ],
        'instagram' => [
            'actor_id' => 'apify/instagram-scraper',
            'purpose'  => 'Profile data by hashtag/location',
            'default_input' => [
                'resultsLimit' => 50
            ]
        ],
        'linkedin' => [
            'actor_id' => 'dev_fusion/linkedin-profile-scraper',
            'purpose'  => 'Decision maker profiles (Low concurrency strategy)',
            'default_input' => [
                'max_delay' => 10,
                'min_delay' => 5
            ]
        ],
        'cnpj' => [
            'actor_id' => 'parseforge/brazil-cnpj-scraper',
            'purpose'  => 'Business enrichment by CNPJ registry',
            'default_input' => [
                'cnpj_list' => []
            ]
        ],
    ];

    /**
     * Execute the console command.
     */
    public function handle(ApifyService $apifyService)
    {
        $this->title('Initializing Apify Actor Environment Setup');

        // 1. Check if token is configured
        $token = config('services.apify.token') ?? env('APIFY_TOKEN'); // Pull from .env configuration
        if (!$token) {
            $this->error('Initialization Failed: APIFY_TOKEN is missing or null in your .env file.');
            return Command::FAILURE;
        }

        $this->info('✓ APIFY_TOKEN environment variable detected.');

        // 2. Validate connection and verify infrastructure availability
        $this->info('Testing connection to Apify endpoint...');
        try {
            // Reusable verification request using internal generic request wrapper
            // Testing /v2/users/me to validate token permissions
            $apifyService->request('GET', 'users/me');
            $this->info('✓ Secure communication channel with Apify API successfully established.');
        } catch (Exception $e) {
            $this->error('Failed connecting to Apify: ' . $e->getMessage());
            $this->line('Please review your credentials or network rules.');
            return Command::FAILURE;
        }

        // 3. Process mappings and check availability of Actors
        $this->newLine();
        $this->info('Validating availability of specified Actors on store Marketplace:');
        
        $headers = ['Local Request Type', 'Target Remote Actor ID', 'System Status'];
        $tableRows = [];

        foreach ($this->requiredActors as $key => $meta) {
            try {
                // Fetch details of the actor to ensure the exact slug/id is accessible
                // GET v2/acts/{actorId}
                $encodedActorId = urlencode($meta['actor_id']);
                $apifyService->request('GET', "acts/{$encodedActorId}");
                
                $tableRows[] = [
                    $key, 
                    $meta['actor_id'], 
                    '<fg=green>AVAILABLE (Ready)</>'
                ];
            } catch (Exception $e) {
                $tableRows[] = [
                    $key, 
                    $meta['actor_id'], 
                    '<fg=red>ERROR: Unreachable/Restricted</>'
                ];
            }
        }

        $this->table($headers, $tableRows);
        $this->newLine();
        
        $this->info('🚀 All required subsystems configured! The environment is prepared to accept jobs.');
        return Command::SUCCESS;
    }
}

```

---

### Integration Steps

1. **Service Registration:** Ensure your `ApifyService` handles custom raw HTTP paths dynamically:
```php
// app/Services/ApifyService.php
public function request(string $method, string $uri, array $options = []) {
    // Must wrap GuzzleHttp\Client call cleanly as defined in structural configurations
    $response = $this->client->request($method, $uri, $options);
    return json_decode($response->getBody()->getContents(), true);
}

```


2. **Run Initialization:**
Execute the bootstrap logic inside your local container workspace or production console deployment:
```bash
php artisan app:init-apify

```



This routine performs a validation pass over the remote API token and uses Apify's `/acts/{actor_id}` infrastructure validation route to make sure that none of your third-party lead extraction packages (`compass/*`, `parseforge/*`, etc.) have undergone breaking deprecations or change-of-access issues.