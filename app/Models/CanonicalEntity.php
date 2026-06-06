<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class CanonicalEntity extends Model
{
    protected $fillable = [
        'name', 'cnpj', 'phone', 'email', 'website',
        'address', 'instagram_url', 'linkedin_url',
    ];

    public function leadLinks(): HasMany
    {
        return $this->hasMany(LeadLink::class);
    }

    public function leads(): HasManyThrough
    {
        return $this->hasManyThrough(Lead::class, LeadLink::class, 'canonical_entity_id', 'id', 'id', 'lead_id');
    }

    public function mergeFromLead(Lead $lead): void
    {
        $fill = array_filter([
            'name'          => $this->name ?? $lead->name,
            'phone'         => $this->phone ?? $lead->phone,
            'email'         => $this->email ?? $lead->email,
            'website'       => $this->website ?? $lead->website,
            'address'       => $this->address ?? $lead->address,
            'cnpj'          => $this->cnpj ?? $lead->cnpj,
            'instagram_url' => $this->instagram_url ?? $lead->instagram,
            'linkedin_url'  => $this->linkedin_url ?? $lead->linkedin,
        ], fn($v) => $v !== null);

        $this->update($fill);
    }
}
