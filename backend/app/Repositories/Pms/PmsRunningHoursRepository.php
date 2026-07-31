<?php

namespace App\Repositories\Pms;

use App\Models\Pms\PmsActivity;
use App\Models\Pms\PmsActivitySnapshot;
use App\Models\Pms\PmsRunningHoursEquipment;
use App\Models\Pms\PmsRunningHoursEquipmentDetail;
use App\Models\Pms\PmsRunningHoursEquipmentDetailHistory;
use App\Models\Pms\PmsRunningHoursPart;
use App\Models\Pms\PmsRunningHoursPartDetailHistory;
use App\Models\Vessel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Ported from Controllers/Pms_running_hours_equipments.php. Legacy stores
 * one physical column per day of the month (d1..d31); the Laravel side
 * uses a `daily_hours` JSON map instead — same data, no behavioral
 * difference. The linked "Parts" drill-down page (a whole separate
 * legacy controller) and its standalone entry UI aren't ported — parts
 * are only ever updated here as a cascade from a component-level entry,
 * exactly as legacy does for any component with update_by_component=1.
 * The tb_pms_ticket_running_hours audit ticket and tb_logs entry aren't
 * ported either, matching every other module's dropped audit trail.
 */
class PmsRunningHoursRepository
{
    /** @return array<int, array{id:int,label:string}> */
    public function vesselOptions(): array
    {
        return Vessel::query()->orderBy('name')->get()
            ->map(fn (Vessel $v) => ['id' => $v->id, 'label' => $v->display_name])
            ->all();
    }

    /**
     * Ported from pms_current_month()/pms_current_year(): the one live,
     * editable month for a vessel. Every tracked component's detail row
     * is kept in lockstep on the same (month, year) by proceedNextMonth(),
     * so any single row is representative.
     */
    public function currentPeriod(int $vesselId): ?array
    {
        $detail = PmsRunningHoursEquipmentDetail::query()
            ->whereHas('runningHoursEquipment', fn (Builder $q) => $q->where('vessel_id', $vesselId))
            ->first();

        return $detail ? ['month' => $detail->month, 'year' => $detail->year] : null;
    }

    /** @return array<int, array{month:int,year:int,label:string}> */
    public function periodOptions(int $vesselId): array
    {
        $periods = collect();

        if ($current = $this->currentPeriod($vesselId)) {
            $periods->push($current);
        }

        PmsRunningHoursEquipmentDetailHistory::query()
            ->whereHas('runningHoursEquipment', fn (Builder $q) => $q->where('vessel_id', $vesselId))
            ->select('month', 'year')->distinct()->get()
            ->each(fn ($row) => $periods->push(['month' => $row->month, 'year' => $row->year]));

        return $periods->unique(fn (array $p) => "{$p['year']}-{$p['month']}")
            ->sortByDesc(fn (array $p) => $p['year'] * 100 + $p['month'])
            ->map(fn (array $p) => [...$p, 'label' => Carbon::create($p['year'], $p['month'], 1)->format('F Y')])
            ->values()->all();
    }

    public function table(int $vesselId, ?int $month, ?int $year): array
    {
        $current = $this->currentPeriod($vesselId);
        $targetMonth = $month ?? $current['month'] ?? null;
        $targetYear = $year ?? $current['year'] ?? null;
        $isCurrent = $current && $targetMonth === $current['month'] && $targetYear === $current['year'];

        $tracked = PmsRunningHoursEquipment::query()
            ->where('vessel_id', $vesselId)
            ->where('is_active', true)
            ->with('equipment')
            ->get()
            ->sortBy(fn (PmsRunningHoursEquipment $rh) => $rh->equipment->equipment_code);

        return $tracked->map(function (PmsRunningHoursEquipment $rh) use ($isCurrent, $targetMonth, $targetYear) {
            $detail = $isCurrent
                ? $rh->detail
                : $rh->history()->where('month', $targetMonth)->where('year', $targetYear)->first();

            // Blanked out for parts-tracked components, matching legacy's
            // loadData() edit() callbacks (every numeric column returns ""
            // when update_by_component != 1).
            $tracksAtComponentLevel = $rh->update_by_component;

            return [
                'equipment_id' => $rh->pms_equipment_id,
                'equipment_code' => $rh->equipment->equipment_code,
                'equipment_name' => $rh->equipment->equipment_name,
                'update_by_component' => $tracksAtComponentLevel,
                'since_delivery' => $tracksAtComponentLevel ? ($detail->since_delivery ?? 0) : null,
                'monthly_rh' => $tracksAtComponentLevel ? ($detail->monthly_rh ?? 0) : null,
                'daily_hours' => $tracksAtComponentLevel ? ($detail->daily_hours ?? (object) []) : (object) [],
            ];
        })->values()->all();
    }

