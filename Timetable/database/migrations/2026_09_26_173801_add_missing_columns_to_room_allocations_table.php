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
            if (!Schema::hasColumn('room_allocations', 'division')) {
                $table->string('division')->nullable();
            }

            if (!Schema::hasColumn('room_allocations', 'term')) {
                $table->string('term')->nullable();
            }

            if (!Schema::hasColumn('room_allocations', 'academic_year')) {
                $table->string('academic_year')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $columns = [];

        if (Schema::hasColumn('room_allocations', 'division')) {
            $columns[] = 'division';
        }

        if (Schema::hasColumn('room_allocations', 'term')) {
            $columns[] = 'term';
        }

        if (Schema::hasColumn('room_allocations', 'academic_year')) {
            $columns[] = 'academic_year';
        }

        if (!empty($columns)) {
            Schema::table('room_allocations', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }
};