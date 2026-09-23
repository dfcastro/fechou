<?php

namespace App\Models;



use Illuminate\Database\Eloquent\Casts\Attribute;
use App\Support\BrazilianInput;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Business extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'name',
        'document',
        'email',
        'phone',
        'whatsapp',
        'logo_path',
        'address',
        'city',
        'state',
        'postal_code',
        'pix_key',
        'payment_collection_enabled',
        'payment_instructions',
        'onboarding_completed_at',
        'follow_up_enabled',
        'follow_up_sent_after_days',
        'follow_up_viewed_after_days',
        'follow_up_expiry_warning_days',
        'follow_up_cooldown_hours',
    ];

    protected function casts(): array
    {
        return [
            'onboarding_completed_at' => 'datetime',
            'payment_collection_enabled' => 'boolean',
            'follow_up_enabled' => 'boolean',
            'follow_up_sent_after_days' => 'integer',
            'follow_up_viewed_after_days' => 'integer',
            'follow_up_expiry_warning_days' => 'integer',
            'follow_up_cooldown_hours' => 'integer',
        ];
    }
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(
            Subscription::class
        );
    }

    public function currentSubscription(): HasOne
    {
        return $this
            ->hasOne(Subscription::class)
            ->whereIn(
                'status',
                ['trialing', 'active', 'past_due']
            )
            ->latestOfMany();
    }


    protected function document(): Attribute
    {
        return Attribute::make(
            set: fn ($value) =>
                BrazilianInput::document($value)
        );
    }

    protected function phone(): Attribute
    {
        return Attribute::make(
            set: fn ($value) =>
                BrazilianInput::phone($value)
        );
    }

    protected function whatsapp(): Attribute
    {
        return Attribute::make(
            set: fn ($value) =>
                BrazilianInput::phone($value)
        );
    }

    protected function postalCode(): Attribute
    {
        return Attribute::make(
            set: fn ($value) =>
                BrazilianInput::cep($value)
        );
    }

    protected function state(): Attribute
    {
        return Attribute::make(
            set: fn ($value) =>
                BrazilianInput::state($value)
        );
    }
}
