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
        if (!Schema::hasColumn('room_allocations', 'second_classroom_id')) {
            Schema::table('room_allocations', function (Blueprint $table) {
                $table->foreignId('second_classroom_id')
                    ->nullable()
                    ->constrained('classrooms')
                    ->nullOnDelete()
                    ->after('classroom_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('room_allocations', 'second_classroom_id')) {
            Schema::table('room_allocations', function (Blueprint $table) {
                $table->dropForeign(['second_classroom_id']);
                $table->dropColumn('second_classroom_id');
            });
        }
    }
};