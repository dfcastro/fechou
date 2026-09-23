<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'plan_id',
        'status',
        'starts_at',
        'trial_ends_at',
        'current_period_starts_at',
        'current_period_ends_at',
        'canceled_at',
        'ends_at',

        'payment_provider',
        'billing_status',

        'provider_customer_id',
        'provider_subscription_id',
        'provider_checkout_id',
        'provider_checkout_status',
        'provider_payment_id',
        'provider_payment_status',

        'past_due_at',
        'grace_ends_at',
        'access_suspended_at',
        'last_payment_confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'current_period_starts_at' => 'datetime',
            'current_period_ends_at' => 'datetime',
            'canceled_at' => 'datetime',
            'ends_at' => 'datetime',

            'past_due_at' => 'datetime',
            'grace_ends_at' => 'datetime',
            'access_suspended_at' => 'datetime',
            'last_payment_confirmed_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(
            Business::class
        );
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(
            Plan::class
        );
    }

    public function isActive(): bool
    {
        if ($this->status === 'trialing') {
            return $this->isTrialing();
        }

        return $this->status === 'active';
    }

    public function isTrialing(): bool
    {
        return $this->status === 'trialing'
            && $this->trial_ends_at?->isFuture();
    }

    public function isInGracePeriod(): bool
    {
        return $this->status === 'past_due'
            && $this->grace_ends_at?->isFuture()
            && $this->access_suspended_at === null;
    }

    public function hasScheduledCancellation(): bool
    {
        return $this->canceled_at !== null
            && $this->ends_at?->isFuture();
    }
}
