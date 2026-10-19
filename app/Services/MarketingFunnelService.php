<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Anonymous operational counters. No cookies, IPs, user IDs, click IDs,
 * referrers or form values are stored. Counts are not unique visitors.
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

    public function record(string $event): void
    {
        if (! in_array($event, self::EVENTS, true)) {
            return;
        }

        try {
            // Atomic increment also works on SQLite (tests) and MySQL (production).
            DB::table('marketing_funnel_daily_counts')->upsert(
                [
                    [
                        'day' => now()->toDateString(),
                        'event' => $event,
                        'total' => 1,
                    ],
                ],
                ['day', 'event'],
                ['total' => DB::raw('total + 1')]
            );
        } catch (Throwable $exception) {
            // Analytics must never prevent landing pages or registration from working.
            report($exception);
        }
    }

    public function summary(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        $summary = array_fill_keys(self::EVENTS, 0);

        try {
            $rows = DB::table('marketing_funnel_daily_counts')
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
