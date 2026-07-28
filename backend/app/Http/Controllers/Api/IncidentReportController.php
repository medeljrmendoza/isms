<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IncidentReportRequest;
use App\Models\IncidentLocation;
use App\Models\IncidentOperation;
use App\Models\IncidentReport;
use App\Models\LocationOfInjury;
use App\Models\NatureOfIncident;
use App\Models\RootCauseCategory;
use App\Models\TypeOfInjury;
use App\Repositories\IncidentReportRepository;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Ported from Controllers/Incident.php. Not ported: file attachment
 * upload/S3 storage, the tb_logs audit trail, and user_level-gated
 * button visibility — same deferrals as NonconformityController (see
 * its docblock and IncidentReportRepository's docblocks for the
 * specific business-logic quirks that ARE kept faithfully).
 */
class IncidentReportController extends Controller
{
    public function __construct(private readonly IncidentReportRepository $incidentReports)
    {
    }

    /**
     * GET /api/incident-reports
     */
    public function index(Request $request): JsonResponse
    {
        $vesselId = $request->query('vessel_id');
        $year = $request->query('year');

        $paginator = $this->incidentReports->fullTable(
            TableQuery::fromRequest($request),
            $vesselId !== '' ? $vesselId : null,
            $year !== '' ? $year : null,
        );

        return response()->json([
            'data' => [
                'columns' => IncidentReportRepository::moduleColumns(),
                'rows' => collect($paginator->items())->map(fn (IncidentReport $r) => $this->mapRow($r))->all(),
                'meta' => $this->meta($paginator),
            ],
        ]);
    }

    /**
     * GET /api/incident-reports/options
     */
    public function options(): JsonResponse
    {
        $years = IncidentReport::query()->get('dateof_report')
            ->map(fn (IncidentReport $r) => $r->dateof_report->format('Y'))->unique()->sortDesc()->values()->all();
        if (! in_array(date('Y'), $years, true)) {
            array_unshift($years, date('Y'));
        }

        return response()->json([
            'data' => [
                'vessels' => $this->incidentReports->vesselOptions(),
                'years' => $years,
                'nature_of_incidents' => NatureOfIncident::query()->orderBy('name')
                    ->get()->map(fn ($n) => ['id' => $n->id, 'label' => $n->name])->all(),
                'incident_locations' => IncidentLocation::query()->orderBy('name')
                    ->get()->map(fn ($l) => ['id' => $l->id, 'label' => $l->name])->all(),
                'incident_operations' => IncidentOperation::query()->orderBy('name')
                    ->get()->map(fn ($o) => ['id' => $o->id, 'label' => $o->name])->all(),
                'types_of_injury' => TypeOfInjury::query()->orderBy('name')
                    ->get()->map(fn ($t) => ['id' => $t->id, 'label' => $t->name])->all(),
                'locations_of_injury' => LocationOfInjury::query()->orderBy('body_part')
                    ->get()->map(fn ($l) => ['id' => $l->id, 'label' => $l->body_part])->all(),
                'root_cause_categories' => RootCauseCategory::query()->with('rootCauses')->orderBy('name')->get()
                    ->map(fn (RootCauseCategory $c) => [
                        'id' => $c->id,
                        'label' => $c->name,
                        'root_causes' => $c->rootCauses->map(fn ($rc) => ['id' => $rc->id, 'label' => $rc->name])->all(),
                    ])->all(),
            ],
        ]);
    }

    /**
     * GET /api/incident-reports/{incidentReport}
     */
    public function show(IncidentReport $incidentReport): JsonResponse
    {
        $this->loadRelations($incidentReport);

        return response()->json(['data' => $this->mapDetail($incidentReport)]);
    }

    /**
     * POST /api/incident-reports
     */
    public function store(IncidentReportRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $rootCauses = $validated['root_causes'] ?? [];
        $persons = $validated['persons'] ?? [];
        unset($validated['root_causes'], $validated['persons']);

        $report = $this->incidentReports->create($validated, $rootCauses, $persons);
        $this->loadRelations($report);

        return response()->json(['data' => $this->mapDetail($report)], 201);
    }

    /**
     * PUT /api/incident-reports/{incidentReport}
     */
    public function update(IncidentReportRequest $request, IncidentReport $incidentReport): JsonResponse
    {
        if (! $this->canEdit($incidentReport)) {
            abort(422, 'This report can no longer be edited.');
        }

        $validated = $request->validated();
        $rootCauses = $validated['root_causes'] ?? [];
        $persons = $validated['persons'] ?? [];
        unset($validated['root_causes'], $validated['persons'], $validated['vessel_id']);

        $report = $this->incidentReports->update($incidentReport, $validated, $rootCauses, $persons);
        $this->loadRelations($report);

        return response()->json(['data' => $this->mapDetail($report)]);
    }

