<?php

namespace App\Http\Controllers\Api\CommitteeMeetings;

use App\Http\Controllers\Controller;
use App\Http\Requests\CommitteeMeetings\CommitteeMeetingRequest;
use App\Models\CommitteeMeetings\CommitteeMeeting;
use App\Models\CommitteeMeetings\CommitteeMeetingType;
use App\Repositories\CommitteeMeetings\CommitteeMeetingRepository;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Ported from Controllers/Committee_meeting.php. Not ported: the tb_logs
 * audit trail, user_level-gated button visibility, and the legacy
 * S3-file-sync side effects that every save/publish/approve/delete
 * triggered (no equivalent system exists here). The "auto-populate
 * topics from the selected meeting type" AJAX convenience
 * (get_meeting_topics(), backed by a Setup-managed
 * pl_committee_meeting_topics lookup table) is dropped — that lookup
 * isn't migrated, so topics are entered as free text here instead, same
 * convention as Non-SIRE's inspection_type. The printable header/footer
 * (tied to tb_report_footer, a Setup-managed report-branding feature) is
 * dropped too — nothing else in this migration uses it either.
 *
 * There's deliberately no reopen endpoint — Committee Meeting has no
 * closing-date concept in legacy at all.
 */
class CommitteeMeetingController extends Controller
{
    public function __construct(private readonly CommitteeMeetingRepository $committeeMeetings)
    {
    }

    /**
     * GET /api/committee-meetings
     */
    public function index(Request $request): JsonResponse
    {
        $vesselId = $request->query('vessel_id');

        $paginator = $this->committeeMeetings->fullTable(
            TableQuery::fromRequest($request),
            $vesselId !== '' ? $vesselId : null,
        );

        return response()->json([
            'data' => [
                'columns' => CommitteeMeetingRepository::moduleColumns(),
                'rows' => collect($paginator->items())->map(fn (CommitteeMeeting $m) => $this->mapRow($m))->all(),
                'meta' => $this->meta($paginator),
            ],
        ]);
    }

    /**
     * GET /api/committee-meetings/options
     */
    public function options(): JsonResponse
    {
        return response()->json([
            'data' => [
                'vessels' => $this->committeeMeetings->vesselOptions(),
                'meeting_types' => $this->committeeMeetings->meetingTypeOptions(),
            ],
        ]);
    }

    /**
     * GET /api/committee-meetings/{committeeMeeting}
     */
    public function show(CommitteeMeeting $committeeMeeting): JsonResponse
    {
        $committeeMeeting->load(['vessel', 'meetingTypes', 'attendees', 'members', 'topics']);

        return response()->json(['data' => $this->mapDetail($committeeMeeting)]);
    }

    /**
     * POST /api/committee-meetings
     */
    public function store(CommitteeMeetingRequest $request): JsonResponse
    {
        $validated = $request->validated();
        [$data, $meetingTypes, $attendees, $members, $topics] = $this->splitPayload($validated);

        $meeting = $this->committeeMeetings->create($data, $meetingTypes, $attendees, $members, $topics);
        $meeting->load(['vessel', 'meetingTypes', 'attendees', 'members', 'topics']);

        return response()->json(['data' => $this->mapDetail($meeting)], 201);
    }

    /**
     * PUT /api/committee-meetings/{committeeMeeting}
     */
    public function update(CommitteeMeetingRequest $request, CommitteeMeeting $committeeMeeting): JsonResponse
    {
        $validated = $request->validated();
        [$data, $meetingTypes, $attendees, $members, $topics] = $this->splitPayload($validated);

        $committeeMeeting = $this->committeeMeetings->update($committeeMeeting, $data, $meetingTypes, $attendees, $members, $topics);
        $committeeMeeting->load(['vessel', 'meetingTypes', 'attendees', 'members', 'topics']);

        return response()->json(['data' => $this->mapDetail($committeeMeeting)]);
    }

    /**
     * DELETE /api/committee-meetings/{committeeMeeting}
     */
    public function destroy(CommitteeMeeting $committeeMeeting): JsonResponse
    {
        if (! $this->canDelete($committeeMeeting)) {
            abort(422, 'This meeting cannot be deleted.');
        }

        $this->committeeMeetings->delete($committeeMeeting);

        return response()->json(['data' => ['ok' => true]]);
    }

