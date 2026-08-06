<?php

namespace App\Repositories\Pms;

use App\Models\Pms\PmsActivity;
use App\Models\Pms\PmsActivitySnapshot;
use App\Models\Pms\PmsCriticality;
use App\Models\Pms\PmsDepartment;
use App\Models\Pms\PmsTicket;
use App\Models\Pms\SpectecMainGroup;
use App\Models\Vessel;
use App\Support\LegacyDb;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Ported from Controllers/Pms_activities.php. Not ported: the actual-
 * inventory-used tracking on mark-done (its Add-Item button/modal are
 * commented out of the live add_activity_v.php template, so the whole
 * feature is dead in production already) and view_item()/
 * view_workprocedure(), both explicitly marked "(not used)" in the
 * legacy source. There is no Add/Edit Activity Master UI anywhere in
 * this legacy module either — activities are populated outside it — so
 * this port is operational only: browse the schedule, mark an activity
 * done, postpone it, and view its ticket log. Legacy stores each
 * month's done/postponed cell as five physical columns per month
 * (60 columns total); ported here as two JSON maps
 * (monthly_done/monthly_postponed keyed "1".."12") on PmsActivity,
 * mirroring the daily_hours JSON precedent from PMS Running Hours.
 */
class PmsActivitiesRepository
{
    private const HOURS_PER_UNIT = ['H' => 1, 'D' => 24, 'W' => 7 * 24, 'M' => 30 * 24, 'Y' => 365 * 24];

    private const MONTH_KEYS = [
        1 => 'january', 2 => 'february', 3 => 'march', 4 => 'april',
        5 => 'may', 6 => 'june', 7 => 'july', 8 => 'august',
        9 => 'september', 10 => 'october', 11 => 'november', 12 => 'december',
    ];

    public function __construct(private readonly PmsRunningHoursRepository $runningHours) {}

    /** @return array<int, array{id:int,label:string}> */
    public function vesselOptions(): array
    {
        return Vessel::query()->orderBy('name')->get()
            ->map(fn (Vessel $v) => ['id' => $v->id, 'label' => $v->display_name])
            ->all();
    }

    /** @return array<int, array{id:int,label:string}> */
    public function departmentOptions(): array
    {
        return PmsDepartment::query()->orderBy('name')->get()
            ->map(fn (PmsDepartment $d) => ['id' => $d->id, 'label' => $d->name])
            ->all();
    }

    /** @return array<int, array{id:int,label:string}> */
    public function criticalityOptions(): array
    {
        return PmsCriticality::query()->orderBy('name')->get()
            ->map(fn (PmsCriticality $c) => ['id' => $c->id, 'label' => $c->name])
            ->all();
    }

    /** @return array<int, array{id:int,label:string}> */
    public function mainGroupOptions(): array
    {
        return SpectecMainGroup::query()->orderBy('code')->get()
            ->map(fn (SpectecMainGroup $g) => ['id' => $g->id, 'label' => "{$g->code} - {$g->name}"])
            ->all();
    }

    /**
     * Ported from pms_current_month()/pms_current_year(): the vessel's
     * one live running-hours period, reused here as "the current PMS
     * calendar year" — the same table PmsRunningHours already tracks.
     */
    public function currentYear(int $vesselId): ?int
    {
        return $this->runningHours->currentPeriod($vesselId)['year'] ?? null;
    }

    /** @return array{month:int,year:int}|null */
    public function currentPeriod(int $vesselId): ?array
    {
        return $this->runningHours->currentPeriod($vesselId);
    }

    /** @return array<int, int> years with an archived snapshot for this vessel, newest first */
    public function historicalYears(int $vesselId): array
    {
        return PmsActivitySnapshot::where('vessel_id', $vesselId)
            ->distinct()->orderByDesc('archived_year')->pluck('archived_year')->all();
    }

    /**
     * Ported from loadActivitiesData(). Legacy switches to
     * tb_pms_activities_history when the requested year isn't the
     * vessel's current year; here that's PmsActivitySnapshot. No
     * pagination — mirrors PmsRunningHoursPage's calendar-grid pattern,
     * since a vessel's activity list is a bounded set browsed as one grid.
     */
    public function table(int $vesselId, ?int $year, ?int $mainGroupId, ?int $criticalityId, ?string $search): array
    {
        $currentYear = $this->currentYear($vesselId);
        $isCurrent = $year === null || $year === $currentYear;

        $rows = $isCurrent
            ? $this->currentYearRows($vesselId, $mainGroupId, $criticalityId)
            : $this->historicalYearRows($vesselId, $year, $mainGroupId, $criticalityId);

        if ($search !== null && $search !== '') {
            $term = mb_strtolower($search);
            $rows = $rows->filter(function (array $row) use ($term) {
                $haystack = mb_strtolower(implode(' ', [
                    $row['activity_code'], $row['activity_name'], $row['equipment_name'] ?? '',
                    $row['part_name'] ?? '', $row['department'] ?? '', $row['criticality'] ?? '',
                    $row['incharge'] ?? '',
                ]));

                return str_contains($haystack, $term);
            });
        }

        // Ported default DataTable order: criticality DESC, activity_code ASC.
        return $rows->sortBy([
            fn (array $a, array $b) => strcmp($b['criticality'] ?? '', $a['criticality'] ?? ''),
            fn (array $a, array $b) => strcmp($a['activity_code'] ?? '', $b['activity_code'] ?? ''),
        ])->values()->all();
    }

