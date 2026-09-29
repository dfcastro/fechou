<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectPlatformAdmin
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $user = $request->user();

        $isPlatformAdmin =
            $user?->is_admin === true
            && !$user->business()->exists();

        /*
         * Admin exclusivo da plataforma:
         * não deve navegar pela área operacional
         * de uma empresa.
         */
        if (
            $isPlatformAdmin
            && !$request->routeIs('admin.*')
        ) {
            return redirect()->route(
                'admin.metrics'
            );
        }

        return $next($request);
    }
}
