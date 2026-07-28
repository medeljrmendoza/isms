<?php

namespace App\Http\Controllers\Api\InternalAudits;

use App\Http\Controllers\Controller;
use App\Http\Requests\InternalAudits\InternalAuditRequest;
use App\Models\InternalAudits\InternalAuditReport;
use App\Repositories\InternalAudits\InternalAuditReportRepository;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Ported from Controllers/Internal.php. Not ported: file attachment
 * upload/S3 storage, the tb_logs audit trail, the SIRE book /
 * Observations linkage (no Observations module exists), and
 * user_level-gated delete button visibility.
 *
 * There's deliberately no reopen endpoint: the closing-date input is
 * commented out in add_internal_report.php and the list renders no
 * Re-open button, so legacy's reopen_internal_report() is unreachable
 * dead code — same situation as Company Inspections.
 *
 * Legacy uses separate full-page add/edit routes; this app uses a modal
 * form instead, consistent with every other module.
 */
class InternalAuditController extends Controller
{
    public function __construct(private readonly InternalAuditReportRepository $internalAudits)
    {
    }

    /**
     * GET /api/internal-audits
     */
    public function index(Request $request): JsonResponse
    {
        $vesselId = $request->query('vessel_id');

        $paginator = $this->internalAudits->fullTable(
            TableQuery::fromRequest($request),
            $vesselId !== '' ? $vesselId : null,
        );

        return response()->json([
            'data' => [
                'columns' => InternalAuditReportRepository::moduleColumns(),
                'rows' => collect($paginator->items())->map(fn (InternalAuditReport $r) => $this->mapRow($r))->all(),
                'meta' => $this->meta($paginator),
            ],
        ]);
    }

    /**
     * GET /api/internal-audits/options
     */
    public function options(): JsonResponse
    {
        return response()->json([
            'data' => [
                'vessels' => $this->internalAudits->vesselOptions(),
            ],
        ]);
    }

    /**
     * GET /api/internal-audits/{internalAuditReport}
     */
    public function show(InternalAuditReport $internalAuditReport): JsonResponse
    {
        $internalAuditReport->load('vessel');
        // mapRow reads these counts; route-model binding doesn't run the
        // list query's withCount, so they have to be loaded explicitly.
        $internalAuditReport->loadCount([
            'nonconformities as pending_nc_count' => fn ($q) => $q->where('is_inactive', false)->whereNull('close_out_date'),
            'nonconformities as total_nc_count' => fn ($q) => $q->where('is_inactive', false),
        ]);

        return response()->json(['data' => $this->mapDetail($internalAuditReport)]);
    }

    /**
     * POST /api/internal-audits
     */
    public function store(InternalAuditRequest $request): JsonResponse
    {
        $internalAuditReport = $this->internalAudits->create($request->validated());

        return response()->json(['data' => $this->mapDetail($internalAuditReport)], 201);
    }

    /**
     * PUT /api/internal-audits/{internalAuditReport}
     */
    public function update(InternalAuditRequest $request, InternalAuditReport $internalAuditReport): JsonResponse
    {
        $internalAuditReport = $this->internalAudits->update($internalAuditReport, $request->validated());

        return response()->json(['data' => $this->mapDetail($internalAuditReport)]);
    }

    /**
     * DELETE /api/internal-audits/{internalAuditReport}
     */
    public function destroy(InternalAuditReport $internalAuditReport): JsonResponse
    {
        $this->internalAudits->delete($internalAuditReport);

        return response()->json(['data' => ['ok' => true]]);
    }

    private function mapRow(InternalAuditReport $r): array
    {
        return [
            'id' => $r->id,
            'audit_ref' => $r->audit_ref,
            'vessel' => $r->vessel?->display_name ?? '',
            'this_date' => $r->this_date->format('Y-m-d'),
            'placeof_audit' => $r->placeof_audit,
            'typeof_audit' => $r->typeof_audit,
            'auditor_name' => $r->auditor_name,
            'pending_nc_count' => $r->pending_nc_count ?? 0,
            'total_nc_count' => $r->total_nc_count ?? 0,
            'can_edit' => true,
            'can_delete' => true,
        ];
    }

    private function mapDetail(InternalAuditReport $r): array
    {
        return [
            ...$this->mapRow($r),
            'vessel_id' => $r->vessel_id,
            'department' => $r->department,
            'master_name' => $r->master_name,
            'chief_engineer' => $r->chief_engineer,
            'remarks' => $r->remarks,
        ];
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
