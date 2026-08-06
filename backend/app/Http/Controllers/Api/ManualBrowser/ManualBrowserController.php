<?php

namespace App\Http\Controllers\Api\ManualBrowser;

use App\Http\Controllers\Controller;
use App\Repositories\ManualPublish\ManualBrowserRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Ported from Controllers/Sms.php — see ManualBrowserRepository's docblock for what's dropped. */
class ManualBrowserController extends Controller
{
    public function __construct(private readonly ManualBrowserRepository $manuals) {}

    /**
     * GET /api/manuals/options
     */
    public function options(Request $request): JsonResponse
    {
        return response()->json([
            'data' => [
                'vessels' => $this->manuals->legacyVesselOptions($request->user()?->legacy_user_id),
            ],
        ]);
    }

    /**
     * GET /api/manuals/tree?sms_type=&vessel_id=
     */
    public function tree(Request $request): JsonResponse
    {
        $smsType = $request->query('sms_type') ?: null;

        return response()->json([
            'data' => $this->manuals->legacyTree($smsType, $this->stringOrNull($request->query('vessel_id'))),
        ]);
    }

    /**
     * GET /api/manuals/search?q=&sms_type=&vessel_id=
     */
    public function search(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));

        if ($term === '') {
            return response()->json(['data' => []]);
        }

        $smsType = $request->query('sms_type') ?: null;

        return response()->json([
            'data' => $this->manuals->legacySearch($term, $smsType, $this->stringOrNull($request->query('vessel_id'))),
        ]);
    }

    private function stringOrNull(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : (string) $value;
    }
}
