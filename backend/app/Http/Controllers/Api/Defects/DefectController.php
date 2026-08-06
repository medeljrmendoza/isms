<?php

namespace App\Http\Controllers\Api\Defects;

use App\Http\Controllers\Controller;
use App\Http\Requests\Defects\DefectRequest;
use App\Models\Defects\Defect;
use App\Repositories\Defects\DefectRepository;
use App\Support\LegacyDb;
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
        $vessels = LegacyDb::isConfigured() ? $this->defects->legacyVesselOptions() : $this->defects->vesselOptions();

        return response()->json(['data' => ['vessels' => $vessels]]);
    }

    /**
     * GET /api/defects?vessel_id=&date_from=&date_to=&priority=&...
     */
    public function index(Request $request): JsonResponse
    {
        if (LegacyDb::isConfigured()) {
            $result = $this->defects->legacyFullTable(
                $this->stringOrNull($request->query('vessel_id')),
                $request->query('date_from') ?: null,
                $request->query('date_to') ?: null,
                $request->query('priority') ?: null,
                TableQuery::fromRequest($request),
                $request->user()?->legacy_user_id,
            );

            return response()->json([
                'data' => [
                    'columns' => DefectRepository::columns(),
                    'rows' => $result['rows'],
                    'meta' => $result['meta'],
                ],
            ]);
        }

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
     *
     * String param (not an Eloquent-bound model) so a legacy defectID —
     * a string with no matching local row — can reach legacyDetail().
     */
    public function show(string $defect): JsonResponse
    {
        if (LegacyDb::isConfigured()) {
            $detail = $this->defects->legacyDetail($defect);
            abort_if($detail === null, 404);

            return response()->json(['data' => $detail]);
        }

        $model = Defect::query()->with('vessel')->findOrFail((int) $defect);

        return response()->json(['data' => $this->mapDetail($model)]);
    }

    /**
     * POST /api/defects
     */
    public function store(DefectRequest $request): JsonResponse
    {
        if (LegacyDb::isConfigured()) {
            return response()->json(['data' => $this->defects->legacyCreate($request->validated())], 201);
        }

        $defect = $this->defects->create($request->validated());
        $defect->load('vessel');

        return response()->json(['data' => $this->mapDetail($defect)], 201);
    }

    /**
     * PUT /api/defects/{defect}
     */
    public function update(DefectRequest $request, string $defect): JsonResponse
    {
        if (LegacyDb::isConfigured()) {
            return response()->json(['data' => $this->defects->legacyUpdate($defect, $request->validated())]);
        }

        $model = $this->defects->update(Defect::query()->findOrFail((int) $defect), $request->validated());
        $model->load('vessel');

        return response()->json(['data' => $this->mapDetail($model)]);
    }

    /**
     * DELETE /api/defects/{defect}
     *
     * Legacy has no delete endpoint for tb_defect_list (confirmed via
     * Defect_list.php) — the frontend never offers delete for
     * legacy-sourced (string-ID) rows, matching the "no unreachable
     * actions" rule used throughout this migration.
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

    private function stringOrNull(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : (string) $value;
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
            'can_edit' => true,
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
