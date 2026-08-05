<?php

namespace App\Http\Controllers\Api\PmsConfiguration;

use App\Http\Controllers\Controller;
use App\Models\Vessel;
use App\Repositories\Pms\PmsConfigurationRepository;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/** Ported from Controllers/Pms_setup_configuration.php. */
class PmsConfigurationController extends Controller
{
    public function __construct(private readonly PmsConfigurationRepository $configuration) {}

    /**
     * GET /api/pms-configuration/options
     */
    public function options(): JsonResponse
    {
        return response()->json(['data' => ['principals' => $this->configuration->principalOptions()]]);
    }

    /**
     * GET /api/pms-configuration?principal_id=&...
     */
    public function index(Request $request): JsonResponse
    {
        $paginator = $this->configuration->table((int) $request->query('principal_id'), TableQuery::fromRequest($request));

        return response()->json([
            'data' => [
                'rows' => collect($paginator->items())->map(fn (Vessel $v) => $this->mapRow($v))->all(),
                'meta' => $this->meta($paginator),
            ],
        ]);
    }

    /**
     * PUT /api/pms-configuration/{vessel}
     */
    public function update(Request $request, Vessel $vessel): JsonResponse
    {
        $data = $request->validate(['configuration' => 'required|in:SHORE,VESSEL']);
        $vessel = $this->configuration->updateConfiguration($vessel, $data['configuration']);

        return response()->json(['data' => $this->mapRow($vessel)]);
    }

    private function mapRow(Vessel $v): array
    {
        return [
            'id' => $v->id,
            'vessel_name' => $v->display_name,
            'short_name' => $v->short_name,
            'configuration' => $v->configuration,
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
