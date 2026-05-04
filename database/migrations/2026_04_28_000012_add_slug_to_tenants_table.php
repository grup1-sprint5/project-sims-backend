<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (!Schema::hasColumn('tenants', 'slug')) {
                $table->string('slug')->nullable()->unique();
            }
        });

        if (Schema::hasColumn('tenants', 'slug')) {
            DB::table('tenants')
                ->whereNull('slug')
                ->update(['slug' => DB::raw('id')]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('tenants', 'slug')) {
            return;
        }

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
