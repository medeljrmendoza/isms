<?php

namespace App\Http\Controllers\Api\CompanyInspections;

use App\Http\Controllers\Controller;
use App\Http\Requests\CompanyInspections\CompanyInspectionRequest;
use App\Models\CompanyInspections\AuditReport;
use App\Repositories\CompanyInspections\AuditReportRepository;
use App\Support\LegacyDb;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Ported from Controllers/Company.php. Not ported: file attachment
 * upload/S3 storage, the tb_logs audit trail, the SIRE book /
 * Observations linkage (no Observations module exists), and
 * user_level-gated delete button visibility.
 *
 * There's deliberately no reopen endpoint: the closing-date input is
 * commented out in add_company_report.php and the list renders no
 * Re-open button, so legacy's reopen_company_report() is unreachable
 * dead code. date_closed was dropped from the schema for the same
 * reason (see the dashboard-phase migration).
 *
 * Legacy uses separate full-page add/edit routes; this app uses a modal
 * form instead, consistent with every other module.
 */
class CompanyInspectionController extends Controller
{
    public function __construct(private readonly AuditReportRepository $auditReports) {}

    /**
     * GET /api/company-inspections
     */
    public function index(Request $request): JsonResponse
    {
        $vesselId = $request->query('vessel_id');
        $vesselId = $vesselId !== '' ? $vesselId : null;

        if (LegacyDb::isConfigured()) {
            $result = $this->auditReports->legacyFullTable(
                TableQuery::fromRequest($request),
                $vesselId,
                $request->user()?->legacy_user_id,
            );

            return response()->json([
                'data' => [
                    'columns' => AuditReportRepository::moduleColumns(),
                    'rows' => $result['rows'],
                    'meta' => $result['meta'],
                ],
            ]);
        }

        $paginator = $this->auditReports->fullTable(TableQuery::fromRequest($request), $vesselId);

        return response()->json([
            'data' => [
                'columns' => AuditReportRepository::moduleColumns(),
                'rows' => collect($paginator->items())->map(fn (AuditReport $r) => $this->mapRow($r))->all(),
                'meta' => $this->meta($paginator),
            ],
        ]);
    }

    /**
     * GET /api/company-inspections/options
     */
    public function options(Request $request): JsonResponse
    {
        return response()->json([
            'data' => [
                'vessels' => LegacyDb::isConfigured()
                    ? $this->auditReports->legacyVesselOptions($request->user()?->legacy_user_id)
                    : $this->auditReports->vesselOptions(),
                'audit_types' => $this->auditReports->auditTypeOptions(),
                'audit_kinds' => $this->auditReports->auditKindOptions(),
            ],
        ]);
    }

    /**
     * GET /api/company-inspections/{auditReport}
     */
    public function show(string $auditReport): JsonResponse
    {
        if (LegacyDb::isConfigured()) {
            $detail = $this->auditReports->legacyDetail($auditReport);
            abort_if($detail === null, 404);

            return response()->json(['data' => $detail]);
        }

        $model = AuditReport::query()->with(['vessel', 'auditType', 'auditKind'])->findOrFail((int) $auditReport);
        $model->loadCount([
            'nonconformities as pending_nc_count' => fn ($q) => $q->where('is_inactive', false)->whereNull('close_out_date'),
            'nonconformities as total_nc_count' => fn ($q) => $q->where('is_inactive', false),
        ]);

        return response()->json(['data' => $this->mapDetail($model)]);
    }

    /**
     * POST /api/company-inspections
     */
    public function store(CompanyInspectionRequest $request): JsonResponse
    {
        $auditReport = $this->auditReports->create($request->validated());

        return response()->json(['data' => $this->mapDetail($auditReport)], 201);
    }

    /**
     * PUT /api/company-inspections/{auditReport}
     */
    public function update(CompanyInspectionRequest $request, AuditReport $auditReport): JsonResponse
    {
        $auditReport = $this->auditReports->update($auditReport, $request->validated());

        return response()->json(['data' => $this->mapDetail($auditReport)]);
    }

    /**
     * DELETE /api/company-inspections/{auditReport}
     */
    public function destroy(AuditReport $auditReport): JsonResponse
    {
        $this->auditReports->delete($auditReport);

        return response()->json(['data' => ['ok' => true]]);
    }

    private function mapRow(AuditReport $r): array
    {
        return [
            'id' => $r->id,
            'audit_ref' => $r->audit_ref,
            'vessel_company' => $r->vessel_company === 'VESSEL' ? ($r->vessel?->display_name ?? '') : ($r->company ?? ''),
            'this_date' => $r->this_date->format('Y-m-d'),
            'placeof_audit' => $r->placeof_audit,
            'audit_type' => $r->auditType?->name,
            'audit_kind' => $r->auditKind?->name,
            'pending_nc_count' => $r->pending_nc_count ?? 0,
            'total_nc_count' => $r->total_nc_count ?? 0,
            'can_edit' => true,
            'can_delete' => true,
        ];
    }

    private function mapDetail(AuditReport $r): array
    {
        return [
            ...$this->mapRow($r),
            'vessel_company_raw' => $r->vessel_company,
            'vessel_id' => $r->vessel_id,
            'company' => $r->company,
            'department' => $r->department,
            'audit_type_id' => $r->audit_type_id,
            'audit_kind_id' => $r->audit_kind_id,
            'inspector_name' => $r->inspector_name,
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
