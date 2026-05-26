<?php

namespace App\Providers;

class ApifyActorRegistry
{
    /**
     * List of all Apify actors with their configurations.
     *
     * @return array<int, array{id: string, name: string, source: string}>
     */
    public function getActors(): array
    {
        return [
            [
                'id' => 'compass/crawler-google-places',
                'name' => 'Google Maps',
                'source' => 'google_maps',
            ],
            [
                'id' => 'apify/instagram-scraper',
                'name' => 'Instagram',
                'source' => 'instagram',
            ],
            [
                'id' => 'dev_fusion/linkedin-profile-scraper',
                'name' => 'LinkedIn',
                'source' => 'linkedin',
            ],
            [
                'id' => 'parseforge/brazil-cnpj-scraper',
                'name' => 'CNPJ',
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