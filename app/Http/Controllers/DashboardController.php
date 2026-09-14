<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Collection;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $organization = auth()->user()->organization;

        $range = $request->get('range', 'week');

        $query = Document::where(
            'organization_id',
            $organization->id
        );

        [$start, $end] = $this->getRange(
            $range,
            $request->start_date,
            $request->end_date
        );
        $documents = (clone $query)
            ->whereBetween('created_at', [$start, $end]);

        $stats = [
            'total' => (clone $documents)->count(),

            'draft' => (clone $documents)
                ->where('status', 'draft')
                ->count(),

            'sent' => (clone $documents)
                ->where('status', 'sent')
                ->count(),

            'completed' => (clone $documents)
                ->where('status', 'completed')
                ->count(),
        ];

        $recentDocuments = Document::query()
            ->where('organization_id', auth()->user()->organization->id)
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($document) {
                return [
                    'id' => $document->id,
                    'name' => $document->name,
                    'status' => $document->status,

                    // Human readable
                    'created_at_human' => $document->created_at->diffForHumans(),

                    // Full formatted date
                    'created_at' => $document->created_at->format('d M Y, H:i'),

                    // Optional
                    'updated_at' => $document->updated_at->diffForHumans(),
                ];
            });



        $chart = $this->buildChart(
           (clone $documents)->get(),
            $range,
            $start,
            $end
        );

        $summary = $this->buildSummary(
            $chart,
            $range
        );

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'chart' => $chart,
            'recentDocuments' => $recentDocuments,
            'range' => $range,
            'startDate' => $start?->toDateString(),
            'endDate' => $end?->toDateString(),
            'summary' => $summary,
        ]);
    }

    private function getRange(
        string $range,
        ?string $startDate = null,
        ?string $endDate = null
    ): array
    {
        return match ($range) {

            'today' => [
                now()->startOfDay(),
                now()->endOfDay(),
            ],

            'week' => [
                now()->startOfWeek(),
                now()->endOfWeek(),
            ],

            'month' => [
                now()->startOfMonth(),
                now()->endOfMonth(),
            ],

            'year' => [
                now()->startOfYear(),
                now()->endOfYear(),
            ],

            'custom' => [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay(),
            ],

            default => [
                now()->startOfWeek(),
                now()->endOfWeek(),
            ],
        };
    }

    private function buildChart(
        $documents,
        string $range,
        Carbon $start,
        Carbon $end
    ) {
        switch ($range) {

            /*
            |--------------------------------------------------------------------------
            | Today
            |--------------------------------------------------------------------------
            */

            case 'today':
                return collect(range(0, 23))->map(function ($hour) use ($documents) {

                    return [
                        'label' => sprintf('%02d:00', $hour),
                        'total' => $documents
                            ->whereBetween('created_at', [
                                now()->copy()->hour($hour)->startOfHour(),
                                now()->copy()->hour($hour)->endOfHour(),
                            ])
                            ->count(),
                    ];

                });

            /*
            |--------------------------------------------------------------------------
            | Week
            |--------------------------------------------------------------------------
            */

            case 'week':
                return collect(range(1, 7))->map(function ($day) use ($documents) {

                    $date = now()->startOfWeek()->addDays($day - 1);

                    return [
                        'label' => $date->format('D'),
                        'total' => $documents
                            ->filter(fn ($doc) => $doc->created_at->isSameDay($date))
                            ->count(),
                    ];

                });

            /*
            |--------------------------------------------------------------------------
            | Month
            |--------------------------------------------------------------------------
            */

            case 'month':

                $weeks = ceil($start->daysInMonth / 7);

                return collect(range(1, $weeks))
                    ->map(function ($week) use ($documents, $start) {

                        $weekStart = $start
                            ->copy()
                            ->startOfMonth()
                            ->addDays(($week - 1) * 7);

                        $weekEnd = $weekStart
                            ->copy()
                            ->addDays(6)
                            ->endOfDay();

                        return [
                            'label' => "Week {$week}",
                            'total' => $documents
                                ->filter(fn ($doc) =>
                                    $doc->created_at->between($weekStart, $weekEnd)
                                )
                                ->count(),
                        ];

                    });

            /*
            |--------------------------------------------------------------------------
            | Year
            |--------------------------------------------------------------------------
            */

            case 'year':

                return collect(range(1, 12))
                    ->map(function ($month) use ($documents) {

                        return [

                            'label' => Carbon::create()
                                ->month($month)
                                ->format('M'),

                            'total' => $documents
                                ->filter(fn ($doc) =>
                                    $doc->created_at->month == $month
                                )
                                ->count(),

                        ];

                    });

        }

        /*
        |--------------------------------------------------------------------------
        | Custom Range
        |--------------------------------------------------------------------------
        */

        $days = $start->diffInDays($end);

        /*
        |--------------------------------------------------------------------------
        | Daily (≤14 days)
        |--------------------------------------------------------------------------
        */

        if ($days <= 14) {

            return collect(CarbonPeriod::create($start, $end))
                ->map(function ($date) use ($documents) {

                    return [

                        'label' => $date->format('d M'),

                        'total' => $documents
                            ->filter(fn ($doc) =>
                                $doc->created_at->isSameDay($date)
                            )
                            ->count(),

                    ];

                });

        }

        /*
        |--------------------------------------------------------------------------
        | Weekly (≤90 days)
        |--------------------------------------------------------------------------
        */

        if ($days <= 90) {

            $weeks = collect();

            $current = $start->copy()->startOfWeek();

            while ($current <= $end) {

                $weeks->push($current->copy());

                $current->addWeek();

            }

            return $weeks->values()->map(function ($weekStart) use ($documents) {

                $weekEnd = $weekStart->copy()->endOfWeek();

                return [

                    'label' => 'W'.$weekStart->weekOfMonth,

                    'total' => $documents
                        ->filter(fn ($doc) =>
                            $doc->created_at->between($weekStart, $weekEnd)
                        )
                        ->count(),

                ];

            });

        }

        /*
        |--------------------------------------------------------------------------
        | Monthly (≤1 year)
        |--------------------------------------------------------------------------
        */

        if ($days <= 365) {

            return collect(
                CarbonPeriod::create(
                    $start->copy()->startOfMonth(),
                    '1 month',
                    $end->copy()->startOfMonth()
                )
            )->map(function ($month) use ($documents) {

                return [

                    'label' => $month->format('M'),

                    'total' => $documents
                        ->filter(fn ($doc) =>
                            $doc->created_at->format('Y-m') ==
                            $month->format('Y-m')
                        )
                        ->count(),

                ];

            });

        }

        /*
        |--------------------------------------------------------------------------
        | Yearly
        |--------------------------------------------------------------------------
        */

        return collect(range($start->year, $end->year))
            ->map(function ($year) use ($documents) {

                return [

                    'label' => (string) $year,

                    'total' => $documents
                        ->filter(fn ($doc) =>
                            $doc->created_at->year == $year
                        )
                        ->count(),

                ];

            });

    }

    private function buildSummary(Collection $chart, string $range): array
    {
        $peak = $chart->sortByDesc('total')->first();

        $average = round(
            $chart->avg('total'),
            1
        );

        $label = match ($range) {

            'today' => 'Peak Hour',

            'week' => 'Peak Day',

            'month' => 'Peak Week',

            'year' => 'Peak Month',

            default => 'Peak Period',

        };

        return [

            'peakLabel' => $label,

            'peakValue' => $peak['label'],

            'peakTotal' => $peak['total'],

            'average' => $average,

            'total' => $chart->sum('total'),

        ];
    }
}