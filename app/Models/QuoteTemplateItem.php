<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuoteTemplateItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'quote_template_id',
        'type',
        'description',
        'quantity',
        'unit',
        'unit_price',
        'sort_order',
    ];


    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }


    public function quoteTemplate(): BelongsTo
    {
        return $this->belongsTo(
            QuoteTemplate::class
        );
    }
}