    private function currentYearRows(int $vesselId, ?int $mainGroupId, ?int $criticalityId): Collection
    {
        $activities = PmsActivity::where('vessel_id', $vesselId)->where('is_active', true)
            ->with(['equipment.criticality', 'part', 'department', 'mainGroup'])
            ->when($mainGroupId !== null, fn ($q) => $q->where('spectec_main_group_id', $mainGroupId))
            ->when($criticalityId !== null, fn ($q) => $q->whereHas('equipment', fn ($eq) => $eq->where('criticality_id', $criticalityId)))
            ->get();

        return $activities->map(fn (PmsActivity $a) => $this->mapActivityRow($a));
    }

    private function historicalYearRows(int $vesselId, int $year, ?int $mainGroupId, ?int $criticalityId): Collection
    {
        $criticalityName = $criticalityId !== null ? PmsCriticality::find($criticalityId)?->name : null;
        $mainGroupName = $mainGroupId !== null ? SpectecMainGroup::find($mainGroupId)?->name : null;

        $snapshots = PmsActivitySnapshot::where('vessel_id', $vesselId)->where('archived_year', $year)
            ->when($mainGroupName !== null, fn ($q) => $q->where('main_group_name', $mainGroupName))
            ->when($criticalityName !== null, fn ($q) => $q->where('criticality_name', $criticalityName))
            ->get();

        return $snapshots->map(fn (PmsActivitySnapshot $s) => $this->mapSnapshotRow($s));
    }

    private function mapActivityRow(PmsActivity $a): array
    {
        return [
            'id' => $a->id,
            'is_snapshot' => false,
            'main_group' => $a->mainGroup ? "{$a->mainGroup->code} - {$a->mainGroup->name}" : null,
            'department' => $a->department?->name,
            'activity_code' => $a->activity_code,
            'activity_name' => $a->activity_name,
            'criticality' => $a->equipment?->criticality?->name,
            'equipment_name' => $a->equipment?->equipment_name,
            'part_name' => $a->part?->part_name,
            'incharge' => $a->incharge,
            'frequency' => $this->formatFrequency($a->min_count_interval, $a->max_count_interval, $a->unit, $a->other_unit),
            'total_hours' => $a->no_of_hours > 0 ? "{$a->no_of_hours} H" : null,
            'last_done' => $a->last_done?->format('Y-m-d'),
            // Blank for RH-tracked activities, matching legacy hiding due_date once count_rh >= 1.
            'due_date' => $a->since_delivery === null ? $a->due_date?->format('Y-m-d') : null,
            'status' => $this->statusFor($a),
            'months' => $this->monthCells($a->monthly_done, $a->monthly_postponed),
        ];
    }

    private function mapSnapshotRow(PmsActivitySnapshot $s): array
    {
        return [
            'id' => $s->id,
            'is_snapshot' => true,
            'main_group' => $s->main_group_name,
            'department' => $s->department_name,
            'activity_code' => $s->activity_code,
            'activity_name' => $s->activity_name,
            'criticality' => $s->criticality_name,
            'equipment_name' => $s->equipment_name,
            'part_name' => $s->part_name,
            'incharge' => $s->incharge,
            'frequency' => $this->formatFrequency($s->min_count_interval, $s->max_count_interval, $s->unit, null),
            'total_hours' => $s->no_of_hours > 0 ? "{$s->no_of_hours} H" : null,
            'last_done' => null,
            'due_date' => $s->since_delivery === null ? $s->due_date?->format('Y-m-d') : null,
            'status' => null,
            'months' => $this->monthCells($s->monthly_done, $s->monthly_postponed),
        ];
    }

    private function formatFrequency(int $min, int $max, string $unit, ?string $otherUnit): ?string
    {
        if ($unit === 'O') {
            return $otherUnit;
        }

        if ($max === 0) {
            return null;
        }

        return $min !== 0 ? "{$min} - {$max} {$unit}" : "{$max} {$unit}";
    }

    /** Ported from the is_overdue DataTable callback. */
    private function statusFor(PmsActivity $a): ?string
    {
        if ($a->is_postponed) {
            return 'postponed';
        }

        if ($a->since_delivery !== null) {
            $hoursPerUnit = self::HOURS_PER_UNIT[$a->unit];
            $overdueInterval = $a->max_count_interval !== 0 ? $a->max_count_interval : $a->min_count_interval;
            $overdueRange = ($overdueInterval * $hoursPerUnit) - $a->no_of_hours;

            if ($overdueRange < 0) {
                return 'overdue';
            }

            // Upcoming requires a nonzero running-hours meter, matching legacy's
            // `since_delivery != "0"` guard (absent from the overdue check above).
            if ($a->since_delivery === 0.0) {
                return null;
            }

            $upcomingInterval = $a->min_count_interval !== 0 ? $a->min_count_interval : $a->max_count_interval;
            $upcomingRange = ($upcomingInterval * $hoursPerUnit) - $a->no_of_hours;

            return ($upcomingRange > 0 && $upcomingRange <= 720) ? 'upcoming' : null;
        }

        if ($a->due_date === null) {
            return null;
        }

        $today = Carbon::today();

        if ($a->due_date->lt($today)) {
            return 'overdue';
        }

        return $a->due_date->copy()->subDays(30)->lte($today) ? 'upcoming' : null;
    }