    /**
     * POST /api/committee-meetings/{committeeMeeting}/publish
     */
    public function publish(CommitteeMeeting $committeeMeeting): JsonResponse
    {
        if (! $this->canPublish($committeeMeeting)) {
            abort(422, 'This meeting cannot be published/unpublished.');
        }

        $committeeMeeting = $this->committeeMeetings->publish($committeeMeeting);

        return response()->json(['data' => $this->mapDetail($committeeMeeting)]);
    }

    /**
     * POST /api/committee-meetings/{committeeMeeting}/approve
     */
    public function approve(CommitteeMeeting $committeeMeeting): JsonResponse
    {
        if (! $this->canApprove($committeeMeeting)) {
            abort(422, 'This meeting cannot be approved.');
        }

        $committeeMeeting = $this->committeeMeetings->approve($committeeMeeting);

        return response()->json(['data' => $this->mapDetail($committeeMeeting)]);
    }

    private function splitPayload(array $validated): array
    {
        $meetingTypes = $validated['meeting_types'] ?? [];
        $attendees = $validated['attendees'] ?? [];
        $members = $validated['members'] ?? [];
        $topics = $validated['topics'] ?? [];
        unset($validated['meeting_types'], $validated['attendees'], $validated['members'], $validated['topics']);

        return [$validated, $meetingTypes, $attendees, $members, $topics];
    }

    private function mapRow(CommitteeMeeting $m): array
    {
        return [
            'id' => $m->id,
            'meeting_date' => $m->meeting_date->format('Y-m-d'),
            'added_by' => $m->added_by,
            'shore_vessel_meeting' => $m->shore_vessel_meeting,
            'vessel' => $m->vessel?->display_name ?? 'SHORE',
            'meeting_type' => $m->meeting_types_label,
            'chairman' => $m->chairman,
            'incharge' => $m->incharge,
            'has_shore_remarks' => $m->shore_remarks !== '' && $m->shore_remarks !== null,
            'published' => ($m->added_by === 'SHORE' && $m->vessel_id !== null) ? $m->is_published : null,
            'is_approved' => $m->vessel_id !== null ? $m->is_approved : null,
            'can_edit' => true,
            'can_publish' => $this->canPublish($m),
            'can_approve' => $this->canApprove($m),
            'can_delete' => $this->canDelete($m),
        ];
    }

    private function mapDetail(CommitteeMeeting $m): array
    {
        return [
            ...$this->mapRow($m),
            'vessel_id' => $m->vessel_id,
            'meeting_position' => $m->meeting_position,
            'meeting_time' => $m->meeting_time,
            'vessel_remarks' => $m->vessel_remarks,
            'shore_remarks' => $m->shore_remarks,
            'meeting_types' => $m->meetingTypes->map(fn (CommitteeMeetingType $t) => [
                'committee_meeting_type_id' => $t->id,
                'name' => $t->name,
                'type_other' => $t->pivot->type_other,
            ])->all(),
            'attendees' => $m->attendees->map(fn ($a) => ['name' => $a->name])->all(),
            'members' => $m->members->map(fn ($mem) => ['name' => $mem->name])->all(),
            'topics' => $m->topics->map(fn ($t) => [
                'topic_name' => $t->topic_name,
                'meeting_details' => $t->meeting_details,
                'meeting_comments' => $t->meeting_comments,
            ])->all(),
        ];
    }

    private function canPublish(CommitteeMeeting $m): bool
    {
        return $m->added_by === 'SHORE' && $m->vessel_id !== null;
    }

    /**
     * Ported from the approve-button gate in loadData(): a SHORE-added
     * meeting (which is only ever reachable when it's VESSEL-scoped —
     * SHORE-only meetings auto-approve and never surface this button)
     * must be published first; a true VESSEL-added meeting just needs to
     * be unapproved.
     */
    private function canApprove(CommitteeMeeting $m): bool
    {
        if ($m->is_approved) {
            return false;
        }

        return $m->added_by === 'VESSEL' || ($m->added_by === 'SHORE' && $m->is_published);
    }

    private function canDelete(CommitteeMeeting $m): bool
    {
        return $m->added_by === 'SHORE';
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
