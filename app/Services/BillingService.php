<?php

namespace App\Services;

use App\Enums\MeterType;
use App\Models\BillingPeriod;
use App\Models\Plot;
use App\Models\Tariff;
use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class BillingService
{
    public function __construct(private readonly ConsumptionCalculator $calculator) {}

    /**
     * The bill of every plot for one year.
     *
     * @return Collection<int, array{plot: Plot, lines: list<BillingLine>, total_cents: int, complete: bool, open_reviews: int}>
     */
    public function forYear(int $year): Collection
    {
        $context = $this->context($year);

        return Plot::query()
            ->with(['meters' => $this->metersOfYear($context)])
            ->get()
            ->sortBy('number', SORT_NATURAL)
            ->values()
            ->map(fn (Plot $plot) => $this->bill($plot, $year, $context));
    }

    /**
     * The bill of one plot for one year.
     *
     * @return array{plot: Plot, lines: list<BillingLine>, total_cents: int, complete: bool, open_reviews: int}
     */
    public function forPlot(Plot $plot, int $year): array
    {
        $context = $this->context($year);

        return $this->bill($plot->load(['meters' => $this->metersOfYear($context)]), $year, $context);
    }

    /**
     * @return array{tariffs: Collection<string, Tariff>, keyDates: array<int, Carbon>, periodEnd: Carbon, previousPeriodEnd: Carbon}
     */
    private function context(int $year): array
    {
        $keyDates = BillingPeriod::keyDates();

        return [
            'tariffs' => Tariff::query()->where('year', $year)->get()->keyBy(fn (Tariff $t) => $t->meter_type->value),
            'keyDates' => $keyDates,
            'periodEnd' => $this->calculator->periodEnd($year, $keyDates),
            'previousPeriodEnd' => $this->calculator->periodEnd($year - 1, $keyDates),
        ];
    }

    /**
     * A meter belongs to the year if it was in service at some point between
     * the end of the previous billing year and the end of this one.
     *
     * @param  array{periodEnd: Carbon, previousPeriodEnd: Carbon}  $context
     */
    private function metersOfYear(array $context): Closure
    {
        return fn ($query) => $query
            ->whereDate('installed_on', '<=', $context['periodEnd'])
            ->where(fn ($q) => $q->whereNull('removed_on')->orWhereDate('removed_on', '>', $context['previousPeriodEnd']))
            ->with('effectiveReadings');
    }

    /**
     * @param  array{tariffs: Collection<string, Tariff>, keyDates: array<int, Carbon>}  $context
     * @return array{plot: Plot, lines: list<BillingLine>, total_cents: int, complete: bool, open_reviews: int}
     */
    private function bill(Plot $plot, int $year, array $context): array
    {
        $lines = [];

        foreach ($plot->meters->sortBy(fn ($m) => [$m->type->sortOrder(), $m->id]) as $meter) {
            $period = $this->calculator->periodForYear($meter, $year, $context['keyDates']);
            $consumption = $period?->consumptionMilli();
            $tariff = $context['tariffs']->get($meter->type->value);

            $lines[] = new BillingLine(
                $meter,
                $consumption,
                $consumption !== null && $tariff !== null ? $this->cost($consumption, $tariff) : null,
                $period,
                $tariff,
            );
        }

        return [
            'plot' => $plot,
            'lines' => $lines,
            'total_cents' => array_sum(array_map(fn (BillingLine $l) => $l->costCents ?? 0, $lines)),
            'complete' => $lines !== [] && collect($lines)->every(fn (BillingLine $l) => $l->isComplete()),
            // Readings still flagged for review: the bill should not go out before someone looked at them.
            'open_reviews' => $plot->meters->sum(fn ($m) => $m->effectiveReadings->filter->needsReview()->count()),
        ];
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