    /** @return array<int, array{done: ?array, postponed: ?array}> */
    private function monthCells(?array $done, ?array $postponed): array
    {
        $cells = [];

        foreach (self::MONTH_KEYS as $month => $label) {
            $cells[$month] = [
                'done' => $done[$month] ?? null,
                'postponed' => $postponed[$month] ?? null,
            ];
        }

        return $cells;
    }

    public function activityDetail(PmsActivity $activity): array
    {
        $activity->load(['vessel', 'equipment.criticality', 'part', 'department', 'mainGroup']);

        return [
            'id' => $activity->id,
            'vessel' => $activity->vessel->display_name,
            'activity_code' => $activity->activity_code,
            'activity_name' => $activity->activity_name,
            'equipment_name' => $activity->equipment?->equipment_name,
            'part_name' => $activity->part?->part_name,
            'department' => $activity->department?->name,
            'main_group' => $activity->mainGroup ? "{$activity->mainGroup->code} - {$activity->mainGroup->name}" : null,
            'criticality' => $activity->equipment?->criticality?->name,
            'incharge' => $activity->incharge,
            'work_procedure' => $activity->work_procedure,
            'frequency' => $this->formatFrequency($activity->min_count_interval, $activity->max_count_interval, $activity->unit, $activity->other_unit),
            'last_done' => $activity->last_done?->format('Y-m-d'),
            'due_date' => $activity->due_date?->format('Y-m-d'),
            'is_overdue' => $this->statusFor($activity) === 'overdue',
            'is_postponed' => $activity->is_postponed,
            'postpone_date' => $activity->postpone_date?->format('Y-m-d'),
            'is_running_hours_tracked' => $activity->since_delivery !== null,
        ];
    }

    /**
     * Ported from update_last_done(). $lastDone within the vessel's live
     * month updates the activity forward (due date, overdue flag, and —
     * for running-hours-tracked activities — resets the since-last-
     * activity meter); an earlier $lastDone is a backdated correction
     * that only touches that month's calendar cell, matching legacy's
     * verified_* branching. Always writes a ticket log entry.
     */
    public function markDone(PmsActivity $activity, string $lastDone, bool $unplanned, ?string $description, ?string $possibleCause, ?string $remarks, string $reportedBy): PmsActivity
    {
        $lastDoneDate = Carbon::parse($lastDone);
        $month = $lastDoneDate->month;
        $isForward = $activity->last_done === null || $lastDoneDate->gte($activity->last_done);

        $isOverdue = $this->computeDoneOverdue($activity, $lastDoneDate);
        $ticketNo = $this->nextTicketNo($activity->vessel_id, $unplanned ? 'UNPLANNED' : 'PLANNED');

        $monthlyDone = $activity->monthly_done ?? [];
        $monthlyDone[$month] = ['day' => $lastDoneDate->day, 'ticket_no' => $ticketNo, 'is_overdue' => $isOverdue];

        $update = ['monthly_done' => $monthlyDone];

        if ($isForward) {
            $dueDate = $this->computeDueDate($lastDoneDate, $activity->max_count_interval, $activity->unit);

            $update = [
                ...$update,
                'last_done' => $lastDoneDate,
                'previous_due_date' => $activity->due_date,
                'due_date' => $dueDate,
                'is_overdue' => $isOverdue,
                'no_of_hours' => 0,
            ];
        }

        $activity->update($update);

        PmsTicket::create([
            'ticket_no' => $ticketNo,
            'vessel_id' => $activity->vessel_id,
            'pms_activity_id' => $activity->id,
            'type' => $unplanned ? 'UNPLANNED' : 'PLANNED',
            'activity_name' => $activity->activity_name,
            'date_of_activity' => $lastDoneDate,
            'description' => $description,
            'possible_cause' => $possibleCause,
            'remarks' => $remarks,
            'incharge' => $activity->incharge,
            'min_count_interval' => $activity->min_count_interval,
            'max_count_interval' => $activity->max_count_interval,
            'unit' => $activity->unit,
            'other_unit' => $activity->other_unit,
            'is_overdue' => $isOverdue,
            'equipment_name' => $activity->equipment?->equipment_name,
            'part_name' => $activity->part?->part_name,
            'previous_last_done' => $activity->getOriginal('last_done'),
            'previous_due_date' => $activity->getOriginal('due_date'),
            'reported_by' => $reportedBy,
        ]);

        return $activity->refresh();
    }

    private function computeDoneOverdue(PmsActivity $activity, Carbon $lastDoneDate): bool
    {
        if ($activity->since_delivery !== null) {
            $hoursPerUnit = self::HOURS_PER_UNIT[$activity->unit];

            return $activity->no_of_hours > ($activity->max_count_interval * $hoursPerUnit);
        }

        if ($activity->previous_due_date === null) {
            return false;
        }

        return $lastDoneDate->gt($activity->previous_due_date);
    }

