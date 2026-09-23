<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuoteTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'name',
        'title',
        'description',
        'validity_days',
        'discount',
        'notes',
    ];


    protected function casts(): array
    {
        return [
            'validity_days' => 'integer',
            'discount' => 'decimal:2',
        ];
    }


    public function business(): BelongsTo
    {
        return $this->belongsTo(
            Business::class
        );
    }


    public function items(): HasMany
    {
        return $this
            ->hasMany(
                QuoteTemplateItem::class
            )
            ->orderBy('sort_order');
    }
}
