<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_allocation_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->string('semester', 20);
            $table->string('class_name', 20);
            $table->string('group_name', 80);
            $table->json('divisions');
            $table->foreignId('classroom_id')->nullable()->constrained('classrooms')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['department_id', 'semester', 'class_name', 'group_name'],
                'lab_allocation_groups_selection_unique'
            );
            $table->index(
                ['department_id', 'semester', 'class_name'],
                'lab_allocation_groups_selection_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_allocation_groups');
    }
};