    private function computeDueDate(Carbon $from, int $maxCountInterval, string $unit): Carbon
    {
        return match ($unit) {
            'H' => $from->copy()->addHours($maxCountInterval),
            'D' => $from->copy()->addDays($maxCountInterval),
            'W' => $from->copy()->addWeeks($maxCountInterval),
            'M' => $from->copy()->addMonths($maxCountInterval),
            'Y' => $from->copy()->addYears($maxCountInterval),
            default => $from->copy(),
        };
    }

    /** Ported from postpone_activity(). */
    public function postpone(PmsActivity $activity, string $postponeDate, string $description, string $possibleCause, ?string $remarks): PmsActivity
    {
        $postponeCarbon = Carbon::parse($postponeDate);
        $month = $postponeCarbon->month;
        $ticketNo = $this->nextTicketNo($activity->vessel_id, 'POSTPONED');

        $monthlyPostponed = $activity->monthly_postponed ?? [];
        $monthlyPostponed[$month] = ['day' => $postponeCarbon->day, 'ticket_no' => $ticketNo];

        $activity->update([
            'is_postponed' => true,
            'postpone_date' => $postponeCarbon,
            'monthly_postponed' => $monthlyPostponed,
        ]);

        PmsTicket::create([
            'ticket_no' => $ticketNo,
            'vessel_id' => $activity->vessel_id,
            'pms_activity_id' => $activity->id,
            'type' => 'POSTPONED',
            'activity_name' => $activity->activity_name,
            'date_of_activity' => $postponeCarbon,
            'description' => $description,
            'possible_cause' => $possibleCause,
            'remarks' => $remarks,
            'incharge' => $activity->incharge,
            'min_count_interval' => $activity->min_count_interval,
            'max_count_interval' => $activity->max_count_interval,
            'unit' => $activity->unit,
            'other_unit' => $activity->other_unit,
            'equipment_name' => $activity->equipment?->equipment_name,
            'part_name' => $activity->part?->part_name,
        ]);

        return $activity->refresh();
    }

    /** Ported from the ticket numbering in update_last_done()/postpone_activity(): TYPE-{vessel short name}-{year}-{seq}, sequence reset per vessel+type+year (of ticket creation, not activity date). */
    private function nextTicketNo(int $vesselId, string $type): string
    {
        $vessel = Vessel::findOrFail($vesselId);
        $year = Carbon::today()->year;

        $count = PmsTicket::where('vessel_id', $vesselId)->where('type', $type)
            ->whereYear('created_at', $year)->count();

        $shortName = $vessel->prefix ? str_replace(' ', '', $vessel->prefix.$vessel->name) : str_replace(' ', '', $vessel->name);

        return "{$type}-{$shortName}-{$year}-".($count + 1);
    }

    public function ticket(string $ticketNo): PmsTicket
    {
        $ticket = PmsTicket::with('vessel')->where('ticket_no', $ticketNo)->first();

        if (! $ticket) {
            throw ValidationException::withMessages(['ticket_no' => ['Ticket not found.']]);
        }

        return $ticket;
    }

    /*
     * ------------------------------------------------------------------
     * Legacy DB support. tb_pms_activities stores each month's done/
     * postponed cell as five physical columns (not the JSON maps used
     * locally) — read/written directly below. Not ported: actual-
     * inventory-used tracking and the running-hours side effects on
     * mark-done, for the same reasons already documented at the top of
     * this class (dead in production / owned by the separate PMS
     * Running Hours module) — this mirrors the same scope reduction
     * against the legacy connection.
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

    /** @return array<int, array{id:string,label:string}> */
    public function legacyDepartmentOptions(): array
    {
        return DB::connection('legacy')->table('tb_pms_department')
            ->orderBy('department_name')
            ->get()
            ->map(fn ($d) => ['id' => $d->deptID, 'label' => $d->department_name])
            ->all();
    }

    /** @return array<int, array{id:string,label:string}> */
    public function legacyCriticalityOptions(): array
    {
        return DB::connection('legacy')->table('pl_pms_criticality')
            ->orderBy('criticality')
            ->get()
            ->map(fn ($c) => ['id' => $c->criticalID, 'label' => $c->criticality])
            ->all();
    }

    /** @return array<int, array{id:string,label:string}> */
    public function legacyMainGroupOptions(): array
    {
        return DB::connection('legacy')->table('pl_spectec_main_group')
            ->orderBy('main_group_no')
            ->get()
            ->map(fn ($g) => ['id' => $g->mgID, 'label' => "{$g->main_group_no} - {$g->main_group_name}"])
            ->all();
    }

    /** Ported from pms_current_month()/pms_current_year(), reading tb_pms_running_hours_details. */
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

    public function legacyCurrentYear(string $vesselId): ?int
    {
        return $this->legacyCurrentPeriod($vesselId)['year'] ?? null;
    }

    /** @return array<int, int> years with tb_pms_activities_history rows for this vessel, newest first */
    public function legacyHistoricalYears(string $vesselId): array
    {
        return DB::connection('legacy')->table('tb_pms_activities_history')
            ->where('vesID', $vesselId)
            ->distinct()->orderByDesc('year')->pluck('year')
            ->map(fn ($y) => (int) $y)->all();
    }

