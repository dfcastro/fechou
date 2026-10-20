<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Anonymous operational counters: no IPs, user IDs, click IDs, referrers
 * or form contents are written to the reporting tables.
 *
 * The existing Laravel session remembers only the coarse source category
 * for a maximum of 30 minutes to attribute the steps of one registration
 * journey. We do not set another cookie or retain the Google click ID.
 *
 * Neither table represents unique visitors, nor is this a verified
 * Google Ads conversion attribution system.
 */
class MarketingFunnelService
{
    public const EVENTS = [
        'landing_view',
        'cta_click',
        'register_view',
        'register_submit',
        'register_invalid',
        'turnstile_rejected',
        'register_throttled',
        'registered',
    ];

    public const CHANNELS = [
        'google_ads',
        'unattributed',
    ];

    public function record(string $event): void
    {
        if (! in_array($event, self::EVENTS, true)) {
            return;
        }

        // The administrator's authenticated checks must not inflate the
        // commercial funnel. Private browsing without login is anonymous
        // and therefore cannot be recognized as an admin visit.
        if (request()->user()?->is_admin === true) {
            return;
        }

        try {
            $channel = $this->channelFor(request());
            $day = now()->toDateString();

            DB::transaction(function () use ($event, $day, $channel): void {
                DB::table('marketing_funnel_daily_counts')->upsert(
                    [[
                        'day' => $day,
                        'event' => $event,
                        'total' => 1,
                    ]],
                    ['day', 'event'],
                    ['total' => DB::raw('total + 1')]
                );

                DB::table('marketing_funnel_channel_daily_counts')->upsert(
                    [[
                        'day' => $day,
                        'source' => $channel,
                        'event' => $event,
                        'total' => 1,
                    ]],
                    ['day', 'source', 'event'],
                    ['total' => DB::raw('total + 1')]
                );
            });
        } catch (Throwable $exception) {
            // Reporting cannot interrupt a valid registration or a landing visit.
            report($exception);
        }
    }

    /**
     * Only the presence of a Google click parameter is inspected, never its
     * value. Manual UTMs are accepted only when they identify Google + paid
     * search. This is an indication of source, not fraud-proof attribution.
     */
    private function channelFor(Request $request): string
    {
        // Command-line jobs and direct service invocations have no HTTP
        // session. Their counters are still valid, but unattributed.
        if (! $request->hasSession()) {
            return 'unattributed';
        }

        $hasGoogleClickMarker = false;

        foreach (['gclid', 'gbraid', 'wbraid'] as $parameter) {
            $value = $request->query($parameter);
            if (is_string($value) && $value !== '') {
                $hasGoogleClickMarker = true;
                break;
            }
        }

        $source = $request->query('utm_source');
        $medium = $request->query('utm_medium');

        $hasGooglePaidUtm = is_string($source)
            && is_string($medium)
            && strtolower($source) === 'google'
            && in_array(strtolower($medium), ['cpc', 'ppc', 'paidsearch', 'paid_search'], true);

        if ($hasGoogleClickMarker || $hasGooglePaidUtm) {
            $request->session()->put('marketing_funnel_source', [
                'source' => 'google_ads',
                'expires_at' => now()->addMinutes(30)->getTimestamp(),
            ]);

            return 'google_ads';
        }

        $sessionSource = $request->session()->get('marketing_funnel_source');

        if (is_array($sessionSource)
            && ($sessionSource['source'] ?? null) === 'google_ads'
            && is_numeric($sessionSource['expires_at'] ?? null)
            && (int) $sessionSource['expires_at'] >= now()->getTimestamp()) {
            return 'google_ads';
        }

        return 'unattributed';
    }

    public function summary(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        return $this->totals(
            'marketing_funnel_daily_counts',
            $start,
            $end
        );
    }

    /**
     * These counts start with the channel migration, whereas summary()
     * includes the earlier unsplit history. Do not backfill attribution.
     */
    public function summaryByChannel(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        $summary = [];

        foreach (self::CHANNELS as $channel) {
            $summary[$channel] = array_fill_keys(self::EVENTS, 0);
        }

        try {
            $rows = DB::table('marketing_funnel_channel_daily_counts')
                ->select(['source', 'event'])
                ->selectRaw('SUM(total) AS total')
                ->whereBetween('day', [
                    $start->format('Y-m-d'),
                    $end->format('Y-m-d'),
                ])
                ->groupBy('source', 'event')
                ->get();

            foreach ($rows as $row) {
                if (isset($summary[$row->source])
                    && array_key_exists($row->event, $summary[$row->source])) {
                    $summary[$row->source][$row->event] = (int) $row->total;
                }
            }
        } catch (Throwable $exception) {
            report($exception);
        }

        return $summary;
    }

    private function totals(string $table, \DateTimeInterface $start, \DateTimeInterface $end): array
    {
        $summary = array_fill_keys(self::EVENTS, 0);

        try {
            $rows = DB::table($table)
                ->select('event')
                ->selectRaw('SUM(total) AS total')
                ->whereBetween('day', [
                    $start->format('Y-m-d'),
                    $end->format('Y-m-d'),
                ])
                ->groupBy('event')
                ->get();

            foreach ($rows as $row) {
                if (array_key_exists($row->event, $summary)) {
                    $summary[$row->event] = (int) $row->total;
                }
            }
        } catch (Throwable $exception) {
            report($exception);
        }

        return $summary;
    }
}
