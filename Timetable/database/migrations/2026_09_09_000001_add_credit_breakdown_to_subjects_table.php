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
            if (! Schema::hasColumn('subjects', 'lecture_credit')) {
                $table->unsignedInteger('lecture_credit')->nullable()->after('credit');
            }

            if (! Schema::hasColumn('subjects', 'lab_credit')) {
                $table->unsignedInteger('lab_credit')->nullable()->after('lecture_credit');
            }

            if (! Schema::hasColumn('subjects', 'tutorial_credit')) {
                $table->unsignedInteger('tutorial_credit')->nullable()->after('lab_credit');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            if (Schema::hasColumn('subjects', 'tutorial_credit')) {
                $table->dropColumn('tutorial_credit');
            }

            if (Schema::hasColumn('subjects', 'lab_credit')) {
                $table->dropColumn('lab_credit');
            }

            if (Schema::hasColumn('subjects', 'lecture_credit')) {
                $table->dropColumn('lecture_credit');
            }
        });
    }
};
