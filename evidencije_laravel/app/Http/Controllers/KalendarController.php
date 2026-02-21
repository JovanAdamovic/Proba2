<?php

namespace App\Http\Controllers;

use App\Models\Zadatak;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class KalendarController extends Controller
{
    public function rokovi(Request $request)
    {
        $user = $request->user();

        if (!in_array($user->uloga, ['STUDENT', 'PROFESOR', 'ADMIN'], true)) {
            return response()->json(['message' => 'Zabranjeno'], 403);
        }

        $now = now();

        $zadaciQuery = Zadatak::query()
            ->with(['predmet:id,naziv,sifra', 'profesor:id,ime,prezime'])
            ->where('rok_predaje', '>=', $now)
            ->orderBy('rok_predaje');

        if ($user->uloga === 'STUDENT') {
            $predmetIds = $user->predmeti()->pluck('predmeti.id');
            $zadaciQuery->whereIn('predmet_id', $predmetIds);
        }

        if ($user->uloga === 'PROFESOR') {
            $zadaciQuery->where('profesor_id', $user->id);
        }

        $lokalniRokovi = $zadaciQuery->get()->map(function (Zadatak $zadatak) {
            return [
                'id' => 'zadatak-' . $zadatak->id,
                'source' => 'internal',
                'title' => $zadatak->naslov,
                'description' => $zadatak->opis,
                'start' => $zadatak->rok_predaje?->toIso8601String(),
                'end' => $zadatak->rok_predaje?->toIso8601String(),
                'all_day' => false,
                'subject' => $zadatak->predmet?->naziv,
                'subject_code' => $zadatak->predmet?->sifra,
                'profesor' => trim(($zadatak->profesor?->ime ?? '') . ' ' . ($zadatak->profesor?->prezime ?? '')),
            ];
        })->values();

        $eksterniRokovi = $this->fetchPublicHolidayEvents($now);

        $rokovi = $lokalniRokovi
            ->concat($eksterniRokovi)
            ->sortBy('start')
            ->values();

        return response()->json([
            'data' => $rokovi,
            'meta' => [
                'external_calendar_provider' => 'nager_date_public_holidays',
                'external_calendar_connected' => $this->calendarApiConfigured(),

                'today' => [
                    'date' => $now->toDateString(),
                    'day_name' => $now->locale('sr')->isoFormat('dddd'),
                ],
            ],
        ]);
    }

    private function fetchPublicHolidayEvents(Carbon $now)
    {
        if (!$this->calendarApiConfigured()) {
            return collect();
        }

        $countryCode = strtoupper(config('services.calendar_api.country_code', 'RS'));
        $yearsAhead = max(1, (int) config('services.calendar_api.years_ahead', 5));
        $years = collect(range(0, $yearsAhead))
            ->map(fn (int $offset) => $now->copy()->addYears($offset)->year)
            ->all();
            
        try {
            $items = collect($years)
                ->unique()
                ->flatMap(function (int $year) use ($countryCode) {
                    $cacheKey = sprintf('calendar_holidays_%s_%s', $countryCode, $year);

                    return Cache::remember($cacheKey, now()->addHours(12), function () use ($countryCode, $year) {
                        $response = Http::timeout(8)
                            ->get(sprintf('https://date.nager.at/api/v3/PublicHolidays/%s/%s', $year, $countryCode));

                        if (!$response->successful()) {
                            Log::warning('Nager.Date events fetch failed', [
                                'status' => $response->status(),
                                'body' => $response->body(),
                                'year' => $year,
                                'country_code' => $countryCode,
                            ]);

                            return [];
                        }

                        return $response->json();
                    });
                })
                ->values();

            return collect($items)
                ->map(function (array $event) {
                    $start = $event['date'] ?? null;

                    return [
                        'id' => 'holiday-' . ($event['date'] ?? uniqid()),
                        'source' => 'external_calendar',
                        'title' => $event['localName'] ?? ($event['name'] ?? 'Neradni dan'),
                        'description' => $event['name'] ?? 'Drzavni praznik',
                        'start' => $start ? Carbon::parse($start)->toIso8601String() : null,
                        'end' => $start ? Carbon::parse($start)->endOfDay()->toIso8601String() : null,
                        'all_day' => true,
                        'subject' => null,
                        'subject_code' => null,
                        'profesor' => null,
                    ];
                })
                ->filter(function ($mapped) use ($now) {
                    return !empty($mapped['start']) &&
                        Carbon::parse($mapped['start'])->greaterThanOrEqualTo($now->copy()->startOfDay());
                })
                ->sortBy('start')
                ->values();
        } catch (\Throwable $e) {
            Log::warning('External calendar sync failed', [
                'message' => $e->getMessage(),
            ]);

            return collect();
        }
    }

    private function calendarApiConfigured(): bool
    {
        return !empty(config('services.calendar_api.country_code'));
    }
}