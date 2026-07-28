<?php

namespace App\Http\Controllers\Api\PscReports;

use App\Http\Controllers\Controller;
use App\Http\Requests\PscReports\PscReportRequest;
use App\Models\PscReports\PscReport;
use App\Repositories\PscReports\PscReportRepository;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Ported from Controllers/Psc.php. Not ported: file attachment
 * upload/S3 storage, the SIRE book / Observations linkage (no
 * Observations module exists — see conversation notes on the standing
 * decision), and user_level-gated delete button visibility. Legacy uses
 * separate full-page add/edit routes; this app uses a modal form
 * instead, consistent with every other module.
 */
class PscReportController extends Controller
{
    public function __construct(private readonly PscReportRepository $pscReports)
    {
    }

    /**
     * GET /api/psc-reports
     */
    public function index(Request $request): JsonResponse
    {
        $vesselId = $request->query('vessel_id');

        $paginator = $this->pscReports->fullTable(
            TableQuery::fromRequest($request),
            $vesselId !== '' ? $vesselId : null,
        );

        return response()->json([
            'data' => [
                'columns' => PscReportRepository::moduleColumns(),
                'rows' => collect($paginator->items())->map(fn (PscReport $r) => $this->mapRow($r))->all(),
                'meta' => $this->meta($paginator),
            ],
        ]);
    }

    /**
     * GET /api/psc-reports/options
     */
    public function options(): JsonResponse
    {
        return response()->json([
            'data' => [
                'vessels' => $this->pscReports->vesselOptions(),
                'mou_authorities' => $this->pscReports->mouOptions(),
            ],
        ]);
    }

    /**
     * GET /api/psc-reports/{pscReport}
     */
    public function show(PscReport $pscReport): JsonResponse
    {
        $pscReport->load(['vessel', 'mou']);
        // mapRow reads these counts; route-model binding doesn't run the
        // list query's withCount, so they have to be loaded explicitly.
        $pscReport->loadCount([
            'nonconformities as pending_nc_count' => fn ($q) => $q->where('is_inactive', false)->whereNull('close_out_date'),
            'nonconformities as total_nc_count' => fn ($q) => $q->where('is_inactive', false),
        ]);

        return response()->json(['data' => $this->mapDetail($pscReport)]);
    }

    /**
     * POST /api/psc-reports
     */
    public function store(PscReportRequest $request): JsonResponse
    {
        $pscReport = $this->pscReports->create($request->validated());

        return response()->json(['data' => $this->mapDetail($pscReport)], 201);
    }

    /**
     * PUT /api/psc-reports/{pscReport}
     */
    public function update(PscReportRequest $request, PscReport $pscReport): JsonResponse
    {
        $pscReport = $this->pscReports->update($pscReport, $request->validated());

        return response()->json(['data' => $this->mapDetail($pscReport)]);
    }

    /**
     * DELETE /api/psc-reports/{pscReport}
     */
    public function destroy(PscReport $pscReport): JsonResponse
    {
        $this->pscReports->delete($pscReport);

        return response()->json(['data' => ['ok' => true]]);
    }

    /**
     * POST /api/psc-reports/{pscReport}/reopen
     */
    public function reopen(PscReport $pscReport): JsonResponse
    {
        if ($pscReport->closing_date === null) {
            abort(422, 'This report is not closed.');
        }

        $pscReport = $this->pscReports->reopen($pscReport);

        return response()->json(['data' => $this->mapDetail($pscReport)]);
    }

    private function mapRow(PscReport $r): array
    {
        return [
            'id' => $r->id,
            'ref_no' => $r->ref_no,
            'vessel' => $r->vessel?->display_name ?? '',
            'dateof_inspection' => $r->dateof_inspection->format('Y-m-d'),
            'mou' => $this->mouLabel($r),
            'pending_nc_count' => $r->pending_nc_count ?? 0,
            'total_nc_count' => $r->total_nc_count ?? 0,
            'can_edit' => true,
            'can_delete' => true,
            'can_reopen' => $r->closing_date !== null,
        ];
    }

    private function mapDetail(PscReport $r): array
    {
        return [
            ...$this->mapRow($r),
            'vessel_id' => $r->vessel_id,
            'placeof_inspection' => $r->placeof_inspection,
            'mou_id' => $r->mou_id,
            'mou_others' => $r->mou_others,
            'name_psco' => $r->name_psco,
            'master_name' => $r->master_name,
            'chief_engineer' => $r->chief_engineer,
            'is_detained' => $r->is_detained,
            'detained_date' => $r->detained_date?->format('Y-m-d'),
            'detained_time' => $r->detained_time,
            'is_released' => $r->is_released,
            'released_date' => $r->released_date?->format('Y-m-d'),
            'released_time' => $r->released_time,
            'closing_date' => $r->closing_date?->format('Y-m-d'),
            'remarks' => $r->remarks,
        ];
    }

    private function mouLabel(PscReport $r): ?string
    {
        if ($r->mou === null) {
            return null;
        }

        return $r->mou->name === 'Others' ? "MOU - {$r->mou_others}" : $r->mou->name;
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