    /**
     * Ported from loadActivitiesData(), reading tb_pms_activities
     * (current year) or tb_pms_activities_history (past years) directly
     * from the legacy connection. Not ported: DataTable pagination —
     * mirrors the local table()'s "bounded set browsed as one grid".
     */
    public function legacyTable(string $vesselId, ?int $year, ?string $mainGroupId, ?string $criticalityId, ?string $search): array
    {
        $currentYear = $this->legacyCurrentYear($vesselId);
        $isCurrent = $year === null || $year === $currentYear;
        $table = $isCurrent ? 'tb_pms_activities' : 'tb_pms_activities_history';

        $builder = DB::connection('legacy')->table("{$table} as ta")
            ->leftJoin('pl_spectec_main_group', DB::raw('pl_spectec_main_group.main_group_no'), '=', DB::raw('SUBSTR(ta.activity_code,1,1)'))
            ->leftJoin('tb_pms_equipment', 'tb_pms_equipment.equipmentID', '=', 'ta.equipmentID')
            ->leftJoin('pl_pms_criticality', 'pl_pms_criticality.criticalID', '=', 'tb_pms_equipment.criticalID')
            ->leftJoin('tb_pms_parts as tp', 'tp.partsID', '=', 'ta.partsID')
            ->leftJoin('tb_pms_department', 'tb_pms_department.deptID', '=', 'ta.deptID')
            ->leftJoin('pl_position', 'pl_position.posID', '=', 'ta.posID')
            ->where('ta.vesID', $vesselId)
            ->where('ta.active_status', 1);

        if (! $isCurrent) {
            $builder->where('ta.year', $year);
        }

        if ($mainGroupId !== null) {
            $builder->where('pl_spectec_main_group.mgID', $mainGroupId);
        }

        if ($criticalityId !== null) {
            $builder->where('tb_pms_equipment.criticalID', $criticalityId);
        }

        $monthColumns = collect(self::MONTH_KEYS)->flatMap(fn ($label, $m) => [
            "ta.{$label}", "ta.{$label}_is_overdue", "ta.{$label}_update_ticketID",
            "ta.{$label}_postponed", "ta.{$label}_postponed_ticketID",
        ])->all();

        $rows = $builder->select([
            'ta.activityID', 'ta.activity_code', 'ta.activity_name',
            'pl_spectec_main_group.main_group_no', 'pl_spectec_main_group.main_group_name',
            'tb_pms_department.department_name',
            'pl_pms_criticality.criticality',
            'tb_pms_equipment.equipment_name',
            'tp.part_name',
            'pl_position.long_posname as incharge',
            'ta.min_count_interval', 'ta.max_count_interval', 'ta.unit', 'ta.other_unit',
            'ta.no_of_hours', 'ta.last_done', 'ta.due_date', 'ta.is_overdue', 'ta.is_postpone',
            DB::raw('(SELECT COUNT(*) FROM tb_pms_running_hours_details WHERE partsID = ta.partsID) as count_rh'),
            ...$monthColumns,
        ])->get();

        $rows = $rows->map(fn ($r) => $this->mapLegacyRow($r, ! $isCurrent));

        if ($search !== null && $search !== '') {
            $term = mb_strtolower($search);
            $rows = $rows->filter(function (array $row) use ($term) {
                $haystack = mb_strtolower(implode(' ', [
                    $row['activity_code'] ?? '', $row['activity_name'], $row['equipment_name'] ?? '',
                    $row['part_name'] ?? '', $row['department'] ?? '', $row['criticality'] ?? '',
                    $row['incharge'] ?? '',
                ]));

                return str_contains($haystack, $term);
            });
        }

        return $rows->sortBy([
            fn (array $a, array $b) => strcmp($b['criticality'] ?? '', $a['criticality'] ?? ''),
            fn (array $a, array $b) => strcmp($a['activity_code'] ?? '', $b['activity_code'] ?? ''),
        ])->values()->all();
    }

    /** @param object $r raw joined row from legacyTable()'s query */
    private function mapLegacyRow(object $r, bool $isSnapshot): array
    {
        $zeroDateToNull = fn (?string $date) => ($date === null || $date === '0000-00-00') ? null : $date;

        $months = [];
        foreach (self::MONTH_KEYS as $month => $label) {
            $day = $r->{$label};
            $postponedDay = $r->{"{$label}_postponed"};

            $months[$month] = [
                'done' => ($day !== null && $day !== '')
                    ? ['day' => (int) $day, 'ticket_no' => $r->{"{$label}_update_ticketID"}, 'is_overdue' => (bool) $r->{"{$label}_is_overdue"}]
                    : null,
                'postponed' => ($postponedDay !== null && $postponedDay !== '')
                    ? ['day' => (int) $postponedDay, 'ticket_no' => $r->{"{$label}_postponed_ticketID"}]
                    : null,
            ];
        }

        $status = null;
        if ((bool) $r->is_postpone) {
            $status = 'postponed';
        } elseif ((bool) $r->is_overdue) {
            $status = 'overdue';
        } elseif ($zeroDateToNull($r->due_date) !== null) {
            $dueDate = Carbon::parse($r->due_date);
            $today = Carbon::today();

            if ($dueDate->gte($today) && $dueDate->copy()->subDays(30)->lte($today)) {
                $status = 'upcoming';
            }
        }

        return [
            'id' => $r->activityID,
            'is_snapshot' => $isSnapshot,
            'main_group' => $r->main_group_no !== null ? "{$r->main_group_no} - {$r->main_group_name}" : null,
            'department' => $r->department_name,
            'activity_code' => $r->activity_code,
            'activity_name' => $r->activity_name,
            'criticality' => $r->criticality,
            'equipment_name' => $r->equipment_name,
            'part_name' => $r->part_name,
            'incharge' => $r->incharge,
            'frequency' => $this->formatFrequency((int) $r->min_count_interval, (int) $r->max_count_interval, $r->unit, $r->other_unit),
            'total_hours' => $r->no_of_hours > 0 ? "{$r->no_of_hours} H" : null,
            'last_done' => $zeroDateToNull($r->last_done),
            'due_date' => ((int) $r->count_rh) >= 1 ? null : $zeroDateToNull($r->due_date),
            'status' => $status,
            'months' => $months,
        ];
    }

