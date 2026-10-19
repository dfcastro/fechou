<?php

namespace App\Http\Middleware;

use App\Services\MarketingFunnelService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackMarketingFunnel
{
    public function __construct(private MarketingFunnelService $funnel)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('POST') && $request->is('register')) {
            $this->funnel->record('register_submit');
        }

        $response = $next($request);

        if ($response->isSuccessful() && $request->isMethod('GET')) {
            if ($request->is('para-empresas')) {
                $this->funnel->record('landing_view');
            } elseif ($request->is('register')) {
                $this->funnel->record('register_view');
            }
        }

        return $response;
    }
}
