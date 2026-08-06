<?php

namespace App\Repositories\CommitteeMeetings;

use App\Support\LegacyDb;
use App\Support\TableQuery;
use Illuminate\Support\Facades\DB;

class CommitteeMeetingRepository
{
    private const COLUMNS = [
        ['key' => 'meeting_date', 'label' => 'DATE', 'sortable' => true],
        // Legacy's own column header, even though it always resolves to
        // a vessel name here — the filter requires a vessel to be set.
        ['key' => 'vessel', 'label' => 'SHORE/VESSEL', 'sortable' => false],
        ['key' => 'type', 'label' => 'TYPE', 'sortable' => false],
    ];

    /**
     * The full module list's column set — see Committee_meeting.php's
     * loadData(). shore_remarks is a presence flag (✓/✕), not the text
     * itself, matching legacy's own column formatter.
     */
    private const MODULE_COLUMNS = [
        ['key' => 'meeting_date', 'label' => 'DATE', 'sortable' => true],
        ['key' => 'added_by', 'label' => 'ADDED BY', 'sortable' => true],
        ['key' => 'vessel', 'label' => 'SHORE/VESSEL', 'sortable' => false],
        ['key' => 'meeting_type', 'label' => 'TYPE', 'sortable' => false],
        ['key' => 'chairman', 'label' => 'CHAIRMAN', 'sortable' => true],
        ['key' => 'incharge', 'label' => 'IN-CHARGE', 'sortable' => true],
        ['key' => 'has_shore_remarks', 'label' => 'SHORE REMARKS', 'sortable' => false],
        ['key' => 'published', 'label' => 'PUBLISHED', 'sortable' => false],
        ['key' => 'is_approved', 'label' => 'APPROVED', 'sortable' => false],
    ];

    public static function columns(): array
    {
        return self::COLUMNS;
    }

    public static function moduleColumns(): array
    {
        return self::MODULE_COLUMNS;
    }

