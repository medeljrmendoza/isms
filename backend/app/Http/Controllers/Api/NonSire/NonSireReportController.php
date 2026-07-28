<?php

namespace App\Http\Controllers\Api\NonSire;

use App\Http\Controllers\Controller;
use App\Http\Requests\NonSire\NonSireReportRequest;
use App\Models\NonSire\NonSireReport;
use App\Repositories\NonSire\NonSireReportRepository;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Ported from Controllers/Non_sire.php. Not ported: file attachment
 * upload/S3 storage, the tb_logs audit trail, user_level-gated button
 * visibility, the SIRE book / Observations linkage, and the legacy
 * S3-file-sync side effects that publish/approve triggered.
 *
 * There's deliberately no reopen endpoint — Non-SIRE has no closing-date
 * concept in legacy at all. Unlike SIRE, can_delete IS gated to
 * added_by === 'SHORE' — legacy's SHORE-only gate on the delete button
 * is live here (not commented out like it is in Sire.php).
 */
class NonSireReportController extends Controller
{
    public function __construct(private readonly NonSireReportRepository $nonSireReports)
    {
    }

    /**
     * GET /api/non-sire-reports
     */
    public function index(Request $request): JsonResponse
    {
        $vesselId = $request->query('vessel_id');

        $paginator = $this->nonSireReports->fullTable(
            TableQuery::fromRequest($request),
            $vesselId !== '' ? $vesselId : null,
        );

        return response()->json([
            'data' => [
                'columns' => NonSireReportRepository::moduleColumns(),
                'rows' => collect($paginator->items())->map(fn (NonSireReport $r) => $this->mapRow($r))->all(),
                'meta' => $this->meta($paginator),
            ],
        ]);
    }

    /**
     * GET /api/non-sire-reports/options
     */
    public function options(): JsonResponse
    {
        return response()->json([
            'data' => [
                'vessels' => $this->nonSireReports->vesselOptions(),
            ],
        ]);
    }

    /**
     * GET /api/non-sire-reports/{nonSireReport}
     */
    public function show(NonSireReport $nonSireReport): JsonResponse
    {
        $nonSireReport->load('vessel');

        return response()->json(['data' => $this->mapDetail($nonSireReport)]);
    }

    /**
     * POST /api/non-sire-reports
     */
    public function store(NonSireReportRequest $request): JsonResponse
    {
        $nonSireReport = $this->nonSireReports->create($request->validated());

        return response()->json(['data' => $this->mapDetail($nonSireReport)], 201);
    }

    /**
     * PUT /api/non-sire-reports/{nonSireReport}
     */
    public function update(NonSireReportRequest $request, NonSireReport $nonSireReport): JsonResponse
    {
        $nonSireReport = $this->nonSireReports->update($nonSireReport, $request->validated());

        return response()->json(['data' => $this->mapDetail($nonSireReport)]);
    }

    /**
     * DELETE /api/non-sire-reports/{nonSireReport}
     */
    public function destroy(NonSireReport $nonSireReport): JsonResponse
    {
        if (! $this->canDelete($nonSireReport)) {
            abort(422, 'This report cannot be deleted.');
        }

        $this->nonSireReports->delete($nonSireReport);

        return response()->json(['data' => ['ok' => true]]);
    }

    /**
     * POST /api/non-sire-reports/{nonSireReport}/publish
     */
    public function publish(NonSireReport $nonSireReport): JsonResponse
    {
        if (! $this->canPublish($nonSireReport)) {
            abort(422, 'This report cannot be published/unpublished.');
        }

        $nonSireReport = $this->nonSireReports->publish($nonSireReport);

        return response()->json(['data' => $this->mapDetail($nonSireReport)]);
    }

    /**
     * POST /api/non-sire-reports/{nonSireReport}/approve
     */
    public function approve(NonSireReport $nonSireReport): JsonResponse
    {
        if (! $this->canApprove($nonSireReport)) {
            abort(422, 'This report cannot be approved.');
        }

        $nonSireReport = $this->nonSireReports->approve($nonSireReport);

        return response()->json(['data' => $this->mapDetail($nonSireReport)]);
    }

    private function mapRow(NonSireReport $r): array
    {
        return [
            'id' => $r->id,
            'vessel' => $r->vessel?->display_name ?? '',
            'added_by' => $r->added_by,
            'dateof_inspection' => $r->dateof_inspection->format('Y-m-d'),
            'placeof_inspection' => $r->placeof_inspection,
            'company_name' => $r->company_name,
            'inspector_name' => $r->inspector_name,
            'inspection_type' => $r->inspection_type,
            'pass_fail' => $r->pass_fail,
            'published' => $r->added_by === 'SHORE' ? $r->is_published : null,
            'is_approved' => $r->is_published ? $r->is_approved : null,
            'can_edit' => true,
            'can_publish' => $this->canPublish($r),
            'can_approve' => $this->canApprove($r),
            'can_delete' => $this->canDelete($r),
        ];
    }

    private function mapDetail(NonSireReport $r): array
    {
        return [
            ...$this->mapRow($r),
            'vessel_id' => $r->vessel_id,
            'sire_cost' => $r->sire_cost,
            'shore_remarks' => $r->shore_remarks,
            'vessel_remarks' => $r->vessel_remarks,
        ];
    }

    private function canPublish(NonSireReport $r): bool
    {
        return $r->added_by === 'SHORE';
    }

    /**
     * Ported from the approve-button gate in loadData(): VESSEL reports
     * can always be approved once unapproved, but SHORE reports must be
     * published first.
     */
    private function canApprove(NonSireReport $r): bool
    {
        if ($r->is_approved) {
            return false;
        }

        return $r->added_by === 'VESSEL' || ($r->added_by === 'SHORE' && $r->is_published);
    }

    /** Ported from the delete-button gate in loadData(): SHORE-only, unlike Sire.php's commented-out equivalent. */
    private function canDelete(NonSireReport $r): bool
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