    /** Ported from view_activity(). */
    public function legacyActivityDetail(string $activityId): array
    {
        $a = DB::connection('legacy')->table('tb_pms_activities as ta')
            ->leftJoin('tb_vessel', 'tb_vessel.vesID', '=', 'ta.vesID')
            ->leftJoin('tb_pms_equipment', 'tb_pms_equipment.equipmentID', '=', 'ta.equipmentID')
            ->leftJoin('pl_pms_criticality', 'pl_pms_criticality.criticalID', '=', 'tb_pms_equipment.criticalID')
            ->leftJoin('tb_pms_parts as tp', 'tp.partsID', '=', 'ta.partsID')
            ->leftJoin('tb_pms_department', 'tb_pms_department.deptID', '=', 'ta.deptID')
            ->leftJoin('pl_position', 'pl_position.posID', '=', 'ta.posID')
            ->leftJoin('pl_spectec_main_group', DB::raw('pl_spectec_main_group.main_group_no'), '=', DB::raw('SUBSTR(ta.activity_code,1,1)'))
            ->where('ta.activityID', $activityId)
            ->select([
                'ta.activityID', 'ta.activity_code', 'ta.activity_name', 'ta.work_procedure',
                'ta.min_count_interval', 'ta.max_count_interval', 'ta.unit', 'ta.other_unit',
                'ta.last_done', 'ta.due_date', 'ta.is_overdue', 'ta.is_postpone', 'ta.postpone_date', 'ta.partsID',
                'tb_vessel.vessel_name', 'tb_vessel.vessel_prefix',
                'tb_pms_equipment.equipment_name', 'tp.part_name', 'tb_pms_department.department_name',
                'pl_pms_criticality.criticality', 'pl_position.long_posname as incharge',
                'pl_spectec_main_group.main_group_no', 'pl_spectec_main_group.main_group_name',
                DB::raw('(SELECT COUNT(*) FROM tb_pms_running_hours_details WHERE partsID = ta.partsID) as count_rh'),
            ])
            ->first();

        abort_if($a === null, 404);

        $zeroDateToNull = fn (?string $date) => ($date === null || $date === '0000-00-00') ? null : $date;

        return [
            'id' => $a->activityID,
            'vessel' => trim("{$a->vessel_prefix} {$a->vessel_name}"),
            'activity_code' => $a->activity_code,
            'activity_name' => $a->activity_name,
            'equipment_name' => $a->equipment_name,
            'part_name' => $a->part_name,
            'department' => $a->department_name,
            'main_group' => $a->main_group_no !== null ? "{$a->main_group_no} - {$a->main_group_name}" : null,
            'criticality' => $a->criticality,
            'incharge' => $a->incharge,
            'work_procedure' => $a->work_procedure,
            'frequency' => $this->formatFrequency((int) $a->min_count_interval, (int) $a->max_count_interval, $a->unit, $a->other_unit),
            'last_done' => $zeroDateToNull($a->last_done),
            'due_date' => ((int) $a->count_rh) >= 1 ? null : $zeroDateToNull($a->due_date),
            'is_overdue' => (bool) $a->is_overdue,
            'is_postponed' => (bool) $a->is_postpone,
            'postpone_date' => $zeroDateToNull($a->postpone_date),
            'is_running_hours_tracked' => ((int) $a->count_rh) >= 1,
        ];
    }

