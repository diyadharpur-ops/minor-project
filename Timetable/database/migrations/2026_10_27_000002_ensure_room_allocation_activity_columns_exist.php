<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('room_allocations')) {
            return;
        }

        Schema::table('room_allocations', function (Blueprint $table): void {
            if (! Schema::hasColumn('room_allocations', 'activity_type')) {
                $table->string('activity_type', 20)->nullable();
            }

            if (! Schema::hasColumn('room_allocations', 'activity_batch')) {
                $table->string('activity_batch', 10)->nullable();
            }
        });
    }

    public function down(): void
    {
        // Keep compatibility columns and existing allocation data on rollback.
    }
};
