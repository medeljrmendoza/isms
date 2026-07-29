<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every field below is read-only in the migrated admin — legacy's
     * add_report() only ever writes the approval-track fields (see the
     * next migration's docblock isn't needed here, but the same note
     * applies): report content originates from the unmigrated
     * vessel-side app and is only ever displayed/seeded here, never
     * created or edited through this admin.
     */
    public function up(): void
    {
        Schema::table('risk_assessments', function (Blueprint $table) {
            $table->date('risk_schedule')->nullable()->after('risk_date');
            $table->string('port')->nullable()->after('risk_schedule');
            $table->string('department')->nullable()->after('port');
            $table->string('activity')->nullable()->after('department');
            $table->string('overall_risk')->nullable()->after('marine_is_approved');
            $table->string('master')->nullable()->after('overall_risk');
            $table->string('ce_co')->nullable()->after('master');
            $table->text('vessel_remarks')->nullable()->after('ce_co');
            $table->date('date_approved')->nullable()->after('vessel_remarks');
            $table->text('shore_remarks')->nullable()->after('date_approved');
            $table->date('marine_date_approved')->nullable()->after('shore_remarks');
            $table->text('marine_remarks')->nullable()->after('marine_date_approved');
            $table->date('date_closed')->nullable()->after('marine_remarks');
        });
    }

    public function down(): void
    {
        Schema::table('risk_assessments', function (Blueprint $table) {
            $table->dropColumn([
                'risk_schedule', 'port', 'department', 'activity', 'overall_risk',
                'master', 'ce_co', 'vessel_remarks', 'date_approved', 'shore_remarks',
                'marine_date_approved', 'marine_remarks', 'date_closed',
            ]);
        });
    }
};
