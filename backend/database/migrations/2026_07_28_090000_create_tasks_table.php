<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mapped from the legacy tb_task table (see
     * Controllers/Dashboard_tasks.php), scoped to what the Assigned Task
     * dashlet actually displays. `assigned_to` and `vesID` are dropped —
     * neither is a displayed column here, and vessel scoping is deferred
     * the same way as the other dashlets. All the write-side concerns
     * (rejection tracking, S3 file attachments, approve/delete) belong to
     * a future full Tasks module, not this read-only dashlet.
     */
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            // Legacy's human-readable business ID (e.g. "2026-S2S-00001"), distinct from our internal auto-increment id.
            $table->string('task_no')->unique();
            $table->string('category');
            $table->string('reference_tag')->nullable();
            $table->date('due_date');
            $table->string('priority');
            $table->string('task_status');
            $table->enum('task_type', ['SHORE TO SHORE', 'SHORE TO VESSEL', 'VESSEL TO SHORE']);
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_approved')->default(false);
            $table->boolean('is_deleted')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
