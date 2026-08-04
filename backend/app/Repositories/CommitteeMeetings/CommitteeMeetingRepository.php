<?php

namespace App\Repositories\CommitteeMeetings;

use App\Models\CommitteeMeetings\CommitteeMeeting;
use App\Models\CommitteeMeetings\CommitteeMeetingAttendee;
use App\Models\CommitteeMeetings\CommitteeMeetingMember;
use App\Models\CommitteeMeetings\CommitteeMeetingTopic;
use App\Models\CommitteeMeetings\CommitteeMeetingType;
use App\Models\Vessel;
use App\Support\LegacyDb;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
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
     * loadData() WHERE clause: a meeting still needs shore remarks or
     * approval. Unlike most of the other dashlets, this one has no
     * Nonconformities/Observations dependency at all — self-contained.
     * Vessel scoping deferred as elsewhere.
     */
    public function pendingQuery(): Builder
    {
        return CommitteeMeeting::query()
            ->with(['vessel', 'meetingTypes'])
            ->where('is_deleted', false)
            ->where(function (Builder $query) {
                $needsAttention = fn (Builder $q) => $q->where('shore_remarks', '')->orWhere('is_approved', false);

                $query->where(function (Builder $shore) use ($needsAttention) {
                    $shore->where('added_by', 'SHORE')
                        ->where('is_published', true)
                        ->where($needsAttention);
                })->orWhere(function (Builder $vessel) use ($needsAttention) {
                    $vessel->where('added_by', 'VESSEL')
                        ->where($needsAttention);
                });
            });
    }

    public function table(TableQuery $query): LengthAwarePaginator
    {
        $builder = $this->pendingQuery();

        if ($query->search !== null) {
            $term = "%{$query->search}%";
            $builder->where(function (Builder $q) use ($term) {
                $q->where('meeting_date', 'like', $term)
                    ->orWhereHas('vessel', fn (Builder $v) => $v->where('name', 'like', $term))
                    ->orWhereHas('meetingTypes', fn (Builder $t) => $t->where('name', 'like', $term));
            });
        }

        $sortable = array_column(array_filter(self::COLUMNS, fn ($c) => $c['sortable']), 'key');
        $sort = in_array($query->sort, $sortable, true) ? $query->sort : 'meeting_date';

        return $builder->orderBy($sort, $query->direction)
            ->paginate($query->perPage, page: $query->page);
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
     * Ported from Controllers/Committee_meeting.php's loadData(). The
     * `WHERE vesID IN (SELECT ... tb_user_vessel)` scoping is dropped
     * like everywhere else. `vesselId === 'SHORE'` reproduces legacy's
     * special filter value: company-wide SHORE-only meetings (no
     * vessel_id at all), distinct from "ALL" and from a specific vessel.
     */
    public function fullTable(TableQuery $query, ?string $vesselId): LengthAwarePaginator
    {
        $builder = CommitteeMeeting::query()->with(['vessel', 'meetingTypes'])->where('is_deleted', false);

        if ($vesselId === 'SHORE') {
            $builder->where('added_by', 'SHORE')->whereNull('vessel_id');
        } elseif ($vesselId !== null && $vesselId !== '' && $vesselId !== 'ALL') {
            $builder->where('vessel_id', $vesselId);
        }

        if ($query->search !== null) {
            $term = "%{$query->search}%";
            $builder->where(function (Builder $q) use ($term) {
                $q->where('meeting_date', 'like', $term)
                    ->orWhere('chairman', 'like', $term)
                    ->orWhere('incharge', 'like', $term)
                    ->orWhereHas('vessel', fn (Builder $v) => $v->where('name', 'like', $term))
                    ->orWhereHas('meetingTypes', fn (Builder $t) => $t->where('name', 'like', $term));
            });
        }

        $sortable = array_column(array_filter(self::MODULE_COLUMNS, fn ($c) => $c['sortable']), 'key');
        $sort = in_array($query->sort, $sortable, true) ? $query->sort : 'meeting_date';

        return $builder->orderBy($sort, $query->direction)
            ->paginate($query->perPage, page: $query->page);
    }

    /**
     * Ported from add_record()'s insert branch: new records are always
     * SHORE-added (there's no VESSEL-origin path reachable from this
     * admin). shore_vessel_meeting drives everything else: a SHORE-only
     * (company-wide) meeting has no vessel and is auto-approved — there's
     * no vessel audience to approve it for — while a SHORE-entered
     * VESSEL meeting starts unapproved like every other workflowed
     * module. vessel_remarks is frozen blank; only the unmigrated
     * vessel-side app would ever populate it.
     *
     * @param  array<int, array{committee_meeting_type_id:int, type_other:?string}>  $meetingTypes
     * @param  array<int, array{name:string}>  $attendees
     * @param  array<int, array{name:string}>  $members
     * @param  array<int, array{topic_name:string, meeting_details:?string, meeting_comments:?string}>  $topics
     */
    public function create(array $data, array $meetingTypes, array $attendees, array $members, array $topics): CommitteeMeeting
    {
        $isVesselMeeting = ($data['shore_vessel_meeting'] ?? null) === 'VESSEL';

        $meeting = CommitteeMeeting::create([
            ...$data,
            'added_by' => 'SHORE',
            'vessel_id' => $isVesselMeeting ? $data['vessel_id'] : null,
            'vessel_remarks' => null,
            // Column is NOT NULL by design (pendingQuery() compares it to
            // '' directly) — the "nullable" empty-string submission from
            // the form gets normalized here rather than relaxing the
            // column, which would silently break that comparison.
            'shore_remarks' => $data['shore_remarks'] ?? '',
            'is_published' => false,
            'is_approved' => ! $isVesselMeeting,
            'is_deleted' => false,
        ]);

        $this->syncMeetingTypes($meeting, $meetingTypes);
        $this->syncAttendees($meeting, $attendees);
        $this->syncMembers($meeting, $members);
        $this->syncTopics($meeting, $topics);

        return $meeting;
    }

    /**
     * Ported from add_record()'s edit branch. added_by,
     * shore_vessel_meeting, vessel_id, vessel_remarks, and is_published
     * are all frozen at creation time (legacy always re-reads them from
     * the existing row, never from the edit payload). is_approved is
     * recalculated the same way as on create — SHORE-only meetings stay
     * auto-approved, VESSEL-scoped ones reset to unapproved on every
     * save.
     */
    public function update(CommitteeMeeting $meeting, array $data, array $meetingTypes, array $attendees, array $members, array $topics): CommitteeMeeting
    {
        unset($data['vessel_id'], $data['shore_vessel_meeting']);

        $meeting->update([
            ...$data,
            'shore_remarks' => $data['shore_remarks'] ?? '',
            'is_approved' => $meeting->shore_vessel_meeting !== 'VESSEL',
        ]);

        $this->syncMeetingTypes($meeting, $meetingTypes);
        $this->syncAttendees($meeting, $attendees);
        $this->syncMembers($meeting, $members);
        $this->syncTopics($meeting, $topics);

        return $meeting;
    }

    /** Ported from publish_record(): toggles is_published, always sets is_approved true. */
    public function publish(CommitteeMeeting $meeting): CommitteeMeeting
    {
        $meeting->update([
            'is_published' => ! $meeting->is_published,
            'is_approved' => true,
        ]);

        return $meeting;
    }

    /** Ported from approve_record(). */
    public function approve(CommitteeMeeting $meeting): CommitteeMeeting
    {
        $meeting->update(['is_approved' => true]);

        return $meeting;
    }

    /** Ported from delete_record(): soft delete. Sub-table rows are left as-is (legacy also just flags the parent). */
    public function delete(CommitteeMeeting $meeting): void
    {
        $meeting->update(['is_deleted' => true]);
    }

    /** @return array<int, array{id:int,label:string}> */
    public function vesselOptions(): array
    {
        return Vessel::query()->orderBy('name')->get()
            ->map(fn (Vessel $v) => ['id' => $v->id, 'label' => $v->display_name])
            ->all();
    }

    /** @return array<int, array{id:int,label:string}> */
    public function meetingTypeOptions(): array
    {
        return CommitteeMeetingType::query()->orderBy('name')->get()
            ->map(fn (CommitteeMeetingType $t) => ['id' => $t->id, 'label' => $t->name])
            ->all();
    }

    private function syncMeetingTypes(CommitteeMeeting $meeting, array $rows): void
    {
        $meeting->meetingTypes()->sync(
            collect($rows)->mapWithKeys(fn (array $row) => [
                $row['committee_meeting_type_id'] => ['type_other' => $row['type_other'] ?? null],
            ])->all()
        );
    }

    /** @param array<int, array{name:string}> $rows */
    private function syncAttendees(CommitteeMeeting $meeting, array $rows): void
    {
        $meeting->attendees()->delete();

        foreach (array_values($rows) as $index => $row) {
            CommitteeMeetingAttendee::create([
                'committee_meeting_id' => $meeting->id,
                'name' => $row['name'],
                'arrangement' => $index,
            ]);
        }
    }

    /** @param array<int, array{name:string}> $rows */
    private function syncMembers(CommitteeMeeting $meeting, array $rows): void
    {
        $meeting->members()->delete();

        foreach (array_values($rows) as $index => $row) {
            CommitteeMeetingMember::create([
                'committee_meeting_id' => $meeting->id,
                'name' => $row['name'],
                'arrangement' => $index,
            ]);
        }
    }

    /** @param array<int, array{topic_name:string, meeting_details:?string, meeting_comments:?string}> $rows */
    private function syncTopics(CommitteeMeeting $meeting, array $rows): void
    {
        $meeting->topics()->delete();

        foreach (array_values($rows) as $index => $row) {
            CommitteeMeetingTopic::create([
                'committee_meeting_id' => $meeting->id,
                'topic_name' => $row['topic_name'],
                'meeting_details' => $row['meeting_details'] ?? null,
                'meeting_comments' => $row['meeting_comments'] ?? null,
                'arrangement' => $index,
            ]);
        }
    }

    /**
     * Ported from admin/committee_meeting/view_committee_meeting.php,
     * surfaced via the dashboard's clickable meeting_date column.
     * Read-only — see SireReportRepository::detail()'s docblock for the
     * convention.
     */
    public function detail(int $id): ?array
    {
        $m = CommitteeMeeting::query()->with(['vessel', 'meetingTypes', 'attendees', 'members', 'topics'])->find($id);

        if ($m === null) {
            return null;
        }

        return $this->toDetailArray([
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
            'meeting_position' => $m->meeting_position,
            'meeting_time' => $m->meeting_time,
            'vessel_remarks' => $m->vessel_remarks,
            'shore_remarks' => $m->shore_remarks,
            'meeting_types' => $m->meetingTypes->map(fn (CommitteeMeetingType $t) => [
                'committee_meeting_type_id' => 0,
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
        ]);
    }

    /** Same as detail(), reading tb_committee_meeting directly from the legacy connection. */
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
            'id' => 0,
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
