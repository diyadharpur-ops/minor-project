<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('room_allocations')) {
            Schema::create('room_allocations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
                $table->string('semester')->nullable();
                $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('faculty_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('classroom_id')->nullable()->constrained()->nullOnDelete();
                $table->string('class_name')->nullable();
                $table->string('day')->nullable();
                $table->string('start_time')->nullable();
                $table->string('end_time')->nullable();
                $table->integer('student_count')->nullable();
                $table->string('status')->default('Allocated');
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('room_allocations');
    }
};
