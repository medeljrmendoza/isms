<?php

namespace App\Repositories\CommitteeMeetings;

use App\Models\CommitteeMeetings\CommitteeMeeting;
use App\Models\CommitteeMeetings\CommitteeMeetingAttendee;
use App\Models\CommitteeMeetings\CommitteeMeetingMember;
use App\Models\CommitteeMeetings\CommitteeMeetingTopic;
use App\Models\CommitteeMeetings\CommitteeMeetingType;
use App\Models\Vessel;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

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
     * @param array<int, array{committee_meeting_type_id:int, type_other:?string}> $meetingTypes
     * @param array<int, array{name:string}> $attendees
     * @param array<int, array{name:string}> $members
     * @param array<int, array{topic_name:string, meeting_details:?string, meeting_comments:?string}> $topics
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
}
