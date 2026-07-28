<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ported from Controllers/Nonconformities.php's save_item()/view_item().
     * The dashboard-only migration for this table (see its own docblock)
     * covered just what the summary dashlet needed; this fills in the
     * rest for the full module.
     *
     * Not modeled: legacy's `module` column (write-only — set on insert,
     * never read back anywhere) and the S3 file attachment pipeline
     * (`file_nonconformity[]` upload, tb_documents rows, presigned URLs)
     * — no file storage has been built anywhere else in this migration
     * either, so the 7 "what's attached" checkboxes are kept as plain
     * metadata flags without a real upload behind them, consistent with
     * Company Documentation / SMS Publish Manual / Vessel Documentation.
     */
    public function up(): void
    {
        Schema::table('nonconformities', function (Blueprint $table) {
            $table->string('department_name')->nullable()->after('vessel_company');
            $table->enum('reported_by', ['SHORE', 'VESSEL'])->nullable()->after('department_name');
            $table->string('reporter_name')->nullable()->after('reported_by');

            $table->string('source_of_nc_others')->nullable()->after('source_of_nc_ref_no');
            $table->foreignId('manual_chapter_id')->nullable()->after('source_of_nc_others')->constrained();
            $table->string('sms_details')->nullable()->after('manual_chapter_id');

            $table->text('root_cause')->nullable()->after('description');
            $table->string('root_cause_incharge')->nullable()->after('root_cause');

            $table->text('corrective_action')->nullable()->after('root_cause_incharge');
            $table->string('corrective_action_incharge')->nullable()->after('corrective_action');
            $table->date('corrective_action_date')->nullable()->after('corrective_action_incharge');

            $table->enum('verification', ['COMPLETED', 'FOLLOW-UP', 'ASSISTANCE'])->nullable()->after('corrective_action_date');
            $table->text('verification_followup')->nullable()->after('verification');
            $table->text('verification_assistance')->nullable()->after('verification_followup');
            $table->string('verification_dpa')->nullable()->after('verification_assistance');
            $table->date('verification_date')->nullable()->after('verification_dpa');

            $table->boolean('close_out_completed')->default(false)->after('verification_date');
            $table->boolean('close_out_followup')->default(false)->after('close_out_completed');
            $table->text('close_out_followup_nature')->nullable()->after('close_out_followup');
            $table->string('close_out_dpa')->nullable()->after('close_out_followup_nature');

            $table->boolean('attach_safety_meeting')->default(false)->after('close_out_date');
            $table->boolean('attach_record_training')->default(false)->after('attach_safety_meeting');
            $table->boolean('attach_logbook')->default(false)->after('attach_record_training');
            $table->boolean('attach_delivery_note')->default(false)->after('attach_logbook');
            $table->boolean('attach_photo')->default(false)->after('attach_delivery_note');
            $table->boolean('attach_company_forms')->default(false)->after('attach_photo');
            $table->boolean('attach_others')->default(false)->after('attach_company_forms');
            $table->string('attach_others_details')->nullable()->after('attach_others');
        });
    }

    public function down(): void
    {
        Schema::table('nonconformities', function (Blueprint $table) {
            $table->dropConstrainedForeignId('manual_chapter_id');
            $table->dropColumn([
                'department_name', 'reported_by', 'reporter_name',
                'source_of_nc_others', 'sms_details',
                'root_cause', 'root_cause_incharge',
                'corrective_action', 'corrective_action_incharge', 'corrective_action_date',
                'verification', 'verification_followup', 'verification_assistance', 'verification_dpa', 'verification_date',
                'close_out_completed', 'close_out_followup', 'close_out_followup_nature', 'close_out_dpa',
                'attach_safety_meeting', 'attach_record_training', 'attach_logbook', 'attach_delivery_note',
                'attach_photo', 'attach_company_forms', 'attach_others', 'attach_others_details',
            ]);
        });
    }
};
