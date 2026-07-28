<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ported from Controllers/Committee_meeting.php's add_record(). Legacy
 * supports a genuinely vessel-less "SHORE meeting" (shore_vessel_meeting
 * = "SHORE", vesID = "") alongside SHORE-entered-on-behalf-of-a-vessel
 * and true VESSEL-added meetings — vessel_id has to become nullable to
 * represent that. shore_vessel_meeting is distinct from added_by: it's
 * what actually drives the approval workflow (a SHORE-only meeting has
 * no vessel audience, so it's always auto-approved and never
 * publishable — see CommitteeMeetingRepository).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('committee_meetings', function (Blueprint $table) {
            $table->foreignId('vessel_id')->nullable()->change();
            $table->enum('shore_vessel_meeting', ['SHORE', 'VESSEL'])->default('VESSEL')->after('added_by');
            $table->string('meeting_position')->nullable()->after('meeting_date');
            $table->string('meeting_time')->nullable()->after('meeting_position');
            $table->string('chairman')->nullable()->after('meeting_time');
            $table->string('incharge')->nullable()->after('chairman');
            $table->text('vessel_remarks')->nullable()->after('incharge');
        });
    }

    public function down(): void
    {
        Schema::table('committee_meetings', function (Blueprint $table) {
            $table->dropColumn([
                'shore_vessel_meeting',
                'meeting_position',
                'meeting_time',
                'chairman',
                'incharge',
                'vessel_remarks',
            ]);
            $table->foreignId('vessel_id')->nullable(false)->change();
        });
    }
};
