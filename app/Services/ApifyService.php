<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class ApifyService
{
    private Client $client;

    private string $token;

    private const BASE_URL = 'https://api.apify.com';

    public function __construct()
    {
        $this->token = config('services.apify.token', env('APIFY_TOKEN'));

        $this->client = new Client([
            'base_uri' => self::BASE_URL,
            'headers' => [
                'Authorization' => "Bearer {$this->token}",
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    public function startActorRun(string $actorId, array $input): array
    {
        try {
            $encodedId = str_replace('/', '~', $actorId);
            $response = $this->client->post("/v2/acts/{$encodedId}/runs", [
                'json' => $input,
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            return [
                'success' => true,
                'run_id' => $data['data']['id'] ?? null,
                'dataset_id' => $data['data']['defaultDatasetId'] ?? null,
                'status' => $data['data']['status'] ?? 'UNKNOWN',
            ];
        } catch (RequestException $e) {
            Log::error('Apify startActorRun failed', [
                'actor_id' => $actorId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function getRunStatus(string $runId): array
    {
        try {
            $response = $this->client->get("/v2/actor-runs/{$runId}");
            $data = json_decode($response->getBody()->getContents(), true);

            return [
                'success' => true,
                'status' => $data['data']['status'] ?? 'UNKNOWN',
                'dataset_id' => $data['data']['datasetId'] ?? null,
                'finished_at' => $data['data']['finishedAt'] ?? null,
            ];
        } catch (RequestException $e) {
            Log::error('Apify getRunStatus failed', [
                'run_id' => $runId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function getDatasetItems(string $datasetId, int $limit = 100, int $offset = 0): array
    {
        try {
            $response = $this->client->get("/v2/datasets/{$datasetId}/items", [
                'query' => [
                    'limit' => $limit,
                    'offset' => $offset,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            return [
                'success' => true,
                'items' => $data,
                'count' => count($data),
            ];
        } catch (RequestException $e) {
            Log::error('Apify getDatasetItems failed', [
                'dataset_id' => $datasetId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function getAllDatasetItems(string $datasetId): array
    {
        $allItems = [];
        $offset = 0;
        $limit = 100;

        do {
            $result = $this->getDatasetItems($datasetId, $limit, $offset);

            if (! $result['success']) {
                return $result;
            }

            $items = $result['items'];
            $allItems = array_merge($allItems, $items);
            $offset += $limit;
        } while (count($items) === $limit);

        return [
            'success' => true,
            'items' => $allItems,
            'count' => count($allItems),
        ];
    }

    public function cancelRun(string $runId): array
    {
        try {
            $response = $this->client->post("/v2/actor-runs/{$runId}/abort");
            $data = json_decode($response->getBody()->getContents(), true);

            return [
                'success' => true,
                'status' => $data['data']['status'] ?? 'ABORTED',
            ];
        } catch (RequestException $e) {
            Log::error('Apify cancelRun failed', [
                'run_id' => $runId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function getActorDetails(string $actorId): array
    {
        try {
            $encodedId = str_replace('/', '~', $actorId);
            $response = $this->client->get("/v2/acts/{$encodedId}");
            $data = json_decode($response->getBody()->getContents(), true);

            return [
                'success' => true,
                'actor_id' => $data['data']['id'] ?? $actorId,
                'name' => $data['data']['name'] ?? null,
                'description' => $data['data']['description'] ?? null,
                'version' => $data['data']['version'] ?? null,
            ];
        } catch (RequestException $e) {
            Log::error('Apify getActorDetails failed', [
                'actor_id' => $actorId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
