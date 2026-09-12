<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('faculty_workloads', function (Blueprint $table) {
            if (! Schema::hasColumn('faculty_workloads', 'department_id')) {
                $table->unsignedBigInteger('department_id')->nullable()->after('department');
            }

            if (! Schema::hasColumn('faculty_workloads', 'semester')) {
                $table->string('semester')->nullable()->after('department_id');
            }

            if (! Schema::hasColumn('faculty_workloads', 'subject_name')) {
                $table->string('subject_name')->nullable()->after('semester');
            }

            if (! Schema::hasColumn('faculty_workloads', 'subject_code')) {
                $table->string('subject_code')->nullable()->after('subject_name');
            }

            if (! Schema::hasColumn('faculty_workloads', 'lecture_hours')) {
                $table->unsignedInteger('lecture_hours')->default(0)->after('subject_code');
            }

            if (! Schema::hasColumn('faculty_workloads', 'tutorial_hours')) {
                $table->unsignedInteger('tutorial_hours')->default(0)->after('lecture_hours');
            }

            if (! Schema::hasColumn('faculty_workloads', 'lab_hours')) {
                $table->unsignedInteger('lab_hours')->default(0)->after('tutorial_hours');
            }

            if (! Schema::hasColumn('faculty_workloads', 'weekly_hours')) {
                $table->unsignedInteger('weekly_hours')->default(0)->after('lab_hours');
            }

            if (! Schema::hasColumn('faculty_workloads', 'weekly_workload')) {
                $table->unsignedInteger('weekly_workload')->default(0)->after('weekly_hours');
            }
        });
    }

    public function down(): void
    {
        Schema::table('faculty_workloads', function (Blueprint $table) {
            $columns = ['department_id', 'semester', 'subject_name', 'subject_code', 'lecture_hours', 'tutorial_hours', 'lab_hours', 'weekly_hours', 'weekly_workload'];

            foreach ($columns as $column) {
                if (Schema::hasColumn('faculty_workloads', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
