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
        if (!Schema::hasColumn('admins', 'password_changed_at')) {
            Schema::table('admins', function (Blueprint $table) {
                $table->timestamp('password_changed_at')->nullable();
            });
        }

        if (!Schema::hasColumn('faculties', 'password_changed_at')) {
            Schema::table('faculties', function (Blueprint $table) {
                $table->timestamp('password_changed_at')->nullable();
            });
        }

        if (!Schema::hasColumn('users', 'password_changed_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->timestamp('password_changed_at')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('admins', 'password_changed_at')) {
            Schema::table('admins', function (Blueprint $table) {
                $table->dropColumn('password_changed_at');
            });
        }

        if (Schema::hasColumn('faculties', 'password_changed_at')) {
            Schema::table('faculties', function (Blueprint $table) {
                $table->dropColumn('password_changed_at');
            });
        }

        if (Schema::hasColumn('users', 'password_changed_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('password_changed_at');
            });
        }
    }
};