<?php

namespace App\Models;



use Illuminate\Database\Eloquent\Casts\Attribute;
use App\Support\BrazilianInput;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
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
        'public_profile_enabled',
        'public_slug',
        'public_description',
        'public_services',
        'address',
        'address_number',
        'address_complement',
        'province',
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
            'public_profile_enabled' => 'boolean',
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
            set: fn($value) =>
                BrazilianInput::document($value)
        );
    }

    protected function phone(): Attribute
    {
        return Attribute::make(
            set: fn($value) =>
                BrazilianInput::phone($value)
        );
    }

    protected function whatsapp(): Attribute
    {
        return Attribute::make(
            set: fn($value) =>
                BrazilianInput::phone($value)
        );
    }

    protected function postalCode(): Attribute
    {
        return Attribute::make(
            set: fn($value) =>
                BrazilianInput::cep($value)
        );
    }

    protected function state(): Attribute
    {
        return Attribute::make(
            set: fn($value) =>
                BrazilianInput::state($value)
        );
    }

    public function scopePubliclyListed(
        Builder $query
    ): Builder {
        return $query
            ->where(
                'public_profile_enabled',
                true
            )
            ->whereNotNull('public_slug')
            ->whereNotNull('city');
    }

    public function publicPhoneDisplay(): ?string
    {
        $digits = preg_replace(
            '/\D+/',
            '',
            (string) $this->phone
        );

        if (!$digits) {
            return null;
        }

        if (strlen($digits) === 11) {
            return sprintf(
                '(%s) %s-%s',
                substr($digits, 0, 2),
                substr($digits, 2, 5),
                substr($digits, 7, 4)
            );
        }

        if (strlen($digits) === 10) {
            return sprintf(
                '(%s) %s-%s',
                substr($digits, 0, 2),
                substr($digits, 2, 4),
                substr($digits, 6, 4)
            );
        }

        return $this->phone;
    }

    public function publicWhatsAppUrl(): ?string
    {
        $number = preg_replace(
            '/\D+/',
            '',
            (string) $this->whatsapp
        );

        if (!$number) {
            return null;
        }

        if (strlen($number) <= 11) {
            $number = '55' . $number;
        }

        return 'https://wa.me/' . $number;
    }

    public function accessGrants(): HasMany
    {
        return $this->hasMany(
            BusinessAccessGrant::class
        );
    }

    public function activeAccessGrants(): HasMany
    {
        return $this
            ->accessGrants()
            ->active();
    }
}
