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
            if (! Schema::hasColumn('room_allocations', 'allocation_type')) {
                $table->string('allocation_type')->nullable()->after('student_count');
            }
            if (! Schema::hasColumn('room_allocations', 'second_classroom_id')) {
                $table->foreignId('second_classroom_id')->nullable()->after('classroom_id')->constrained('classrooms')->nullOnDelete();
            }
            if (! Schema::hasColumn('room_allocations', 'division')) {
                $table->string('division')->nullable()->after('class_name');
            }
            if (! Schema::hasColumn('room_allocations', 'term')) {
                $table->string('term')->nullable()->after('division');
            }
            if (! Schema::hasColumn('room_allocations', 'academic_year')) {
                $table->string('academic_year')->nullable()->after('term');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('room_allocations', function (Blueprint $table) {
            if (Schema::hasColumn('room_allocations', 'allocation_type')) {
                $table->dropColumn('allocation_type');
            }
            if (Schema::hasColumn('room_allocations', 'second_classroom_id')) {
                $table->dropConstrainedForeignId('second_classroom_id');
            }
        });
    }
};
