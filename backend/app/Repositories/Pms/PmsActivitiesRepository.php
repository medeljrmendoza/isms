<?php

namespace App\Repositories\Pms;

use App\Models\Pms\PmsActivity;
use App\Models\Pms\PmsActivitySnapshot;
use App\Models\Pms\PmsCriticality;
use App\Models\Pms\PmsDepartment;
use App\Models\Pms\PmsTicket;
use App\Models\Pms\SpectecMainGroup;
use App\Models\Vessel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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
}
