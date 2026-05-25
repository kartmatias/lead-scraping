<?php

namespace App\Providers;

class ApifyActorRegistry
{
    /**
     * List of all Apify actors with their configurations.
     *
     * @return array<int, array{id: string, source: string}>
     */
    public function getActors(): array
    {
        return [
            [
                'id' => 'compass/crawler-google-places',
                'source' => 'google_maps',
            ],
            [
                'id' => 'apify/instagram-scraper',
                'source' => 'instagram',
            ],
            [
                'id' => 'dev_fusion/linkedin-profile-scraper',
                'source' => 'linkedin',
            ],
            [
                'id' => 'parseforge/brazil-cnpj-scraper',
                'source' => 'cnpj',
            ],
        ];
    }

    /**
     * Get just the actor IDs.
     *
     * @return array<int, string>
     */
    public function getActorIds(): array
    {
        return array_column($this->getActors(), 'id');
    }
}