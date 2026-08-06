<?php

namespace App\Repositories\RiskAssessment;

use App\Support\LegacyDb;
use App\Support\TableQuery;
use Illuminate\Support\Facades\DB;

class RiskAssessmentRepository
{
    /**
     * "vessel", "category" and "task" (operation) aren't real sortable
     * columns — vessel is relation-derived, and category/task each
     * resolve to either a lookup name or a free-text fallback.
     */
    private const COLUMNS = [
        ['key' => 'report_no', 'label' => 'REPORT NO.', 'sortable' => true],
        ['key' => 'vessel', 'label' => 'VESSEL', 'sortable' => false],
        ['key' => 'risk_date', 'label' => 'DATE', 'sortable' => true],
        ['key' => 'category', 'label' => 'CATEGORY', 'sortable' => false],
        ['key' => 'task', 'label' => 'TASK', 'sortable' => false],
    ];

    /** The full-module list's column set — see Controllers/Risk_assessment_vessel.php's loadData(). */
    private const FULL_COLUMNS = [
        ['key' => 'report_no', 'label' => 'REPORT NO.', 'sortable' => true],
        ['key' => 'vessel', 'label' => 'VESSEL', 'sortable' => false],
        ['key' => 'risk_date', 'label' => 'DATE', 'sortable' => true],
        ['key' => 'port', 'label' => 'PORT', 'sortable' => true],
        ['key' => 'category', 'label' => 'CATEGORY', 'sortable' => false],
        ['key' => 'task', 'label' => 'TASK', 'sortable' => false],
    ];

    public static function columns(): array
    {
        return self::COLUMNS;
    }

    public static function fullColumns(): array
    {
        return self::FULL_COLUMNS;
    }

