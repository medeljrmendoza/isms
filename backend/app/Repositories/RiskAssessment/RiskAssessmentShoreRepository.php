<?php

namespace App\Repositories\RiskAssessment;

use App\Support\LegacyDb;
use App\Support\TableQuery;
use Illuminate\Support\Facades\DB;

/**
 * Ported from Controllers/Risk_assessment_shore.php. Fully independent
 * of RiskAssessmentRepository (the Vessel module) — legacy keeps these
 * as two genuinely separate tables, not a shared one.
 */
class RiskAssessmentShoreRepository
{
    private const COLUMNS = [
        ['key' => 'report_no', 'label' => 'REPORT NO.', 'sortable' => true],
        ['key' => 'report_type', 'label' => 'TYPE', 'sortable' => true],
        ['key' => 'vessel', 'label' => 'VESSEL', 'sortable' => false],
        ['key' => 'risk_date', 'label' => 'DATE', 'sortable' => true],
        ['key' => 'port', 'label' => 'PORT/LOCATION', 'sortable' => true],
        ['key' => 'category', 'label' => 'CATEGORY', 'sortable' => false],
        ['key' => 'task', 'label' => 'TASK', 'sortable' => false],
    ];

    public static function columns(): array
    {
        return self::COLUMNS;
    }

    /** Same as years(), reading tb_risk_assessment_shore directly from the legacy connection. MySQL's YEAR() replaces the SQLite strftime() call. */
    public function legacyYears(): array
    {
        return DB::connection('legacy')->table('tb_risk_assessment_shore')
            ->where('risk_date', '!=', '0000-00-00')
            ->selectRaw('DISTINCT YEAR(risk_date) as year')
            ->orderByDesc('year')
            ->pluck('year')
            ->filter()
            ->map(fn ($y) => (int) $y)
            ->all();
    }

    /** @return array<int, array{id:string,label:string}> */
    public function legacyVesselOptions(?string $legacyUserId): array
    {
        return LegacyDb::assignedVesselOptions($legacyUserId);
    }

