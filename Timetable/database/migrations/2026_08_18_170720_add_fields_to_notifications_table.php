<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            // Add new columns if they do not exist
            if (! Schema::hasColumn('notifications', 'description')) {
                $table->text('description')->nullable()->after('title');
            }
            if (! Schema::hasColumn('notifications', 'priority')) {
                $table->string('priority')->default('Info')->after('description');
            }
            if (! Schema::hasColumn('notifications', 'category')) {
                $table->string('category')->nullable()->after('priority');
            }
            if (! Schema::hasColumn('notifications', 'status')) {
                $table->string('status')->default('Unread')->after('category');
            }
            if (! Schema::hasColumn('notifications', 'module_name')) {
                $table->string('module_name')->nullable()->after('status');
            }
            if (! Schema::hasColumn('notifications', 'reference_id')) {
                $table->string('reference_id')->nullable()->after('module_name');
            }
            if (! Schema::hasColumn('notifications', 'created_by')) {
                $table->string('created_by')->nullable()->after('reference_id');
            }
        });

        // Handle backward compatibility columns (type, message, audience) safely
        Schema::table('notifications', function (Blueprint $table) {
            if (Schema::hasColumn('notifications', 'type')) {
                $table->string('type')->nullable()->change();
            } else {
                $table->string('type')->nullable();
            }

            if (Schema::hasColumn('notifications', 'message')) {
                $table->text('message')->nullable()->change();
            } else {
                $table->text('message')->nullable();
            }

            if (! Schema::hasColumn('notifications', 'audience')) {
                $table->string('audience')->default('all');
            }
        });

        // Copy message/type to description/category if description/category is empty
        try {
            DB::table('notifications')->whereNull('description')->update([
                'description' => DB::raw('message'),
            ]);
            DB::table('notifications')->whereNull('category')->update([
                'category' => DB::raw('type'),
            ]);
        } catch (Throwable $e) {
            // Ignore if columns were empty or tables didn't contain matches
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropColumn([
                'description',
                'priority',
                'category',
                'status',
                'module_name',
                'reference_id',
                'created_by',
            ]);
        });
    }
};
