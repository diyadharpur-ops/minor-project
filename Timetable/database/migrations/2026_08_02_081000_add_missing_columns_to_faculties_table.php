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
        Schema::table('faculties', function (Blueprint $table) {
            if (!Schema::hasColumn('faculties', 'designation')) {
                $table->string('designation')->nullable()->after('email');
            }
            if (!Schema::hasColumn('faculties', 'qualification')) {
                $table->string('qualification')->nullable()->after('email');
            }
            if (!Schema::hasColumn('faculties', 'folder_path')) {
                $table->string('folder_path')->nullable()->after('subjects');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('faculties', function (Blueprint $table) {
            if (Schema::hasColumn('faculties', 'designation')) {
                $table->dropColumn('designation');
            }
            if (Schema::hasColumn('faculties', 'qualification')) {
                $table->dropColumn('qualification');
            }
            if (Schema::hasColumn('faculties', 'folder_path')) {
                $table->dropColumn('folder_path');
            }
        });
    }
};