    /**
     * Ported from loadData(): SHORE-type rows are always visible; a
     * VESSEL-type row only shows when it belongs to the selected
     * vessel. With no vessel filter ("ALL"), everything is visible.
     * Reads tb_risk_assessment_shore directly from the legacy
     * connection. SHORE-type rows stay company-wide visible (no vessel
     * scoping — they aren't attributed to a vessel at all); VESSEL-type
     * rows are scoped to the logged-in user's assigned vessels.
     * Read-only: can_edit/can_delete/can_reopen are always false.
     */
    public function legacyTable(TableQuery $query, ?string $vesselId, ?int $year, ?string $legacyUserId): array
    {
        $vessels = LegacyDb::vesselNames();
        $assignedVesselIds = LegacyDb::assignedVesselIds($legacyUserId);

        $builder = DB::connection('legacy')->table('tb_risk_assessment_shore')
            ->leftJoin('tb_risk_category_shore', 'tb_risk_category_shore.categoryID', '=', 'tb_risk_assessment_shore.categoryID')
            ->leftJoin('tb_risk_operation_shore', 'tb_risk_operation_shore.operationID', '=', 'tb_risk_assessment_shore.operationID')
            ->where(function ($q) use ($assignedVesselIds) {
                $q->where('tb_risk_assessment_shore.report_type', 'SHORE')
                    ->orWhere(function ($vq) use ($assignedVesselIds) {
                        $vq->where('tb_risk_assessment_shore.report_type', 'VESSEL')
                            ->whereIn('tb_risk_assessment_shore.vesid', $assignedVesselIds);
                    });
            })
            ->select([
                'tb_risk_assessment_shore.riskID', 'tb_risk_assessment_shore.report_no', 'tb_risk_assessment_shore.report_type',
                'tb_risk_assessment_shore.vesid', 'tb_risk_assessment_shore.risk_date', 'tb_risk_assessment_shore.port',
                'tb_risk_assessment_shore.categoryID', 'tb_risk_assessment_shore.other_category_name', 'tb_risk_category_shore.category',
                'tb_risk_assessment_shore.operationID', 'tb_risk_assessment_shore.other_operation_name', 'tb_risk_operation_shore.operation',
                'tb_risk_assessment_shore.approval_from_shore', 'tb_risk_assessment_shore.shore_is_approved',
                'tb_risk_assessment_shore.approval_from_marine', 'tb_risk_assessment_shore.marine_shore_is_approved',
                'tb_risk_assessment_shore.date_closed',
            ])
            ->selectSub(
                fn ($q) => $q->from('tb_risk_assessment_hazzards_shore')->selectRaw('COUNT(*)')->whereColumn('riskID', 'tb_risk_assessment_shore.riskID'),
                'hazard_count',
            );

        if ($vesselId !== null) {
            $builder->where(function ($q) use ($vesselId) {
                $q->where('tb_risk_assessment_shore.report_type', 'SHORE')
                    ->orWhere(function ($vq) use ($vesselId) {
                        $vq->where('tb_risk_assessment_shore.report_type', 'VESSEL')->where('tb_risk_assessment_shore.vesid', $vesselId);
                    });
            });
        }

        if ($year !== null) {
            $builder->whereRaw('YEAR(tb_risk_assessment_shore.risk_date) = ?', [$year]);
        }

        if ($query->search !== null) {
            $term = "%{$query->search}%";
            $builder->where(function ($q) use ($term) {
                $q->where('tb_risk_assessment_shore.report_no', 'like', $term)
                    ->orWhere('tb_risk_assessment_shore.report_type', 'like', $term)
                    ->orWhere('tb_risk_assessment_shore.port', 'like', $term)
                    ->orWhere('tb_risk_assessment_shore.other_category_name', 'like', $term)
                    ->orWhere('tb_risk_assessment_shore.other_operation_name', 'like', $term);
            });
        }

        $sortMap = ['report_no' => 'tb_risk_assessment_shore.report_no', 'report_type' => 'tb_risk_assessment_shore.report_type', 'risk_date' => 'tb_risk_assessment_shore.risk_date', 'port' => 'tb_risk_assessment_shore.port'];
        $sort = $sortMap[$query->sort ?? ''] ?? 'tb_risk_assessment_shore.risk_date';

        $paginator = $builder->orderBy($sort, $query->direction)->paginate($query->perPage, page: $query->page);

        $rows = collect($paginator->items())->map(fn ($r) => [
            'id' => $r->riskID,
            'report_no' => $r->report_no,
            'report_type' => $r->report_type,
            'vessel' => $r->report_type === 'VESSEL' ? ($vessels[$r->vesid] ?? '') : '',
            'risk_date' => $r->risk_date,
            'port' => $r->port,
            'category' => $r->categoryID === 'OTHER' ? $r->other_category_name : $r->category,
            'task' => $r->operationID === 'OTHER' ? $r->other_operation_name : $r->operation,
            'approval_from_shore' => (bool) $r->approval_from_shore,
            'shore_is_approved' => (bool) $r->shore_is_approved,
            'approval_from_marine' => (bool) $r->approval_from_marine,
            'marine_is_approved' => (bool) $r->marine_shore_is_approved,
            'date_closed' => $r->date_closed === '0000-00-00' ? null : $r->date_closed,
            'hazard_count' => $r->hazard_count,
            'can_edit' => false,
            'can_delete' => false,
            'can_reopen' => false,
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
     * Ported from admin/riskassessmentshore/view_report.php, surfaced
     * via the module's own list. Read-only, reading
     * tb_risk_assessment_shore directly from the legacy connection.
     */
    public function legacyDetail(string $riskID): ?array
    {
        $r = DB::connection('legacy')->table('tb_risk_assessment_shore')
            ->leftJoin('tb_risk_category_shore', 'tb_risk_category_shore.categoryID', '=', 'tb_risk_assessment_shore.categoryID')
            ->leftJoin('tb_risk_operation_shore', 'tb_risk_operation_shore.operationID', '=', 'tb_risk_assessment_shore.operationID')
            ->where('tb_risk_assessment_shore.riskID', $riskID)
            ->select(['tb_risk_assessment_shore.*', 'tb_risk_category_shore.category', 'tb_risk_operation_shore.operation'])
            ->first();

        if ($r === null) {
            return null;
        }

        $vessels = LegacyDb::vesselNames();
        $zeroDateToNull = fn (?string $date) => ($date === null || $date === '0000-00-00') ? null : $date;

        $hazards = DB::connection('legacy')->table('tb_risk_assessment_hazzards_shore')
            ->where('riskID', $riskID)
            ->orderBy('arrangement')
            ->get();

        $people = DB::connection('legacy')->table('tb_risk_assessment_person_shore')
            ->where('riskID', $riskID)
            ->orderBy('arrangement')
            ->get();

        return [
            'id' => $r->riskID,
            'report_no' => $r->report_no,
            'report_type' => $r->report_type,
            'vessel' => $r->report_type === 'VESSEL' ? ($vessels[$r->vesid] ?? '') : '',
            'risk_date' => $zeroDateToNull($r->risk_date),
            'port' => $r->port,
            'category' => $r->categoryID === 'OTHER' ? $r->other_category_name : $r->category,
            'task' => $r->operationID === 'OTHER' ? $r->other_operation_name : $r->operation,
            'approval_from_shore' => (bool) $r->approval_from_shore,
            'shore_is_approved' => (bool) $r->shore_is_approved,
            'approval_from_marine' => (bool) $r->approval_from_marine,
            'marine_is_approved' => (bool) $r->marine_shore_is_approved,
            'date_closed' => $zeroDateToNull($r->date_closed),
            'hazard_count' => $hazards->count(),
            'can_edit' => false,
            'can_delete' => false,
            'can_reopen' => false,
            'vessel_id' => null,
            'risk_schedule' => $zeroDateToNull($r->risk_schedule),
            'department' => $r->department,
            'activity' => $r->activity,
            'risk_category_shore_id' => null,
            'other_category_name' => $r->other_category_name,
            'risk_operation_shore_id' => null,
            'other_operation_name' => $r->other_operation_name,
            'overall_risk' => $r->overall_risk,
            'remarks' => $r->remarks,
            'date_approved' => $zeroDateToNull($r->date_approved),
            'shore_remarks' => $r->shore_remarks,
            'marine_date_approved' => $zeroDateToNull($r->marine_date_approved),
            'marine_remarks' => $r->marine_remarks,
            'hazards' => $hazards->map(fn ($h) => [
                'id' => $h->asshazzID,
                'arrangement' => $h->arrangement,
                'unwanted_consequences' => $h->unwanted_consequences,
                'underlying_causes' => $h->underlying_causes,
                'severity' => $h->severity,
                'likelihood' => $h->likelihood,
                'risk' => $h->risk,
                'existing_control' => $h->existing_control,
                'additional_control' => $h->additional_control,
                're_severity' => $h->re_severity,
                're_likelihood' => $h->re_likelihood,
                're_risk' => $h->re_risk,
            ])->all(),
            'people' => $people->map(fn ($p) => [
                'id' => $p->riskPersonID,
                'arrangement' => $p->arrangement,
                'person_details' => $p->person_details,
            ])->all(),
        ];
    }
}
