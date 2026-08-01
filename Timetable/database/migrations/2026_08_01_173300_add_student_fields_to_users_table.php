<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('enrollment_number')->nullable()->after('name');
            $table->string('department')->nullable()->after('enrollment_number');
            $table->string('semester')->nullable()->after('department');
            $table->string('student_class')->nullable()->after('semester');
            $table->string('divcon')->nullable()->after('student_class');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['enrollment_number', 'department', 'semester', 'student_class', 'divcon']);
        });
    }
};
