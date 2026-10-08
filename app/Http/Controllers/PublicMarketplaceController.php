<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Client;
use App\Models\Quote;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicMarketplaceController extends Controller
{
    public function home(): View
    {
        $featuredBusinesses = Business::query()
            ->publiclyListed()
            ->latest()
            ->limit(6)
            ->get();

        $stats = [
            'businesses' => Business::query()->count(),
            'clients' => Client::query()->count(),
            'quotes' => Quote::query()->count(),
        ];

        return view(
            'marketplace.home',
            compact(
                'featuredBusinesses',
                'stats'
            )
        );
    }

    public function index(
        Request $request
    ): View {
        $service = trim(
            (string) $request->query(
                'servico',
                ''
            )
        );

        $city = trim(
            (string) $request->query(
                'cidade',
                ''
            )
        );

        $businesses = Business::query()
            ->publiclyListed()
            ->when(
                $service !== '',
                function ($query) use ($service) {
                    $query->where(
                        function ($query) use ($service) {
                            $query
                                ->where(
                                    'name',
                                    'like',
                                    '%' . $service . '%'
                                )
                                ->orWhere(
                                    'public_services',
                                    'like',
                                    '%' . $service . '%'
                                )
                                ->orWhere(
                                    'public_description',
                                    'like',
                                    '%' . $service . '%'
                                );
                        }
                    );
                }
            )
            ->when(
                $city !== '',
                fn ($query) =>
                    $query->where(
                        'city',
                        'like',
                        '%' . $city . '%'
                    )
            )
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return view(
            'marketplace.index',
            compact(
                'businesses',
                'service',
                'city'
            )
        );
    }

    public function show(
        string $slug
    ): View {
        $business = Business::query()
            ->publiclyListed()
            ->where(
                'public_slug',
                $slug
            )
            ->firstOrFail();

        return view(
            'marketplace.show',
            compact('business')
        );
    }
}