    /**
     * Ported from Controllers/Dashboard_committee_meeting.php's
     * loadData(): a meeting that still needs shore remarks or approval,
     * scoped to the logged-in user's assigned vessels (legacy also
     * requires a non-blank vesID, i.e. excludes company-wide meetings
     * from this dashlet even though the module itself allows them).
     */
    public function legacyTable(TableQuery $query, ?string $legacyUserId): array
    {
        $vessels = LegacyDb::vesselNames();
        $assignedVesselIds = LegacyDb::assignedVesselIds($legacyUserId);

        $typesSub = DB::raw("(SELECT cmt.meetingID,
            GROUP_CONCAT(
                CASE WHEN pmt.type_name = 'OTHERS' THEN CONCAT(pmt.type_name, ' (', cmt.type_other, ')') ELSE pmt.type_name END
                SEPARATOR ', '
            ) AS meeting_type
            FROM tb_committee_meeting_type cmt
            JOIN pl_committee_meeting_type pmt ON pmt.typeID = cmt.typeID
            GROUP BY cmt.meetingID) as mt");

        $builder = DB::connection('legacy')->table('tb_committee_meeting')
            ->leftJoin($typesSub, 'mt.meetingID', '=', 'tb_committee_meeting.meetingID')
            ->where('tb_committee_meeting.vesID', '!=', '')
            ->whereIn('tb_committee_meeting.vesID', $assignedVesselIds)
            ->where(function ($q) {
                $needsAttention = fn ($qq) => $qq->where('tb_committee_meeting.shore_remarks', '')->orWhere('tb_committee_meeting.is_approved', '0');

                $q->where(function ($shore) use ($needsAttention) {
                    $shore->where('tb_committee_meeting.added_by', 'SHORE')
                        ->where('tb_committee_meeting.is_published', '1')
                        ->where($needsAttention);
                })->orWhere(function ($vessel) use ($needsAttention) {
                    $vessel->where('tb_committee_meeting.added_by', 'VESSEL')
                        ->where($needsAttention);
                });
            })
            ->where('tb_committee_meeting.is_deleted', '0')
            ->select(['tb_committee_meeting.meetingID', 'tb_committee_meeting.vesID', 'tb_committee_meeting.meeting_date', 'mt.meeting_type']);

        if ($query->search !== null) {
            $term = "%{$query->search}%";
            $builder->where(function ($q) use ($term) {
                $q->where('tb_committee_meeting.meeting_date', 'like', $term)
                    ->orWhere('mt.meeting_type', 'like', $term);
            });
        }

        $sortMap = ['meeting_date' => 'tb_committee_meeting.meeting_date'];
        $sort = $sortMap[$query->sort ?? ''] ?? 'tb_committee_meeting.meeting_date';

        $paginator = $builder->orderBy($sort, $query->direction)->paginate($query->perPage, page: $query->page);

        $rows = collect($paginator->items())->map(fn ($r) => [
            'record_id' => $r->meetingID,
            'meeting_date' => $r->meeting_date,
            'vessel' => $vessels[$r->vesID] ?? '',
            'type' => $r->meeting_type ?? '',
        ])->all();

        return [
            'rows' => $rows,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    /**
     * Same as fullTable(), reading tb_committee_meeting directly from
     * the legacy connection. Keeps the vesID-in-assigned-vessels
     * scoping fullTable() drops (see its docblock); the "SHORE"
     * filter value means "no vessel at all" (blank/null vesID).
     */
    public function legacyFullTable(TableQuery $query, ?string $vesselId, ?string $legacyUserId): array
    {
        $vessels = LegacyDb::vesselNames();
        $assignedVesselIds = LegacyDb::assignedVesselIds($legacyUserId);

        $typesSub = DB::raw("(SELECT cmt.meetingID,
            GROUP_CONCAT(
                CASE WHEN pmt.type_name = 'OTHERS' THEN CONCAT(pmt.type_name, ' (', cmt.type_other, ')') ELSE pmt.type_name END
                SEPARATOR ', '
            ) AS meeting_type
            FROM tb_committee_meeting_type cmt
            JOIN pl_committee_meeting_type pmt ON pmt.typeID = cmt.typeID
            GROUP BY cmt.meetingID) as mt");

        $builder = DB::connection('legacy')->table('tb_committee_meeting')
            ->leftJoin($typesSub, 'mt.meetingID', '=', 'tb_committee_meeting.meetingID')
            ->where('tb_committee_meeting.is_deleted', '0')
            ->where(function ($q) use ($assignedVesselIds) {
                $q->where('tb_committee_meeting.vesID', '')->orWhereIn('tb_committee_meeting.vesID', $assignedVesselIds);
            })
            ->select([
                'tb_committee_meeting.meetingID', 'tb_committee_meeting.vesID', 'tb_committee_meeting.added_by',
                'tb_committee_meeting.shore_vessel_meeting', 'tb_committee_meeting.meeting_date',
                'tb_committee_meeting.chairman', 'tb_committee_meeting.incharge',
                'tb_committee_meeting.shore_remarks', 'tb_committee_meeting.is_published', 'tb_committee_meeting.is_approved',
                'mt.meeting_type',
            ]);

        if ($vesselId === 'SHORE') {
            $builder->where(function ($q) {
                $q->where('tb_committee_meeting.vesID', '')->orWhereNull('tb_committee_meeting.vesID');
            });
        } elseif ($vesselId !== null && $vesselId !== '' && $vesselId !== 'ALL') {
            $builder->where('tb_committee_meeting.vesID', $vesselId);
        }

        if ($query->search !== null) {
            $term = "%{$query->search}%";
            $builder->where(function ($q) use ($term) {
                $q->where('tb_committee_meeting.meeting_date', 'like', $term)
                    ->orWhere('tb_committee_meeting.chairman', 'like', $term)
                    ->orWhere('tb_committee_meeting.incharge', 'like', $term)
                    ->orWhere('mt.meeting_type', 'like', $term);
            });
        }

        $sortMap = ['meeting_date' => 'tb_committee_meeting.meeting_date', 'chairman' => 'tb_committee_meeting.chairman', 'incharge' => 'tb_committee_meeting.incharge'];
        $sort = $sortMap[$query->sort ?? ''] ?? 'tb_committee_meeting.meeting_date';

        $paginator = $builder->orderBy($sort, $query->direction)->paginate($query->perPage, page: $query->page);

        $rows = collect($paginator->items())->map(function ($r) use ($vessels) {
            $hasVessel = $r->vesID !== '' && $r->vesID !== null;

            return [
                'id' => $r->meetingID,
                'meeting_date' => $r->meeting_date,
                'added_by' => $r->added_by,
                'shore_vessel_meeting' => $r->shore_vessel_meeting,
                'vessel' => $hasVessel ? ($vessels[$r->vesID] ?? '') : 'SHORE',
                'meeting_type' => $r->meeting_type ?? '',
                'chairman' => $r->chairman,
                'incharge' => $r->incharge,
                'has_shore_remarks' => $r->shore_remarks !== '' && $r->shore_remarks !== null,
                'published' => ($r->added_by === 'SHORE' && $hasVessel) ? $r->is_published === '1' : null,
                'is_approved' => $hasVessel ? $r->is_approved === '1' : null,
                'can_edit' => false,
                'can_publish' => false,
                'can_approve' => false,
                'can_delete' => false,
            ];
        })->all();

        return [
            'rows' => $rows,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    /** @return array<int, array{id:string,label:string}> */
    public function legacyVesselOptions(?string $legacyUserId): array
    {
        return LegacyDb::assignedVesselOptions($legacyUserId);
    }

    /**
     * Ported from admin/committee_meeting/view_committee_meeting.php,
     * surfaced via the dashboard's clickable meeting_date column.
     * Same as legacyDetail(), reading tb_committee_meeting directly
     * from the legacy connection.
     */
    public function legacyDetail(string $meetingID): ?array
    {
        $m = DB::connection('legacy')->table('tb_committee_meeting')->where('meetingID', $meetingID)->first();

        if ($m === null) {
            return null;
        }

        $vessels = LegacyDb::vesselNames();

        $meetingType = DB::connection('legacy')->table('tb_committee_meeting_type')
            ->join('pl_committee_meeting_type', 'pl_committee_meeting_type.typeID', '=', 'tb_committee_meeting_type.typeID')
            ->where('tb_committee_meeting_type.meetingID', $meetingID)
            ->select(['pl_committee_meeting_type.type_name', 'tb_committee_meeting_type.type_other'])
            ->get();
        $meetingTypeLabel = $meetingType->map(fn ($t) => $t->type_name === 'OTHERS' && $t->type_other
            ? "{$t->type_name} ({$t->type_other})"
            : $t->type_name)->implode(', ');

        $attendees = DB::connection('legacy')->table('tb_committee_meeting_attendance')
            ->where('meetingID', $meetingID)->where('is_inactive', '!=', '1')
            ->orderBy('arrangement')->pluck('attendance_name')
            ->map(fn ($name) => ['name' => $name])->all();
        $members = DB::connection('legacy')->table('tb_committee_meeting_member')
            ->where('meetingID', $meetingID)->where('is_inactive', '!=', '1')
            ->orderBy('arrangement')->pluck('member_name')
            ->map(fn ($name) => ['name' => $name])->all();
        $topics = DB::connection('legacy')->table('tb_committee_meeting_topics')
            ->where('meetingID', $meetingID)->where('is_inactive', '!=', '1')
            ->orderBy('arrangement')
            ->get(['topic_name', 'meeting_details', 'meeting_comments'])
            ->map(fn ($t) => ['topic_name' => $t->topic_name, 'meeting_details' => $t->meeting_details, 'meeting_comments' => $t->meeting_comments])
            ->all();

        $hasVessel = $m->vesID !== '' && $m->vesID !== null;

        return $this->toDetailArray([
            'id' => $m->meetingID,
            'meeting_date' => $m->meeting_date,
            'added_by' => $m->added_by,
            'shore_vessel_meeting' => $m->shore_vessel_meeting,
            'vessel' => $hasVessel ? ($vessels[$m->vesID] ?? '') : 'SHORE',
            'meeting_type' => $meetingTypeLabel,
            'chairman' => $m->chairman,
            'incharge' => $m->incharge,
            'has_shore_remarks' => $m->shore_remarks !== '' && $m->shore_remarks !== null,
            'published' => ($m->added_by === 'SHORE' && $hasVessel) ? $m->is_published === '1' : null,
            'is_approved' => $hasVessel ? $m->is_approved === '1' : null,
            'meeting_position' => $m->meeting_position,
            'meeting_time' => $m->meeting_time,
            'vessel_remarks' => $m->vessel_remarks,
            'shore_remarks' => $m->shore_remarks,
            'meeting_types' => $meetingType->map(fn ($t) => ['committee_meeting_type_id' => 0, 'name' => $t->type_name, 'type_other' => $t->type_other])->all(),
            'attendees' => $attendees,
            'members' => $members,
            'topics' => $topics,
        ]);
    }

    /** @param array<string, mixed> $r */
    private function toDetailArray(array $r): array
    {
        return [
            'id' => $r['id'],
            'meeting_date' => $r['meeting_date'],
            'added_by' => $r['added_by'],
            'shore_vessel_meeting' => $r['shore_vessel_meeting'],
            'vessel' => $r['vessel'],
            'meeting_type' => $r['meeting_type'],
            'chairman' => $r['chairman'],
            'incharge' => $r['incharge'],
            'has_shore_remarks' => $r['has_shore_remarks'],
            'published' => $r['published'],
            'is_approved' => $r['is_approved'],
            'can_edit' => false,
            'can_publish' => false,
            'can_approve' => false,
            'can_delete' => false,
            'vessel_id' => null,
            'meeting_position' => $r['meeting_position'],
            'meeting_time' => $r['meeting_time'],
            'vessel_remarks' => $r['vessel_remarks'],
            'shore_remarks' => $r['shore_remarks'],
            'meeting_types' => $r['meeting_types'],
            'attendees' => $r['attendees'],
            'members' => $r['members'],
            'topics' => $r['topics'],
        ];
    }
}
