<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ported from Controllers/Incident.php's add_incident_report()/
     * view_incident_report(). The dashboard-only migration for this
     * table covered just the summary dashlet's needs (see its own
     * docblock); this fills in the rest for the full module.
     *
     * Renamed from legacy for clarity: `master_id`/`ce_id` are actually
     * free-text name fields (not FKs — confirmed against the add-form,
     * which renders plain text inputs), so they're `master_name` /
     * `chief_engineer_name` here. The shore-side root-cause summary
     * text is `shore_root_cause_summary` to avoid colliding with the
     * separate per-incident root-cause line items table
     * (incident_root_causes).
     *
     * Not modeled: file attachment upload/S3 storage and the tb_logs
     * audit trail — same simplification as every other module in this
     * migration (see NonconformityController docblock).
     */
    public function up(): void
    {
        Schema::table('incident_reports', function (Blueprint $table) {
            $table->string('voyage_no')->nullable()->after('vessel_id');
            $table->string('report_no')->nullable()->after('voyage_no');
            $table->string('master_name')->nullable()->after('report_no');
            $table->string('chief_engineer_name')->nullable()->after('master_name');
            $table->string('person_reporting')->nullable()->after('chief_engineer_name');
            $table->enum('added_by', ['SHORE', 'VESSEL'])->default('SHORE')->after('person_reporting');
            $table->boolean('published')->default(false)->after('added_by');

            $table->text('statementof_work')->nullable()->after('accident_collision');

            // --- Accident-only particulars ---
            $table->enum('bac', ['NO', 'YES'])->nullable();
            $table->enum('vdr', ['NO', 'YES'])->nullable();
            $table->date('dateof_event')->nullable();
            $table->string('timeof_event')->nullable();
            $table->string('zone')->nullable();
            $table->string('country')->nullable();
            $table->string('speed')->nullable();
            $table->string('course')->nullable();
            $table->string('draft_forward')->nullable();
            $table->string('draft_alt')->nullable();
            $table->string('wind_direction')->nullable();
            $table->string('direction_sea')->nullable();
            $table->string('direction_swell')->nullable();
            $table->string('geographical_location')->nullable();
            $table->string('port_departure')->nullable();
            $table->date('date_departure')->nullable();
            $table->string('port_which_bound')->nullable();
            $table->string('type_cargo')->nullable();
            $table->string('cargo_quantity')->nullable();
            $table->string('special_requirement')->nullable();
            $table->boolean('atmospheric_clear')->default(false);
            $table->boolean('atmospheric_partly_cloudy')->default(false);
            $table->boolean('atmospheric_overcast')->default(false);
            $table->boolean('atmospheric_fog')->default(false);
            $table->boolean('atmospheric_rain')->default(false);
            $table->boolean('atmospheric_snow')->default(false);
            $table->boolean('atmospheric_other')->default(false);
            $table->string('atmospheric_other_name')->nullable();
            $table->boolean('distance1')->default(false);
            $table->boolean('distance2')->default(false);
            $table->boolean('distance3')->default(false);
            $table->boolean('sea1')->default(false);
            $table->boolean('sea2')->default(false);
            $table->boolean('sea3')->default(false);
            $table->unsignedInteger('crew_onboard')->nullable();
            $table->unsignedInteger('other_onboard')->nullable();
            $table->unsignedInteger('total_onboard')->nullable();
            $table->unsignedInteger('crew_dead')->nullable();
            $table->unsignedInteger('other_dead')->nullable();
            $table->unsignedInteger('total_dead')->nullable();
            $table->unsignedInteger('crew_missing')->nullable();
            $table->unsignedInteger('other_missing')->nullable();
            $table->unsignedInteger('total_missing')->nullable();
            $table->unsignedInteger('crew_injured')->nullable();
            $table->unsignedInteger('other_injured')->nullable();
            $table->unsignedInteger('total_injured')->nullable();
            $table->enum('fs_ro', ['NO', 'YES'])->nullable();

            // --- Hazardous-occurrence-only fields ---
            $table->foreignId('incident_location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('location_other')->nullable();
            $table->string('ship_position')->nullable();
            $table->foreignId('incident_operation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ship_operation_other')->nullable();
            $table->enum('hazardous_occurrence_ppe_used', ['NO', 'YES', 'NA'])->nullable();
            $table->text('hazardous_occurrence_ppe_used_comment')->nullable();
            $table->enum('hazardous_occurrence_severity', ['HIGH', 'MEDIUM', 'LOW'])->nullable();
            $table->text('hazardous_occurrence_severity_comment')->nullable();
            $table->enum('hazardous_occurrence_likelihood', ['HIGH', 'MEDIUM', 'LOW'])->nullable();
            $table->text('hazardous_occurrence_likelihood_comment')->nullable();
            $table->enum('subject_investigation', ['NO', 'YES'])->nullable();
            $table->boolean('evidence_safety_meeting')->default(false);
            $table->boolean('evidence_certificate')->default(false);
            $table->boolean('evidence_logbook')->default(false);
            $table->boolean('evidence_delivery')->default(false);
            $table->boolean('evidence_photo')->default(false);
            $table->boolean('evidence_company')->default(false);
            $table->boolean('evidence_others')->default(false);
            $table->string('evidence_others_name')->nullable();
            $table->text('causal_factor')->nullable();
            $table->text('intermediate_cause')->nullable();
            $table->text('shore_root_cause_summary')->nullable();

            // --- Always shown ---
            $table->enum('severity_itp', ['FATALITY', 'FAC', 'LWC', 'MTC', 'PPD', 'PTD', 'RWC'])->nullable();
            $table->text('comment_itp')->nullable();
            $table->foreignId('location_of_injury_id')->nullable()->constrained('locations_of_injury')->nullOnDelete();
            $table->foreignId('type_of_injury_id')->nullable()->constrained('types_of_injury')->nullOnDelete();
            $table->string('other_typeof_injury')->nullable();
            $table->string('other_affected_area')->nullable();
            $table->enum('severity_itv', ['low', 'medium', 'high'])->nullable();
            $table->text('comment_itv')->nullable();
            $table->string('signed_by')->nullable();
            $table->date('date_signed')->nullable();
            $table->text('vessel_remarks')->nullable();
            $table->date('date_received')->nullable();
            $table->string('reviewed_by')->nullable();
            $table->string('investigator')->nullable();
            $table->string('dpa')->nullable();
        });

        Schema::create('incident_root_causes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('root_cause_id')->nullable()->constrained()->nullOnDelete();
            // Free text used when the selected root cause's category is "OTHER".
            $table->string('root_cause_other')->nullable();
            $table->text('investigation')->nullable();
            $table->text('analysis')->nullable();
            $table->text('corrective_actions')->nullable();
            $table->unsignedInteger('arrangement')->default(0);
            $table->timestamps();
        });

        Schema::create('incident_persons_participated', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_report_id')->constrained()->cascadeOnDelete();
            $table->string('person_name');
            $table->string('position')->nullable();
            $table->unsignedInteger('arrangement')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_persons_participated');
        Schema::dropIfExists('incident_root_causes');

        Schema::table('incident_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('incident_location_id');
            $table->dropConstrainedForeignId('incident_operation_id');
            $table->dropConstrainedForeignId('location_of_injury_id');
            $table->dropConstrainedForeignId('type_of_injury_id');

            $table->dropColumn([
                'voyage_no', 'report_no', 'master_name', 'chief_engineer_name', 'person_reporting', 'added_by', 'published',
                'statementof_work',
                'bac', 'vdr', 'dateof_event', 'timeof_event', 'zone', 'country', 'speed', 'course', 'draft_forward', 'draft_alt',
                'wind_direction', 'direction_sea', 'direction_swell', 'geographical_location', 'port_departure', 'date_departure',
                'port_which_bound', 'type_cargo', 'cargo_quantity', 'special_requirement',
                'atmospheric_clear', 'atmospheric_partly_cloudy', 'atmospheric_overcast', 'atmospheric_fog', 'atmospheric_rain',
                'atmospheric_snow', 'atmospheric_other', 'atmospheric_other_name',
                'distance1', 'distance2', 'distance3', 'sea1', 'sea2', 'sea3',
                'crew_onboard', 'other_onboard', 'total_onboard', 'crew_dead', 'other_dead', 'total_dead',
                'crew_missing', 'other_missing', 'total_missing', 'crew_injured', 'other_injured', 'total_injured', 'fs_ro',
                'location_other', 'ship_position', 'ship_operation_other',
                'hazardous_occurrence_ppe_used', 'hazardous_occurrence_ppe_used_comment',
                'hazardous_occurrence_severity', 'hazardous_occurrence_severity_comment',
                'hazardous_occurrence_likelihood', 'hazardous_occurrence_likelihood_comment',
                'subject_investigation',
                'evidence_safety_meeting', 'evidence_certificate', 'evidence_logbook', 'evidence_delivery',
                'evidence_photo', 'evidence_company', 'evidence_others', 'evidence_others_name',
                'causal_factor', 'intermediate_cause', 'shore_root_cause_summary',
                'severity_itp', 'comment_itp', 'other_typeof_injury', 'other_affected_area',
                'severity_itv', 'comment_itv',
                'signed_by', 'date_signed', 'vessel_remarks', 'date_received', 'reviewed_by', 'investigator', 'dpa',
            ]);
        });
    }
};
