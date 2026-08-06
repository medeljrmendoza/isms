<?php

namespace App\Http\Controllers\Api\PmsConfiguration;

use App\Http\Controllers\Controller;
use App\Repositories\Pms\PmsConfigurationRepository;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Ported from Controllers/Pms_setup_configuration.php. */
class PmsConfigurationController extends Controller
{
    public function __construct(private readonly PmsConfigurationRepository $configuration) {}

    /**
     * GET /api/pms-configuration/options
     */
    public function options(): JsonResponse
    {
        return response()->json(['data' => ['principals' => $this->configuration->legacyPrincipalOptions()]]);
    }

    /**
     * GET /api/pms-configuration?principal_id=&...
     */
    public function index(Request $request): JsonResponse
    {
        $result = $this->configuration->legacyTable((string) $request->query('principal_id'), TableQuery::fromRequest($request));

        return response()->json(['data' => ['rows' => $result['rows'], 'meta' => $result['meta']]]);
    }

    /**
     * PUT /api/pms-configuration/{vessel}
     */
    public function update(Request $request, string $vessel): JsonResponse
    {
        $data = $request->validate(['configuration' => 'required|in:SHORE,VESSEL']);

        return response()->json(['data' => $this->configuration->legacyUpdateConfiguration($vessel, $data['configuration'])]);
    }
}
