<?php

namespace App\Http\Controllers;

use App\Models\Business;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicMarketplaceController extends Controller
{
    public function home(): View
    {
        $featuredBusinesses = Business::query()
            ->publiclyListed()
            ->with([
                'services.category',
            ])
            ->latest()
            ->limit(6)
            ->get();

        return view(
            'marketplace.home',
            compact(
                'featuredBusinesses'
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
            ->with([
                'services.category',
            ])
            ->when(
                $service !== '',
                function ($query) use ($service) {
                    $like = '%'.$service.'%';

                    $query->where(
                        function ($query) use ($like) {
                            $query
                                ->where(
                                    'businesses.name',
                                    'like',
                                    $like
                                )
                                ->orWhere(
                                    'public_services',
                                    'like',
                                    $like
                                )
                                ->orWhere(
                                    'public_description',
                                    'like',
                                    $like
                                )
                                ->orWhereHas(
                                    'services',
                                    function ($query) use ($like) {
                                        $query
                                            ->where(
                                                'services.name',
                                                'like',
                                                $like
                                            )
                                            ->orWhere(
                                                'services.slug',
                                                'like',
                                                $like
                                            )
                                            ->orWhere(
                                                'services.search_terms',
                                                'like',
                                                $like
                                            )
                                            ->orWhereHas(
                                                'category',
                                                function ($query) use ($like) {
                                                    $query
                                                        ->where(
                                                            'service_categories.name',
                                                            'like',
                                                            $like
                                                        )
                                                        ->orWhere(
                                                            'service_categories.slug',
                                                            'like',
                                                            $like
                                                        )
                                                        ->orWhere(
                                                            'service_categories.search_terms',
                                                            'like',
                                                            $like
                                                        );
                                                }
                                            );
                                    }
                                );
                        }
                    );
                }
            )
            ->when(
                $city !== '',
                fn ($query) => $query->where(
                    'city',
                    'like',
                    '%'.$city.'%'
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
            ->with([
                'services.category',
            ])
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
