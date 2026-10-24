<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table): void {
            if (! Schema::hasColumn('subjects', 'class_selections')) {
                $table->json('class_selections')->nullable();
            }

            if (! Schema::hasColumn('subjects', 'division_selections')) {
                $table->json('division_selections')->nullable();
            }

            if (! Schema::hasColumn('subjects', 'tutorial_selections')) {
                $table->json('tutorial_selections')->nullable();
            }
        });
    }

    public function down(): void
    {
        // These columns may predate this migration, so dropping them could erase saved assignments.
    }
};
