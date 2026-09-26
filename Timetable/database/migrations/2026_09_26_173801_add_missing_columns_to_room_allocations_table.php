<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('room_allocations', function (Blueprint $table) {
            $table->string('division')->nullable();
            $table->string('term')->nullable();
            $table->string('academic_year')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('room_allocations', function (Blueprint $table) {
            $table->dropColumn(['division', 'term', 'academic_year']);
        });
    }
};
