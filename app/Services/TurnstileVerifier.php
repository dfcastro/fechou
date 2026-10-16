<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class TurnstileVerifier
{
    public function isEnabled(): bool
    {
        return filled(config('services.turnstile.site_key'))
            || filled(config('services.turnstile.secret_key'));
    }

    /**
     * Fail closed when configured incompletely, token is missing/invalid,
     * or Cloudflare is unavailable. With both keys blank, the integration
     * is disabled and the registration-specific rate limit still applies.
     */
    public function passes(?string $token, ?string $ip): bool
    {
        if (! $this->isEnabled()) {
            return true;
        }

        if (! filled(config('services.turnstile.site_key'))
            || ! filled(config('services.turnstile.secret_key'))
            || ! is_string($token)
            || strlen($token) < 1
            || strlen($token) > 2048) {
            return false;
        }

        try {
            $response = Http::asForm()
                ->connectTimeout(3)
                ->timeout(5)
                ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                    'secret' => config('services.turnstile.secret_key'),
                    'response' => $token,
                    'remoteip' => $ip,
                ]);

            return $response->successful()
                && $response->json('success') === true;
        } catch (ConnectionException) {
            return false;
        }
    }
}