    /**
     * DELETE /api/incident-reports/{incidentReport}
     */
    public function destroy(IncidentReport $incidentReport): JsonResponse
    {
        if (! $this->canDelete($incidentReport)) {
            abort(422, 'This report cannot be deleted.');
        }

        $this->incidentReports->delete($incidentReport);

        return response()->json(['data' => ['ok' => true]]);
    }

    /**
     * POST /api/incident-reports/{incidentReport}/publish
     */
    public function publish(IncidentReport $incidentReport): JsonResponse
    {
        if (! $this->canPublish($incidentReport)) {
            abort(422, 'This report cannot be published/unpublished.');
        }

        $report = $this->incidentReports->publish($incidentReport);
        $this->loadRelations($report);

        return response()->json(['data' => $this->mapDetail($report)]);
    }

    /**
     * POST /api/incident-reports/{incidentReport}/approve
     */
    public function approve(IncidentReport $incidentReport): JsonResponse
    {
        if (! $this->canApprove($incidentReport)) {
            abort(422, 'This report cannot be approved.');
        }

        $report = $this->incidentReports->approve($incidentReport);
        $this->loadRelations($report);

        return response()->json(['data' => $this->mapDetail($report)]);
    }

    /**
     * POST /api/incident-reports/{incidentReport}/reopen
     */
    public function reopen(IncidentReport $incidentReport): JsonResponse
    {
        if (! $incidentReport->is_closed) {
            abort(422, 'This report is not closed.');
        }

        $report = $this->incidentReports->reopen($incidentReport);
        $this->loadRelations($report);

        return response()->json(['data' => $this->mapDetail($report)]);
    }

    private function loadRelations(IncidentReport $report): void
    {
        $report->load([
            'vessel', 'natureOfIncident', 'incidentLocation', 'incidentOperation',
            'locationOfInjury', 'typeOfInjury',
            'rootCauses.rootCause.category', 'personsParticipated',
        ]);
    }

    private function mapRow(IncidentReport $r): array
    {
        return [
            'id' => $r->id,
            'vessel' => $r->vessel?->display_name ?? '',
            'dateof_report' => $r->dateof_report->format('Y-m-d'),
            'report_no' => $r->report_no,
            'nature' => $r->nature_type === 'accident' ? 'ACCIDENT' : 'HAZARDOUS OCCURRENCE',
            'type' => $this->typeLabel($r),
            'added_by' => $r->added_by,
            'published' => $r->added_by === 'SHORE' ? $r->published : null,
            'is_approved' => $r->is_approved,
            'status' => $r->is_closed ? 'CLOSED' : 'IN PROGRESS',
            'status_color' => $this->statusColor($r),
            'can_edit' => $this->canEdit($r),
            'can_publish' => $this->canPublish($r),
            'can_approve' => $this->canApprove($r),
            'can_reopen' => $r->is_closed,
            'can_delete' => $this->canDelete($r),
        ];
    }

    private function mapDetail(IncidentReport $r): array
    {
        return [
            ...$this->mapRow($r),
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
                'id' => $rc->id,
                'root_cause_id' => $rc->root_cause_id,
                'root_cause_category_label' => $rc->rootCause?->category?->name,
                'root_cause_label' => $rc->rootCause?->name,
                'root_cause_other' => $rc->root_cause_other,
                'investigation' => $rc->investigation,
                'analysis' => $rc->analysis,
                'corrective_actions' => $rc->corrective_actions,
            ])->all(),
            'persons' => $r->personsParticipated->map(fn ($p) => [
                'id' => $p->id,
                'person_name' => $p->person_name,
                'position' => $p->position,
            ])->all(),
        ];
    }

    private function typeLabel(IncidentReport $r): string
    {
        if ($r->nature_type === 'accident') {
            $name = $r->natureOfIncident?->name ?? '';

            return match ($name) {
                'Other' => trim("{$name} - {$r->others}"),
                'Collision' => trim("{$name} - {$r->accident_collision}"),
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
     * Ported from loadData()'s report_status column formatter: closed
     * reports that are published but not yet approved still show as
     * "needs attention" (yellow) rather than fully done (green).
     */
    private function statusColor(IncidentReport $r): string
    {
        if (! $r->is_closed) {
            return 'yellow';
        }

        if ($r->published && ! $r->is_approved) {
            return 'yellow';
        }

        return 'green';
    }

    private function canEdit(IncidentReport $r): bool
    {
        return ! $r->is_closed;
    }

    private function canPublish(IncidentReport $r): bool
    {
        return $r->added_by === 'SHORE';
    }

    private function canApprove(IncidentReport $r): bool
    {
        return ! $r->is_approved;
    }

    private function canDelete(IncidentReport $r): bool
    {
        return $r->added_by === 'SHORE';
    }

    private function meta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }
}
