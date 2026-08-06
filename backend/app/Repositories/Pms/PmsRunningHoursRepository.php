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
use App\Support\LegacyDb;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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

    private const DAY_COLUMNS = [
        'd1', 'd2', 'd3', 'd4', 'd5', 'd6', 'd7', 'd8', 'd9', 'd10',
        'd11', 'd12', 'd13', 'd14', 'd15', 'd16', 'd17', 'd18', 'd19', 'd20',
        'd21', 'd22', 'd23', 'd24', 'd25', 'd26', 'd27', 'd28', 'd29', 'd30', 'd31',
    ];

    private const ACTIVITY_MONTH_KEYS = [
        1 => 'january', 2 => 'february', 3 => 'march', 4 => 'april',
        5 => 'may', 6 => 'june', 7 => 'july', 8 => 'august',
        9 => 'september', 10 => 'october', 11 => 'november', 12 => 'december',
    ];

    /*
     * ------------------------------------------------------------------
     * Legacy DB support. Legacy stores one physical column per day
     * (d1..d31) exactly like the local port's daily_hours JSON map — read
     * back below the same way. Not ported (same as the local port above,
     * see class docblock): the standalone Parts drill-down page (parts
     * are only ever updated here as a cascade from a component-level
     * entry) and the tb_pms_ticket_running_hours audit ticket / tb_logs
     * entry, matching every other module's dropped audit trail.
     * ------------------------------------------------------------------
     */

    /** @return array<int, array{id:string,label:string}> */
    public function legacyVesselOptions(): array
    {
        return collect(LegacyDb::vesselNames())
            ->map(fn ($name, $id) => ['id' => $id, 'label' => $name])
            ->values()
            ->sortBy('label')
            ->values()
            ->all();
    }

    /**
     * Ported from pms_current_month()/pms_current_year(), reading
     * tb_pms_running_hours_details (the parts table) — the same source
     * legacy itself uses to decide "current" vs. "previous" in
     * update_running_hours().
     */
    public function legacyCurrentPeriod(string $vesselId): ?array
    {
        $row = DB::connection('legacy')->table('tb_pms_running_hours_details')
            ->where('vesselID', $vesselId)
            ->where('month', '!=', '')
            ->select('month', 'year')
            ->first();

        if ($row === null) {
            return null;
        }

        return ['month' => (int) $row->month, 'year' => (int) $row->year];
    }

    /** @return array<int, array{month:int,year:int,label:string}> */
    public function legacyPeriodOptions(string $vesselId): array
    {
        $periods = collect();

        if ($current = $this->legacyCurrentPeriod($vesselId)) {
            $periods->push($current);
        }

        DB::connection('legacy')->table('tb_pms_running_hours_equipment_details_history')
            ->where('vesselID', $vesselId)->where('is_reset', 0)
            ->select('month', 'year')->distinct()->get()
            ->each(fn ($row) => $periods->push(['month' => (int) $row->month, 'year' => (int) $row->year]));

        return $periods->unique(fn (array $p) => "{$p['year']}-{$p['month']}")
            ->sortByDesc(fn (array $p) => $p['year'] * 100 + $p['month'])
            ->map(fn (array $p) => [...$p, 'label' => Carbon::create($p['year'], $p['month'], 1)->format('F Y')])
            ->values()->all();
    }

    /** Ported from loadData(), reading tb_pms_running_hours_equipment(_details[_history]) directly from the legacy connection. */
    public function legacyTable(string $vesselId, ?int $month, ?int $year): array
    {
        $legacy = DB::connection('legacy');
        $current = $this->legacyCurrentPeriod($vesselId);
        $targetMonth = $month ?? $current['month'] ?? null;
        $targetYear = $year ?? $current['year'] ?? null;
        $isCurrent = $current && $targetMonth === $current['month'] && $targetYear === $current['year'];

        $tracked = $legacy->table('tb_pms_running_hours_equipment as rhe')
            ->leftJoin('tb_pms_equipment as e', 'e.equipmentID', '=', 'rhe.equipmentID')
            ->where('rhe.vesID', $vesselId)->where('rhe.status', 1)
            ->select(['rhe.equipmentID', 'rhe.update_by_component', 'e.equipment_code', 'e.equipment_name'])
            ->get()
            ->sortBy('equipment_code');

        return $tracked->map(function ($rhe) use ($legacy, $isCurrent, $targetMonth, $targetYear) {
            $tracksAtComponentLevel = (bool) $rhe->update_by_component;

            $detail = null;
            if ($tracksAtComponentLevel) {
                $detail = $isCurrent
                    ? $legacy->table('tb_pms_running_hours_equipment_details')->where('equipmentID', $rhe->equipmentID)->first()
                    : $legacy->table('tb_pms_running_hours_equipment_details_history')
                        ->where('equipmentID', $rhe->equipmentID)->where('month', $targetMonth)->where('year', $targetYear)->where('is_reset', 0)
                        ->first();
            }

            $dailyHours = [];
            if ($detail !== null) {
                foreach (self::DAY_COLUMNS as $i => $col) {
                    $value = (float) $detail->{$col};
                    if ($value !== 0.0) {
                        $dailyHours[(string) ($i + 1)] = $value;
                    }
                }
            }

            return [
                'equipment_id' => $rhe->equipmentID,
                'equipment_code' => $rhe->equipment_code,
                'equipment_name' => $rhe->equipment_name,
                'update_by_component' => $tracksAtComponentLevel,
                'since_delivery' => $tracksAtComponentLevel ? (float) ($detail->trh_since_delivery ?? 0) : null,
                'monthly_rh' => $tracksAtComponentLevel ? (float) ($detail->monthly_rh ?? 0) : null,
                'daily_hours' => $dailyHours,
            ];
        })->values()->all();
    }

    /** Ported from update_running_hours(). */
    public function legacyUpdateRunningHours(string $equipmentId, string $date, float $hours, ?string $remarks): void
    {
        $legacy = DB::connection('legacy');
        $rhEquipment = $legacy->table('tb_pms_running_hours_equipment')->where('equipmentID', $equipmentId)->first();
        abort_if($rhEquipment === null, 404);

        if (! $rhEquipment->update_by_component) {
            throw ValidationException::withMessages(['equipment_id' => ["This component's hours are tracked at the part level."]]);
        }

        $vesselId = $rhEquipment->vesID;
        $entryDate = Carbon::parse($date);
        $dayCol = 'd'.$entryDate->day;

        $current = $this->legacyCurrentPeriod($vesselId);
        $isCurrent = $current && $entryDate->month === $current['month'] && $entryDate->year === $current['year'];

        if ($isCurrent) {
            $existing = (float) ($legacy->table('tb_pms_running_hours_equipment_details')->where('equipmentID', $equipmentId)->value($dayCol) ?? 0);
        } else {
            $existing = (float) ($legacy->table('tb_pms_running_hours_equipment_details_history')
                ->where('equipmentID', $equipmentId)->where('month', $entryDate->month)->where('year', $entryDate->year)->where('is_reset', 0)
                ->value($dayCol) ?? 0);
        }

        if ($existing !== 0.0) {
            throw ValidationException::withMessages(['date' => ['This date already has an entry.']]);
        }

        if (! $isCurrent) {
            $historyExists = $legacy->table('tb_pms_running_hours_equipment_details_history')
                ->where('equipmentID', $equipmentId)->where('month', $entryDate->month)->where('year', $entryDate->year)->where('is_reset', 0)
                ->exists();

            if (! $historyExists) {
                throw ValidationException::withMessages(['date' => ['No running-hours record exists for that month.']]);
            }
        }

        $liveDetail = $legacy->table('tb_pms_running_hours_equipment_details')->where('equipmentID', $equipmentId)->first();
        abort_if($liveDetail === null, 404);

        if ($isCurrent) {
            $newSinceDelivery = $liveDetail->trh_since_delivery + $hours;
            $newAve = $liveDetail->ave_rh_per_day != 0 ? ($liveDetail->ave_rh_per_day + $hours) / 2 : $hours;
            $newMonthlyRh = $liveDetail->monthly_rh + $hours;

            $legacy->table('tb_pms_running_hours_equipment_details')->where('equipmentID', $equipmentId)->update([
                'trh_since_delivery' => $newSinceDelivery,
                $dayCol => $hours,
                'ave_rh_per_day' => $newAve,
                'monthly_rh' => $newMonthlyRh,
            ]);
        } else {
            $newSinceDelivery = $liveDetail->trh_since_delivery + $hours;
            $newAve = $liveDetail->ave_rh_per_day != 0 ? ($liveDetail->ave_rh_per_day + $hours) / 2 : $hours;

            $legacy->table('tb_pms_running_hours_equipment_details')->where('equipmentID', $equipmentId)->update([
                'trh_since_delivery' => $newSinceDelivery,
                'ave_rh_per_day' => $newAve,
            ]);

            $legacy->table('tb_pms_running_hours_equipment_details_history')
                ->where('equipmentID', $equipmentId)
                ->where(fn ($q) => $q->where('year', '>', $entryDate->year)
                    ->orWhere(fn ($q2) => $q2->where('year', $entryDate->year)->where('month', '>=', $entryDate->month)))
                ->increment('trh_since_delivery', $hours);

            $legacy->table('tb_pms_running_hours_equipment_details_history')
                ->where('equipmentID', $equipmentId)->where('year', $entryDate->year)->where('month', $entryDate->month)
                ->update([$dayCol => $hours, 'monthly_rh' => DB::raw("monthly_rh + {$hours}")]);
        }

        $parts = $legacy->table('tb_pms_running_hours')->where('equipmentID', $equipmentId)->get();

        foreach ($parts as $part) {
            $this->legacyApplyPartHours($part->partsID, $isCurrent, $entryDate, $dayCol, $hours);
        }

        // Ported from the activity no_of_hours cascade, gated on the
        // entry date being on/after the activity's last_done — legacy's
        // own rule, and unlike the local port's blanket increment
        // (dropped there only because PmsActivity's flat, non-legacy
        // structure made the gate impractical), directly available here
        // against the real last_done column.
        foreach ($parts as $part) {
            $activities = $legacy->table('tb_pms_activities')->where('partsID', $part->partsID)->get();

            foreach ($activities as $activity) {
                $lastDone = $activity->last_done === '0000-00-00' ? null : $activity->last_done;

                if ($lastDone === null || $entryDate->toDateString() >= $lastDone) {
                    $legacy->table('tb_pms_activities')->where('activityID', $activity->activityID)
                        ->update(['no_of_hours' => $activity->no_of_hours + $hours]);
                }
            }
        }
    }

    private function legacyApplyPartHours(string $partsId, bool $isCurrent, Carbon $entryDate, string $dayCol, float $hours): void
    {
        $legacy = DB::connection('legacy');
        $liveDetail = $legacy->table('tb_pms_running_hours_details')->where('partsID', $partsId)->first();

        if ($liveDetail === null) {
            return;
        }

        $lastOverhauled = $liveDetail->date_last_overhauled === '0000-00-00' ? null : $liveDetail->date_last_overhauled;
        $overhaulBump = ($lastOverhauled === null || $entryDate->toDateString() >= $lastOverhauled) ? $hours : 0;

        if ($isCurrent) {
            $legacy->table('tb_pms_running_hours_details')->where('partsID', $partsId)->update([
                'trh_since_delivery' => $liveDetail->trh_since_delivery + $hours,
                'trh_since_last_overhaul' => $liveDetail->trh_since_last_overhaul + $overhaulBump,
                $dayCol => $hours,
            ]);

            return;
        }

        $historyRows = $legacy->table('tb_pms_running_hours_details_history')
            ->where('partsID', $partsId)
            ->where(fn ($q) => $q->where('year', '>', $entryDate->year)
                ->orWhere(fn ($q2) => $q2->where('year', $entryDate->year)->where('month', '>=', $entryDate->month)))
            ->get();

        foreach ($historyRows as $row) {
            $rowLastOverhauled = $row->date_last_overhauled === '0000-00-00' ? null : $row->date_last_overhauled;
            $rowOverhaulBump = ($rowLastOverhauled === null || $entryDate->toDateString() >= $rowLastOverhauled) ? $hours : 0;

            $legacy->table('tb_pms_running_hours_details_history')->where('histRunHrsID', $row->histRunHrsID)->update([
                'trh_since_delivery' => $row->trh_since_delivery + $hours,
                'trh_since_last_overhaul' => $row->trh_since_last_overhaul + $rowOverhaulBump,
            ]);
        }

        $legacy->table('tb_pms_running_hours_details_history')
            ->where('partsID', $partsId)->where('year', $entryDate->year)->where('month', $entryDate->month)
            ->update([$dayCol => $hours]);

        $legacy->table('tb_pms_running_hours_details')->where('partsID', $partsId)->update([
            'trh_since_delivery' => $liveDetail->trh_since_delivery + $hours,
            'trh_since_last_overhaul' => $liveDetail->trh_since_last_overhaul + $overhaulBump,
        ]);
    }

    /** Ported from proceedNextMonth(). */
    public function legacyProceedNextMonth(string $vesselId): void
    {
        $legacy = DB::connection('legacy');

        $partDetails = $legacy->table('tb_pms_running_hours_details')->where('vesselID', $vesselId)->get();
        foreach ($partDetails as $d) {
            $legacy->table('tb_pms_running_hours_details_history')->insert([
                'histRunHrsID' => 'histrunhrs'.uniqid(),
                'runninghrsID' => $d->runninghrsID,
                'eID' => $d->eID,
                'partsID' => $d->partsID,
                'activityID' => '',
                'trh_since_delivery' => $d->trh_since_delivery,
                'trh_since_last_overhaul' => $d->trh_since_last_overhaul,
                'trh_current_month' => $d->trh_current_month,
                ...collect(self::DAY_COLUMNS)->mapWithKeys(fn ($col) => [$col => $d->{$col}])->all(),
                'date_last_overhauled' => $d->date_last_overhauled,
                'date_last_reset' => $d->date_last_reset,
                'month' => $d->month,
                'year' => $d->year,
                'vesselID' => $d->vesselID,
                'last_update' => now()->toDateString(),
                'is_reset' => 0,
            ]);
        }

        $equipDetails = $legacy->table('tb_pms_running_hours_equipment_details')->where('vesselID', $vesselId)->get();
        foreach ($equipDetails as $d) {
            $legacy->table('tb_pms_running_hours_equipment_details_history')->insert([
                'rhequipdethistID' => 'rhequipdet'.uniqid(),
                'rhequipdetID' => $d->rhequipdetID,
                'rhequipID' => $d->rhequipID,
                'equipmentID' => $d->equipmentID,
                'trh_since_delivery' => $d->trh_since_delivery,
                'monthly_rh' => $d->monthly_rh,
                ...collect(self::DAY_COLUMNS)->mapWithKeys(fn ($col) => [$col => $d->{$col}])->all(),
                'month' => $d->month,
                'year' => $d->year,
                'vesselID' => $d->vesselID,
                'is_reset' => 0,
            ]);
        }

        $current = $this->legacyCurrentPeriod($vesselId);

        if ($current === null) {
            return;
        }

        ['month' => $month, 'year' => $year] = $current;

        if ($month === 12) {
            $nextMonth = 1;
            $nextYear = $year + 1;
            $this->legacyArchiveActivitiesForYearEnd($vesselId, $year, $nextYear);
        } else {
            $nextMonth = $month + 1;
            $nextYear = $year;
        }

        $legacy->table('tb_pms_running_hours_details')->where('vesselID', $vesselId)->update([
            'trh_since_last_overhaul' => DB::raw('trh_since_last_overhaul + trh_current_month'),
            'trh_current_month' => 0,
            ...array_fill_keys(self::DAY_COLUMNS, 0),
            'year' => $nextYear,
            'month' => $nextMonth,
        ]);

        $legacy->table('tb_pms_running_hours_equipment_details')->where('vesselID', $vesselId)->update([
            'monthly_rh' => 0,
            ...array_fill_keys(self::DAY_COLUMNS, 0),
            'year' => $nextYear,
            'month' => $nextMonth,
        ]);
    }

    /** Ported from proceedNextMonth()'s December -> January branch: archives every activity to tb_pms_activities_history, then blanks all 12 months' calendar columns. */
    private function legacyArchiveActivitiesForYearEnd(string $vesselId, int $year, int $nextYear): void
    {
        $legacy = DB::connection('legacy');
        $activities = $legacy->table('tb_pms_activities')->where('vesID', $vesselId)->get();

        foreach ($activities as $a) {
            $legacy->table('tb_pms_activities_history')->insert([
                'actHistID' => 'actHist'.uniqid(),
                'activityID' => $a->activityID,
                'vesID' => $a->vesID,
                'type' => $a->type,
                'equipmentID' => $a->equipmentID,
                'partsID' => $a->partsID,
                'location' => $a->location,
                'sub_location' => $a->sub_location,
                'deptID' => $a->deptID,
                'posID' => $a->posID,
                'assigneeID' => $a->assigneeID,
                'activity_name' => $a->activity_name,
                'mgID' => $a->mgID,
                'gID' => $a->gID,
                'sbID' => $a->sbID,
                'jobID' => $a->jobID,
                'jobtypeID' => $a->jobtypeID,
                'priorityID' => $a->priorityID,
                'schedID' => $a->schedID,
                'activity_code' => $a->activity_code,
                'sub_activity_code' => $a->sub_activity_code,
                'unit' => $a->unit,
                'other_unit' => $a->other_unit,
                'min_count_interval' => $a->min_count_interval,
                'max_count_interval' => $a->max_count_interval,
                'no_of_hours' => $a->no_of_hours,
                'is_drydock' => $a->is_drydock,
                'is_shipcrew' => $a->is_shipcrew,
                'is_porthelper' => $a->is_porthelper,
                'work_procedure' => $a->work_procedure,
                'previous_due_date' => $a->previous_due_date,
                'last_done' => $a->last_done,
                'due_date' => $a->due_date,
                'next_duedate' => $a->next_duedate,
                'status' => $a->status,
                'is_report' => $a->is_report,
                'is_overdue' => $a->is_overdue,
                'is_postpone' => $a->is_postpone,
                'postpone_date' => $a->postpone_date,
                'reportID' => $a->reportID,
                'remarks' => $a->remarks,
                'reference' => $a->reference,
                ...collect(self::ACTIVITY_MONTH_KEYS)->flatMap(fn ($label) => [
                    $label => $a->{$label},
                    "{$label}_is_overdue" => $a->{"{$label}_is_overdue"},
                    "{$label}_update_ticketID" => $a->{"{$label}_update_ticketID"},
                    "{$label}_postponed" => $a->{"{$label}_postponed"},
                    "{$label}_postponed_ticketID" => $a->{"{$label}_postponed_ticketID"},
                ])->all(),
                'year' => $a->year,
                'active_status' => $a->active_status,
            ]);
        }

        $legacy->table('tb_pms_activities')->where('vesID', $vesselId)->update([
            'year' => $nextYear,
            ...collect(self::ACTIVITY_MONTH_KEYS)->flatMap(fn ($label) => [
                $label => '',
                "{$label}_is_overdue" => 0,
                "{$label}_update_ticketID" => '',
                "{$label}_postponed" => '',
                "{$label}_postponed_ticketID" => '',
            ])->all(),
        ]);
    }
}