    /**
     * Ported from update_last_done()'s non-inventory path: computes the
     * due date the same way (H/D/W/M/Y interval added to $lastDone),
     * writes the target month's cell, and only moves last_done/due_date/
     * is_overdue/is_postpone forward when $lastDone is not a backdated
     * correction — mirrors the local markDone()'s isForward branching,
     * itself ported from update_last_done()'s verified_* variables.
     */
    public function legacyMarkDone(string $activityId, string $lastDone, bool $unplanned, ?string $description, ?string $possibleCause, ?string $remarks, string $reportedBy): array
    {
        $legacy = DB::connection('legacy');
        $activity = $legacy->table('tb_pms_activities')->where('activityID', $activityId)->first();
        abort_if($activity === null, 404);

        $vessel = $legacy->table('tb_vessel')->where('vesID', $activity->vesID)->first();
        $lastDoneDate = Carbon::parse($lastDone);
        $monthKey = self::MONTH_KEYS[$lastDoneDate->month];
        $isForward = $lastDone >= $activity->last_done;

        $isOverdue = $activity->previous_due_date !== '0000-00-00'
            && $lastDoneDate->gt(Carbon::parse($activity->previous_due_date));

        $ticketType = $unplanned ? 'UNPLANNED' : 'PLANNED';
        $ticketNo = $this->legacyNextTicketNo($activity->vesID, $vessel->short_name, $ticketType);

        $updates = [
            $monthKey => $lastDoneDate->day,
            "{$monthKey}_is_overdue" => $isOverdue ? 1 : 0,
            "{$monthKey}_update_ticketID" => $ticketNo,
        ];

        $dueDateForTicket = $activity->due_date;

        if ($isForward) {
            $dueDate = $this->computeLegacyDueDate($lastDoneDate, (int) $activity->max_count_interval, $activity->unit);
            $dueDateForTicket = $dueDate->toDateString();

            $updates = [
                ...$updates,
                'last_done' => $lastDoneDate->toDateString(),
                'no_of_hours' => 0,
                'previous_due_date' => $activity->due_date,
                'due_date' => $dueDateForTicket,
                'is_overdue' => $isOverdue ? 1 : 0,
                'is_postpone' => 0,
                'postpone_date' => '0000-00-00',
            ];
        }

        $legacy->table('tb_pms_activities')->where('activityID', $activityId)->update($updates);

        $equipment = $activity->equipmentID !== '' ? $legacy->table('tb_pms_equipment')->where('equipmentID', $activity->equipmentID)->first() : null;
        $part = $activity->partsID !== '' ? $legacy->table('tb_pms_parts')->where('partsID', $activity->partsID)->first() : null;
        $incharge = $activity->posID !== '' ? $legacy->table('pl_position')->where('posID', $activity->posID)->first() : null;

        $legacy->table('tb_pms_ticket')->where('ticketID', $ticketNo)->delete();
        $legacy->table('tb_pms_ticket')->insert([
            ...$this->legacyTicketDefaults(),
            'ticketID' => $ticketNo,
            'vesID' => $activity->vesID,
            'type' => $ticketType,
            'deptID' => $activity->deptID,
            'date_created' => Carbon::today()->toDateString(),
            'activityID' => $activityId,
            'activity_name' => $activity->activity_name,
            'last_done' => $lastDoneDate->toDateString(),
            'is_unplanned' => $unplanned ? 'YES' : 'NO',
            'description' => $description ?? '',
            'possible_cause' => $possibleCause ?? '',
            'remarks' => $remarks ?? '',
            'incharge' => $activity->posID,
            'incharge_name' => $incharge->long_posname ?? '',
            'min_count_interval' => $activity->min_count_interval,
            'max_count_interval' => $activity->max_count_interval,
            'unit' => $activity->unit,
            'other_unit' => $activity->other_unit,
            'due_date' => $dueDateForTicket,
            'year' => $lastDoneDate->year,
            'is_overdue' => $isOverdue ? 1 : 0,
            'equipmentID' => $activity->equipmentID,
            'equipment_name' => $equipment->equipment_name ?? '',
            'partsID' => $activity->partsID,
            'part_name' => $part->part_name ?? '',
            'previous_last_done' => $activity->last_done,
            'previous_duedate' => $activity->due_date,
            'reported_by' => $reportedBy,
            'datetime' => Carbon::now()->toDateTimeString(),
        ]);

        return $this->legacyActivityDetail($activityId);
    }

    /** Ported from postpone_activity(). */
    public function legacyPostpone(string $activityId, string $postponeDate, string $description, string $possibleCause, ?string $remarks): array
    {
        $legacy = DB::connection('legacy');
        $activity = $legacy->table('tb_pms_activities')->where('activityID', $activityId)->first();
        abort_if($activity === null, 404);

        $vessel = $legacy->table('tb_vessel')->where('vesID', $activity->vesID)->first();
        $postponeCarbon = Carbon::parse($postponeDate);
        $monthKey = self::MONTH_KEYS[$postponeCarbon->month];
        $ticketNo = $this->legacyNextTicketNo($activity->vesID, $vessel->short_name, 'POSTPONED');

        $legacy->table('tb_pms_activities')->where('activityID', $activityId)->update([
            'is_postpone' => 1,
            'postpone_date' => $postponeCarbon->toDateString(),
            "{$monthKey}_postponed" => $postponeCarbon->day,
            "{$monthKey}_postponed_ticketID" => $ticketNo,
        ]);

        $legacy->table('tb_pms_ticket')->where('ticketID', $ticketNo)->delete();
        $legacy->table('tb_pms_ticket')->insert([
            ...$this->legacyTicketDefaults(),
            'ticketID' => $ticketNo,
            'vesID' => $activity->vesID,
            'type' => 'POSTPONED',
            'deptID' => $activity->deptID,
            'date_created' => Carbon::today()->toDateString(),
            'activityID' => $activityId,
            'description' => $description,
            'possible_cause' => $possibleCause,
            'remarks' => $remarks ?? '',
            'date_postponed' => $postponeCarbon->toDateString(),
            'last_done' => $activity->last_done,
            'due_date' => $activity->due_date,
            'incharge' => $activity->posID,
            'min_count_interval' => $activity->min_count_interval,
            'max_count_interval' => $activity->max_count_interval,
            'unit' => $activity->unit,
            'other_unit' => $activity->other_unit,
            'year' => (int) $activity->year,
            'equipmentID' => $activity->equipmentID,
            'partsID' => $activity->partsID,
            'datetime' => Carbon::now()->toDateTimeString(),
        ]);

        return $this->legacyActivityDetail($activityId);
    }

