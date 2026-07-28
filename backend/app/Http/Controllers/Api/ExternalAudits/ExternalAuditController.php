<?php

namespace App\Http\Controllers\Api\ExternalAudits;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExternalAudits\ExternalAuditRequest;
use App\Models\ExternalAudits\ExternalAuditReport;
use App\Repositories\ExternalAudits\ExternalAuditReportRepository;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Ported from Controllers/External.php. Not ported: file attachment
 * upload/S3 storage, the tb_logs audit trail, user_level-gated button
 * visibility, and the legacy S3-file-sync side effects that
 * publish/approve triggered (delete+reinsert of every linked
 * Nonconformity/Observation purely to poke a vessel-side sync watcher —
 * no equivalent system exists here). The real (non-S3) half of that same
 * delete+reinsert — force-syncing is_published/is_approved onto every
 * linked Nonconformity row — IS ported, in
 * ExternalAuditReportRepository::publish()/approve().
 *
 * There's deliberately no reopen endpoint — External Audits has no
 * closing-date concept in legacy at all.
 *
 * Legacy's `is_approved` list-column formatter always renders "✕" for
 * VESSEL-added rows regardless of the actual flag, while the button
 * logic elsewhere in the same file (and Incident Report's equivalent
 * column) correctly reads the real value — that reads as a copy-paste
 * bug, not a real business rule, so mapRow here reports the true
 * boolean for both branches instead of replicating it.
 */
class ExternalAuditController extends Controller
{
    public function __construct(private readonly ExternalAuditReportRepository $externalAudits)
    {
    }

    /**
     * GET /api/external-audits
     */
    public function index(Request $request): JsonResponse
    {
        $vesselId = $request->query('vessel_id');

        $paginator = $this->externalAudits->fullTable(
            TableQuery::fromRequest($request),
            $vesselId !== '' ? $vesselId : null,
        );

        return response()->json([
            'data' => [
                'columns' => ExternalAuditReportRepository::moduleColumns(),
                'rows' => collect($paginator->items())->map(fn (ExternalAuditReport $r) => $this->mapRow($r))->all(),
                'meta' => $this->meta($paginator),
            ],
        ]);
    }

    /**
     * GET /api/external-audits/options
     */
    public function options(): JsonResponse
    {
        return response()->json([
            'data' => [
                'vessels' => $this->externalAudits->vesselOptions(),
            ],
        ]);
    }

    /**
     * GET /api/external-audits/{externalAuditReport}
     */
    public function show(ExternalAuditReport $externalAuditReport): JsonResponse
    {
        $externalAuditReport->load('vessel');
        // mapRow reads these counts; route-model binding doesn't run the
        // list query's withCount, so they have to be loaded explicitly.
        $externalAuditReport->loadCount([
            'nonconformities as pending_nc_count' => fn ($q) => $q->where('is_inactive', false)->whereNull('close_out_date'),
            'nonconformities as total_nc_count' => fn ($q) => $q->where('is_inactive', false),
        ]);

        return response()->json(['data' => $this->mapDetail($externalAuditReport)]);
    }

    /**
     * POST /api/external-audits
     */
    public function store(ExternalAuditRequest $request): JsonResponse
    {
        $externalAuditReport = $this->externalAudits->create($request->validated());

        return response()->json(['data' => $this->mapDetail($externalAuditReport)], 201);
    }

    /**
     * PUT /api/external-audits/{externalAuditReport}
     */
    public function update(ExternalAuditRequest $request, ExternalAuditReport $externalAuditReport): JsonResponse
    {
        $externalAuditReport = $this->externalAudits->update($externalAuditReport, $request->validated());

        return response()->json(['data' => $this->mapDetail($externalAuditReport)]);
    }

    /**
     * DELETE /api/external-audits/{externalAuditReport}
     */
    public function destroy(ExternalAuditReport $externalAuditReport): JsonResponse
    {
        if (! $this->canDelete($externalAuditReport)) {
            abort(422, 'This report cannot be deleted.');
        }

        $this->externalAudits->delete($externalAuditReport);

        return response()->json(['data' => ['ok' => true]]);
    }

    /**
     * POST /api/external-audits/{externalAuditReport}/publish
     */
    public function publish(ExternalAuditReport $externalAuditReport): JsonResponse
    {
        if (! $this->canPublish($externalAuditReport)) {
            abort(422, 'This report cannot be published/unpublished.');
        }

        $externalAuditReport = $this->externalAudits->publish($externalAuditReport);

        return response()->json(['data' => $this->mapDetail($externalAuditReport)]);
    }

    /**
     * POST /api/external-audits/{externalAuditReport}/approve
     */
    public function approve(ExternalAuditReport $externalAuditReport): JsonResponse
    {
        if (! $this->canApprove($externalAuditReport)) {
            abort(422, 'This report cannot be approved.');
        }

        $externalAuditReport = $this->externalAudits->approve($externalAuditReport);

        return response()->json(['data' => $this->mapDetail($externalAuditReport)]);
    }

    private function mapRow(ExternalAuditReport $r): array
    {
        return [
            'id' => $r->id,
            'ref_no' => $r->ref_no,
            'vessel' => $r->vessel?->display_name ?? '',
            'added_by' => $r->added_by,
            'dateof_audit' => $r->dateof_audit->format('Y-m-d'),
            'portof_audit' => $r->portof_audit,
            'typeof_audit' => $r->typeof_audit,
            'published' => $r->added_by === 'SHORE' ? $r->is_published : null,
            'is_approved' => $r->is_approved,
            'pending_nc_count' => $r->pending_nc_count ?? 0,
            'total_nc_count' => $r->total_nc_count ?? 0,
            'can_edit' => true,
            'can_publish' => $this->canPublish($r),
            'can_approve' => $this->canApprove($r),
            'can_delete' => $this->canDelete($r),
        ];
    }

    private function mapDetail(ExternalAuditReport $r): array
    {
        return [
            ...$this->mapRow($r),
            'vessel_id' => $r->vessel_id,
            'department' => $r->department,
            'master_name' => $r->master_name,
            'chief_engineer' => $r->chief_engineer,
            'auditor_name' => $r->auditor_name,
            'shore_remarks' => $r->shore_remarks,
            'vessel_remarks' => $r->vessel_remarks,
        ];
    }

    private function canPublish(ExternalAuditReport $r): bool
    {
        return $r->added_by === 'SHORE';
    }

    /**
     * Ported from the approve-button gate in loadData(): VESSEL reports
     * can always be approved once unapproved, but SHORE reports must be
     * published first.
     */
    private function canApprove(ExternalAuditReport $r): bool
    {
        if ($r->is_approved) {
            return false;
        }

        return $r->added_by === 'VESSEL' || ($r->added_by === 'SHORE' && $r->is_published);
    }

    private function canDelete(ExternalAuditReport $r): bool
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
