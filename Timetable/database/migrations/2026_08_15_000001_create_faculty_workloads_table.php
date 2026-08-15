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
        Schema::create('faculty_workloads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('faculty_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->string('subject_type')->default('Theory');
            $table->string('semester');
            $table->string('class_name')->nullable();
            $table->string('division')->nullable();
            $table->unsignedInteger('theory_hours')->default(0);
            $table->unsignedInteger('practical_hours')->default(0);
            $table->text('assigned_classes')->nullable();
            $table->text('free_periods')->nullable();
            $table->foreignId('timetable_id')->nullable()->constrained('timetable_entries')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('faculty_workloads');
    }
};
