<?php

namespace App\Http\Controllers\Api\FlagState;

use App\Http\Controllers\Controller;
use App\Http\Requests\FlagState\FlagStateReportRequest;
use App\Models\FlagState\FlagStateReport;
use App\Repositories\FlagState\FlagStateReportRepository;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Ported from Controllers/Flag_state.php. Not ported: file attachment
 * upload/S3 storage, the tb_logs audit trail, user_level-gated button
 * visibility, the SIRE book / Observations linkage, and the legacy
 * S3-file-sync side effects that publish/approve triggered (delete+
 * reinsert of every linked Nonconformity/Observation purely to poke a
 * vessel-side sync watcher — no equivalent system exists here). The real
 * (non-S3) half of that same delete+reinsert — force-syncing
 * is_published/is_approved onto every linked Nonconformity row — IS
 * ported, in FlagStateReportRepository::publish()/approve().
 *
 * There's deliberately no reopen endpoint — Flag State has no
 * closing-date concept in legacy at all. Unlike External Audits, the
 * `is_approved` list-column formatter here is gated on `is_published`
 * (not a per-added_by special case) — legacy's own code for this column
 * doesn't have the VESSEL-always-shows-✕ bug External Audits has, so
 * mapRow mirrors it exactly rather than deviating.
 */
class FlagStateReportController extends Controller
{
    public function __construct(private readonly FlagStateReportRepository $flagStateReports) {}

    /**
     * GET /api/flag-state-reports
     */
    public function index(Request $request): JsonResponse
    {
        $vesselId = $request->query('vessel_id');

        $paginator = $this->flagStateReports->fullTable(
            TableQuery::fromRequest($request),
            $vesselId !== '' ? $vesselId : null,
        );

        return response()->json([
            'data' => [
                'columns' => FlagStateReportRepository::moduleColumns(),
                'rows' => collect($paginator->items())->map(fn (FlagStateReport $r) => $this->mapRow($r))->all(),
                'meta' => $this->meta($paginator),
            ],
        ]);
    }

    /**
     * GET /api/flag-state-reports/options
     */
    public function options(): JsonResponse
    {
        return response()->json([
            'data' => [
                'vessels' => $this->flagStateReports->vesselOptions(),
            ],
        ]);
    }

    /**
     * GET /api/flag-state-reports/{flagStateReport}
     */
    public function show(FlagStateReport $flagStateReport): JsonResponse
    {
        $flagStateReport->load('vessel');
        // mapRow reads these counts; route-model binding doesn't run the
        // list query's withCount, so they have to be loaded explicitly.
        $flagStateReport->loadCount([
            'nonconformities as pending_nc_count' => fn ($q) => $q->where('is_inactive', false)->whereNull('close_out_date'),
            'nonconformities as total_nc_count' => fn ($q) => $q->where('is_inactive', false),
        ]);

        return response()->json(['data' => $this->mapDetail($flagStateReport)]);
    }

    /**
     * POST /api/flag-state-reports
     */
    public function store(FlagStateReportRequest $request): JsonResponse
    {
        $flagStateReport = $this->flagStateReports->create($request->validated());

        return response()->json(['data' => $this->mapDetail($flagStateReport)], 201);
    }

    /**
     * PUT /api/flag-state-reports/{flagStateReport}
     */
    public function update(FlagStateReportRequest $request, FlagStateReport $flagStateReport): JsonResponse
    {
        $flagStateReport = $this->flagStateReports->update($flagStateReport, $request->validated());

        return response()->json(['data' => $this->mapDetail($flagStateReport)]);
    }

    /**
     * DELETE /api/flag-state-reports/{flagStateReport}
     */
    public function destroy(FlagStateReport $flagStateReport): JsonResponse
    {
        if (! $this->canDelete($flagStateReport)) {
            abort(422, 'This report cannot be deleted.');
        }

        $this->flagStateReports->delete($flagStateReport);

        return response()->json(['data' => ['ok' => true]]);
    }

    /**
     * POST /api/flag-state-reports/{flagStateReport}/publish
     */
    public function publish(FlagStateReport $flagStateReport): JsonResponse
    {
        if (! $this->canPublish($flagStateReport)) {
            abort(422, 'This report cannot be published/unpublished.');
        }

        $flagStateReport = $this->flagStateReports->publish($flagStateReport);

        return response()->json(['data' => $this->mapDetail($flagStateReport)]);
    }

    /**
     * POST /api/flag-state-reports/{flagStateReport}/approve
     */
    public function approve(FlagStateReport $flagStateReport): JsonResponse
    {
        if (! $this->canApprove($flagStateReport)) {
            abort(422, 'This report cannot be approved.');
        }

        $flagStateReport = $this->flagStateReports->approve($flagStateReport);

        return response()->json(['data' => $this->mapDetail($flagStateReport)]);
    }

    private function mapRow(FlagStateReport $r): array
    {
        return [
            'id' => $r->id,
            'ref_no' => $r->ref_no,
            'vessel' => $r->vessel?->display_name ?? '',
            'added_by' => $r->added_by,
            'dateof_inspection' => $r->dateof_inspection->format('Y-m-d'),
            'placeof_inspection' => $r->placeof_inspection,
            'inspector' => $r->inspector,
            'published' => $r->added_by === 'SHORE' ? $r->is_published : null,
            'is_approved' => $r->is_published ? $r->is_approved : null,
            'pending_nc_count' => $r->pending_nc_count ?? 0,
            'total_nc_count' => $r->total_nc_count ?? 0,
            'can_edit' => true,
            'can_publish' => $this->canPublish($r),
            'can_approve' => $this->canApprove($r),
            'can_delete' => $this->canDelete($r),
        ];
    }

    private function mapDetail(FlagStateReport $r): array
    {
        return [
            ...$this->mapRow($r),
            'vessel_id' => $r->vessel_id,
            'flag_cost' => $r->flag_cost,
            'shore_remarks' => $r->shore_remarks,
            'vessel_remarks' => $r->vessel_remarks,
        ];
    }

    private function canPublish(FlagStateReport $r): bool
    {
        return $r->added_by === 'SHORE';
    }

    /**
     * Ported from the approve-button gate in loadData(): VESSEL reports
     * can always be approved once unapproved, but SHORE reports must be
     * published first.
     */
    private function canApprove(FlagStateReport $r): bool
    {
        if ($r->is_approved) {
            return false;
        }

        return $r->added_by === 'VESSEL' || ($r->added_by === 'SHORE' && $r->is_published);
    }

    private function canDelete(FlagStateReport $r): bool
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