    /** Ported from the ticket numbering in update_last_done()/postpone_activity(): TYPE-{vessel short name}-{year}-{seq}, sequence reset per vessel+type+year (of ticket creation, not activity date). */
    private function legacyNextTicketNo(string $vesselId, string $shortName, string $type): string
    {
        $year = Carbon::today()->year;

        $count = DB::connection('legacy')->table('tb_pms_ticket')
            ->where('vesID', $vesselId)->where('type', $type)
            ->whereYear('datetime', $year)->count();

        return "{$type}-".str_replace(' ', '', $shortName)."-{$year}-".($count + 1);
    }

    private function computeLegacyDueDate(Carbon $from, int $maxCountInterval, string $unit): Carbon
    {
        return match ($unit) {
            'H' => $from->copy()->addHours($maxCountInterval),
            'D' => $from->copy()->addDays($maxCountInterval),
            'W' => $from->copy()->addWeeks($maxCountInterval),
            'M' => $from->copy()->addMonths($maxCountInterval),
            'Y' => $from->copy()->addYears($maxCountInterval),
            default => $from->copy(),
        };
    }

    /** @return array<string, mixed> defaults for tb_pms_ticket's NOT NULL columns not otherwise set per call */
    private function legacyTicketDefaults(): array
    {
        return [
            'userID' => '', 'remarks' => '', 'running_hours' => '', 'reset_status' => '',
            'replaced_by' => '', 'replaced_record' => '', 'no_of_hrs_since_delivery' => 0, 'no_of_hrs' => 0,
            'planned' => '', 'equipmentID' => '', 'equipment_name' => '', 'partsID' => '', 'part_name' => '',
            'activityID' => '', 'activity_name' => '', 'last_done' => '0000-00-00',
            'work_plan_type' => '', 'work_plan_location' => '', 'work_plan_sub_location' => '',
            'work_plan_activity' => '', 'work_plan_jobID' => '', 'work_plan_jobtypeID' => '',
            'work_plan_incharge' => '', 'work_plan_assignee' => '', 'work_plan_work_procedure' => '',
            'is_unplanned' => 'NO', 'description' => '', 'possible_cause' => '', 'unplanned' => '',
            'master' => '', 'chief_engineer' => '', 'incharge' => '', 'incharge_name' => '', 'reported_by' => '',
            'min_count_interval' => 0, 'max_count_interval' => 0, 'unit' => '', 'other_unit' => '',
            'previous_duedate' => '0000-00-00', 'previous_is_overdue' => 0, 'previous_is_postpone' => 0,
            'previous_postpone_date' => '0000-00-00', 'previous_last_done' => '0000-00-00',
            'due_date' => '0000-00-00', 'year' => (int) date('Y'), 'is_overdue' => 0,
            'is_closed' => 0, 'is_approved' => 0, 'is_rejected' => 0,
            'date_rejected' => '0000-00-00', 'date_postponed' => '0000-00-00',
            'date_running_hours' => '0000-00-00', 'date_reset' => '0000-00-00',
            'status' => 0, 'datetime' => now()->toDateTimeString(),
        ];
    }

    public function legacyTicket(string $ticketNo): array
    {
        $t = DB::connection('legacy')->table('tb_pms_ticket as t')
            ->leftJoin('tb_vessel', 'tb_vessel.vesID', '=', 't.vesID')
            ->where('t.ticketID', $ticketNo)
            ->select(['t.*', 'tb_vessel.vessel_name', 'tb_vessel.vessel_prefix'])
            ->first();

        if ($t === null) {
            throw ValidationException::withMessages(['ticket_no' => ['Ticket not found.']]);
        }

        $zeroDateToNull = fn (?string $date) => ($date === null || $date === '0000-00-00') ? null : $date;

        return [
            'ticket_no' => $t->ticketID,
            'type' => $t->type,
            'vessel' => trim("{$t->vessel_prefix} {$t->vessel_name}"),
            'activity_name' => $t->activity_name,
            'date_of_activity' => $zeroDateToNull($t->type === 'POSTPONED' ? $t->date_postponed : $t->last_done),
            'description' => $t->description ?: null,
            'possible_cause' => $t->possible_cause ?: null,
            'remarks' => $t->remarks ?: null,
            'incharge' => $t->incharge_name ?: null,
            'frequency' => $this->formatFrequency((int) $t->min_count_interval, (int) $t->max_count_interval, $t->unit, $t->other_unit),
            'is_overdue' => $t->type === 'POSTPONED' ? null : (bool) $t->is_overdue,
            'equipment_name' => $t->equipment_name ?: null,
            'part_name' => $t->part_name ?: null,
            'previous_last_done' => $zeroDateToNull($t->previous_last_done),
            'previous_due_date' => $zeroDateToNull($t->previous_duedate),
            'reported_by' => $t->reported_by ?: null,
            'created_at' => substr($t->datetime, 0, 16),
        ];
    }
}
