<?php

namespace App\Http\Controllers\Api\PmsConfiguration;

use App\Http\Controllers\Controller;
use App\Models\Vessel;
use App\Repositories\Pms\PmsConfigurationRepository;
use App\Support\LegacyDb;
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
        $principals = LegacyDb::isConfigured()
            ? $this->configuration->legacyPrincipalOptions()
            : $this->configuration->principalOptions();

        return response()->json(['data' => ['principals' => $principals]]);
    }

    /**
     * GET /api/pms-configuration?principal_id=&...
     */
    public function index(Request $request): JsonResponse
    {
        if (LegacyDb::isConfigured()) {
            $result = $this->configuration->legacyTable((string) $request->query('principal_id'), TableQuery::fromRequest($request));

            return response()->json(['data' => ['rows' => $result['rows'], 'meta' => $result['meta']]]);
        }

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
     *
     * String param (not an Eloquent-bound model) so a legacy vesID — a
     * string with no matching local row — can reach legacyUpdateConfiguration().
     */
    public function update(Request $request, string $vessel): JsonResponse
    {
        $data = $request->validate(['configuration' => 'required|in:SHORE,VESSEL']);

        if (LegacyDb::isConfigured()) {
            return response()->json(['data' => $this->configuration->legacyUpdateConfiguration($vessel, $data['configuration'])]);
        }

        $updated = $this->configuration->updateConfiguration(Vessel::query()->findOrFail((int) $vessel), $data['configuration']);

        return response()->json(['data' => $this->mapRow($updated)]);
    }

    private function mapRow(Vessel $v): array
    {
        return [
            'id' => $v->id,
            'vessel_name' => $v->display_name,
            'short_name' => $v->short_name,
            'configuration' => $v->configuration,
            'can_edit' => true,
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
