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
    public function options(): JsonResponse
    {
        return response()->json(['data' => ['vessels' => $this->manuals->vesselOptions()]]);
    }

    /**
     * GET /api/manuals/tree?sms_type=&vessel_id=
     */
    public function tree(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->manuals->tree(
                $request->query('sms_type') ?: null,
                $this->intOrNull($request->query('vessel_id')),
            ),
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

        return response()->json([
            'data' => $this->manuals->search(
                $term,
                $request->query('sms_type') ?: null,
                $this->intOrNull($request->query('vessel_id')),
            ),
        ]);
    }

    private function intOrNull(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }
}
