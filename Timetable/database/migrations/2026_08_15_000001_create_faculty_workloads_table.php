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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('faculty_workloads');
    }
};
