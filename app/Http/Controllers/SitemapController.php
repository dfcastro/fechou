<?php

namespace App\Http\Controllers;

use App\Models\Business;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $xml = Cache::remember('seo:sitemap:v1', now()->addHour(), function (): string {
            $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
            $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

            // Only public, indexable pages belong in the sitemap.
            foreach ([
                route('home'),
                route('for-businesses'),
                route('marketplace.index'),
            ] as $url) {
                $xml .= $this->urlEntry($url);
            }

            // Use exactly the same visibility rules as public company profiles.
            foreach (
                Business::query()
                    ->publiclyListed()
                    ->select(['id', 'public_slug', 'updated_at'])
                    ->orderBy('id')
                    ->cursor() as $business
            ) {
                $xml .= $this->urlEntry(
                    route('marketplace.show', $business->public_slug),
                    $business->updated_at?->toAtomString()
                );
            }

            return $xml.'</urlset>'."\n";
        });

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }

    private function urlEntry(string $url, ?string $lastModified = null): string
    {
        $escapedUrl = htmlspecialchars($url, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        return '  <url><loc>'.$escapedUrl.'</loc>'
            .($lastModified ? '<lastmod>'.$lastModified.'</lastmod>' : '')
            .'</url>'."\n";
    }
}
