# Apify Actor Validator Bootstrapper

## Overview

A Laravel service provider that runs at application boot time, verifying all configured Apify actors are accessible and exist on the Apify platform. Fails fast if any actor is missing or inaccessible.

## Background

The application uses four Apify actors for lead scraping:
- `compass/crawler-google-places` (Google Maps)
- `apify/instagram-scraper` (Instagram)
- `dev_fusion/linkedin-profile-scraper` (LinkedIn)
- `parseforge/brazil-cnpj-scraper` (CNPJ)

These are currently hardcoded in `RunApifyActor.php`. Need validation at startup to fail fast if an actor is removed or unavailable.

## Design

### Components

1. **ApifyActorRegistry** - Configuration class defining all required actors
2. **ApifyService extension** - Add `getActorDetails(string $actorId)` method
3. **ApifyActorValidationServiceProvider** - Laravel service provider that runs at boot

### Data Flow

```
App Boots
    → ApifyActorValidationServiceProvider::boot()
    → Get all actor IDs from ApifyActorRegistry
    → For each actor: call $apifyService->getActorDetails($actorId)
    → If any actor fails (not found, inaccessible):
        → Log error with actor ID
        → Throw RuntimeException with list of failed actors
    → If all succeed: log success for each verified actor
```

### API Changes

Add to `ApifyService`:

```php
public function getActorDetails(string $actorId): array
{
    try {
        $response = $this->client->get("/acts/{$actorId}");
        $data = json_decode($response->getBody()->getContents(), true);

        return [
            'success' => true,
            'actor_id' => $data['data']['id'] ?? $actorId,
            'name' => $data['data']['name'] ?? null,
            'description' => $data['data']['description'] ?? null,
            'version' => $data['data']['version'] ?? null,
        ];
    } catch (RequestException $e) {
        return [
            'success' => false,
            'error' => $e->getMessage(),
        ];
    }
}
```

### Configuration

`ApifyActorRegistry` returns array of actor configurations:

```php
return [
    ['id' => 'compass/crawler-google-places', 'name' => 'Google Maps', 'source' => 'google_maps'],
    ['id' => 'apify/instagram-scraper', 'name' => 'Instagram', 'source' => 'instagram'],
    ['id' => 'dev_fusion/linkedin-profile-scraper', 'name' => 'LinkedIn', 'source' => 'linkedin'],
    ['id' => 'parseforge/brazil-cnpj-scraper', 'name' => 'CNPJ', 'source' => 'cnpj'],
];
```

### Error Messages

- **Actor not found**: `Apify actor '{actorId}' not found or not accessible`
- **Network error**: `Failed to validate Apify actors: {error message}`

### Testing

1. **Unit test**: `getActorDetails` returns actor metadata on success
2. **Unit test**: `getActorDetails` returns failure on missing actor
3. **Unit test**: Service provider throws exception when any actor is missing
4. **Unit test**: Service provider succeeds when all actors are valid

## Acceptance Criteria

- [ ] App fails to boot if any configured actor is missing
- [ ] Error message clearly identifies which actor(s) failed
- [ ] All current actors are validated at startup
- [ ] Validation can be disabled via environment variable for local development