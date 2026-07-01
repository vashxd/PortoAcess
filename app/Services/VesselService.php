<?php

namespace App\Services;

use App\Enums\DepartureStatus;
use App\Models\VesselDeparture;
use App\Models\VesselSchedule;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class VesselService
{
    /**
     * Gera as viagens (partidas datadas) a partir da grade recorrente, para o
     * intervalo informado. Idempotente: não duplica partidas já existentes.
     *
     * @return int quantidade de viagens criadas
     */
    public function generateDepartures(CarbonInterface $from, CarbonInterface $to): int
    {
        $schedules = VesselSchedule::with('vessel')
            ->where('active', true)
            ->whereHas('vessel', fn ($q) => $q->where('active', true))
            ->get();

        if ($schedules->isEmpty()) {
            return 0;
        }

        $start = CarbonImmutable::parse($from)->startOfDay();
        $end = CarbonImmutable::parse($to)->startOfDay();
        $created = 0;

        for ($day = $start; $day->lessThanOrEqualTo($end); $day = $day->addDay()) {
            foreach ($schedules as $schedule) {
                if (! $schedule->runsOn($day)) {
                    continue;
                }

                $departureAt = Carbon::parse($day->format('Y-m-d').' '.$schedule->departure_time);

                $departure = VesselDeparture::firstOrCreate(
                    ['vessel_id' => $schedule->vessel_id, 'departure_at' => $departureAt],
                    [
                        'vessel_schedule_id' => $schedule->id,
                        'departure_date' => $day->format('Y-m-d'),
                        'departure_time' => $schedule->departure_time,
                        'destination' => $schedule->destination ?: $schedule->vessel->default_destination,
                        'status' => DepartureStatus::Agendada,
                    ],
                );

                if ($departure->wasRecentlyCreated) {
                    $created++;
                }
            }
        }

        return $created;
    }

    /**
     * Garante que as viagens dos próximos dias existam e devolve as partidas
     * ainda disponíveis (hoje em diante, não encerradas/canceladas) para o
     * operador escolher na guarita.
     */
    public function upcomingDepartures(int $daysAhead = 2): Collection
    {
        $today = CarbonImmutable::now()->startOfDay();
        $this->generateDepartures($today, $today->addDays($daysAhead));

        return VesselDeparture::with('vessel')
            ->whereIn('status', [DepartureStatus::Agendada, DepartureStatus::Embarcando])
            ->where('departure_at', '>=', CarbonImmutable::now()->subHours(2))
            ->orderBy('departure_at')
            ->limit(60)
            ->get()
            ->map(fn (VesselDeparture $d) => [
                'id' => $d->id,
                'vessel_id' => $d->vessel_id,
                'vessel_name' => $d->vessel->name,
                'departure_at' => $d->departure_at->toIso8601String(),
                'departure_time' => $d->departure_time,
                'departure_date' => $d->departure_date->toDateString(),
                'destination' => $d->destination,
                'status' => $d->status->value,
            ]);
    }
}
