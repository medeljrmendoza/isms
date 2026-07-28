<?php

namespace Database\Seeders;

use App\Models\CommitteeMeetings\CommitteeMeeting;
use App\Models\CommitteeMeetings\CommitteeMeetingAttendee;
use App\Models\CommitteeMeetings\CommitteeMeetingMember;
use App\Models\CommitteeMeetings\CommitteeMeetingTopic;
use App\Models\CommitteeMeetings\CommitteeMeetingType;
use App\Models\Vessel;
use Illuminate\Database\Seeder;

/**
 * Supersedes CommitteeMeetingCompanyDocsSeeder's CommitteeMeeting rows
 * with the full record (position/time/chairman/incharge/remarks) plus a
 * genuine SHORE-only (company-wide, no vessel) meeting and a true
 * VESSEL-added row, to exercise every added_by/shore_vessel_meeting
 * combination. Clears and recreates rather than updateOrCreate: matching
 * on meeting_date (a date-cast column) is unreliable across re-runs in
 * SQLite, same landmine fixed in IncidentReportSeeder/SireSeeder/
 * NonSireSeeder. Sub-table rows (types/attendees/members/topics) cascade
 * on delete via FK.
 */
class CommitteeMeetingSeeder extends Seeder
{
    public function run(): void
    {
        CommitteeMeeting::query()->delete();

        $pacificStar = Vessel::firstOrCreate(['name' => 'Pacific Star'], ['prefix' => 'MV']);
        $coralVoyager = Vessel::firstOrCreate(['name' => 'Coral Voyager'], ['prefix' => 'MV']);

        $safety = CommitteeMeetingType::firstOrCreate(['name' => 'SAFETY']);
        $environment = CommitteeMeetingType::firstOrCreate(['name' => 'ENVIRONMENT']);
        $security = CommitteeMeetingType::firstOrCreate(['name' => 'SECURITY']);
        $others = CommitteeMeetingType::firstOrCreate(['name' => 'OTHERS']);

        // SHORE-only (company-wide) meeting — no vessel, always auto-approved, never publishable.
        $m1 = CommitteeMeeting::create([
            'vessel_id' => null,
            'meeting_date' => '2026-07-12',
            'added_by' => 'SHORE',
            'shore_vessel_meeting' => 'SHORE',
            'meeting_position' => 'Head Office',
            'meeting_time' => '10:00 AM',
            'chairman' => 'Capt. R. Alonzo',
            'incharge' => 'HSSE Manager',
            'shore_remarks' => 'Quarterly fleet-wide safety briefing.',
            'is_published' => false,
            'is_approved' => true,
            'is_deleted' => false,
        ]);
        $m1->meetingTypes()->sync([$safety->id]);
        $this->addTopics($m1, [
            ['topic_name' => 'Fleet Safety Performance Review', 'meeting_details' => 'Reviewed Q2 incident statistics across the fleet.', 'meeting_comments' => 'No major trends identified.'],
        ]);
        $this->addMembers($m1, ['DPA', 'HSSE Manager', 'Fleet Operations Manager']);
        $this->addAttendees($m1, ['Capt. R. Alonzo', 'HSSE Manager', 'Fleet Operations Manager']);

        // SHORE-added VESSEL meeting, published, unapproved — appears in dashboard "pending" dashlet.
        $m2 = CommitteeMeeting::create([
            'vessel_id' => $pacificStar->id,
            'meeting_date' => '2026-07-15',
            'added_by' => 'SHORE',
            'shore_vessel_meeting' => 'VESSEL',
            'meeting_position' => 'Bridge',
            'meeting_time' => '09:00 AM',
            'chairman' => 'Capt. R. Alonzo',
            'incharge' => 'Chief Officer',
            'shore_remarks' => '',
            'is_published' => true,
            'is_approved' => false,
            'is_deleted' => false,
        ]);
        $m2->meetingTypes()->sync([$safety->id, $environment->id]);
        $this->addTopics($m2, [
            ['topic_name' => 'Fire Drill Debrief', 'meeting_details' => 'Discussed response times during last fire drill.', 'meeting_comments' => null],
            ['topic_name' => 'Garbage Management Compliance', 'meeting_details' => 'Reviewed MARPOL Annex V record keeping.', 'meeting_comments' => null],
        ]);
        $this->addMembers($m2, ['Chief Officer', 'Chief Engineer', 'Bosun']);
        $this->addAttendees($m2, ['Capt. R. Alonzo', 'Chief Officer', 'Chief Engineer', 'Bosun']);

        // SHORE-added VESSEL meeting, published + approved + has shore remarks — excluded from dashlet.
        $m3 = CommitteeMeeting::create([
            'vessel_id' => $coralVoyager->id,
            'meeting_date' => '2026-06-25',
            'added_by' => 'SHORE',
            'shore_vessel_meeting' => 'VESSEL',
            'meeting_position' => 'Officer\'s Mess',
            'meeting_time' => '02:00 PM',
            'chairman' => 'Capt. E. Navarro',
            'incharge' => 'Chief Officer',
            'shore_remarks' => 'Reviewed and closed out.',
            'is_published' => true,
            'is_approved' => true,
            'is_deleted' => false,
        ]);
        $m3->meetingTypes()->sync([$security->id]);
        $this->addTopics($m3, [
            ['topic_name' => 'ISPS Drill Review', 'meeting_details' => 'Reviewed last security drill outcomes.', 'meeting_comments' => 'Satisfactory.'],
        ]);
        $this->addMembers($m3, ['Chief Officer', 'SSO']);
        $this->addAttendees($m3, ['Capt. E. Navarro', 'Chief Officer', 'SSO']);

        // SHORE-added VESSEL meeting, unpublished draft — excluded, publishable.
        $m4 = CommitteeMeeting::create([
            'vessel_id' => $pacificStar->id,
            'meeting_date' => '2026-06-20',
            'added_by' => 'SHORE',
            'shore_vessel_meeting' => 'VESSEL',
            'meeting_position' => 'Bridge',
            'meeting_time' => '11:00 AM',
            'chairman' => 'Capt. R. Alonzo',
            'incharge' => 'Second Officer',
            'shore_remarks' => '',
            'is_published' => false,
            'is_approved' => false,
            'is_deleted' => false,
        ]);
        $m4->meetingTypes()->sync([$safety->id]);
        $this->addTopics($m4, [
            ['topic_name' => 'Lifeboat Drill Planning', 'meeting_details' => 'Scheduled next lifeboat lowering drill.', 'meeting_comments' => null],
        ]);
        $this->addMembers($m4, ['Second Officer']);
        $this->addAttendees($m4, ['Capt. R. Alonzo', 'Second Officer']);

        // VESSEL-added, unapproved — dashboard pending; approvable without a publish step.
        $m5 = CommitteeMeeting::create([
            'vessel_id' => $coralVoyager->id,
            'meeting_date' => '2026-07-18',
            'added_by' => 'VESSEL',
            'shore_vessel_meeting' => 'VESSEL',
            'meeting_position' => 'Crew Mess',
            'meeting_time' => '06:00 PM',
            'chairman' => 'Capt. E. Navarro',
            'incharge' => 'Chief Officer',
            'vessel_remarks' => 'Crew welfare concerns raised during the meeting.',
            'shore_remarks' => '',
            'is_published' => false,
            'is_approved' => false,
            'is_deleted' => false,
        ]);
        $m5->meetingTypes()->sync([$others->id => ['type_other' => 'Crew Welfare Discussion']]);
        $this->addTopics($m5, [
            ['topic_name' => 'Crew Welfare', 'meeting_details' => 'Discussed internet access and recreational facilities.', 'meeting_comments' => null],
        ]);
        $this->addMembers($m5, ['Chief Officer', 'Bosun']);
        $this->addAttendees($m5, ['Capt. E. Navarro', 'Chief Officer', 'Bosun', 'Cook']);

        // VESSEL-added, already approved — excluded from dashlet.
        $m6 = CommitteeMeeting::create([
            'vessel_id' => $pacificStar->id,
            'meeting_date' => '2026-06-10',
            'added_by' => 'VESSEL',
            'shore_vessel_meeting' => 'VESSEL',
            'meeting_position' => 'Bridge',
            'meeting_time' => '08:00 AM',
            'chairman' => 'Capt. R. Alonzo',
            'incharge' => 'Chief Officer',
            'vessel_remarks' => 'Routine monthly safety meeting.',
            'shore_remarks' => 'Reviewed, no action needed.',
            'is_published' => false,
            'is_approved' => true,
            'is_deleted' => false,
        ]);
        $m6->meetingTypes()->sync([$safety->id]);
        $this->addTopics($m6, [
            ['topic_name' => 'Monthly Safety Walkthrough', 'meeting_details' => 'No deficiencies found.', 'meeting_comments' => null],
        ]);
        $this->addMembers($m6, ['Chief Officer']);
        $this->addAttendees($m6, ['Capt. R. Alonzo', 'Chief Officer']);
    }

    /** @param array<int, array{topic_name:string, meeting_details:?string, meeting_comments:?string}> $topics */
    private function addTopics(CommitteeMeeting $meeting, array $topics): void
    {
        foreach ($topics as $index => $row) {
            CommitteeMeetingTopic::create([
                'committee_meeting_id' => $meeting->id,
                'topic_name' => $row['topic_name'],
                'meeting_details' => $row['meeting_details'],
                'meeting_comments' => $row['meeting_comments'],
                'arrangement' => $index,
            ]);
        }
    }

    /** @param array<int, string> $names */
    private function addMembers(CommitteeMeeting $meeting, array $names): void
    {
        foreach ($names as $index => $name) {
            CommitteeMeetingMember::create([
                'committee_meeting_id' => $meeting->id,
                'name' => $name,
                'arrangement' => $index,
            ]);
        }
    }

    /** @param array<int, string> $names */
    private function addAttendees(CommitteeMeeting $meeting, array $names): void
    {
        foreach ($names as $index => $name) {
            CommitteeMeetingAttendee::create([
                'committee_meeting_id' => $meeting->id,
                'name' => $name,
                'arrangement' => $index,
            ]);
        }
    }
}
