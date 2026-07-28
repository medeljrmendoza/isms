<?php

namespace App\Repositories\IncidentReports;

use App\Models\IncidentReports\IncidentPersonParticipated;
use App\Models\IncidentReports\IncidentReport;
use App\Models\IncidentReports\IncidentRootCause;
use App\Models\Vessel;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class IncidentReportRepository
{
    /** Only meaningful when nature_type = 'accident' — cleared on save otherwise. */
    private const ACCIDENT_ONLY_FIELDS = [
        'nature_of_incident_id', 'others', 'accident_collision',
        'bac', 'vdr', 'dateof_event', 'timeof_event', 'zone', 'country', 'speed', 'course',
        'draft_forward', 'draft_alt', 'wind_direction', 'direction_sea', 'direction_swell', 'geographical_location',
        'port_departure', 'date_departure', 'port_which_bound', 'type_cargo', 'cargo_quantity', 'special_requirement',
        'atmospheric_clear', 'atmospheric_partly_cloudy', 'atmospheric_overcast', 'atmospheric_fog', 'atmospheric_rain',
        'atmospheric_snow', 'atmospheric_other', 'atmospheric_other_name', 'distance1', 'distance2', 'distance3',
        'sea1', 'sea2', 'sea3', 'crew_onboard', 'other_onboard', 'total_onboard', 'crew_dead', 'other_dead', 'total_dead',
        'crew_missing', 'other_missing', 'total_missing', 'crew_injured', 'other_injured', 'total_injured', 'fs_ro',
    ];

    /** Only meaningful when nature_type = 'hazardous_occurrence' — cleared on save otherwise. */
    private const HAZARDOUS_OCCURRENCE_ONLY_FIELDS = [
        'hazardous_occurrence_type', 'incident_location_id', 'location_other', 'ship_position', 'incident_operation_id',
        'ship_operation_other', 'hazardous_occurrence_ppe_used', 'hazardous_occurrence_ppe_used_comment',
        'hazardous_occurrence_severity', 'hazardous_occurrence_severity_comment', 'hazardous_occurrence_likelihood',
        'hazardous_occurrence_likelihood_comment', 'subject_investigation', 'evidence_safety_meeting', 'evidence_certificate',
        'evidence_logbook', 'evidence_delivery', 'evidence_photo', 'evidence_company', 'evidence_others', 'evidence_others_name',
        'causal_factor', 'intermediate_cause', 'shore_root_cause_summary',
    ];

    /**
     * "vessel" and "type" aren't real sortable columns — vessel is
     * resolved from a relation, and "type" is a computed label combining
     * nature_type, the nature-of-incident name, and a conditional
     * free-text suffix. Same simplification as Claims' "vessel" column.
     */
    private const COLUMNS = [
        ['key' => 'vessel', 'label' => 'VESSEL', 'sortable' => false],
        ['key' => 'dateof_report', 'label' => 'DATE OF REPORT', 'sortable' => true],
        ['key' => 'nature', 'label' => 'NATURE', 'sortable' => true],
        ['key' => 'type', 'label' => 'TYPE', 'sortable' => false],
    ];

    /** The full module list's column set — see Controllers/Incident.php's loadData(). */
    private const MODULE_COLUMNS = [
        ['key' => 'vessel', 'label' => 'VESSEL', 'sortable' => false],
        ['key' => 'dateof_report', 'label' => 'DATE OF REPORT', 'sortable' => true],
        ['key' => 'report_no', 'label' => 'REPORT NO.', 'sortable' => true],
        ['key' => 'nature', 'label' => 'NATURE', 'sortable' => true],
        ['key' => 'type', 'label' => 'TYPE', 'sortable' => false],
        ['key' => 'added_by', 'label' => 'ADDED BY', 'sortable' => true],
        ['key' => 'published', 'label' => 'PUBLISHED', 'sortable' => false],
        ['key' => 'is_approved', 'label' => 'APPROVED', 'sortable' => false],
        ['key' => 'status', 'label' => 'STATUS', 'sortable' => false],
    ];

    public static function columns(): array
    {
        return self::COLUMNS;
    }

    public static function moduleColumns(): array
    {
        return self::MODULE_COLUMNS;
    }

    /**
     * Ported from Controllers/Dashboard_incident.php's loadIncidentData()
     * WHERE clause: still open (no closing date) or not yet approved.
     * Not scoped by vessel/user — same deferral as the other dashlets.
     */
    public function pendingQuery(): Builder
    {
        return IncidentReport::query()
            ->with(['vessel', 'natureOfIncident'])
            ->where(function (Builder $query) {
                $query->whereNull('closing_date')
                    ->orWhere('is_approved', false);
            });
    }

    public function pending()
    {
        return $this->pendingQuery()->orderByDesc('dateof_report')->get();
    }

    public function table(TableQuery $query): LengthAwarePaginator
    {
        $builder = $this->pendingQuery();

        if ($query->search !== null) {
            $term = "%{$query->search}%";
            $builder->where(function (Builder $q) use ($term) {
                $q->where('dateof_report', 'like', $term)
                    ->orWhere('nature_type', 'like', $term)
                    ->orWhere('hazardous_occurrence_type', 'like', $term)
                    ->orWhereHas('vessel', fn (Builder $v) => $v->where('name', 'like', $term))
                    ->orWhereHas('natureOfIncident', fn (Builder $n) => $n->where('name', 'like', $term));
            });
        }

        $sortable = array_column(array_filter(self::COLUMNS, fn ($c) => $c['sortable']), 'key');
        $sortMap = ['nature' => 'nature_type', 'dateof_report' => 'dateof_report'];
        $sort = in_array($query->sort, $sortable, true) ? ($sortMap[$query->sort] ?? $query->sort) : 'dateof_report';

        return $builder->orderBy($sort, $query->direction)
            ->paginate($query->perPage, page: $query->page);
    }

    /**
     * Ported from Controllers/Incident.php's loadData(). The
     * `WHERE vesid IN (SELECT ... tb_user_vessel)` scoping is dropped
     * like everywhere else (see conversation notes on deferred
     * vessel/user permission scoping); the vessel + year filters are
     * kept since those are genuine user-facing filters.
     */
    public function fullTable(TableQuery $query, ?string $vesselId, ?string $year): LengthAwarePaginator
    {
        $builder = IncidentReport::query()->with(['vessel', 'natureOfIncident']);

        if ($vesselId === 'ALL') {
            if ($year !== null && $year !== '') {
                $builder->whereYear('dateof_report', $year);
            }
        } elseif ($vesselId !== null && $vesselId !== '') {
            $builder->where('vessel_id', $vesselId);
            if ($year !== null && $year !== '') {
                $builder->whereYear('dateof_report', $year);
            }
        }

        if ($query->search !== null) {
            $term = "%{$query->search}%";
            $builder->where(function (Builder $q) use ($term) {
                $q->where('dateof_report', 'like', $term)
                    ->orWhere('report_no', 'like', $term)
                    ->orWhere('nature_type', 'like', $term)
                    ->orWhere('hazardous_occurrence_type', 'like', $term)
                    ->orWhere('added_by', 'like', $term)
                    ->orWhereHas('vessel', fn (Builder $v) => $v->where('name', 'like', $term))
                    ->orWhereHas('natureOfIncident', fn (Builder $n) => $n->where('name', 'like', $term));
            });
        }

        $sortable = array_column(array_filter(self::MODULE_COLUMNS, fn ($c) => $c['sortable']), 'key');
        $sort = in_array($query->sort, $sortable, true) ? $query->sort : 'dateof_report';

        return $builder->orderBy($sort, $query->direction)
            ->paginate($query->perPage, page: $query->page);
    }

    /**
     * Ported from add_incident_report()'s insert branch: new records
     * are SHORE-added and immediately auto-approved (unlike
     * Nonconformities, where new records start unapproved — this is a
     * genuine legacy difference between the two modules, not an
     * inconsistency introduced here).
     */
    public function create(array $data, array $rootCauses, array $persons): IncidentReport
    {
        $data = $this->clearInapplicableFields($data);

        $report = IncidentReport::create([
            ...$data,
            'added_by' => 'SHORE',
            'published' => false,
            'is_approved' => true,
        ]);

        $this->syncRootCauses($report, $rootCauses);
        $this->syncPersons($report, $persons);

        return $report;
    }

    /**
     * Ported from add_incident_report()'s edit branch. Vessel and
     * added_by are frozen at creation time (legacy always re-reads them
     * from the existing row, never from the edit payload). Approval
     * state depends on who added it and whether it was already
     * published: a published SHORE report needs re-approval after an
     * edit, but an unpublished SHORE draft or export stays approved;
     * any VESSEL-added report always needs re-approval when edited.
     */
    public function update(IncidentReport $report, array $data, array $rootCauses, array $persons): IncidentReport
    {
        $data = $this->clearInapplicableFields($data);
        unset($data['vessel_id']);

        $isApproved = $report->added_by === 'SHORE'
            ? ! $report->published
            : false;

        $report->update([...$data, 'is_approved' => $isApproved]);

        $this->syncRootCauses($report, $rootCauses);
        $this->syncPersons($report, $persons);

        return $report;
    }

    /** Ported from publish_incident_report(): toggles published, always sets is_approved true. */
    public function publish(IncidentReport $report): IncidentReport
    {
        $report->update([
            'published' => ! $report->published,
            'is_approved' => true,
        ]);

        return $report;
    }

    /** Ported from approve_incident_report(). */
    public function approve(IncidentReport $report): IncidentReport
    {
        $report->update(['is_approved' => true]);

        return $report;
    }

    /** Ported from reopen_incident_report(): clears the close, and re-approves (matches legacy exactly). */
    public function reopen(IncidentReport $report): IncidentReport
    {
        $report->update(['closing_date' => null, 'is_approved' => true]);

        return $report;
    }

    /** Ported from delete_incident_report() — a real delete, not a soft one (unlike Nonconformities). Root-cause/person rows cascade via FK. */
    public function delete(IncidentReport $report): void
    {
        $report->delete();
    }

    private function clearInapplicableFields(array $data): array
    {
        $blank = fn (string $field) => in_array($field, ['distance1', 'distance2', 'distance3', 'sea1', 'sea2', 'sea3'], true)
            || str_starts_with($field, 'atmospheric_') || str_starts_with($field, 'evidence_')
            ? false
            : null;

        if (($data['nature_type'] ?? null) === 'accident') {
            foreach (self::HAZARDOUS_OCCURRENCE_ONLY_FIELDS as $field) {
                $data[$field] = $blank($field);
            }
        } else {
            foreach (self::ACCIDENT_ONLY_FIELDS as $field) {
                $data[$field] = $blank($field);
            }
        }

        return $data;
    }

    /** @param array<int, array{root_cause_id: ?int, root_cause_other: ?string, investigation: ?string, analysis: ?string, corrective_actions: ?string}> $rows */
    private function syncRootCauses(IncidentReport $report, array $rows): void
    {
        $report->rootCauses()->delete();

        foreach (array_values($rows) as $index => $row) {
            IncidentRootCause::create([
                'incident_report_id' => $report->id,
                'root_cause_id' => $row['root_cause_id'] ?? null,
                'root_cause_other' => $row['root_cause_other'] ?? null,
                'investigation' => $row['investigation'] ?? null,
                'analysis' => $row['analysis'] ?? null,
                'corrective_actions' => $row['corrective_actions'] ?? null,
                'arrangement' => $index,
            ]);
        }
    }

    /** @param array<int, array{person_name: string, position: ?string}> $rows */
    private function syncPersons(IncidentReport $report, array $rows): void
    {
        $report->personsParticipated()->delete();

        foreach (array_values($rows) as $index => $row) {
            IncidentPersonParticipated::create([
                'incident_report_id' => $report->id,
                'person_name' => $row['person_name'],
                'position' => $row['position'] ?? null,
                'arrangement' => $index,
            ]);
        }
    }

    /** @return array<int, array{id:int,label:string}> */
    public function vesselOptions(): array
    {
        return Vessel::query()->orderBy('name')->get()
            ->map(fn (Vessel $v) => ['id' => $v->id, 'label' => $v->display_name])
            ->all();
    }
}
