<?php

namespace App\Models;

use App\Enums\ImportStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Import extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_id',
        'external_import_id',
        'sent_at',
        'status',
        'payload',
        'total_offers',
        'processed_offers',
        'error',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'completed_at' => 'datetime',
            'payload' => 'array',
            'status' => ImportStatus::class,
            'total_offers' => 'integer',
            'processed_offers' => 'integer',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }

    public function offerRows(): array
    {
        return $this->payload['offers'] ?? [];
    }

    public function markProcessing(): void
    {
        $this->update([
            'status' => ImportStatus::Processing,
            'processed_offers' => 0,
            'error' => null,
        ]);
    }

    public function markCompleted(): void
    {
        $this->update([
            'status' => ImportStatus::Completed,
            'completed_at' => now(),
        ]);
    }

    public function markFailed(string $error): void
    {
        $this->update([
            'status' => ImportStatus::Failed,
            'error' => $error,
        ]);
    }
}
