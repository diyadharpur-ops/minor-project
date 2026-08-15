<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('faculty_workloads')) {
            return;
        }

        if (Schema::hasColumn('faculty_workloads', 'faculty_name') && Schema::hasColumn('faculty_workloads', 'department')) {
            return;
        }

        $newTable = 'faculty_workloads_new';

        Schema::create($newTable, function (Blueprint $table) {
            $table->id();
            $table->string('faculty_name');
            $table->string('faculty_id');
            $table->string('department');
            $table->string('subjects_assigned');
            $table->unsignedInteger('theory_hours')->default(0);
            $table->unsignedInteger('practical_hours')->default(0);
            $table->unsignedInteger('total_hours')->default(0);
            $table->text('assigned_classes')->nullable();
            $table->text('free_periods')->nullable();
            $table->string('workload_status')->default('Normal');
            $table->timestamps();
        });

        $rows = DB::table('faculty_workloads')->get();

        foreach ($rows as $row) {
            $facultyName = 'N/A';
            $facultyId = (string) ($row->faculty_id ?? 'N/A');
            $departmentName = 'N/A';
            $subjectName = 'N/A';

            if (! empty($row->faculty_id)) {
                $faculty = DB::table('faculties')->where('id', $row->faculty_id)->first();
                if ($faculty) {
                    $facultyName = $faculty->name;
                    $facultyId = (string) $faculty->id;
                }
            }

            if (! empty($row->department_id)) {
                $department = DB::table('departments')->where('id', $row->department_id)->first();
                if ($department) {
                    $departmentName = $department->name;
                }
            }

            if (! empty($row->subject_id)) {
                $subject = DB::table('subjects')->where('id', $row->subject_id)->first();
                if ($subject) {
                    $subjectName = $subject->name;
                }
            }

            $theoryHours = (int) ($row->theory_hours ?? 0);
            $practicalHours = (int) ($row->practical_hours ?? 0);
            $totalHours = $theoryHours + $practicalHours;

            DB::table($newTable)->insert([
                'faculty_name' => $facultyName,
                'faculty_id' => $facultyId,
                'department' => $departmentName,
                'subjects_assigned' => $subjectName,
                'theory_hours' => $theoryHours,
                'practical_hours' => $practicalHours,
                'total_hours' => $totalHours,
                'assigned_classes' => $row->assigned_classes ?? null,
                'free_periods' => $row->free_periods ?? null,
                'workload_status' => $totalHours > (int) config('faculty_workload.normal_threshold', 18) ? 'Overloaded' : 'Normal',
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
        }

        Schema::drop('faculty_workloads');
        Schema::rename($newTable, 'faculty_workloads');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('faculty_workloads')) {
            return;
        }

        if (! Schema::hasColumn('faculty_workloads', 'faculty_name')) {
            return;
        }

        $oldTable = 'faculty_workloads_backup';
        Schema::create($oldTable, function (Blueprint $table) {
            $table->id();
            $table->foreignId('faculty_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('subject_type')->default('Theory');
            $table->string('semester')->nullable();
            $table->string('class_name')->nullable();
            $table->string('division')->nullable();
            $table->unsignedInteger('theory_hours')->default(0);
            $table->unsignedInteger('practical_hours')->default(0);
            $table->text('assigned_classes')->nullable();
            $table->text('free_periods')->nullable();
            $table->foreignId('timetable_id')->nullable()->constrained('timetable_entries')->nullOnDelete();
            $table->timestamps();
        });

        $rows = DB::table('faculty_workloads')->get();

        foreach ($rows as $row) {
            DB::table($oldTable)->insert([
                'faculty_id' => $row->faculty_id,
                'department_id' => null,
                'subject_id' => null,
                'subject_type' => 'Theory',
                'semester' => null,
                'class_name' => null,
                'division' => null,
                'theory_hours' => $row->theory_hours,
                'practical_hours' => $row->practical_hours,
                'assigned_classes' => $row->assigned_classes,
                'free_periods' => $row->free_periods,
                'timetable_id' => null,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
        }

        Schema::drop('faculty_workloads');
        Schema::rename($oldTable, 'faculty_workloads');
    }
};
