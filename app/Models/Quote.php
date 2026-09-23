<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Quote extends Model
{
    use SoftDeletes;
    use HasFactory;
    protected $fillable = [
        'business_id',
        'client_id',
        'number',
        'public_token',
        'title',
        'description',
        'subtotal',
        'discount',
        'total',
        'status',
        'valid_until',
        'sent_at',
        'first_viewed_at',
        'accepted_at',
        'rejected_at',
        'notes',
        'root_quote_id',
        'version',
        'payment_status',
        'paid_at',
        'payment_collection_enabled',
        'execution_status',
        'execution_started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'total' => 'decimal:2',

            'valid_until' => 'date',
            'sent_at' => 'datetime',
            'first_viewed_at' => 'datetime',
            'accepted_at' => 'datetime',
            'rejected_at' => 'datetime',

            'version' => 'integer',

            'payment_status' => 'string',
            'paid_at' => 'datetime',
            'payment_collection_enabled' => 'boolean',

            'execution_status' => 'string',
            'execution_started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function rootQuote()
    {
        return $this->belongsTo(
            Quote::class,
            'root_quote_id'
        );
    }

    public function versions()
    {
        return $this->hasMany(
            Quote::class,
            'root_quote_id'
        )->orderBy('version');
    }

    protected static function booted(): void
    {
        static::creating(function (Quote $quote) {
            if (blank($quote->public_token)) {
                $quote->public_token = Str::random(48);
            }
        });
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class)->orderBy('sort_order');
    }

    public function events(): HasMany
    {
        return $this->hasMany(QuoteEvent::class);
    }
}
