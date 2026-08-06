<?php

namespace App\Http\Controllers\Api\CommitteeMeetings;

use App\Http\Controllers\Controller;
use App\Repositories\CommitteeMeetings\CommitteeMeetingRepository;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ported from Controllers/Committee_meeting.php. Read-only: Add/Edit/
 * Publish/Approve/Delete never had a legacy write-back path built, so
 * they're not offered here — see CommitteeMeetingRepository.
 */
class CommitteeMeetingController extends Controller
{
    public function __construct(private readonly CommitteeMeetingRepository $committeeMeetings) {}

    /**
     * GET /api/committee-meetings
     */
    public function index(Request $request): JsonResponse
    {
        $vesselId = $request->query('vessel_id');
        $vesselId = $vesselId !== '' ? $vesselId : null;

        $result = $this->committeeMeetings->legacyFullTable(
            TableQuery::fromRequest($request),
            $vesselId,
            $request->user()?->legacy_user_id,
        );

        return response()->json([
            'data' => [
                'columns' => CommitteeMeetingRepository::moduleColumns(),
                'rows' => $result['rows'],
                'meta' => $result['meta'],
            ],
        ]);
    }

    /**
     * GET /api/committee-meetings/options
     */
    public function options(Request $request): JsonResponse
    {
        return response()->json([
            'data' => [
                'vessels' => $this->committeeMeetings->legacyVesselOptions($request->user()?->legacy_user_id),
            ],
        ]);
    }

    /**
     * GET /api/committee-meetings/{committeeMeeting}
     */
    public function show(string $committeeMeeting): JsonResponse
    {
        $detail = $this->committeeMeetings->legacyDetail($committeeMeeting);
        abort_if($detail === null, 404);

        return response()->json(['data' => $detail]);
    }
}
