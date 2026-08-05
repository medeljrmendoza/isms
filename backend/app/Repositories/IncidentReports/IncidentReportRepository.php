<?php

namespace App\Repositories\IncidentReports;

use App\Models\IncidentReports\IncidentPersonParticipated;
use App\Models\IncidentReports\IncidentReport;
use App\Models\IncidentReports\IncidentRootCause;
use App\Models\Vessel;
use App\Support\LegacyDb;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

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
     * Ported from Controllers/Dashboard_incident.php's loadIncidentData():
     * still open (no closing date, or the zero-date sentinel) or not yet
     * approved, scoped to the logged-in user's assigned vessels.
     */
    public function legacyTable(TableQuery $query, ?string $legacyUserId): array
    {
        $vessels = LegacyDb::vesselNames();
        $assignedVesselIds = LegacyDb::assignedVesselIds($legacyUserId);

        $builder = DB::connection('legacy')->table('tb_incident_report')
            ->leftJoin('tb_natureof_incident', 'tb_natureof_incident.natureID', '=', 'tb_incident_report.natureof_incidentid')
            ->where(function ($q) {
                $q->whereNull('tb_incident_report.closing_date')
                    ->orWhere('tb_incident_report.closing_date', '0000-00-00')
                    ->orWhere('tb_incident_report.is_approved', '0');
            })
            ->whereIn('tb_incident_report.vesid', $assignedVesselIds)
            ->select([
                'tb_incident_report.incidentid',
                'tb_incident_report.vesid',
                'tb_incident_report.dateof_report',
                'tb_incident_report.nature_type',
                'tb_incident_report.natureof_incidentid',
                'tb_incident_report.hazardous_occurrence_type',
                'tb_incident_report.others',
                'tb_incident_report.accident_collision',
                'tb_natureof_incident.name as nature_name',
            ]);

        if ($query->search !== null) {
            $term = "%{$query->search}%";
            $builder->where(function ($q) use ($term) {
                $q->where('tb_incident_report.dateof_report', 'like', $term)
                    ->orWhere('tb_incident_report.nature_type', 'like', $term)
                    ->orWhere('tb_incident_report.hazardous_occurrence_type', 'like', $term)
                    ->orWhere('tb_natureof_incident.name', 'like', $term);
            });
        }

        $sortMap = ['dateof_report' => 'tb_incident_report.dateof_report', 'nature' => 'tb_incident_report.nature_type'];
        $sort = $sortMap[$query->sort ?? ''] ?? 'tb_incident_report.dateof_report';

        $paginator = $builder->orderBy($sort, $query->direction)->paginate($query->perPage, page: $query->page);

        $rows = collect($paginator->items())->map(fn ($r) => [
            'record_id' => $r->incidentid,
            'vessel' => $vessels[$r->vesid] ?? '',
            'dateof_report' => $r->dateof_report,
            'nature' => $r->nature_type === 'accident' ? 'ACCIDENT' : 'HAZARDOUS OCCURRENCE',
            'type' => $this->legacyIncidentTypeLabel($r),
        ])->all();

        return [
            'rows' => $rows,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    /**
     * Matches Dashboard_incident.php's edit('natureof_incidentid', ...)
     * exactly: it branches on the fixed natureof_incidentid values (not
     * the name text — the real "Other"/"Collision" lookup rows are named
     * "OTHERS (not named above)" / "COLLISION WITH OTHER VESSEL(S)...",
     * not literally "Other"/"Collision").
     */
    private function legacyIncidentTypeLabel(object $r): string
    {
        if ($r->nature_type === 'accident') {
            $name = $r->nature_name ?? '';

            return match ($r->natureof_incidentid) {
                'nature57199de8cd5ad' => trim("{$name} - {$r->others}"),
                'nature57199de883f63' => trim("{$name} - {$r->accident_collision}"),
                default => $name,
            };
        }

        return match ($r->hazardous_occurrence_type) {
            'unsafe_act' => 'UNSAFE ACT',
            'unsafe_condition' => 'UNSAFE CONDITION',
            'near_miss' => 'NEAR MISS',
            default => '',
        };
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
     * Same as fullTable(), reading tb_incident_report directly from the
     * legacy connection. Keeps the vesid-in-assigned-vessels scoping
     * fullTable() drops (see its docblock) — a real legacy user should
     * only see their own fleet's reports. Read-only: can_* are always
     * false, since there's no legacy write path or local int PK to bind to.
     */
    public function legacyFullTable(TableQuery $query, ?string $vesselId, ?string $year, ?string $legacyUserId): array
    {
        $vessels = LegacyDb::vesselNames();
        $assignedVesselIds = LegacyDb::assignedVesselIds($legacyUserId);

        $builder = DB::connection('legacy')->table('tb_incident_report')
            ->leftJoin('tb_natureof_incident', 'tb_natureof_incident.natureID', '=', 'tb_incident_report.natureof_incidentid')
            ->whereIn('tb_incident_report.vesid', $assignedVesselIds)
            ->select([
                'tb_incident_report.incidentid',
                'tb_incident_report.vesid',
                'tb_incident_report.dateof_report',
                'tb_incident_report.report_no',
                'tb_incident_report.nature_type',
                'tb_incident_report.natureof_incidentid',
                'tb_incident_report.hazardous_occurrence_type',
                'tb_incident_report.others',
                'tb_incident_report.accident_collision',
                'tb_incident_report.added_by',
                'tb_incident_report.published',
                'tb_incident_report.is_approved',
                'tb_incident_report.closing_date',
                'tb_natureof_incident.name as nature_name',
            ]);

        if ($vesselId !== null && $vesselId !== '' && $vesselId !== 'ALL') {
            $builder->where('tb_incident_report.vesid', $vesselId);
        }

        if ($year !== null && $year !== '') {
            $builder->whereRaw('YEAR(tb_incident_report.dateof_report) = ?', [$year]);
        }

        if ($query->search !== null) {
            $term = "%{$query->search}%";
            $builder->where(function ($q) use ($term) {
                $q->where('tb_incident_report.dateof_report', 'like', $term)
                    ->orWhere('tb_incident_report.report_no', 'like', $term)
                    ->orWhere('tb_incident_report.nature_type', 'like', $term)
                    ->orWhere('tb_incident_report.hazardous_occurrence_type', 'like', $term)
                    ->orWhere('tb_incident_report.added_by', 'like', $term)
                    ->orWhere('tb_natureof_incident.name', 'like', $term);
            });
        }

        $sortMap = [
            'dateof_report' => 'tb_incident_report.dateof_report',
            'report_no' => 'tb_incident_report.report_no',
            'nature' => 'tb_incident_report.nature_type',
            'added_by' => 'tb_incident_report.added_by',
        ];
        $sort = $sortMap[$query->sort ?? ''] ?? 'tb_incident_report.dateof_report';

        $paginator = $builder->orderBy($sort, $query->direction)->paginate($query->perPage, page: $query->page);

        $rows = collect($paginator->items())->map(fn ($r) => $this->legacyRow($r, $vessels))->all();

        return [
            'rows' => $rows,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    /** @param array<string, string> $vessels */
    private function legacyRow(object $r, array $vessels): array
    {
        $isClosed = $r->closing_date !== '0000-00-00' && $r->closing_date !== null && $r->closing_date !== '';
        $isPublished = $r->published === '1';
        $isApproved = $r->is_approved === '1';
        $published = $r->added_by === 'SHORE' ? $isPublished : null;

        $statusColor = match (true) {
            ! $isClosed => 'yellow',
            $published && ! $isApproved => 'yellow',
            default => 'green',
        };

        return [
            'id' => $r->incidentid,
            'vessel' => $vessels[$r->vesid] ?? '',
            'dateof_report' => $r->dateof_report,
            'report_no' => $r->report_no,
            'nature' => $r->nature_type === 'accident' ? 'ACCIDENT' : 'HAZARDOUS OCCURRENCE',
            'type' => $this->legacyIncidentTypeLabel($r),
            'added_by' => $r->added_by,
            'published' => $published,
            'is_approved' => $isApproved,
            'status' => $isClosed ? 'CLOSED' : 'IN PROGRESS',
            'status_color' => $statusColor,
            'can_edit' => false,
            'can_publish' => false,
            'can_approve' => false,
            'can_reopen' => false,
            'can_delete' => false,
        ];
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

    /** @return array<int, array{id:string,label:string}> */
    public function legacyVesselOptions(?string $legacyUserId): array
    {
        return LegacyDb::assignedVesselOptions($legacyUserId);
    }

    /** @return array<int, int> years with at least one report, newest first, scoped to assigned vessels. */
    public function legacyYears(?string $legacyUserId): array
    {
        $assignedVesselIds = LegacyDb::assignedVesselIds($legacyUserId);

        return DB::connection('legacy')->table('tb_incident_report')
            ->whereIn('vesid', $assignedVesselIds)
            ->where('dateof_report', '!=', '0000-00-00')
            ->selectRaw('DISTINCT YEAR(dateof_report) as year')
            ->orderByDesc('year')
            ->pluck('year')
            ->filter()
            ->map(fn ($y) => (int) $y)
            ->all();
    }

    /**
     * Ported from admin/incident/view_incident_report_dialog.php,
     * surfaced via the dashboard's clickable vessel column. Read-only —
     * see SireReportRepository::detail()'s docblock for the convention.
     * `location_of_injury_label` has no legacy-verifiable source for the
     * local path (this migration's `locations_of_injury` seed list
     * isn't a literal port of any single legacy lookup table) — see
     * legacyDetail()'s docblock for the real legacy source once
     * available.
     */
    public function detail(int $id): ?array
    {
        $r = IncidentReport::query()
            ->with(['vessel', 'natureOfIncident', 'incidentLocation', 'incidentOperation', 'locationOfInjury', 'typeOfInjury', 'rootCauses.rootCause.category', 'personsParticipated'])
            ->find($id);

        if ($r === null) {
            return null;
        }

        $isClosed = $r->closing_date !== null;
        $typeLabel = $this->typeLabel($r->nature_type, $r->natureOfIncident?->name, $r->others, $r->accident_collision, $r->hazardous_occurrence_type);

        return $this->toDetailArray([
            'vessel' => $r->vessel?->display_name ?? '',
            'dateof_report' => $r->dateof_report->format('Y-m-d'),
            'report_no' => $r->report_no,
            'nature' => $r->nature_type === 'accident' ? 'ACCIDENT' : 'HAZARDOUS OCCURRENCE',
            'type' => $typeLabel,
            'added_by' => $r->added_by,
            'published' => $r->added_by === 'SHORE' ? $r->published : null,
            'is_approved' => $r->is_approved,
            'is_closed' => $isClosed,
            'vessel_id' => $r->vessel_id,
            'voyage_no' => $r->voyage_no,
            'master_name' => $r->master_name,
            'chief_engineer_name' => $r->chief_engineer_name,
            'person_reporting' => $r->person_reporting,
            'nature_type' => $r->nature_type,
            'nature_of_incident_id' => $r->nature_of_incident_id,
            'nature_of_incident_label' => $r->natureOfIncident?->name,
            'hazardous_occurrence_type' => $r->hazardous_occurrence_type,
            'others' => $r->others,
            'accident_collision' => $r->accident_collision,
            'statementof_work' => $r->statementof_work,
            'bac' => $r->bac,
            'vdr' => $r->vdr,
            'dateof_event' => $r->dateof_event?->format('Y-m-d'),
            'timeof_event' => $r->timeof_event,
            'zone' => $r->zone,
            'country' => $r->country,
            'speed' => $r->speed,
            'course' => $r->course,
            'draft_forward' => $r->draft_forward,
            'draft_alt' => $r->draft_alt,
            'wind_direction' => $r->wind_direction,
            'direction_sea' => $r->direction_sea,
            'direction_swell' => $r->direction_swell,
            'geographical_location' => $r->geographical_location,
            'port_departure' => $r->port_departure,
            'date_departure' => $r->date_departure?->format('Y-m-d'),
            'port_which_bound' => $r->port_which_bound,
            'type_cargo' => $r->type_cargo,
            'cargo_quantity' => $r->cargo_quantity,
            'special_requirement' => $r->special_requirement,
            'atmospheric_clear' => $r->atmospheric_clear,
            'atmospheric_partly_cloudy' => $r->atmospheric_partly_cloudy,
            'atmospheric_overcast' => $r->atmospheric_overcast,
            'atmospheric_fog' => $r->atmospheric_fog,
            'atmospheric_rain' => $r->atmospheric_rain,
            'atmospheric_snow' => $r->atmospheric_snow,
            'atmospheric_other' => $r->atmospheric_other,
            'atmospheric_other_name' => $r->atmospheric_other_name,
            'distance1' => $r->distance1,
            'distance2' => $r->distance2,
            'distance3' => $r->distance3,
            'sea1' => $r->sea1,
            'sea2' => $r->sea2,
            'sea3' => $r->sea3,
            'crew_onboard' => $r->crew_onboard,
            'other_onboard' => $r->other_onboard,
            'total_onboard' => $r->total_onboard,
            'crew_dead' => $r->crew_dead,
            'other_dead' => $r->other_dead,
            'total_dead' => $r->total_dead,
            'crew_missing' => $r->crew_missing,
            'other_missing' => $r->other_missing,
            'total_missing' => $r->total_missing,
            'crew_injured' => $r->crew_injured,
            'other_injured' => $r->other_injured,
            'total_injured' => $r->total_injured,
            'fs_ro' => $r->fs_ro,
            'incident_location_id' => $r->incident_location_id,
            'incident_location_label' => $r->incidentLocation?->name,
            'location_other' => $r->location_other,
            'ship_position' => $r->ship_position,
            'incident_operation_id' => $r->incident_operation_id,
            'incident_operation_label' => $r->incidentOperation?->name,
            'ship_operation_other' => $r->ship_operation_other,
            'hazardous_occurrence_ppe_used' => $r->hazardous_occurrence_ppe_used,
            'hazardous_occurrence_ppe_used_comment' => $r->hazardous_occurrence_ppe_used_comment,
            'hazardous_occurrence_severity' => $r->hazardous_occurrence_severity,
            'hazardous_occurrence_severity_comment' => $r->hazardous_occurrence_severity_comment,
            'hazardous_occurrence_likelihood' => $r->hazardous_occurrence_likelihood,
            'hazardous_occurrence_likelihood_comment' => $r->hazardous_occurrence_likelihood_comment,
            'subject_investigation' => $r->subject_investigation,
            'evidence_safety_meeting' => $r->evidence_safety_meeting,
            'evidence_certificate' => $r->evidence_certificate,
            'evidence_logbook' => $r->evidence_logbook,
            'evidence_delivery' => $r->evidence_delivery,
            'evidence_photo' => $r->evidence_photo,
            'evidence_company' => $r->evidence_company,
            'evidence_others' => $r->evidence_others,
            'evidence_others_name' => $r->evidence_others_name,
            'causal_factor' => $r->causal_factor,
            'intermediate_cause' => $r->intermediate_cause,
            'shore_root_cause_summary' => $r->shore_root_cause_summary,
            'severity_itp' => $r->severity_itp,
            'comment_itp' => $r->comment_itp,
            'location_of_injury_id' => $r->location_of_injury_id,
            'location_of_injury_label' => $r->locationOfInjury?->body_part,
            'type_of_injury_id' => $r->type_of_injury_id,
            'type_of_injury_label' => $r->typeOfInjury?->name,
            'other_typeof_injury' => $r->other_typeof_injury,
            'other_affected_area' => $r->other_affected_area,
            'severity_itv' => $r->severity_itv,
            'comment_itv' => $r->comment_itv,
            'signed_by' => $r->signed_by,
            'date_signed' => $r->date_signed?->format('Y-m-d'),
            'vessel_remarks' => $r->vessel_remarks,
            'date_received' => $r->date_received?->format('Y-m-d'),
            'reviewed_by' => $r->reviewed_by,
            'investigator' => $r->investigator,
            'dpa' => $r->dpa,
            'closing_date' => $r->closing_date?->format('Y-m-d'),
            'root_causes' => $r->rootCauses->map(fn ($rc) => [
                'root_cause_id' => $rc->root_cause_id,
                'root_cause_category_label' => $rc->rootCause?->category?->name,
                'root_cause_label' => $rc->rootCause?->name,
                'root_cause_other' => $rc->root_cause_other,
                'investigation' => $rc->investigation,
                'analysis' => $rc->analysis,
                'corrective_actions' => $rc->corrective_actions,
            ])->all(),
            'persons' => $r->personsParticipated->map(fn ($p) => [
                'person_name' => $p->person_name,
                'position' => $p->position,
            ])->all(),
        ]);
    }

    /**
     * Same as detail(), reading tb_incident_report directly from the
     * legacy connection. Ported from Controllers/Incident.php's
     * view_incident_report() query (the joins on
     * tb_locationof_injuries/tb_typeof_injury for
     * location_itp/type_itp, tb_incident_location_occurences for
     * `location`, tb_incident_operations for `ship_operation`, and
     * tb_natureof_incident for `natureof_incidentid`). master_id/ce_id
     * are tb_address_book FKs, resolved the same way as e.g.
     * SireReportRepository's inspector.
     */
    public function legacyDetail(string $incidentid): ?array
    {
        $r = DB::connection('legacy')->table('tb_incident_report')
            ->leftJoin('tb_natureof_incident', 'tb_natureof_incident.natureID', '=', 'tb_incident_report.natureof_incidentid')
            ->leftJoin('tb_incident_location_occurences', 'tb_incident_location_occurences.incidentLocationID', '=', 'tb_incident_report.location')
            ->leftJoin('tb_incident_operations', 'tb_incident_operations.incidentOperationID', '=', 'tb_incident_report.ship_operation')
            ->leftJoin('tb_locationof_injuries', 'tb_locationof_injuries.locationID', '=', 'tb_incident_report.location_itp')
            ->leftJoin('tb_typeof_injury', 'tb_typeof_injury.type_ID', '=', 'tb_incident_report.type_itp')
            ->where('tb_incident_report.incidentid', $incidentid)
            ->select([
                'tb_incident_report.*',
                'tb_natureof_incident.name as nature_name',
                'tb_incident_location_occurences.location_occurences',
                'tb_incident_operations.operation_name',
                'tb_locationof_injuries.body_part',
                'tb_typeof_injury.type as type_of_injury_name',
            ])
            ->first();

        if ($r === null) {
            return null;
        }

        $vessels = LegacyDb::vesselNames();
        $zeroDateToNull = fn (?string $date) => ($date === null || $date === '0000-00-00') ? null : $date;
        $isClosed = $zeroDateToNull($r->closing_date) !== null;
        $typeLabel = $this->typeLabel($r->nature_type, $r->nature_name, $r->others, $r->accident_collision, $r->hazardous_occurrence_type);

        $rootCauses = DB::connection('legacy')->table('tb_incident_root_cause')
            ->leftJoin('tb_root_cause', 'tb_root_cause.rootCauseID', '=', 'tb_incident_root_cause.rootCauseID')
            ->leftJoin('tb_root_cause_category', 'tb_root_cause_category.rootCauseCatID', '=', 'tb_root_cause.rootCauseCatID')
            ->where('tb_incident_root_cause.incidentID', $incidentid)
            ->where('tb_incident_root_cause.is_inactive', '!=', '1')
            ->orderBy('tb_incident_root_cause.arrangement')
            ->select(['tb_incident_root_cause.*', 'tb_root_cause.root_cause as root_cause_name', 'tb_root_cause_category.category as category_name'])
            ->get()
            ->map(fn ($rc) => [
                'root_cause_id' => $rc->rootCauseID,
                'root_cause_category_label' => $rc->category_name,
                'root_cause_label' => $rc->root_cause_name,
                'root_cause_other' => $rc->root_cause_other,
                'investigation' => $rc->investigation,
                'analysis' => $rc->analysis,
                'corrective_actions' => $rc->corrective_actions,
            ])->all();

        $persons = DB::connection('legacy')->table('tb_incident_person_participated')
            ->where('incidentID', $incidentid)
            ->where('is_inactive', '!=', '1')
            ->orderBy('arrangement')
            ->get()
            ->map(fn ($p) => ['person_name' => $p->person_name, 'position' => $p->position])
            ->all();

        return $this->toDetailArray([
            'vessel' => $vessels[$r->vesid] ?? '',
            'dateof_report' => $r->dateof_report,
            'report_no' => $r->report_no,
            'nature' => $r->nature_type === 'accident' ? 'ACCIDENT' : 'HAZARDOUS OCCURRENCE',
            'type' => $typeLabel,
            'added_by' => $r->added_by,
            'published' => $r->added_by === 'SHORE' ? $r->published === '1' : null,
            'is_approved' => $r->is_approved === '1',
            'is_closed' => $isClosed,
            'vessel_id' => null,
            'voyage_no' => $r->voyage_no,
            'master_name' => LegacyDb::addressBookEntry($r->master_id)['name'] ?? $r->master_id,
            'chief_engineer_name' => LegacyDb::addressBookEntry($r->ce_id)['name'] ?? $r->ce_id,
            'person_reporting' => $r->person_reporting,
            'nature_type' => $r->nature_type,
            'nature_of_incident_id' => null,
            'nature_of_incident_label' => $r->nature_name,
            'hazardous_occurrence_type' => $r->hazardous_occurrence_type,
            'others' => $r->others,
            'accident_collision' => $r->accident_collision,
            'statementof_work' => $r->statementof_work,
            'bac' => $r->bac,
            'vdr' => $r->vdr,
            'dateof_event' => $zeroDateToNull($r->dateof_event),
            'timeof_event' => $r->timeof_event,
            'zone' => $r->zone,
            'country' => $r->country,
            'speed' => $r->speed,
            'course' => $r->course,
            'draft_forward' => $r->draft_forward,
            'draft_alt' => $r->draft_alt,
            'wind_direction' => $r->wind_direction,
            'direction_sea' => $r->direction_sea,
            'direction_swell' => $r->direction_swell,
            'geographical_location' => $r->geographical_location,
            'port_departure' => $r->port_departure,
            'date_departure' => $zeroDateToNull($r->date_departure),
            'port_which_bound' => $r->port_which_bound,
            'type_cargo' => $r->type_cargo,
            'cargo_quantity' => $r->cargo_quantity,
            'special_requirement' => $r->special_requirement,
            'atmospheric_clear' => $r->atmospheric_clear === '1',
            'atmospheric_partly_cloudy' => $r->atmospheric_partly_cloudy === '1',
            'atmospheric_overcast' => $r->atmospheric_overcast === '1',
            'atmospheric_fog' => $r->atmospheric_fog === '1',
            'atmospheric_rain' => $r->atmospheric_rain === '1',
            'atmospheric_snow' => $r->atmospheric_snow === '1',
            'atmospheric_other' => $r->atmospheric_other === '1',
            'atmospheric_other_name' => $r->atmospheric_other_name,
            'distance1' => $r->distance1 === '1',
            'distance2' => $r->distance2 === '1',
            'distance3' => $r->distance3 === '1',
            'sea1' => $r->sea1 === '1',
            'sea2' => $r->sea2 === '1',
            'sea3' => $r->sea3 === '1',
            'crew_onboard' => $r->crew_onboard,
            'other_onboard' => $r->other_onboard,
            'total_onboard' => $r->total_onboard,
            'crew_dead' => $r->crew_dead,
            'other_dead' => $r->other_dead,
            'total_dead' => $r->total_dead,
            'crew_missing' => $r->crew_missing,
            'other_missing' => $r->other_missing,
            'total_missing' => $r->total_missing,
            'crew_injured' => $r->crew_injured,
            'other_injured' => $r->other_injured,
            'total_injured' => $r->total_injured,
            'fs_ro' => $r->fs_ro,
            'incident_location_id' => null,
            'incident_location_label' => $r->location_occurences,
            'location_other' => $r->location_other,
            'ship_position' => $r->ship_position,
            'incident_operation_id' => null,
            'incident_operation_label' => $r->operation_name,
            'ship_operation_other' => $r->ship_operation_other,
            'hazardous_occurrence_ppe_used' => $r->hazardous_occurrence_ppe_used,
            'hazardous_occurrence_ppe_used_comment' => $r->hazardous_occurrence_ppe_used_comment,
            'hazardous_occurrence_severity' => $r->hazardous_occurrence_severity,
            'hazardous_occurrence_severity_comment' => $r->hazardous_occurrence_severity_comment,
            'hazardous_occurrence_likelihood' => $r->hazardous_occurrence_likelihood,
            'hazardous_occurrence_likelihood_comment' => $r->hazardous_occurrence_likelihood_comment,
            'subject_investigation' => $r->subject_investigation,
            'evidence_safety_meeting' => $r->evidence_safety_meeting === '1',
            'evidence_certificate' => $r->evidence_certificate === '1',
            'evidence_logbook' => $r->evidence_logbook === '1',
            'evidence_delivery' => $r->evidence_delivery === '1',
            'evidence_photo' => $r->evidence_photo === '1',
            'evidence_company' => $r->evidence_company === '1',
            'evidence_others' => $r->evidence_others === '1',
            'evidence_others_name' => $r->evidence_others_name,
            'causal_factor' => $r->causal_factor,
            'intermediate_cause' => $r->intermediate_cause,
            'shore_root_cause_summary' => null,
            'severity_itp' => $r->severity_itp,
            'comment_itp' => $r->comment_itp,
            'location_of_injury_id' => null,
            'location_of_injury_label' => $r->body_part,
            'type_of_injury_id' => null,
            'type_of_injury_label' => $r->type_of_injury_name,
            'other_typeof_injury' => $r->other_typeof_injury,
            'other_affected_area' => $r->other_affected_area,
            'severity_itv' => $r->severity_itv,
            'comment_itv' => $r->comment_itv,
            'signed_by' => $r->signed_by,
            'date_signed' => $zeroDateToNull($r->date_signed),
            'vessel_remarks' => $r->remarks,
            'date_received' => $zeroDateToNull($r->date_received),
            'reviewed_by' => $r->reviewed_by,
            'investigator' => $r->investigator,
            'dpa' => $r->dpa,
            'closing_date' => $zeroDateToNull($r->closing_date),
            'root_causes' => $rootCauses,
            'persons' => $persons,
        ]);
    }

    private function typeLabel(string $natureType, ?string $natureName, ?string $others, ?string $accidentCollision, ?string $hazardousOccurrenceType): string
    {
        if ($natureType === 'accident') {
            $name = $natureName ?? '';

            return match ($name) {
                'Other' => trim("{$name} - {$others}"),
                'Collision' => trim("{$name} - {$accidentCollision}"),
                default => $name,
            };
        }

        return match ($hazardousOccurrenceType) {
            'unsafe_act' => 'UNSAFE ACT',
            'unsafe_condition' => 'UNSAFE CONDITION',
            'near_miss' => 'NEAR MISS',
            default => '',
        };
    }

    /** @param array<string, mixed> $r */
    private function toDetailArray(array $r): array
    {
        $isClosed = $r['is_closed'];
        $statusColor = ! $isClosed ? 'yellow' : (($r['published'] ?? false) && ! $r['is_approved'] ? 'yellow' : 'green');

        return [
            'id' => 0,
            'vessel' => $r['vessel'],
            'dateof_report' => $r['dateof_report'],
            'report_no' => $r['report_no'],
            'nature' => $r['nature'],
            'type' => $r['type'],
            'added_by' => $r['added_by'],
            'published' => $r['published'],
            'is_approved' => $r['is_approved'],
            'status' => $isClosed ? 'CLOSED' : 'IN PROGRESS',
            'status_color' => $statusColor,
            'can_edit' => false,
            'can_publish' => false,
            'can_approve' => false,
            'can_reopen' => false,
            'can_delete' => false,
            'vessel_id' => $r['vessel_id'],
            'voyage_no' => $r['voyage_no'],
            'master_name' => $r['master_name'],
            'chief_engineer_name' => $r['chief_engineer_name'],
            'person_reporting' => $r['person_reporting'],
            'nature_type' => $r['nature_type'],
            'nature_of_incident_id' => $r['nature_of_incident_id'],
            'nature_of_incident_label' => $r['nature_of_incident_label'],
            'hazardous_occurrence_type' => $r['hazardous_occurrence_type'],
            'others' => $r['others'],
            'accident_collision' => $r['accident_collision'],
            'statementof_work' => $r['statementof_work'],
            'bac' => $r['bac'],
            'vdr' => $r['vdr'],
            'dateof_event' => $r['dateof_event'],
            'timeof_event' => $r['timeof_event'],
            'zone' => $r['zone'],
            'country' => $r['country'],
            'speed' => $r['speed'],
            'course' => $r['course'],
            'draft_forward' => $r['draft_forward'],
            'draft_alt' => $r['draft_alt'],
            'wind_direction' => $r['wind_direction'],
            'direction_sea' => $r['direction_sea'],
            'direction_swell' => $r['direction_swell'],
            'geographical_location' => $r['geographical_location'],
            'port_departure' => $r['port_departure'],
            'date_departure' => $r['date_departure'],
            'port_which_bound' => $r['port_which_bound'],
            'type_cargo' => $r['type_cargo'],
            'cargo_quantity' => $r['cargo_quantity'],
            'special_requirement' => $r['special_requirement'],
            'atmospheric_clear' => $r['atmospheric_clear'],
            'atmospheric_partly_cloudy' => $r['atmospheric_partly_cloudy'],
            'atmospheric_overcast' => $r['atmospheric_overcast'],
            'atmospheric_fog' => $r['atmospheric_fog'],
            'atmospheric_rain' => $r['atmospheric_rain'],
            'atmospheric_snow' => $r['atmospheric_snow'],
            'atmospheric_other' => $r['atmospheric_other'],
            'atmospheric_other_name' => $r['atmospheric_other_name'],
            'distance1' => $r['distance1'],
            'distance2' => $r['distance2'],
            'distance3' => $r['distance3'],
            'sea1' => $r['sea1'],
            'sea2' => $r['sea2'],
            'sea3' => $r['sea3'],
            'crew_onboard' => $r['crew_onboard'],
            'other_onboard' => $r['other_onboard'],
            'total_onboard' => $r['total_onboard'],
            'crew_dead' => $r['crew_dead'],
            'other_dead' => $r['other_dead'],
            'total_dead' => $r['total_dead'],
            'crew_missing' => $r['crew_missing'],
            'other_missing' => $r['other_missing'],
            'total_missing' => $r['total_missing'],
            'crew_injured' => $r['crew_injured'],
            'other_injured' => $r['other_injured'],
            'total_injured' => $r['total_injured'],
            'fs_ro' => $r['fs_ro'],
            'incident_location_id' => $r['incident_location_id'],
            'incident_location_label' => $r['incident_location_label'],
            'location_other' => $r['location_other'],
            'ship_position' => $r['ship_position'],
            'incident_operation_id' => $r['incident_operation_id'],
            'incident_operation_label' => $r['incident_operation_label'],
            'ship_operation_other' => $r['ship_operation_other'],
            'hazardous_occurrence_ppe_used' => $r['hazardous_occurrence_ppe_used'],
            'hazardous_occurrence_ppe_used_comment' => $r['hazardous_occurrence_ppe_used_comment'],
            'hazardous_occurrence_severity' => $r['hazardous_occurrence_severity'],
            'hazardous_occurrence_severity_comment' => $r['hazardous_occurrence_severity_comment'],
            'hazardous_occurrence_likelihood' => $r['hazardous_occurrence_likelihood'],
            'hazardous_occurrence_likelihood_comment' => $r['hazardous_occurrence_likelihood_comment'],
            'subject_investigation' => $r['subject_investigation'],
            'evidence_safety_meeting' => $r['evidence_safety_meeting'],
            'evidence_certificate' => $r['evidence_certificate'],
            'evidence_logbook' => $r['evidence_logbook'],
            'evidence_delivery' => $r['evidence_delivery'],
            'evidence_photo' => $r['evidence_photo'],
            'evidence_company' => $r['evidence_company'],
            'evidence_others' => $r['evidence_others'],
            'evidence_others_name' => $r['evidence_others_name'],
            'causal_factor' => $r['causal_factor'],
            'intermediate_cause' => $r['intermediate_cause'],
            'shore_root_cause_summary' => $r['shore_root_cause_summary'],
            'severity_itp' => $r['severity_itp'],
            'comment_itp' => $r['comment_itp'],
            'location_of_injury_id' => $r['location_of_injury_id'],
            'location_of_injury_label' => $r['location_of_injury_label'],
            'type_of_injury_id' => $r['type_of_injury_id'],
            'type_of_injury_label' => $r['type_of_injury_label'],
            'other_typeof_injury' => $r['other_typeof_injury'],
            'other_affected_area' => $r['other_affected_area'],
            'severity_itv' => $r['severity_itv'],
            'comment_itv' => $r['comment_itv'],
            'signed_by' => $r['signed_by'],
            'date_signed' => $r['date_signed'],
            'vessel_remarks' => $r['vessel_remarks'],
            'date_received' => $r['date_received'],
            'reviewed_by' => $r['reviewed_by'],
            'investigator' => $r['investigator'],
            'dpa' => $r['dpa'],
            'closing_date' => $r['closing_date'],
            'root_causes' => $r['root_causes'],
            'persons' => $r['persons'],
        ];
    }
}
