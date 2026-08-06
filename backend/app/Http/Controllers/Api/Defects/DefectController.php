<?php

namespace App\Http\Controllers\Api\Defects;

use App\Http\Controllers\Controller;
use App\Http\Requests\Defects\DefectRequest;
use App\Repositories\Defects\DefectRepository;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ported from Controllers/Defect_list.php. Not ported: the tb_logs audit
 * trail, user_level-gated Edit button visibility (every action is
 * available here), file attachments (upload_defect_file()'s S3 sync —
 * no file storage anywhere in this migration), and the printable
 * header/footer (tb_report_footer, Setup-managed, not migrated
 * anywhere else either). This module has no workflow/status actions —
 * unlike Master Review/ISPS Review it's plain CRUD, matching Revision
 * History's shape. Unlike most other modules stripped to read-only,
 * Defects writes directly back to the legacy tb_defect_list table, so
 * create/update stay live here — see DefectRepository::legacyCreate()/
 * legacyUpdate(). Legacy has no delete endpoint for tb_defect_list, so
 * there's no destroy() here either.
 */
class DefectController extends Controller
{
    public function __construct(private readonly DefectRepository $defects) {}

    /**
     * GET /api/defects/options
     */
    public function options(): JsonResponse
    {
        return response()->json(['data' => ['vessels' => $this->defects->legacyVesselOptions()]]);
    }

    /**
     * GET /api/defects?vessel_id=&date_from=&date_to=&priority=&...
     */
    public function index(Request $request): JsonResponse
    {
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

    /**
     * GET /api/defects/{defect}
     */
    public function show(string $defect): JsonResponse
    {
        $detail = $this->defects->legacyDetail($defect);
        abort_if($detail === null, 404);

        return response()->json(['data' => $detail]);
    }

    /**
     * POST /api/defects
     */
    public function store(DefectRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->defects->legacyCreate($request->validated())], 201);
    }

    /**
     * PUT /api/defects/{defect}
     */
    public function update(DefectRequest $request, string $defect): JsonResponse
    {
        return response()->json(['data' => $this->defects->legacyUpdate($defect, $request->validated())]);
    }

    private function stringOrNull(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : (string) $value;
    }
}
