<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manual_chapters', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no');
            $table->string('chapter_name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_chapters');
    }
};
