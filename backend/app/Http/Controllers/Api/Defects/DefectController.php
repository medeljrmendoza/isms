<?php

namespace App\Http\Controllers\Api\Defects;

use App\Http\Controllers\Controller;
use App\Http\Requests\Defects\DefectRequest;
use App\Models\Defects\Defect;
use App\Repositories\Defects\DefectRepository;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Ported from Controllers/Defect_list.php. Not ported: the tb_logs audit
 * trail, user_level-gated Edit button visibility (every action is
 * available here), file attachments (upload_defect_file()'s S3 sync —
 * no file storage anywhere in this migration), and the printable
 * header/footer (tb_report_footer, Setup-managed, not migrated
 * anywhere else either). This module has no workflow/status actions —
 * unlike Master Review/ISPS Review it's plain CRUD, matching Revision
 * History's shape.
 */
class DefectController extends Controller
{
    public function __construct(private readonly DefectRepository $defects) {}

    /**
     * GET /api/defects/options
     */
    public function options(): JsonResponse
    {
        return response()->json([
            'data' => [
                'vessels' => $this->defects->vesselOptions(),
            ],
        ]);
    }

    /**
     * GET /api/defects?vessel_id=&date_from=&date_to=&priority=&...
     */
    public function index(Request $request): JsonResponse
    {
        $paginator = $this->defects->fullTable(
            $this->intOrNull($request->query('vessel_id')),
            $request->query('date_from') ?: null,
            $request->query('date_to') ?: null,
            $request->query('priority') ?: null,
            TableQuery::fromRequest($request),
        );

        return response()->json([
            'data' => [
                'columns' => DefectRepository::columns(),
                'rows' => collect($paginator->items())->map(fn (Defect $d) => $this->mapRow($d))->all(),
                'meta' => $this->meta($paginator),
            ],
        ]);
    }

    /**
     * GET /api/defects/{defect}
     */
    public function show(Defect $defect): JsonResponse
    {
        $defect->load('vessel');

        return response()->json(['data' => $this->mapDetail($defect)]);
    }

    /**
     * POST /api/defects
     */
    public function store(DefectRequest $request): JsonResponse
    {
        $defect = $this->defects->create($request->validated());
        $defect->load('vessel');

        return response()->json(['data' => $this->mapDetail($defect)], 201);
    }

    /**
     * PUT /api/defects/{defect}
     */
    public function update(DefectRequest $request, Defect $defect): JsonResponse
    {
        $defect = $this->defects->update($defect, $request->validated());
        $defect->load('vessel');

        return response()->json(['data' => $this->mapDetail($defect)]);
    }

    /**
     * DELETE /api/defects/{defect}
     */
    public function destroy(Defect $defect): JsonResponse
    {
        $this->defects->delete($defect);

        return response()->json(['data' => ['ok' => true]]);
    }

    private function intOrNull(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    private function mapRow(Defect $d): array
    {
        return [
            'id' => $d->id,
            'sl_no' => $d->sl_no,
            'vessel' => $d->vessel?->display_name ?? '',
            'defect_date' => $d->defect_date->format('Y-m-d'),
            'priority' => $d->priority,
            'category' => $d->category,
            'compl_code' => $d->compl_code,
            'description' => $d->description,
            'present_status' => $d->present_status,
            'expected_compl_date' => $d->expected_compl_date?->format('Y-m-d'),
            'compl_date' => $d->compl_date?->format('Y-m-d'),
        ];
    }

    private function mapDetail(Defect $d): array
    {
        return [
            ...$this->mapRow($d),
            'vessel_id' => $d->vessel_id,
            'raised_by' => $d->raised_by,
            'vessel_remarks' => $d->vessel_remarks,
            'shore_remarks' => $d->shore_remarks,
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
