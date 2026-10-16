<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class ThrottleRegistrations
{
    /**
     * Restrict only account creation, not logins, verification or password resets.
     * Both successful and invalid attempts count towards the limit.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('POST') || ! $request->is('register')) {
            return $next($request);
        }

        $prefix = 'register:'.hash('sha256', $request->ip() ?? 'unknown');

        $limits = [
            $prefix.':minute' => [5, 60],
            $prefix.':hour' => [20, 3600],
        ];

        // Defense in depth: unlike X-Forwarded-For, REMOTE_ADDR reflects the
        // transport peer and cannot be chosen by an arbitrary request header.
        // Use a higher threshold because reverse proxies can share one peer IP.
        $transportPeer = $request->server('REMOTE_ADDR');

        if (is_string($transportPeer) && $transportPeer !== '') {
            $peerKey = 'register:peer:'.hash('sha256', $transportPeer);
            $limits[$peerKey.':hour'] = [100, 3600];
        }

        $retryAfter = 0;

        foreach ($limits as $key => [$maxAttempts]) {
            if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
                $retryAfter = max($retryAfter, RateLimiter::availableIn($key));
            }
        }

        if ($retryAfter > 0) {
            return response(
                'Muitas tentativas de cadastro. Aguarde alguns minutos e tente novamente.',
                429
            )->header('Retry-After', (string) $retryAfter);
        }

        foreach ($limits as $key => [, $decaySeconds]) {
            RateLimiter::hit($key, $decaySeconds);
        }

        return $next($request);
    }
}
