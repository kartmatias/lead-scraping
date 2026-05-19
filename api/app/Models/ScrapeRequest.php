<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScrapeRequest extends Model
{
    protected $fillable = [
        'source',
        'status',
        'filters',
        'apify_run_id',
        'apify_dataset_id',
        'total_leads',
        'completed_leads',
        'failed_leads',
        'started_at',
        'completed_at',
        'error_message',
    ];

    protected $casts = [
        'filters' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function startScrape(): void
    {
        $this->update([
            'status' => 'running',
            'started_at' => now(),
        ]);
    }

    public function complete(int $totalLeads): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'total_leads' => $totalLeads,
        ]);
    }

    public function fail(string $message): void
    {
        $this->update([
            'status' => 'failed',
            'completed_at' => now(),
            'error_message' => $message,
        ]);
    }

    public function cancel(): void
    {
        $this->update([
            'status' => 'cancelled',
            'completed_at' => now(),
        ]);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isRunning(): bool
    {
        return $this->status === 'running';
    }
}