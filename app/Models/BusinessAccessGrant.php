<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessAccessGrant extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'plan_id',
        'type',
        'starts_at',
        'ends_at',
        'reason',
        'granted_by',
        'revoked_at',
        'revoked_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'granted_by'
        );
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'revoked_by'
        );
    }

    public function scopeActive(
        Builder $query
    ): Builder {
        return $query
            ->whereNull('revoked_at')
            ->where(function (Builder $query) {
                $query
                    ->whereNull('starts_at')
                    ->orWhere(
                        'starts_at',
                        '<=',
                        now()
                    );
            })
            ->where(
                'ends_at',
                '>',
                now()
            );
    }

    public function isActive(): bool
    {
        if ($this->revoked_at !== null) {
            return false;
        }

        if (
            $this->starts_at !== null
            && $this->starts_at->isFuture()
        ) {
            return false;
        }

        return $this->ends_at->isFuture();
    }
}