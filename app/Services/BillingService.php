<?php

namespace App\Services;

use App\Enums\MeterType;
use App\Models\Plot;
use App\Models\Tariff;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class BillingService
{
    public function __construct(private readonly ConsumptionCalculator $calculator) {}

    /**
     * @return Collection<int, array{plot: Plot, lines: list<BillingLine>, total_cents: int, complete: bool}>
     */
    public function forYear(int $year): Collection
    {
        $tariffs = Tariff::query()->where('year', $year)->get()->keyBy(fn (Tariff $t) => $t->meter_type->value);
        $yearStart = Carbon::create($year, 1, 1);
        $yearEnd = Carbon::create($year, 12, 31);

        return Plot::query()
            ->with(['meters' => fn ($q) => $q
                ->whereDate('installed_on', '<=', $yearEnd)
                ->where(fn ($q) => $q->whereNull('removed_on')->orWhereDate('removed_on', '>=', $yearStart))
                ->with('effectiveReadings'),
            ])
            ->orderBy('number')
            ->get()
            ->map(function (Plot $plot) use ($year, $tariffs) {
                $lines = [];

                foreach ($plot->meters->sortBy(fn ($m) => [$m->type->sortOrder(), $m->id]) as $meter) {
                    $consumption = $this->calculator->forYear($meter, $year);
                    $tariff = $tariffs->get($meter->type->value);

                    $lines[] = new BillingLine(
                        $meter,
                        $consumption,
                        $consumption !== null && $tariff !== null ? $this->cost($consumption, $tariff) : null,
                    );
                }

                return [
                    'plot' => $plot,
                    'lines' => $lines,
                    'total_cents' => array_sum(array_map(fn (BillingLine $l) => $l->costCents ?? 0, $lines)),
                    'complete' => $lines !== [] && collect($lines)->every(fn (BillingLine $l) => $l->isComplete()),
                ];
            });
    }

    /**
     * @return list<MeterType>
     */
    public function missingTariffs(int $year): array
    {
        $existing = Tariff::query()->where('year', $year)->pluck('meter_type')->map(fn ($t) => $t instanceof MeterType ? $t : MeterType::from($t));

        return array_values(array_filter(MeterType::cases(), fn (MeterType $type) => ! $existing->contains($type)));
    }

    /**
     * Price per unit times consumption in milli-units, rounded half up to whole cents.
     */
    public function cost(int $consumptionMilli, Tariff $tariff): int
    {
        return intdiv($consumptionMilli * $tariff->price_per_unit_cents + 500, 1000) + $tariff->base_fee_cents;
    }
}
