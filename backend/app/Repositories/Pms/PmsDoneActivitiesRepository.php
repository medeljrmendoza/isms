<?php

namespace App\Repositories\Pms;

use App\Models\Pms\PmsTicket;
use App\Models\Vessel;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Ported from Controllers/Pms_done_activities.php. A read-only report
 * over the same tb_pms_ticket log that PmsActivitiesRepository::markDone()
 * writes to (PLANNED/UNPLANNED entries only — postponed activities
 * aren't "done" and are excluded, matching legacy's type filter).
 */
class PmsDoneActivitiesRepository
{
    private const COLUMNS = [
        ['key' => 'date_of_activity', 'label' => 'DATE OF ACTIVITY', 'sortable' => true],
        ['key' => 'previous_due_date', 'label' => 'DUE DATE', 'sortable' => true],
        ['key' => 'previous_last_done', 'label' => 'PREVIOUS DATE OF ACTIVITY', 'sortable' => true],
        ['key' => 'equipment_name', 'label' => 'COMPONENT', 'sortable' => true],
        ['key' => 'part_name', 'label' => 'PART', 'sortable' => true],
        ['key' => 'activity_code', 'label' => 'ACTIVITY CODE', 'sortable' => false],
        ['key' => 'activity_name', 'label' => 'ACTIVITY', 'sortable' => true],
        ['key' => 'frequency', 'label' => 'FREQUENCY', 'sortable' => false],
        ['key' => 'incharge', 'label' => 'IN-CHARGE', 'sortable' => true],
        ['key' => 'reported_by', 'label' => 'REPORTED BY', 'sortable' => true],
        ['key' => 'created_at', 'label' => 'DATE REPORTED', 'sortable' => true],
    ];

    public static function columns(): array
    {
        return self::COLUMNS;
    }

    /** @return array<int, array{id:int,label:string}> */
    public function vesselOptions(): array
    {
        return Vessel::query()->orderBy('name')->get()
            ->map(fn (Vessel $v) => ['id' => $v->id, 'label' => $v->display_name])
            ->all();
    }

    /** Ported from loadData(): vessel + a required date_from/date_to range on date_of_activity. */
    public function table(int $vesselId, string $dateFrom, string $dateTo, TableQuery $query): LengthAwarePaginator
    {
        $builder = PmsTicket::query()
            ->with('activity')
            ->where('vessel_id', $vesselId)
            ->whereIn('type', ['PLANNED', 'UNPLANNED'])
            ->whereDate('date_of_activity', '>=', $dateFrom)
            ->whereDate('date_of_activity', '<=', $dateTo);

        if ($query->search !== null) {
            $term = "%{$query->search}%";
            $builder->where(function (Builder $q) use ($term) {
                $q->where('equipment_name', 'like', $term)
                    ->orWhere('part_name', 'like', $term)
                    ->orWhere('activity_name', 'like', $term)
                    ->orWhere('incharge', 'like', $term)
                    ->orWhere('reported_by', 'like', $term)
                    ->orWhereHas('activity', fn (Builder $a) => $a->where('activity_code', 'like', $term));
            });
        }

        $sortable = array_column(array_filter(self::COLUMNS, fn ($c) => $c['sortable']), 'key');
        $sort = in_array($query->sort, $sortable, true) ? $query->sort : 'date_of_activity';

        return $builder->orderBy($sort, $query->direction)
            ->paginate($query->perPage, page: $query->page);
    }

    private function formatFrequency(int $min, int $max, ?string $unit, ?string $otherUnit): ?string
    {
        if ($unit === 'O') {
            return $otherUnit;
        }

        if ($max === 0) {
            return null;
        }

        return $min !== 0 ? "{$min} - {$max} {$unit}" : "{$max} {$unit}";
    }

    public function mapRow(PmsTicket $ticket): array
    {
        return [
            'id' => $ticket->id,
            'ticket_no' => $ticket->ticket_no,
            'date_of_activity' => $ticket->date_of_activity->format('Y-m-d'),
            'previous_due_date' => $ticket->previous_due_date?->format('Y-m-d'),
            'previous_last_done' => $ticket->previous_last_done?->format('Y-m-d'),
            'equipment_name' => $ticket->equipment_name,
            'part_name' => $ticket->part_name,
            'activity_code' => $ticket->activity?->activity_code,
            'activity_name' => $ticket->activity_name,
            'frequency' => $this->formatFrequency($ticket->min_count_interval, $ticket->max_count_interval, $ticket->unit, $ticket->other_unit),
            'incharge' => $ticket->incharge,
            'reported_by' => $ticket->reported_by,
            'created_at' => $ticket->created_at->format('Y-m-d'),
        ];
    }
}
