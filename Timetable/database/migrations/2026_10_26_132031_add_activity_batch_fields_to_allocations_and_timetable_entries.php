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
        Schema::table('room_allocations', function (Blueprint $table): void {
            $table->string('activity_type', 20)->nullable();
            $table->string('activity_batch', 10)->nullable();
            $table->index(
                ['department_id', 'semester', 'subject_id', 'activity_type', 'activity_batch'],
                'room_allocations_activity_lookup'
            );
        });

        Schema::table('timetable_entries', function (Blueprint $table): void {
            $table->string('activity_batch', 10)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('timetable_entries', function (Blueprint $table): void {
            $table->dropColumn('activity_batch');
        });

        Schema::table('room_allocations', function (Blueprint $table): void {
            $table->dropIndex('room_allocations_activity_lookup');
            $table->dropColumn(['activity_type', 'activity_batch']);
        });
    }
};
