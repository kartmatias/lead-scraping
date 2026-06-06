<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadLink extends Model
{
    protected $fillable = ['canonical_entity_id', 'lead_id', 'match_method', 'match_score'];

    public function canonicalEntity(): BelongsTo
    {
        return $this->belongsTo(CanonicalEntity::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
