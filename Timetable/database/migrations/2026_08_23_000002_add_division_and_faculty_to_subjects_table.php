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
        Schema::table('subjects', function (Blueprint $table) {
            $table->foreignId('division_id')->nullable()->after('semester')->constrained('divisions')->cascadeOnDelete();
            $table->foreignId('faculty_id')->nullable()->after('department_id')->constrained('faculties')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->dropForeignIdFor('division_id');
            $table->dropColumn('division_id');
            $table->dropForeignIdFor('faculty_id');
            $table->dropColumn('faculty_id');
        });
    }
};