    /**
     * Ported from Controllers/Dashboard_risk_assessment.php's
     * loadRiskAssessmentData(): reports still awaiting a required
     * approval (shore and/or marine), scoped to the logged-in user's
     * assigned vessels.
     */
    public function legacyTable(TableQuery $query, ?string $legacyUserId): array
    {
        $vessels = LegacyDb::vesselNames();
        $assignedVesselIds = LegacyDb::assignedVesselIds($legacyUserId);

        $builder = DB::connection('legacy')->table('tb_risk_assessment')
            ->leftJoin('tb_risk_category', 'tb_risk_category.categoryID', '=', 'tb_risk_assessment.categoryID')
            ->leftJoin('tb_risk_operation', 'tb_risk_operation.operationID', '=', 'tb_risk_assessment.operationID')
            ->where(function ($q) {
                $q->where(function ($shore) {
                    $shore->where('tb_risk_assessment.approval_from_shore', '1')->where('tb_risk_assessment.shore_is_approved', '0');
                })->orWhere(function ($marine) {
                    $marine->where('tb_risk_assessment.approval_from_marine', '1')->where('tb_risk_assessment.marine_shore_is_approved', '0');
                });
            })
            ->whereIn('tb_risk_assessment.vesid', $assignedVesselIds)
            ->select([
                'tb_risk_assessment.riskID',
                'tb_risk_assessment.report_no',
                'tb_risk_assessment.vesid',
                'tb_risk_assessment.risk_date',
                'tb_risk_assessment.categoryID',
                'tb_risk_assessment.other_category_name',
                'tb_risk_category.category',
                'tb_risk_assessment.operationID',
                'tb_risk_assessment.other_operation_name',
                'tb_risk_operation.operation',
            ]);

        if ($query->search !== null) {
            $term = "%{$query->search}%";
            $builder->where(function ($q) use ($term) {
                $q->where('tb_risk_assessment.report_no', 'like', $term)
                    ->orWhere('tb_risk_assessment.risk_date', 'like', $term)
                    ->orWhere('tb_risk_assessment.other_category_name', 'like', $term)
                    ->orWhere('tb_risk_assessment.other_operation_name', 'like', $term);
            });
        }

        $sortMap = ['report_no' => 'tb_risk_assessment.report_no', 'risk_date' => 'tb_risk_assessment.risk_date'];
        $sort = $sortMap[$query->sort ?? ''] ?? 'tb_risk_assessment.risk_date';

        $paginator = $builder->orderBy($sort, $query->direction)->paginate($query->perPage, page: $query->page);

        $rows = collect($paginator->items())->map(fn ($r) => [
            'record_id' => $r->riskID,
            'report_no' => $r->report_no,
            'vessel' => $vessels[$r->vesid] ?? '',
            'risk_date' => $r->risk_date,
            'category' => $r->categoryID === 'OTHER' ? $r->other_category_name : $r->category,
            'task' => $r->operationID === 'OTHER' ? $r->other_operation_name : $r->operation,
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

    /** @return array<int, array{id:string,label:string}> */
    public function legacyVesselOptions(?string $legacyUserId): array
    {
        return LegacyDb::assignedVesselOptions($legacyUserId);
    }

    /** Same as detail(), reading tb_risk_assessment directly from the legacy connection. */
    public function legacyDetail(string $riskID): ?array
    {
        $r = DB::connection('legacy')->table('tb_risk_assessment')
            ->leftJoin('tb_risk_category', 'tb_risk_category.categoryID', '=', 'tb_risk_assessment.categoryID')
            ->leftJoin('tb_risk_operation', 'tb_risk_operation.operationID', '=', 'tb_risk_assessment.operationID')
            ->where('tb_risk_assessment.riskID', $riskID)
            ->select(['tb_risk_assessment.*', 'tb_risk_category.category', 'tb_risk_operation.operation'])
            ->first();

        if ($r === null) {
            return null;
        }

        $vessels = LegacyDb::vesselNames();
        $zeroDateToNull = fn (?string $date) => ($date === null || $date === '0000-00-00') ? null : $date;

        $hazards = DB::connection('legacy')->table('tb_risk_assessment_hazzards')
            ->where('riskID', $riskID)
            ->orderBy('arrangement')
            ->get();

        $people = DB::connection('legacy')->table('tb_risk_assessment_person')
            ->where('riskID', $riskID)
            ->orderBy('arrangement')
            ->get();

        return $this->toDetailArray([
            'id' => $r->riskID,
            'report_no' => $r->report_no,
            'vessel' => $vessels[$r->vesid] ?? '',
            'risk_date' => $zeroDateToNull($r->risk_date),
            'port' => $r->port,
            'category' => $r->categoryID === 'OTHER' ? $r->other_category_name : $r->category,
            'task' => $r->operationID === 'OTHER' ? $r->other_operation_name : $r->operation,
            'approval_from_shore' => (bool) $r->approval_from_shore,
            'shore_is_approved' => (bool) $r->shore_is_approved,
            'approval_from_marine' => (bool) $r->approval_from_marine,
            'marine_is_approved' => (bool) $r->marine_shore_is_approved,
            'hazard_count' => $hazards->count(),
            'risk_schedule' => $zeroDateToNull($r->risk_schedule),
            'department' => $r->department,
            'activity' => $r->activity,
            'other_category_name' => $r->other_category_name,
            'other_operation_name' => $r->other_operation_name,
            'overall_risk' => $r->overall_risk,
            'master' => $r->masterID,
            'ce_co' => $r->chiefmateID,
            'vessel_remarks' => $r->remarks,
            'date_approved' => $zeroDateToNull($r->date_approved),
            'shore_remarks' => $r->shore_remarks,
            'marine_date_approved' => $zeroDateToNull($r->marine_date_approved),
            'marine_remarks' => $r->marine_remarks,
            'date_closed' => $zeroDateToNull($r->date_closed),
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
        ]);
    }

    /** @param array<string, mixed> $r */
    private function toDetailArray(array $r): array
    {
        return [
            'id' => $r['id'],
            'report_no' => $r['report_no'],
            'vessel' => $r['vessel'],
            'risk_date' => $r['risk_date'],
            'port' => $r['port'],
            'category' => $r['category'],
            'task' => $r['task'],
            'approval_from_shore' => $r['approval_from_shore'],
            'shore_is_approved' => $r['shore_is_approved'],
            'approval_from_marine' => $r['approval_from_marine'],
            'marine_is_approved' => $r['marine_is_approved'],
            'hazard_count' => $r['hazard_count'],
            'can_edit' => false,
            'vessel_id' => 0,
            'risk_schedule' => $r['risk_schedule'],
            'department' => $r['department'],
            'activity' => $r['activity'],
            'risk_category_id' => null,
            'other_category_name' => $r['other_category_name'],
            'risk_operation_id' => null,
            'other_operation_name' => $r['other_operation_name'],
            'overall_risk' => $r['overall_risk'],
            'master' => $r['master'],
            'ce_co' => $r['ce_co'],
            'vessel_remarks' => $r['vessel_remarks'],
            'date_approved' => $r['date_approved'],
            'shore_remarks' => $r['shore_remarks'],
            'marine_date_approved' => $r['marine_date_approved'],
            'marine_remarks' => $r['marine_remarks'],
            'date_closed' => $r['date_closed'],
            'hazards' => $r['hazards'],
            'people' => $r['people'],
        ];
    }

    /**
     * Ported from get_riskassessment_report_year(), reading tb_risk_assessment
     * directly from the legacy connection.
     */
    public function legacyYears(?string $legacyUserId): array
    {
        $assignedVesselIds = LegacyDb::assignedVesselIds($legacyUserId);

        return DB::connection('legacy')->table('tb_risk_assessment')
            ->whereIn('vesid', $assignedVesselIds)
            ->where('risk_date', '!=', '0000-00-00')
            ->selectRaw('DISTINCT YEAR(risk_date) as year')
            ->orderByDesc('year')
            ->pluck('year')
            ->filter()
            ->map(fn ($y) => (int) $y)
            ->all();
    }

    /**
     * Ported from loadData(): the full-module report list requires both
     * a vessel and a year — legacy returns an empty result set
     * (WHERE 1 = 0) until both filters are applied, no default
     * "show everything" list. Reads tb_risk_assessment directly from
     * the legacy connection, scoped to the logged-in user's assigned
     * vessels. Read-only: can_edit is always false.
     */
    public function legacyFullTable(?string $vesselId, ?int $year, TableQuery $query, ?string $legacyUserId): array
    {
        $empty = [
            'rows' => [],
            'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => $query->perPage, 'total' => 0],
        ];

        if ($vesselId === null || $vesselId === '' || $year === null) {
            return $empty;
        }

        $vessels = LegacyDb::vesselNames();
        $assignedVesselIds = LegacyDb::assignedVesselIds($legacyUserId);

        $builder = DB::connection('legacy')->table('tb_risk_assessment')
            ->leftJoin('tb_risk_category', 'tb_risk_category.categoryID', '=', 'tb_risk_assessment.categoryID')
            ->leftJoin('tb_risk_operation', 'tb_risk_operation.operationID', '=', 'tb_risk_assessment.operationID')
            ->where('tb_risk_assessment.vesid', $vesselId)
            ->whereIn('tb_risk_assessment.vesid', $assignedVesselIds)
            ->whereRaw('YEAR(tb_risk_assessment.risk_date) = ?', [$year])
            ->select([
                'tb_risk_assessment.riskID', 'tb_risk_assessment.report_no', 'tb_risk_assessment.vesid',
                'tb_risk_assessment.risk_date', 'tb_risk_assessment.port', 'tb_risk_assessment.categoryID',
                'tb_risk_assessment.other_category_name', 'tb_risk_category.category', 'tb_risk_assessment.operationID',
                'tb_risk_assessment.other_operation_name', 'tb_risk_operation.operation',
                'tb_risk_assessment.approval_from_shore', 'tb_risk_assessment.shore_is_approved',
                'tb_risk_assessment.approval_from_marine', 'tb_risk_assessment.marine_shore_is_approved',
            ])
            ->selectSub(
                fn ($q) => $q->from('tb_risk_assessment_hazzards')->selectRaw('COUNT(*)')->whereColumn('riskID', 'tb_risk_assessment.riskID'),
                'hazard_count',
            );

        if ($query->search !== null) {
            $term = "%{$query->search}%";
            $builder->where(function ($q) use ($term) {
                $q->where('tb_risk_assessment.report_no', 'like', $term)
                    ->orWhere('tb_risk_assessment.port', 'like', $term)
                    ->orWhere('tb_risk_assessment.other_category_name', 'like', $term)
                    ->orWhere('tb_risk_assessment.other_operation_name', 'like', $term);
            });
        }

        $sortMap = ['report_no' => 'tb_risk_assessment.report_no', 'risk_date' => 'tb_risk_assessment.risk_date'];
        $sort = $sortMap[$query->sort ?? ''] ?? 'tb_risk_assessment.risk_date';

        $paginator = $builder->orderBy($sort, $query->direction)->paginate($query->perPage, page: $query->page);

        $rows = collect($paginator->items())->map(fn ($r) => [
            'id' => $r->riskID,
            'report_no' => $r->report_no,
            'vessel' => $vessels[$r->vesid] ?? '',
            'risk_date' => $r->risk_date,
            'port' => $r->port,
            'category' => $r->categoryID === 'OTHER' ? $r->other_category_name : $r->category,
            'task' => $r->operationID === 'OTHER' ? $r->other_operation_name : $r->operation,
            'approval_from_shore' => (bool) $r->approval_from_shore,
            'shore_is_approved' => (bool) $r->shore_is_approved,
            'approval_from_marine' => (bool) $r->approval_from_marine,
            'marine_is_approved' => (bool) $r->marine_shore_is_approved,
            'hazard_count' => $r->hazard_count,
            'can_edit' => false,
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
}
