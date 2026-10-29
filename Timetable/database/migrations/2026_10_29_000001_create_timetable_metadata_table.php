<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timetable_metadata', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->string('semester', 50);
            $table->string('division', 50);
            $table->string('academic_year', 50);
            $table->string('term', 50);
            $table->string('term_code', 50)->nullable();
            $table->string('term_dates', 100)->nullable();
            $table->string('wef_date', 50)->nullable();
            $table->string('sbt_dates', 100)->nullable();
            $table->timestamps();

            $table->unique(
                ['department_id', 'semester', 'division', 'academic_year', 'term'],
                'timetable_metadata_selection_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timetable_metadata');
    }
};