    /**
     * Ported from update_running_hours(). Entering an hours reading for a
     * date within the vessel's live month updates that component's
     * current-month totals directly. Entering one for an earlier month
     * (a backdated correction) updates that specific month's snapshot
     * *and* rolls the delta forward into every later month's
     * since_delivery, including the live one — since_delivery is a
     * running lifetime total, so a correction to the past raises every
     * subsequent cumulative figure.
     */
    public function updateRunningHours(int $equipmentId, string $date, float $hours, ?string $remarks): void
    {
        $rhEquipment = PmsRunningHoursEquipment::where('pms_equipment_id', $equipmentId)->with('equipment.parts.runningHours.detail')->firstOrFail();

        if (! $rhEquipment->update_by_component) {
            throw ValidationException::withMessages(['equipment_id' => ["This component's hours are tracked at the part level."]]);
        }

        $entryDate = Carbon::parse($date);
        $day = (string) $entryDate->day;
        $month = $entryDate->month;
        $year = $entryDate->year;

        $current = $this->currentPeriod($rhEquipment->vessel_id);
        $isCurrent = $current && $month === $current['month'] && $year === $current['year'];

        $target = $isCurrent
            ? $rhEquipment->detail
            : $rhEquipment->history()->where('month', $month)->where('year', $year)->first();

        if (! $target) {
            throw ValidationException::withMessages(['date' => ['No running-hours record exists for that month.']]);
        }

        if (($target->daily_hours[$day] ?? null) !== null) {
            throw ValidationException::withMessages(['date' => ['This date already has an entry.']]);
        }

        $daily = $target->daily_hours ?? [];
        $daily[$day] = $hours;
        $target->update([
            'since_delivery' => $target->since_delivery + $hours,
            'monthly_rh' => $target->monthly_rh + $hours,
            'daily_hours' => $daily,
        ]);

        if (! $isCurrent) {
            $rhEquipment->history()
                ->where('id', '!=', $target->id)
                ->where(fn (Builder $q) => $q->where('year', '>', $year)->orWhere(fn (Builder $q2) => $q2->where('year', $year)->where('month', '>=', $month)))
                ->increment('since_delivery', $hours);

            $rhEquipment->detail?->increment('since_delivery', $hours);
        }

        foreach ($rhEquipment->equipment->parts as $part) {
            $this->applyPartHours($part->runningHours, $isCurrent, $month, $year, $entryDate, $hours);
        }

        // Ported from the activity no_of_hours cascade — legacy gates this
        // on the activity's last_done date, a field PmsActivity doesn't
        // model (it's a flat, vessel-level dashboard summary — see
        // PmsRepository's docblock), so every linked activity is
        // incremented unconditionally.
        PmsActivity::where('pms_equipment_id', $equipmentId)->increment('no_of_hours', $hours);
    }

    private function applyPartHours(?PmsRunningHoursPart $rhPart, bool $isCurrent, int $month, int $year, Carbon $entryDate, float $hours): void
    {
        if (! $rhPart || ! $rhPart->detail) {
            return;
        }

        $liveDetail = $rhPart->detail;
        $overhaulBump = (! $liveDetail->date_last_overhauled || $entryDate->gte($liveDetail->date_last_overhauled)) ? $hours : 0;

        $target = $isCurrent ? $liveDetail : $rhPart->history()->where('month', $month)->where('year', $year)->first();

        if ($target) {
            $day = (string) $entryDate->day;
            $daily = $target->daily_hours ?? [];
            $daily[$day] = $hours;
            $target->update([
                'since_delivery' => $target->since_delivery + $hours,
                'since_last_overhaul' => $target->since_last_overhaul + $overhaulBump,
                'daily_hours' => $daily,
            ]);
        }

        if (! $isCurrent) {
            $rhPart->history()
                ->when($target, fn (Builder $q) => $q->where('id', '!=', $target->id))
                ->where(fn (Builder $q) => $q->where('year', '>', $year)->orWhere(fn (Builder $q2) => $q2->where('year', $year)->where('month', '>=', $month)))
                ->get()
                ->each(function (PmsRunningHoursPartDetailHistory $row) use ($hours, $overhaulBump) {
                    $row->increment('since_delivery', $hours);
                    $row->increment('since_last_overhaul', $overhaulBump);
                });

            $liveDetail->increment('since_delivery', $hours);
            $liveDetail->increment('since_last_overhaul', $overhaulBump);
        }
    }

