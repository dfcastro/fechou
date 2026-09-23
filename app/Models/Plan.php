<?php

namespace App\Models;

use App\Enums\PlanFeature;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory;


    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'billing_interval',
        'quote_limit',
        'is_active',
        'sort_order',
        'features',
    ];


    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',

            'quote_limit' => 'integer',

            'is_active' => 'boolean',

            'sort_order' => 'integer',

            'features' => 'array',
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Relacionamentos
    |--------------------------------------------------------------------------
    */

    public function subscriptions(): HasMany
    {
        return $this->hasMany(
            Subscription::class
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Tipo do plano
    |--------------------------------------------------------------------------
    */

    public function isFree(): bool
    {
        return (float) $this->price === 0.0;
    }


    public function hasUnlimitedQuotes(): bool
    {
        return $this->quote_limit === null;
    }


    /*
    |--------------------------------------------------------------------------
    | Recursos
    |--------------------------------------------------------------------------
    */

    public function hasFeature(
        PlanFeature|string $feature
    ): bool {
        $feature = $feature instanceof PlanFeature
            ? $feature->value
            : $feature;


        return in_array(
            $feature,
            $this->features ?? [],
            true
        );
    }


    public function hasAllFeatures(
        array $features
    ): bool {
        foreach ($features as $feature) {

            if (!$this->hasFeature($feature)) {
                return false;
            }

        }


        return true;
    }
}