<?php

namespace App\Models;



use Illuminate\Database\Eloquent\Casts\Attribute;
use App\Support\BrazilianInput;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Client extends Model
{
    use SoftDeletes;
    use HasFactory;
    protected $fillable = [
        'business_id',
        'name',
        'document',
        'email',
        'phone',
        'whatsapp',
        'notes',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
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
}