    /**
     * Ported from proceedNextMonth(): snapshots every tracked component
     * and part's current-month row into history, then resets the live
     * row for the next month. Parts additionally fold their just-completed
     * monthly_rh into since_last_overhaul (legacy resets month-by-month
     * running-since-overhaul counters this way). December -> January
     * additionally archives the vessel's PMS activities — see
     * PmsActivitySnapshot's docblock for why that's a plain point-in-time
     * snapshot rather than a faithful port of legacy's per-month-column
     * archival.
     */
    public function proceedNextMonth(int $vesselId): void
    {
        $current = $this->currentPeriod($vesselId);

        if (! $current) {
            return;
        }

        ['month' => $month, 'year' => $year] = $current;
        $nextMonth = $month === 12 ? 1 : $month + 1;
        $nextYear = $month === 12 ? $year + 1 : $year;

        $equipmentRhs = PmsRunningHoursEquipment::where('vessel_id', $vesselId)->with('detail')->get();

        foreach ($equipmentRhs as $rh) {
            $detail = $rh->detail;

            if (! $detail) {
                continue;
            }

            PmsRunningHoursEquipmentDetailHistory::updateOrCreate(
                ['pms_running_hours_equipment_id' => $rh->id, 'month' => $month, 'year' => $year],
                ['since_delivery' => $detail->since_delivery, 'monthly_rh' => $detail->monthly_rh, 'daily_hours' => $detail->daily_hours],
            );

            $detail->update(['monthly_rh' => 0, 'daily_hours' => null, 'month' => $nextMonth, 'year' => $nextYear]);
        }

        $partRhs = PmsRunningHoursPart::whereHas('equipment', fn (Builder $q) => $q->where('vessel_id', $vesselId))->with('detail')->get();

        foreach ($partRhs as $rh) {
            $detail = $rh->detail;

            if (! $detail) {
                continue;
            }

            PmsRunningHoursPartDetailHistory::updateOrCreate(
                ['pms_running_hours_parts_id' => $rh->id, 'month' => $month, 'year' => $year],
                [
                    'since_delivery' => $detail->since_delivery,
                    'since_last_overhaul' => $detail->since_last_overhaul,
                    'date_last_overhauled' => $detail->date_last_overhauled,
                    'monthly_rh' => $detail->monthly_rh,
                    'daily_hours' => $detail->daily_hours,
                ],
            );

            $detail->update([
                'since_last_overhaul' => $detail->since_last_overhaul + $detail->monthly_rh,
                'monthly_rh' => 0,
                'daily_hours' => null,
                'month' => $nextMonth,
                'year' => $nextYear,
            ]);
        }

        if ($month === 12) {
            $this->archiveActivitiesForYearEnd($vesselId, $year);
        }
    }

    private function archiveActivitiesForYearEnd(int $vesselId, int $year): void
    {
        $activities = PmsActivity::where('vessel_id', $vesselId)->where('is_active', true)
            ->with(['equipment.criticality', 'part', 'department', 'mainGroup'])
            ->get();

        foreach ($activities as $activity) {
            PmsActivitySnapshot::create([
                'vessel_id' => $vesselId,
                'pms_activity_id' => $activity->id,
                'activity_name' => $activity->activity_name,
                'activity_code' => $activity->activity_code,
                'equipment_name' => $activity->equipment?->equipment_name,
                'part_name' => $activity->part?->part_name,
                'department_name' => $activity->department?->name,
                'main_group_name' => $activity->mainGroup?->name,
                'criticality_name' => $activity->equipment?->criticality?->name,
                'incharge' => $activity->incharge,
                'unit' => $activity->unit,
                'min_count_interval' => $activity->min_count_interval,
                'max_count_interval' => $activity->max_count_interval,
                'no_of_hours' => $activity->no_of_hours,
                'since_delivery' => $activity->since_delivery,
                'due_date' => $activity->due_date,
                'monthly_done' => $activity->monthly_done,
                'monthly_postponed' => $activity->monthly_postponed,
                'archived_year' => $year,
            ]);
        }
    }
}
