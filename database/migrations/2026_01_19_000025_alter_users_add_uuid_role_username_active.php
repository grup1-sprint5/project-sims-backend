<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // uuid column removed per new schema: ids are integers
            if (!Schema::hasColumn('users', 'username')) {
                $table->string('username')->nullable()->after('email');
            }
            if (!Schema::hasColumn('users', 'active')) {
                $table->boolean('active')->default(true)->after('password');
            }
            if (!Schema::hasColumn('users', 'role_id')) {
                $table->unsignedBigInteger('role_id')->nullable()->after('active');
                $table->foreign('role_id')->references('id')->on('roles')->onDelete('set null');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'role_id')) {
                $table->dropForeign(['role_id']);
                $table->dropColumn('role_id');
            }
            if (Schema::hasColumn('users', 'active')) {
                $table->dropColumn('active');
            }
            if (Schema::hasColumn('users', 'username')) {
                $table->dropColumn('username');
            }
            // no uuid column to drop
        });
    }
};
