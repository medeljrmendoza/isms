<?php

namespace App\Http\Controllers\Api\Sire;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sire\SireReportRequest;
use App\Models\Sire\SireReport;
use App\Repositories\Sire\SireReportRepository;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Ported from Controllers/Sire.php. Not ported: file attachment
 * upload/S3 storage, the tb_logs audit trail, user_level-gated button
 * visibility, the SIRE book / Observations linkage, the legacy
 * S3-file-sync side effects that publish/approve triggered, and the
 * "SIRE - Archived" view (sire_archived()/loadArchivedData(): a
 * read-only listing of soft-deleted reports — no other module in this
 * app exposes a trash view, so this doesn't either).
 *
 * There's deliberately no reopen endpoint — SIRE has no closing-date
 * concept in legacy at all. can_delete is unconditional: legacy's own
 * SHORE-only gate on the delete button is commented out
 * (`//if($added_by == "SHORE"){`), so both SHORE and VESSEL rows are
 * deletable — unlike External Audits, where that gate is live.
 */
class SireReportController extends Controller
{
    public function __construct(private readonly SireReportRepository $sireReports)
    {
    }

    /**
     * GET /api/sire-reports
     */
    public function index(Request $request): JsonResponse
    {
        $vesselId = $request->query('vessel_id');

        $paginator = $this->sireReports->fullTable(
            TableQuery::fromRequest($request),
            $vesselId !== '' ? $vesselId : null,
        );

        return response()->json([
            'data' => [
                'columns' => SireReportRepository::moduleColumns(),
                'rows' => collect($paginator->items())->map(fn (SireReport $r) => $this->mapRow($r))->all(),
                'meta' => $this->meta($paginator),
            ],
        ]);
    }

    /**
     * GET /api/sire-reports/options
     */
    public function options(): JsonResponse
    {
        return response()->json([
            'data' => [
                'vessels' => $this->sireReports->vesselOptions(),
            ],
        ]);
    }

    /**
     * GET /api/sire-reports/{sireReport}
     */
    public function show(SireReport $sireReport): JsonResponse
    {
        $sireReport->load('vessel');

        return response()->json(['data' => $this->mapDetail($sireReport)]);
    }

    /**
     * POST /api/sire-reports
     */
    public function store(SireReportRequest $request): JsonResponse
    {
        $sireReport = $this->sireReports->create($request->validated());

        return response()->json(['data' => $this->mapDetail($sireReport)], 201);
    }

    /**
     * PUT /api/sire-reports/{sireReport}
     */
    public function update(SireReportRequest $request, SireReport $sireReport): JsonResponse
    {
        $sireReport = $this->sireReports->update($sireReport, $request->validated());

        return response()->json(['data' => $this->mapDetail($sireReport)]);
    }

    /**
     * DELETE /api/sire-reports/{sireReport}
     */
    public function destroy(SireReport $sireReport): JsonResponse
    {
        $this->sireReports->delete($sireReport);

        return response()->json(['data' => ['ok' => true]]);
    }

    /**
     * POST /api/sire-reports/{sireReport}/publish
     */
    public function publish(SireReport $sireReport): JsonResponse
    {
        if (! $this->canPublish($sireReport)) {
            abort(422, 'This report cannot be published/unpublished.');
        }

        $sireReport = $this->sireReports->publish($sireReport);

        return response()->json(['data' => $this->mapDetail($sireReport)]);
    }

    /**
     * POST /api/sire-reports/{sireReport}/approve
     */
    public function approve(SireReport $sireReport): JsonResponse
    {
        if (! $this->canApprove($sireReport)) {
            abort(422, 'This report cannot be approved.');
        }

        $sireReport = $this->sireReports->approve($sireReport);

        return response()->json(['data' => $this->mapDetail($sireReport)]);
    }

    private function mapRow(SireReport $r): array
    {
        return [
            'id' => $r->id,
            'vessel' => $r->vessel?->display_name ?? '',
            'added_by' => $r->added_by,
            'dateof_inspection' => $r->dateof_inspection->format('Y-m-d'),
            'placeof_inspection' => $r->placeof_inspection,
            'company_name' => $r->company_name,
            'inspector_name' => $r->inspector_name,
            'pass_fail' => $r->pass_fail,
            'published' => $r->added_by === 'SHORE' ? $r->is_published : null,
            'is_approved' => $r->is_published ? $r->is_approved : null,
            'can_edit' => true,
            'can_publish' => $this->canPublish($r),
            'can_approve' => $this->canApprove($r),
            'can_delete' => true,
        ];
    }

    private function mapDetail(SireReport $r): array
    {
        return [
            ...$this->mapRow($r),
            'vessel_id' => $r->vessel_id,
            'sire_cost' => $r->sire_cost,
            'shore_remarks' => $r->shore_remarks,
            'vessel_remarks' => $r->vessel_remarks,
        ];
    }

    private function canPublish(SireReport $r): bool
    {
        return $r->added_by === 'SHORE';
    }

    /**
     * Ported from the approve-button gate in loadData(): VESSEL reports
     * can always be approved once unapproved, but SHORE reports must be
     * published first.
     */
    private function canApprove(SireReport $r): bool
    {
        if ($r->is_approved) {
            return false;
        }

        return $r->added_by === 'VESSEL' || ($r->added_by === 'SHORE' && $r->is_published);
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
