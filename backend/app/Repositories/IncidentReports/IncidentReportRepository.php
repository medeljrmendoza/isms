<?php

namespace App\Repositories\IncidentReports;

use App\Support\LegacyDb;
use App\Support\TableQuery;
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
     * Ported from Controllers/Incident.php's loadData(), reading
     * tb_incident_report directly from the legacy connection, scoped to
     * the logged-in user's assigned vessels. Read-only: can_* are
     * always false, since there's no legacy write path.
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
        $isPublished = self::isFlagSet($r->published);
        $isApproved = self::isFlagSet($r->is_approved);
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
            ...$this->computeFlags($r->added_by, $isClosed, $isApproved),
        ];
    }

    /**
     * tb_incident_report's is_approved/published/closing_date-derived
     * flags are real SQL `int` columns, and this connection's PDO
     * returns native-typed ints for them, so comparing against the
     * string '1' silently and always fails — see the identical fix in
     * NonconformityRepository.
     */
    private static function isFlagSet(mixed $value): bool
    {
        return (string) $value === '1';
    }

    /**
     * Ported verbatim from loadData()'s `incidentid` column callback:
     * Edit/Delete only show while open, Publish/Approve are available
     * regardless of open/closed, Reopen only shows once closed.
     * user_level (MEMBER vs SUPERADMIN/BTSOLVE) gating is intentionally
     * dropped, matching the no-roles-yet precedent used by every other
     * write-enabled module in this migration.
     */
    private function computeFlags(string $addedBy, bool $isClosed, bool $isApproved): array
    {
        return [
            'can_edit' => ! $isClosed,
            'can_publish' => $addedBy === 'SHORE',
            'can_approve' => ! $isApproved,
            'can_reopen' => $isClosed,
            'can_delete' => $addedBy === 'SHORE' && ! $isClosed,
        ];
    }

    /**
     * Ported from add_incident_report(): create (incidentid empty) and
     * edit share one delete-then-reinsert save, plus a full
     * delete-then-reinsert of the root-cause/person sub-tables (fresh
     * uniqid-based row IDs each time — matches legacy exactly, unlike
     * the elaborate mark-inactive/reinsert/purge dance publish/approve/
     * reopen do to those same tables purely to poke their S3 file-sync
     * watcher, which this migration doesn't model — see class docblock
     * precedent). On create, added_by/published/is_approved are always
     * SHORE/false/true (new shore reports start pre-approved — a real
     * difference from Nonconformities, not an inconsistency). On edit,
     * vesid/added_by are frozen from the existing row, and is_approved
     * is recomputed: an unpublished SHORE report stays approved, a
     * published SHORE report needs re-approval, and any VESSEL-added
     * report always needs re-approval after edit.
     */
    public function legacySave(?string $incidentid, array $data, array $rootCauses, array $persons): array
    {
        $legacy = DB::connection('legacy');
        $isEdit = $incidentid !== null;
        $newId = $incidentid ?? ('incident'.uniqid());
        $existing = $isEdit ? $legacy->table('tb_incident_report')->where('incidentid', $newId)->first() : null;

        if ($isEdit && $existing === null) {
            abort(404);
        }

        $data = $this->clearInapplicableFields($data);

        if ($isEdit) {
            $vesid = $existing->vesid;
            $addedBy = $existing->added_by;
            $isApproved = $addedBy === 'SHORE' ? ! self::isFlagSet($existing->published) : false;
            $published = $existing->published;
        } else {
            $vesid = $data['vessel_id'];
            $addedBy = 'SHORE';
            $isApproved = true;
            $published = '0';
        }

        $closingDate = $data['closing_date'] ?? '';
        $reportStatus = ($closingDate !== '' && $closingDate !== '0000-00-00') ? '1' : '0';

        $row = [
            'incidentid' => $newId,
            'vesid' => $vesid,
            'voyage_no' => $data['voyage_no'] ?? '',
            'dateof_report' => $data['dateof_report'],
            'report_no' => $data['report_no'] ?? '',
            'master_id' => $data['master_name'] ?? '',
            'ce_id' => $data['chief_engineer_name'] ?? '',
            'person_reporting' => $data['person_reporting'] ?? '',
            'nature_type' => $data['nature_type'],
            'natureof_incidentid' => $data['nature_of_incident_id'] ?? '',
            'accident_collision' => $data['accident_collision'] ?? '',
            'others' => $data['others'] ?? '',
            'hazardous_occurrence_type' => $data['hazardous_occurrence_type'] ?? '',
            'statementof_work' => $data['statementof_work'] ?? '',
            'location' => $data['incident_location_id'] ?? '',
            'location_other' => $data['location_other'] ?? '',
            'ship_position' => $data['ship_position'] ?? '',
            'ship_operation' => $data['incident_operation_id'] ?? '',
            'ship_operation_other' => $data['ship_operation_other'] ?? '',
            'hazardous_occurrence_ppe_used' => $data['hazardous_occurrence_ppe_used'] ?? '',
            'hazardous_occurrence_ppe_used_comment' => $data['hazardous_occurrence_ppe_used_comment'] ?? '',
            'hazardous_occurrence_severity' => $data['hazardous_occurrence_severity'] ?? '',
            'hazardous_occurrence_severity_comment' => $data['hazardous_occurrence_severity_comment'] ?? '',
            'hazardous_occurrence_likelihood' => $data['hazardous_occurrence_likelihood'] ?? '',
            'hazardous_occurrence_likelihood_comment' => $data['hazardous_occurrence_likelihood_comment'] ?? '',
            'severity_itp' => $data['severity_itp'] ?? '',
            'comment_itp' => $data['comment_itp'] ?? '',
            'location_itp' => $data['location_of_injury_id'] ?? '',
            'type_itp' => $data['type_of_injury_id'] ?? '',
            'other_typeof_injury' => $data['other_typeof_injury'] ?? '',
            'other_affected_area' => $data['other_affected_area'] ?? '',
            'severity_itv' => $data['severity_itv'] ?? '',
            'comment_itv' => $data['comment_itv'] ?? '',
            'signed_by' => $data['signed_by'],
            'date_signed' => $data['date_signed'],
            'remarks' => $data['vessel_remarks'] ?? '',
            'subject_investigation' => $data['subject_investigation'] ?? '',
            'evidence_safety_meeting' => $data['evidence_safety_meeting'] ?? '0',
            'evidence_certificate' => $data['evidence_certificate'] ?? '0',
            'evidence_logbook' => $data['evidence_logbook'] ?? '0',
            'evidence_delivery' => $data['evidence_delivery'] ?? '0',
            'evidence_photo' => $data['evidence_photo'] ?? '0',
            'evidence_company' => $data['evidence_company'] ?? '0',
            'evidence_others' => $data['evidence_others'] ?? '0',
            'evidence_others_name' => $data['evidence_others_name'] ?? '',
            'causal_factor' => $data['causal_factor'] ?? '',
            'intermediate_cause' => $data['intermediate_cause'] ?? '',
            'root_cause' => $data['shore_root_cause_summary'] ?? '',
            'bac' => $data['bac'] ?? '',
            'vdr' => $data['vdr'] ?? '',
            'dateof_event' => $data['dateof_event'] ?? '',
            'timeof_event' => $data['timeof_event'] ?? '',
            'zone' => $data['zone'] ?? '',
            'country' => $data['country'] ?? '',
            'speed' => $data['speed'] ?? '',
            'course' => $data['course'] ?? '',
            'draft_forward' => $data['draft_forward'] ?? '',
            'draft_alt' => $data['draft_alt'] ?? '',
            'wind_direction' => $data['wind_direction'] ?? '',
            'direction_sea' => $data['direction_sea'] ?? '',
            'direction_swell' => $data['direction_swell'] ?? '',
            'geographical_location' => $data['geographical_location'] ?? '',
            'port_departure' => $data['port_departure'] ?? '',
            'type_cargo' => $data['type_cargo'] ?? '',
            'date_departure' => $data['date_departure'] ?? '',
            'cargo_quantity' => $data['cargo_quantity'] ?? '',
            'port_which_bound' => $data['port_which_bound'] ?? '',
            'special_requirement' => $data['special_requirement'] ?? '',
            'atmospheric_clear' => $data['atmospheric_clear'] ?? '0',
            'atmospheric_partly_cloudy' => $data['atmospheric_partly_cloudy'] ?? '0',
            'atmospheric_overcast' => $data['atmospheric_overcast'] ?? '0',
            'atmospheric_fog' => $data['atmospheric_fog'] ?? '0',
            'atmospheric_rain' => $data['atmospheric_rain'] ?? '0',
            'atmospheric_snow' => $data['atmospheric_snow'] ?? '0',
            'atmospheric_other' => $data['atmospheric_other'] ?? '0',
            'atmospheric_other_name' => $data['atmospheric_other_name'] ?? '',
            'distance1' => $data['distance1'] ?? '0',
            'distance2' => $data['distance2'] ?? '0',
            'distance3' => $data['distance3'] ?? '0',
            'sea1' => $data['sea1'] ?? '0',
            'sea2' => $data['sea2'] ?? '0',
            'sea3' => $data['sea3'] ?? '0',
            'crew_onboard' => $data['crew_onboard'] ?? '',
            'other_onboard' => $data['other_onboard'] ?? '',
            'total_onboard' => $data['total_onboard'] ?? '',
            'crew_dead' => $data['crew_dead'] ?? '',
            'other_dead' => $data['other_dead'] ?? '',
            'total_dead' => $data['total_dead'] ?? '',
            'crew_missing' => $data['crew_missing'] ?? '',
            'other_missing' => $data['other_missing'] ?? '',
            'total_missing' => $data['total_missing'] ?? '',
            'crew_injured' => $data['crew_injured'] ?? '',
            'other_injured' => $data['other_injured'] ?? '',
            'total_injured' => $data['total_injured'] ?? '',
            'fs_ro' => $data['fs_ro'] ?? '',
            'date_received' => $data['date_received'],
            'reviewed_by' => $data['reviewed_by'] ?? '',
            'investigator' => $data['investigator'] ?? '',
            'dpa' => $data['dpa'],
            'closing_date' => $closingDate,
            'added_by' => $addedBy,
            'report_status' => $reportStatus,
            'is_approved' => $isApproved ? '1' : '0',
            'published' => $published,
        ];

        $legacy->table('tb_incident_report')->where('incidentid', $newId)->delete();
        $legacy->table('tb_incident_report')->insert($row);

        $legacy->table('tb_incident_root_cause')->where('incidentID', $newId)->delete();
        foreach (array_values($rootCauses) as $index => $rc) {
            $legacy->table('tb_incident_root_cause')->insert([
                'incidentRCID' => 'rootcause'.uniqid().$index,
                'incidentID' => $newId,
                'arrangement' => $index,
                'rootCauseID' => $rc['root_cause_id'] ?? '',
                'investigation' => $rc['investigation'] ?? '',
                'analysis' => $rc['analysis'] ?? '',
                'corrective_actions' => $rc['corrective_actions'] ?? '',
                'root_cause_other' => $rc['root_cause_other'] ?? '',
                'is_inactive' => '0',
            ]);
        }

        $legacy->table('tb_incident_person_participated')->where('incidentID', $newId)->delete();
        foreach (array_values($persons) as $index => $p) {
            $legacy->table('tb_incident_person_participated')->insert([
                'personID' => 'per'.uniqid().$index,
                'incidentID' => $newId,
                'arrangement' => $index,
                'person_name' => $p['person_name'],
                'position' => $p['position'] ?? '',
                'is_inactive' => '0',
            ]);
        }

        return $this->legacyDetail($newId);
    }

    /** Ported from publish_incident_report(): delete-then-reinsert toggling published, forcing is_approved to '1' regardless of direction. Sub-tables untouched (see legacySave()'s docblock). */
    public function legacyPublish(string $incidentid): array
    {
        $legacy = DB::connection('legacy');
        $r = $legacy->table('tb_incident_report')->where('incidentid', $incidentid)->first();
        abort_if($r === null, 404);

        $row = (array) $r;
        $row['published'] = self::isFlagSet($r->published) ? '0' : '1';
        $row['is_approved'] = '1';

        $legacy->table('tb_incident_report')->where('incidentid', $incidentid)->delete();
        $legacy->table('tb_incident_report')->insert($row);

        return $this->legacyDetail($incidentid);
    }

    /** Ported from approve_incident_report(): delete-then-reinsert forcing is_approved to '1'. */
    public function legacyApprove(string $incidentid): array
    {
        $legacy = DB::connection('legacy');
        $r = $legacy->table('tb_incident_report')->where('incidentid', $incidentid)->first();
        abort_if($r === null, 404);

        $row = (array) $r;
        $row['is_approved'] = '1';

        $legacy->table('tb_incident_report')->where('incidentid', $incidentid)->delete();
        $legacy->table('tb_incident_report')->insert($row);

        return $this->legacyDetail($incidentid);
    }

    /** Ported from reopen_incident_report(): delete-then-reinsert clearing closing_date/report_status and forcing is_approved to '1' (legacy re-approves on reopen, unlike Nonconformities). published is left untouched. */
    public function legacyReopen(string $incidentid): array
    {
        $legacy = DB::connection('legacy');
        $r = $legacy->table('tb_incident_report')->where('incidentid', $incidentid)->first();
        abort_if($r === null, 404);

        $row = (array) $r;
        $row['closing_date'] = '0000-00-00';
        $row['report_status'] = '0';
        $row['is_approved'] = '1';

        $legacy->table('tb_incident_report')->where('incidentid', $incidentid)->delete();
        $legacy->table('tb_incident_report')->insert($row);

        return $this->legacyDetail($incidentid);
    }

    /** Ported from delete_incident_report(): a real delete, not a soft one — cascades to root-cause/person sub-tables like legacy does. */
    public function legacyDelete(string $incidentid): void
    {
        $legacy = DB::connection('legacy');
        abort_if($legacy->table('tb_incident_report')->where('incidentid', $incidentid)->doesntExist(), 404);

        $legacy->table('tb_incident_report')->where('incidentid', $incidentid)->delete();
        $legacy->table('tb_incident_root_cause')->where('incidentID', $incidentid)->delete();
        $legacy->table('tb_incident_person_participated')->where('incidentID', $incidentid)->delete();
    }

    /**
     * Ported from add_incident_report()'s nature_type branch: when
     * nature_type is 'accident', hazardous-occurrence-only fields are
     * blanked; otherwise accident-only fields are blanked. Matches
     * legacy exactly — its else-branches set every one of these to "".
     */
    private function clearInapplicableFields(array $data): array
    {
        $blank = fn (string $field) => in_array($field, ['distance1', 'distance2', 'distance3', 'sea1', 'sea2', 'sea3'], true)
            || str_starts_with($field, 'atmospheric_') || str_starts_with($field, 'evidence_')
            ? '0'
            : '';

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

    /** @return array<int, array{id:string,label:string}> */
    public function legacyNatureOfIncidentOptions(): array
    {
        return DB::connection('legacy')->table('tb_natureof_incident')->orderBy('name')->get()
            ->map(fn ($n) => ['id' => $n->natureID, 'label' => $n->name])->all();
    }

    /** @return array<int, array{id:string,label:string}> */
    public function legacyIncidentLocationOptions(): array
    {
        return DB::connection('legacy')->table('tb_incident_location_occurences')->orderBy('location_occurences')->get()
            ->map(fn ($l) => ['id' => $l->incidentLocationID, 'label' => $l->location_occurences])->all();
    }

    /** @return array<int, array{id:string,label:string}> */
    public function legacyIncidentOperationOptions(): array
    {
        return DB::connection('legacy')->table('tb_incident_operations')->orderBy('operation_name')->get()
            ->map(fn ($o) => ['id' => $o->incidentOperationID, 'label' => $o->operation_name])->all();
    }

    /** @return array<int, array{id:string,label:string}> */
    public function legacyTypeOfInjuryOptions(): array
    {
        return DB::connection('legacy')->table('tb_typeof_injury')->orderBy('type')->get()
            ->map(fn ($t) => ['id' => $t->type_ID, 'label' => $t->type])->all();
    }

    /** @return array<int, array{id:string,label:string}> */
    public function legacyLocationOfInjuryOptions(): array
    {
        return DB::connection('legacy')->table('tb_locationof_injuries')->orderBy('body_part')->get()
            ->map(fn ($l) => ['id' => $l->locationID, 'label' => $l->body_part])->all();
    }

    /** @return array<int, array{id:string,label:string,root_causes:array<int,array{id:string,label:string}>}> */
    public function legacyRootCauseCategoryOptions(): array
    {
        $rootCauses = DB::connection('legacy')->table('tb_root_cause')->orderBy('root_cause')->get();

        return DB::connection('legacy')->table('tb_root_cause_category')->orderBy('category')->get()
            ->map(fn ($c) => [
                'id' => $c->rootCauseCatID,
                'label' => $c->category,
                'root_causes' => $rootCauses->where('rootCauseCatID', $c->rootCauseCatID)
                    ->map(fn ($rc) => ['id' => $rc->rootCauseID, 'label' => $rc->root_cause])->values()->all(),
            ])->all();
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
     * Ported from Controllers/Incident.php's
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

        return $this->toDetailArray($incidentid, [
            'vessel' => $vessels[$r->vesid] ?? '',
            'dateof_report' => $r->dateof_report,
            'report_no' => $r->report_no,
            'nature' => $r->nature_type === 'accident' ? 'ACCIDENT' : 'HAZARDOUS OCCURRENCE',
            'type' => $typeLabel,
            'added_by' => $r->added_by,
            'published' => $r->added_by === 'SHORE' ? self::isFlagSet($r->published) : null,
            'is_approved' => self::isFlagSet($r->is_approved),
            'is_closed' => $isClosed,
            'vessel_id' => $r->vesid !== '' ? $r->vesid : null,
            'voyage_no' => $r->voyage_no,
            'master_name' => $r->master_id,
            'chief_engineer_name' => $r->ce_id,
            'person_reporting' => $r->person_reporting,
            'nature_type' => $r->nature_type,
            'nature_of_incident_id' => $r->natureof_incidentid !== '' ? $r->natureof_incidentid : null,
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
            'incident_location_id' => $r->location !== '' ? $r->location : null,
            'incident_location_label' => $r->location_occurences,
            'location_other' => $r->location_other,
            'ship_position' => $r->ship_position,
            'incident_operation_id' => $r->ship_operation !== '' ? $r->ship_operation : null,
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
            'location_of_injury_id' => $r->location_itp !== '' ? $r->location_itp : null,
            'location_of_injury_label' => $r->body_part,
            'type_of_injury_id' => $r->type_itp !== '' ? $r->type_itp : null,
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
            'flags' => $this->computeFlags($r->added_by, $isClosed, self::isFlagSet($r->is_approved)),
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
    private function toDetailArray(string $incidentid, array $r): array
    {
        $isClosed = $r['is_closed'];
        $statusColor = ! $isClosed ? 'yellow' : (($r['published'] ?? false) && ! $r['is_approved'] ? 'yellow' : 'green');
        $flags = $r['flags'] ?? ['can_edit' => false, 'can_publish' => false, 'can_approve' => false, 'can_reopen' => false, 'can_delete' => false];

        return [
            'id' => $incidentid,
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
            ...$flags,
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
